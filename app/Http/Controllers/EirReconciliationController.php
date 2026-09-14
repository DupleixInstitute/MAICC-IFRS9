<?php

namespace App\Http\Controllers;

use App\Jobs\RunEirRevenueJob;
use App\Models\ContractEir;
use App\Models\EirAmortisation;
use App\Services\Eir\EirGlReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EirReconciliationController extends Controller
{
    /** Blocked contracts named on screen before the list is summarised. */
    private const NAMED_BLOCKED_LIMIT = 25;

    public function __construct()
    {
        $this->middleware(['auth', 'permission:settings']);
    }

    public function index(Request $request, EirGlReconciliationService $reconciliation)
    {
        $periods = $reconciliation->availablePeriods();
        $period = (string) $request->input('period', '');
        if (! in_array($period, $periods, true)) {
            $period = $periods[0] ?? null;
        }

        $portfolio = trim((string) $request->input('portfolio', ''));
        $result = $reconciliation->forPeriod($period, $portfolio !== '' ? $portfolio : null);

        return Inertia::render('Eir/Reconciliation', [
            'period' => $result['period'],
            'periods' => $periods,
            'portfolios' => ContractEir::query()->whereNotNull('portfolio')->where('portfolio', '<>', '')
                ->distinct()->orderBy('portfolio')->pluck('portfolio'),
            'filters' => ['period' => $period, 'portfolio' => $portfolio],
            'rows' => $result['rows'],
            'bridge' => $result['bridge'],
            'summary' => $result['summary'],
            // What the revenue engine can produce for the selected period, and
            // whether the chain behind it is complete. A page reporting every
            // row as "not calculated" says nothing about which of the two
            // reasons applies: an engine that has never run, or contracts that
            // cannot be solved.
            'revenueReadiness' => $this->readiness($periods, $period),
            'revenueRun' => session('revenue_run'),
        ]);
    }

    /**
     * Run the monthly amortised-cost roll-forward that this reconciliation
     * compares the ledger against.
     *
     * The reconciliation itself is computed on read and needs no button: it
     * joins GL postings to amortisation rows every time the page loads. What
     * had no web entry point at all was the run that produces those rows, so
     * a book whose EIRs were solved and approved through the UI could still
     * only be reconciled from the console.
     *
     * Recalculation is deliberately not offered here. Restating a period
     * voids every later period for the affected contracts and has to carry a
     * stated reason, which is a different decision from running a period that
     * was never run; `eir:run-revenue --recalculate --reason=` remains the
     * way to do it.
     */
    public function runRevenue(Request $request, EirGlReconciliationService $reconciliation)
    {
        $periods = $reconciliation->availablePeriods();
        $data = $request->validate([
            'period' => ['required', 'string', Rule::in($periods)],
            'mode' => ['required', Rule::in(['period', 'catch_up'])],
            'portfolio' => ['nullable', 'string', 'max:100'],
        ]);

        $chain = $this->chainUpTo($periods, $data['period']);
        $missing = array_values(array_diff(array_slice($chain, 0, -1), $this->calculatedPeriods()));

        $redirect = redirect()->route('eir-reconciliation.index', array_filter([
            'period' => $data['period'],
            'portfolio' => $data['portfolio'] ?? null,
        ]));

        // Each opening balance is the prior period's closing. Running a middle
        // period on its own would open it from the present value of what is
        // left rather than from the balance the engine rolled forward, and a
        // later catch-up would leave that row standing: an already-calculated
        // period is left unchanged, not rebuilt. Refuse instead of quietly
        // producing a figure that follows from nothing.
        if ($data['mode'] === 'period' && $missing !== []) {
            return $redirect->with('revenue_run', [
                'status' => 'REFUSED',
                'periods_run' => [],
                'missing_periods' => $missing,
                'message' => count($missing).' earlier period(s) have no amortisation rows, starting at '
                    .$missing[0].'. Each opening balance is the prior period\'s closing, so run the catch-up '
                    .'instead: a single period run here would open from the present value of the remaining '
                    .'cash flows rather than from the balance carried forward.',
            ]);
        }

        $toRun = $data['mode'] === 'catch_up' ? $chain : [$data['period']];
        $totals = ['requested' => 0, 'created' => 0, 'recalculated' => 0, 'unchanged' => 0, 'blocked' => 0,
            'cash_derived_from_schedule' => 0, 'unclassified_cash' => 0.0];
        $blocked = [];

        foreach ($toRun as $period) {
            $summary = app()->call([new RunEirRevenueJob($period, null, false, $request->user()?->id), 'handle']);

            foreach (array_keys($totals) as $key) {
                $totals[$key] += $summary[$key] ?? 0;
            }
            // Keyed by contract and period: the same contract blocking in
            // every month of a catch-up is one cause, not one finding per row,
            // and collapsing them would hide which months are affected.
            foreach ($summary['blocked_contracts'] as $contractId => $reason) {
                $blocked[$contractId.' · '.$period] = $reason;
            }
        }

        return $redirect->with('revenue_run', [
            'status' => $totals['blocked'] > 0 ? 'COMPLETED_WITH_BLOCKERS' : 'COMPLETED',
            'periods_run' => $toRun,
            'missing_periods' => [],
            'totals' => $totals + ['unclassified_cash' => round($totals['unclassified_cash'], 2)],
            'blocked_contracts' => array_slice($blocked, 0, self::NAMED_BLOCKED_LIMIT, true),
            'blocked_truncated' => max(0, count($blocked) - self::NAMED_BLOCKED_LIMIT),
        ]);
    }

    /**
     * Every available period from the earliest up to and including the one
     * selected, oldest first — the order the roll-forward has to be built in.
     *
     * @param  list<string>  $periods  newest first, as availablePeriods() returns them
     * @return list<string>
     */
    private function chainUpTo(array $periods, string $period): array
    {
        $ordered = array_reverse($periods);
        $index = array_search($period, $ordered, true);

        return $index === false ? [] : array_slice($ordered, 0, $index + 1);
    }

    /** @return list<string> Periods that already hold at least one amortisation row. */
    private function calculatedPeriods(): array
    {
        return EirAmortisation::query()->distinct()->orderBy('reporting_period')
            ->pluck('reporting_period')->map(fn ($p) => (string) $p)->all();
    }

    /**
     * @param  list<string>  $periods
     * @return array{locked_contracts:int,calculated_periods:int,rows_for_period:int,missing_periods:list<string>,first_period:?string}
     */
    private function readiness(array $periods, ?string $period): array
    {
        $calculated = $this->calculatedPeriods();
        $chain = $period === null ? [] : $this->chainUpTo($periods, $period);

        return [
            'locked_contracts' => ContractEir::whereNotNull('locked_at')->count(),
            'calculated_periods' => count($calculated),
            'rows_for_period' => $period === null ? 0
                : EirAmortisation::where('reporting_period', $period)->count(),
            'missing_periods' => array_values(array_diff($chain, $calculated)),
            'first_period' => $chain[0] ?? null,
        ];
    }
}
