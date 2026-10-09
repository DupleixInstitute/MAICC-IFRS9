<?php

namespace App\Services\Fli;

use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceService;
use App\Services\Scenario\ScenarioSetService;
use App\Support\Fli\AsAtSeries;
use App\Support\Fli\SeriesAligner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * The forward-looking route (spec v4 sections 14.6 to 14.8, 15.5): how an
 * approved fit, or an overlay, reaches every loan's PD for a period.
 *
 * Under the regression route the approved fit (proxy(t) = slope x
 * driver(t - lag) + intercept) is evaluated once per scenario of the
 * approved set, as at the end of the period and under the fitted lag (see
 * regressionAdjustments): the base window takes the actual driver lag
 * months before the period, the twelve-month window takes the driver lag
 * months before its end (an actual when that is on or before the period,
 * else the scenario's path), and the adjustment is the ratio of the
 * predicted proxies less one. The transmission method in force moves
 * each loan's pre-FLI PD under each scenario; the reported post-FLI PD is
 * the twelve-month PD whose stage PD is the probability-weighted stage PD
 * across scenarios, so the booked ECL is the weighted ECL (the weighting
 * method of 15.5), floored and capped, Stage 3 at 100 percent. Under the manual
 * overlay route the approved, unexpired overlays of the register are
 * applied the same way, each loan taking the sum of the adjustments whose
 * scope covers it (the book, its product group, its contract); under
 * regression plus overlay they sit on top of each scenario's adjustment.
 * With neither a fit nor an overlay, the PD holds and the loan says so.
 * Every loan records the route, the method, the fit, the set and the
 * overlays it was adjusted under.
 *
 * The register replaced the legacy fli_adj row for the system audit of
 * 9 October 2026, finding M3.
 */
class FliRouteService
{
    private OverlayService $overlays;

    public function __construct(private GovernanceService $governance, private TransmissionMethodCatalogue $methods, private ScenarioSetService $sets, ?OverlayService $overlays = null)
    {
        $this->overlays = $overlays ?? new OverlayService($sets);
    }

    /** A reviewer proposes an applied fit for the route; a second person approves it. */
    public function proposeFit(int $fitId, ?int $userId, ?string $note = null): void
    {
        $fit = DB::table('fli_fits')->where('id', $fitId)->first() ?? throw new RuntimeException("No fit {$fitId}.");
        if ($fit->verdict !== 'applied') {
            throw new RuntimeException("Fit {$fitId} was declined by the guardrail ({$fit->declined_reason}); it cannot be proposed.");
        }
        DB::table('fli_fits')->where('id', $fitId)->update(['approval_status' => 'PROPOSED', 'proposed_by' => $userId, 'proposed_at' => now(), 'approval_note' => $note]);
        AuditLoggerService::log('FLI Fit Proposed', 'fli_fits', $fitId, ['meta' => ['user' => $userId, 'note' => $note]]);
    }

    public function approveFit(int $fitId, ?int $approverId, ?string $label = null): void
    {
        $fit = DB::table('fli_fits')->where('id', $fitId)->first() ?? throw new RuntimeException("No fit {$fitId}.");
        if ($fit->approval_status !== 'PROPOSED') {
            throw new RuntimeException("Fit {$fitId} is not proposed.");
        }
        if ($label !== \App\Services\Ebanker\LoanBookBuildService::BOOTSTRAP_LABEL && $approverId !== null && (int) $fit->proposed_by === $approverId) {
            throw new RuntimeException('Maker-checker: the approver must be a different person from the proposer.');
        }
        DB::transaction(function () use ($fit, $fitId, $approverId, $label) {
            // one approved fit per period: an earlier one for the same period is superseded
            DB::table('fli_fits')->where('reporting_period', $fit->reporting_period)->where('approval_status', 'APPROVED')->update(['approval_status' => 'REJECTED', 'approval_note' => 'superseded by fit ' . $fitId]);
            DB::table('fli_fits')->where('id', $fitId)->update(['approval_status' => 'APPROVED', 'approved_by' => $approverId, 'approver_label' => $label, 'approved_at' => now()]);
        });
        AuditLoggerService::log('FLI Fit Approved', 'fli_fits', $fitId, ['meta' => ['approved_by' => $approverId, 'label' => $label]]);
    }

    /** The approved fit for a period, with its relationship. */
    public function approvedFit(string $period): ?object
    {
        $ym = str_replace('-', '', $period);

        return DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')
            ->where('f.reporting_period', $ym)->where('f.approval_status', 'APPROVED')->orderByDesc('f.approved_at')
            ->first(['f.id', 'f.slope', 'f.intercept', 'f.correlation_r', 'f.r_squared', 'f.n_obs', 'r.statistic_code', 'r.proxy_code', 'r.lag_months']);
    }

    /**
     * Apply the route in force to every loan of the period.
     *
     * @return array{period:string,route:string,method:string,fit:?int,set:?int,scenarios:array,overlays:list<array>,loans:int,adjusted:int,held:int,note:?string}
     */
    public function apply(string $period, ?int $userId = null): array
    {
        \App\Support\ReportingPeriodLock::assertOpen($period, 'the forward-looking route');
        $asOf = CarbonImmutable::parse($period . '-01')->endOfMonth();
        $route = $this->setting('fli_adjustment_route', 'Regression', $asOf);
        $weighting = $this->setting('scenario_weighting_method', 'Weight the ECL across scenarios', $asOf);
        $method = $this->methods->inForce();
        $set = DB::table('governed_scenario_sets')->where('reporting_period', $period)->whereIn('status', ['APPROVED', 'LOCKED'])->orderByDesc('version')->first();
        $fit = str_starts_with($route, 'Regression') ? $this->approvedFit($period) : null;

        // the adjustment per scenario
        $scenarios = [];
        $note = null;
        $baseDriver = null;
        if ($fit !== null && $set !== null) {
            [$baseDriver, $scenarios] = $this->regressionAdjustments($period, $fit, $this->sets->paths((int) $set->id));
        } elseif ($fit === null && ! str_contains($route, 'overlay')) {
            // the regression route with nothing approved: the PD holds
            $scenarios = ['Overlay' => ['weight' => 100.0, 'driver' => null, 'predicted_proxy' => null, 'adjustment' => 0.0]];
            $note = 'no approved fit for the period: manual overlay at zero, the PD holds';
        }

        // The overlay register (spec 14.6; system audit of 9 October 2026,
        // finding M3): under "Manual overlay" the approved, unexpired entries
        // are the whole adjustment; under "Regression plus overlay" they sit on
        // top of each scenario's. A book-wide overlay is carried on the
        // scenario row so the result and the audit log show it; an overlay on
        // a product group or a contract is added per loan it covers.
        $overlays = str_contains($route, 'overlay') ? $this->overlays->inForce($period) : collect();
        if (str_contains($route, 'overlay')) {
            $bookAdj = round((float) $overlays->where('scope', 'book')->sum('adjustment'), 8);
            if ($scenarios === []) {
                $scenarios = ['Overlay' => ['weight' => 100.0, 'driver' => null, 'predicted_proxy' => null, 'adjustment' => $bookAdj]];
            } elseif ($bookAdj != 0.0) {
                foreach ($scenarios as $name => $s) {
                    $scenarios[$name]['adjustment'] = round($s['adjustment'] + $bookAdj, 6);
                    $scenarios[$name]['overlay'] = $bookAdj;
                }
            }
            $fitStands = $fit !== null && $scenarios !== [] && ! isset($scenarios['Overlay']);
            $note = $overlays->isEmpty()
                ? ($fitStands ? 'no overlay in force for the period: the regression adjustment stands alone' : (str_starts_with($route, 'Regression') ? 'no approved fit and no overlay in force for the period: the PD holds' : 'no overlay in force for the period: the PD holds'))
                : $overlays->count() . ' overlay' . ($overlays->count() === 1 ? '' : 's') . ' in force from the register (ids ' . $overlays->pluck('id')->implode(', ') . ')' . ($fit === null && str_starts_with($route, 'Regression') ? '; no approved fit' : ($fitStands ? '; on top of the regression adjustment' : ''));
        }
        if ($scenarios === [] && $note === null) {
            $note = 'no approved scenario set for the period: the PD holds';
        }
        if (str_starts_with($weighting, 'Weight the macro')) {
            // today's method: one weighted driver, one adjustment
            $w = array_sum(array_map(fn ($s) => $s['adjustment'] * $s['weight'] / 100, $scenarios));
            $scenarios = ['Weighted path' => ['weight' => 100.0, 'driver' => null, 'predicted_proxy' => null, 'adjustment' => round($w, 6)]];
        }

        // every loan
        $loans = DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('pd_prefli')->get(['id', 'contract_id', 'pd_prefli', 'ifrs9stage_post_qualitative', 'calculated_ifrs9_stage', 'ifrs9stage_pre_qualitative', 'remaining_tenor', 'product_group']);
        $hasOverlayIds = DB::getSchemaBuilder()->hasColumn('loan_books', 'fli_overlay_ids');
        $adjusted = 0; $held = 0;
        DB::transaction(function () use ($loans, $scenarios, $overlays, $method, $route, $fit, $set, $hasOverlayIds, &$adjusted, &$held) {
            foreach ($loans as $loan) {
                $pre = (float) $loan->pd_prefli;
                // the overlays whose scope covers this loan: the book-wide ones are already on the scenario rows
                $covering = $overlays->filter(fn ($o) => $this->overlays->covers($o, $loan));
                $loanAdj = round((float) $covering->where('scope', '!=', 'book')->sum('adjustment'), 8);
                if ($loan->ifrs9stage_post_qualitative === '3') {
                    $post = 1.0; $by = [];
                } else {
                    // Weighting the loss, not the PD (spec 15.5; IFRS 9 5.5.17(a),
                    // B5.5.42): the ECL engine measures a loan as EAD x LGD x its
                    // stage PD, so the probability-weighted ECL across scenarios is
                    // EAD x LGD x the weighted STAGE PD. The post-FLI PD is the
                    // twelve-month PD whose stage PD is that weighted stage PD, so
                    // the allowance the ECL engine books on it equals the weighted
                    // ECL the scenario sensitivity reports. Averaging the twelve-
                    // month PDs instead overstated the allowance wherever the stage
                    // PD is concave in the twelve-month PD (Stage 2 lifetime over
                    // more than a year; Jensen's inequality, the 0.17 percent gap
                    // of August 2026). Each scenario's PD is capped at 100 percent
                    // before it is weighted, as its own ECL would be.
                    $stageRow = (object) ['stage' => $loan->ifrs9stage_post_qualitative ?? $loan->calculated_ifrs9_stage ?? $loan->ifrs9stage_pre_qualitative, 'remaining_tenor' => $loan->remaining_tenor];
                    $pdSum = 0.0; $stageSum = 0.0; $by = []; $weightSum = 0.0;
                    foreach ($scenarios as $name => $s) {
                        $a = $s['adjustment'] + $loanAdj;
                        $p = $this->methods->apply($method, $pre, ['adjustment' => $a, 'segment_adjustment' => $a]);
                        if ($p === null) {
                            continue;
                        }
                        $p = round($p, 8);
                        $by[$name] = ['weight' => $s['weight'], 'pd' => $p];
                        $pdSum += max(0.0, min(1.0, $p)) * $s['weight'] / 100;
                        $stageSum += ScenarioSetService::stagePd($stageRow, $p) * $s['weight'] / 100;
                        $weightSum += $s['weight'];
                    }
                    if ($weightSum > 0) {
                        $norm = $weightSum / 100;
                        // a loan whose stage resolves to 3 has no inverse; its ECL does not move with the PD, so the weighted PD is recorded
                        $post = ScenarioSetService::pd12ForStagePd($stageRow, $stageSum / $norm) ?? max(0.0, min(1.0, $pdSum / $norm));
                    } else {
                        $post = $pre;
                    }
                }
                $changed = abs($post - $pre) > 1e-9;
                $changed ? $adjusted++ : $held++;
                $row = ['pd_post_fli' => round($post, 8), 'fli_adj' => $pre > 0 ? round($post / $pre - 1, 8) : 0,
                    'fli_route' => $route, 'fli_method' => $method, 'fli_fit_id' => $fit?->id, 'fli_set_id' => $set?->id, 'fli_by_scenario' => json_encode($by)];
                if ($hasOverlayIds) {
                    $row['fli_overlay_ids'] = $covering->isEmpty() ? null : json_encode($covering->pluck('id')->map(fn ($id) => (int) $id)->values()->all());
                }
                DB::table('loan_books')->where('id', $loan->id)->update($row);
            }
        });
        $result = ['period' => $period, 'route' => $route, 'method' => $method, 'weighting' => $weighting, 'fit' => $fit?->id, 'fit_relationship' => $fit ? "{$fit->statistic_code} -> {$fit->proxy_code} (lag {$fit->lag_months})" : null, 'base_driver' => $baseDriver,
            'set' => $set?->id, 'scenarios' => $scenarios, 'overlays' => $overlays->map(fn ($o) => ['id' => (int) $o->id, 'scope' => $o->scope, 'scope_value' => $o->scope_value, 'adjustment' => (float) $o->adjustment])->values()->all(),
            'loans' => $loans->count(), 'adjusted' => $adjusted, 'held' => $held, 'note' => $note];
        AuditLoggerService::log('FLI Route Applied', 'loan_books', null, ['reporting_period' => $period, 'rows_affected' => $loans->count(), 'new_values' => array_diff_key($result, ['scenarios' => 1]) + ['scenarios' => array_map(fn ($s) => $s['adjustment'], $scenarios)], 'meta' => ['user' => $userId]]);

        return $result;
    }

    /**
     * The regression adjustment per scenario for period P, under the fitted lag.
     *
     * The approved fit is proxy(t) = slope x driver(t - L) + intercept: the
     * proxy at month t responds to the driver L months earlier (the lag the
     * finder and the regression chose, SeriesAligner). The route follows spec
     * 14.3 step 3 and 14.7: the adjustment is the predicted proxy of the
     * twelve-month window over the predicted proxy of the base window, less
     * one. Applied with the lag:
     *
     *  - base window, t = P: driver(P - L), the actual known at P. With no
     *    such actual the route fails closed for the period.
     *  - twelve-month window, t = P + 12: driver(P + 12 - L).
     *      L >= 12: that month is on or before P, so the driver is an actual
     *      already known at P and every scenario takes it (the lag means the
     *      next year's proxy is already determined by observed data; the
     *      scenarios cannot differ there).
     *      L < 12: the month is P + (12 - L) ahead, inside forecast year
     *      floor((12 - L - 1) / 12) of the scenario path (year 0 for every lag
     *      on the grid), and each scenario takes its own path value for that
     *      year. With no path value the route fails closed.
     *
     * Every value is read as at P (AsAtSeries): nothing after the end of P
     * enters, which is what B5.5.49 to B5.5.54 allow (information reasonably
     * available at the reporting date, forecasts included only as published by
     * then). One adjustment per scenario is applied to the twelve-month PD,
     * from which the ECL engine takes the Stage 2 lifetime PD.
     *
     * @return array{0:array<string,mixed>,1:array<string,array<string,mixed>>}
     */
    private function regressionAdjustments(string $period, object $fit, array $paths): array
    {
        $p = AsAtSeries::ym($period);
        $code = (string) $fit->statistic_code;
        $lag = (int) $fit->lag_months;
        $actuals = AsAtSeries::macro(null, $p, ['actual'], [$code])[$code] ?? [];

        $basePeriod = SeriesAligner::shiftPeriod($p, $lag);
        if (! array_key_exists($basePeriod, $actuals)) {
            throw new RuntimeException("The forward-looking route cannot run for {$period}: fit {$fit->id} ({$code}, lag {$lag} months) needs the {$code} actual for {$basePeriod}, and none is known at the end of {$period}. The PD is not adjusted; load the series or approve a fit the data supports.");
        }
        $base = (float) $actuals[$basePeriod];

        $windowPeriod = SeriesAligner::shiftPeriod($p, $lag - 12);
        $ahead = AsAtSeries::monthsBetween($p, $windowPeriod);
        $observed = null;
        $offset = null;
        if ($ahead <= 0) {
            if (! array_key_exists($windowPeriod, $actuals)) {
                throw new RuntimeException("The forward-looking route cannot run for {$period}: with a lag of {$lag} months the twelve-month window reads the {$code} actual for {$windowPeriod}, and none is known at the end of {$period}.");
            }
            $observed = (float) $actuals[$windowPeriod];
            $source = "actual {$windowPeriod}, known at {$p} (with a lag of {$lag} months the window's driver is on or before the reporting date)";
        } else {
            $offset = intdiv($ahead - 1, 12);
            $source = "scenario path, forecast year {$offset}: " . ($paths['base_source'][$code][$offset] ?? 'no base value');
        }

        $predBase = (float) $fit->slope * $base + (float) $fit->intercept;
        if (abs($predBase) <= 1e-12) {
            throw new RuntimeException("The forward-looking route cannot run for {$period}: fit {$fit->id} predicts a base proxy of zero at {$code} = {$base}, so no ratio to the base exists.");
        }
        $scenarios = [];
        foreach ($paths['scenarios'] as $name => $sc) {
            $driver = $observed ?? ($sc['path'][$code][$offset] ?? null);
            if ($driver === null) {
                throw new RuntimeException("The forward-looking route cannot run for {$period}: scenario '{$name}' has no {$code} value for forecast year {$offset} known at the end of {$period}.");
            }
            $pred = (float) $fit->slope * (float) $driver + (float) $fit->intercept;
            $scenarios[$name] = ['weight' => $sc['weight'], 'driver' => round((float) $driver, 6), 'driver_period' => $windowPeriod, 'predicted_proxy' => round($pred, 6), 'adjustment' => round($pred / $predBase - 1, 6)];
        }

        return [['statistic_code' => $code, 'lag_months' => $lag, 'base_period' => $basePeriod, 'base_value' => round($base, 6), 'predicted_base_proxy' => round($predBase, 6), 'window_period' => $windowPeriod, 'window_driver_source' => $source], $scenarios];
    }

    private function setting(string $key, string $default, CarbonImmutable $asOf): string
    {
        try {
            return $this->governance->get($key, $asOf);
        } catch (Throwable) {
            return $default;
        }
    }
}
