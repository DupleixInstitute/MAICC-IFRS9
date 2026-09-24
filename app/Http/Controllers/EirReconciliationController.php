<?php

namespace App\Http\Controllers;

use App\Exports\Ifrs9ReportExport;
use App\Jobs\RunEirRevenueJob;
use App\Models\ContractEir;
use App\Models\EirAmortisation;
use App\Models\GlInterestPosting;
use App\Models\Setting;
use App\Services\AuditLoggerService;
use App\Services\Eir\EirGlReconciliationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class EirReconciliationController extends Controller
{
    /** Blocked contracts named on screen before the list is summarised. */
    private const NAMED_BLOCKED_LIMIT = 25;

    /**
     * What each named cause means, in the words the download prints. They are
     * the causes the reconstruction of MAIIC's own books turned up, and each
     * one says what the reader should do about it.
     */
    private const CAUSE_MEANINGS = [
        'WITHIN_TOLERANCE' => 'The ledger and the contract agree inside the governed band. Nothing to do.',
        'LATE_DISBURSEMENT' => 'The money was paid out during the month, so only part of the month is charged. Check the disbursement date against the first day the ledger accrued on.',
        'CATCH_UP_POSTING' => 'The ledger posted several months at once, having posted nothing in the months before. Split the posting by month before reading any one month on its own.',
        'MID_MONTH_TRANCHE' => 'More money was drawn during the month, so the month-end balance is not the balance the whole month was charged on. The per-drawdown dates are needed to charge it day by day.',
        'RATE_MISMATCH' => 'The ledger charged a rate the loan book or the offer letter does not carry. Ask MAIIC which rate the core system holds for the account.',
        'NO_POSTING' => 'The loan book shows the account live with a balance but the ledger has no interest for it. Ask for the missing posting, or the reason the account was not accrued.',
        'DATA_GAP' => 'An input the calculation needs is missing, so there is no expected figure to compare. The row names what is missing.',
        'UNEXPLAINED' => 'None of the known causes accounts for the difference. Refer the account for investigation; it is reported here rather than absorbed.',
    ];

    public function __construct()
    {
        // Reading the reconciliation needs only the EIR view permission;
        // downloading it needs the export permission; running the revenue
        // stays on the permission it had.
        $this->middleware(['auth', 'permission:eir.view'])->only('index');
        $this->middleware(['auth', 'permission:eir.export'])->only('export');
        $this->middleware(['auth', 'permission:settings'])->except(['index', 'export']);
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
     * The reconciliation for one period as a workbook or a PDF.
     *
     * Both come off the same payload the hub reports use, so the download looks
     * like every other report in the system and needs no template of its own.
     * The settings the month was worked out under are printed in a header
     * block: a reader who was not in the room has to be able to see the basis,
     * not only the numbers.
     */
    public function export(Request $request, EirGlReconciliationService $reconciliation)
    {
        $periods = $reconciliation->availablePeriods();
        $data = $request->validate([
            'period' => ['nullable', 'string', Rule::in($periods)],
            'portfolio' => ['nullable', 'string', 'max:100'],
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
        ]);

        $period = $data['period'] ?? ($periods[0] ?? null);
        if ($period === null) {
            return back()->with('revenue_run', [
                'status' => 'REFUSED',
                'periods_run' => [],
                'missing_periods' => [],
                'message' => 'There are no GL interest postings loaded yet, so there is nothing to export.',
            ]);
        }

        $portfolio = trim((string) ($data['portfolio'] ?? ''));
        $result = $reconciliation->forPeriod($period, $portfolio !== '' ? $portfolio : null);
        $report = $this->reportPayload($result, $portfolio);

        AuditLoggerService::log('EIR Reconciliation Exported', GlInterestPosting::class, null, [
            'scope' => $portfolio !== '' ? $portfolio : 'ALL_PORTFOLIOS',
            'reporting_period' => $period,
            'new_values' => [
                'format' => $data['format'],
                'gl_posted' => $result['summary']['posted_total'],
                'expected_interest' => $result['summary']['expected_total'],
            ],
            'meta' => [
                'rows' => count($result['rows']),
                'day_count' => $result['summary']['day_count'],
                'tolerance_percent' => $result['summary']['tolerance_percent'],
                'tolerance_floor' => $result['summary']['tolerance_floor'],
                'causes' => $result['summary']['causes'],
            ],
        ]);

        $filename = 'MAIIC-EIR-Interest-Reconciliation-' . $period
            . ($portfolio !== '' ? '-' . Str::slug($portfolio) : '');

        if ($data['format'] === 'pdf') {
            return Pdf::loadView('reports.ifrs9.report', ['report' => $report])
                ->setPaper('a4', 'landscape')
                ->download($filename . '.pdf');
        }

        return Excel::download(new Ifrs9ReportExport($report), $filename . '.xlsx');
    }

    /**
     * The download's content: the basis in force, one row per account with the
     * named cause of its difference, and what each cause means.
     */
    private function reportPayload(array $result, string $portfolio): array
    {
        $summary = $result['summary'];

        $accounts = [];
        foreach ($result['rows'] as $row) {
            $accounts[] = [
                $row['contract_id'],
                $row['customer_name'] ?? 'not in the loan book',
                $row['reporting_period'],
                $row['expected_opening_balance'] === null ? 'not known' : number_format($row['expected_opening_balance'], 2),
                $row['expected_rate'] === null ? 'not known' : number_format($row['expected_rate'] * 100, 2) . '%',
                $row['expected_days'] === null ? 'not known' : (string) $row['expected_days'],
                $row['expected_interest'] === null ? 'not calculated' : number_format($row['expected_interest'], 2),
                number_format($row['gl_posted'], 2),
                $row['expected_difference'] === null ? 'not calculated' : number_format($row['expected_difference'], 2),
                str_replace('_', ' ', strtolower((string) $row['cause'])),
            ];
        }

        $causeRows = [];
        foreach (self::CAUSE_MEANINGS as $cause => $meaning) {
            $count = $summary['causes'][$cause] ?? 0;
            if ($count === 0) {
                continue;
            }
            $causeRows[] = [str_replace('_', ' ', strtolower($cause)), (string) $count, $meaning];
        }

        return [
            'company' => $this->company(),
            'title' => 'EIR Interest Reconciliation',
            'subtitle' => 'What the ledger posted against what the contract charges, account by account, with the cause of every difference'
                . ($portfolio !== '' ? ' | Portfolio: ' . $portfolio : ''),
            'period' => $result['period'],
            'generated_at' => now()->format('d M Y H:i'),
            'generated_by' => optional(auth()->user())->name,
            'kpis' => [
                ['label' => 'Interest posted in the ledger', 'value' => number_format($summary['posted_total'], 2), 'tone' => 'maiic'],
                ['label' => 'Contractual interest expected', 'value' => number_format($summary['expected_total'], 2), 'tone' => 'emerald'],
                ['label' => 'Difference to explain', 'value' => number_format($summary['expected_difference_total'], 2), 'tone' => 'amber'],
                ['label' => 'Rows agreeing inside the band', 'value' => $summary['expected_agrees'] . ' of ' . $summary['rows'], 'tone' => 'rose'],
            ],
            'sections' => [
                [
                    'heading' => 'How this reconciliation is worked out',
                    'columns' => ['Basis', 'In force for this month'],
                    'align' => ['l', 'l'],
                    'rows' => [
                        ['Formula', 'prior month-end outstanding balance x annual contractual rate x days in the month / '
                            . ($summary['day_count'] === '30/360' ? '360' : '365')],
                        ['Day count (governed setting day_count)', $summary['day_count'] ?? 'not approved'],
                        ['Exception band (governed setting recon_tolerance)', $this->toleranceNote($summary)],
                        ['Month of the first disbursement', 'charged from the disbursement day inclusive to the month end'],
                        ['Capitalising moratorium', 'the balance rises each month by exactly the interest charged'],
                        ['Opening balance', 'the outstanding balance on the prior month Loan Book Report'],
                        ['Rate', 'the loan book for the month where it carries one, otherwise the contract master'],
                    ],
                ],
                [
                    'heading' => 'Interest reconciliation by account',
                    'columns' => ['Account', 'Customer', 'Period', 'Opening balance', 'Rate', 'Days',
                        'Expected interest', 'Posted interest', 'Difference (expected less posted)', 'Cause'],
                    'align' => ['l', 'l', 'l', 'r', 'r', 'r', 'r', 'r', 'r', 'l'],
                    'rows' => $accounts,
                ],
                [
                    'heading' => 'What each cause means',
                    'columns' => ['Cause', 'Rows', 'What it means and what to do about it'],
                    'align' => ['l', 'r', 'l'],
                    'rows' => $causeRows,
                ],
            ],
        ];
    }

    /** The exception band as a sentence, from the governed setting. */
    private function toleranceNote(array $summary): string
    {
        $percent = $summary['tolerance_percent'];
        $floor = $summary['tolerance_floor'];
        if ($percent === null) {
            return 'no band is approved for this month';
        }

        return $percent > 0
            ? number_format($percent, 2) . ' percent of the amount posted, with a floor of MWK ' . number_format($floor, 2)
            : 'MWK ' . number_format($floor, 2) . ' per account-month';
    }

    /** The organisation name the reports carry, as the hub reads it. */
    private function company(): string
    {
        try {
            return optional(Setting::where('setting_key', 'company_name')->first())->setting_value ?: config('app.name');
        } catch (\Throwable $e) {
            return config('app.name');
        }
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
