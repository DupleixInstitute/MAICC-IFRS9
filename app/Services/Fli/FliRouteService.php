<?php

namespace App\Services\Fli;

use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceService;
use App\Services\Scenario\ScenarioSetService;
use App\Support\Fli\AsAtSeries;
use App\Support\Fli\GovernedValues;
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

    private FliModelRegistry $registry;

    /** @var array<string,RegressionEngine> one read-only engine per period, for the inputs-hash check */
    private array $engines = [];

    /** @var array<int,array> the scenario paths of a set, read once per instance */
    private array $pathMemo = [];

    /** @var array<string,array<string,array<string,float>>> the macro actuals as known at the end of a period, read once per instance */
    private array $actualMemo = [];

    public function __construct(private GovernanceService $governance, private TransmissionMethodCatalogue $methods, private ScenarioSetService $sets, ?OverlayService $overlays = null, ?FliModelRegistry $registry = null)
    {
        $this->overlays = $overlays ?? new OverlayService($sets);
        $this->registry = $registry ?? new FliModelRegistry();
    }

    /** A reviewer proposes an applied fit for the route; a second person approves it. */
    public function proposeFit(int $fitId, ?int $userId, ?string $note = null): void
    {
        $fit = DB::table('fli_fits')->where('id', $fitId)->first() ?? throw new RuntimeException("No fit {$fitId}.");
        if ($fit->verdict !== 'applied') {
            throw new RuntimeException("Fit {$fitId} was declined by the guardrail ({$fit->declined_reason}); it cannot be proposed.");
        }
        if ($fit->approval_status === 'APPROVED') {
            throw new RuntimeException("Fit {$fitId} is already approved; it cannot be proposed again.");
        }
        $this->assertApplicable($fitId, 'proposed');
        DB::table('fli_fits')->where('id', $fitId)->update(['approval_status' => 'PROPOSED', 'proposed_by' => $userId, 'proposed_at' => now(), 'approval_note' => $note]);
        AuditLoggerService::log('FLI Fit Proposed', 'fli_fits', $fitId, ['meta' => ['user' => $userId, 'note' => $note]]);
    }

    public function approveFit(int $fitId, ?int $approverId, ?string $label = null): void
    {
        $fit = DB::table('fli_fits')->where('id', $fitId)->first() ?? throw new RuntimeException("No fit {$fitId}.");
        if ($fit->approval_status !== 'PROPOSED') {
            throw new RuntimeException("Fit {$fitId} is not proposed.");
        }
        // a named approver who is not the named proposer (audit C9 residual: a null approver skipped the check)
        if ($label !== \App\Services\Ebanker\LoanBookBuildService::BOOTSTRAP_LABEL && ($approverId === null || $fit->proposed_by === null || (int) $fit->proposed_by === $approverId)) {
            throw new RuntimeException('Maker-checker: the approver must be a different person from the proposer, and both must be named.');
        }
        // the data may have moved since the proposal: checked again at approval
        $this->assertApplicable($fitId, 'approved');
        DB::transaction(function () use ($fit, $fitId, $approverId, $label) {
            // one approved fit per period: an earlier one for the same period is superseded
            DB::table('fli_fits')->where('reporting_period', $fit->reporting_period)->where('approval_status', 'APPROVED')->update(['approval_status' => 'REJECTED', 'approval_note' => 'superseded by fit ' . $fitId]);
            DB::table('fli_fits')->where('id', $fitId)->update(['approval_status' => 'APPROVED', 'approved_by' => $approverId, 'approver_label' => $label, 'approved_at' => now()]);
        });
        AuditLoggerService::log('FLI Fit Approved', 'fli_fits', $fitId, ['meta' => ['approved_by' => $approverId, 'label' => $label]]);
        // the approved fit becomes the model version in force from its month (FLI module D-F2)
        $this->registry->publishFromFit($fitId, $approverId, $label);
    }

    /**
     * Whether a fit can be proposed or approved, and if not, why (FLI module
     * D-F1, D-F2, D-F7 and section 7; UI audit item 3). Approving a fit
     * publishes it as the model version in force from its own month, so the
     * fit must be one the route and the projection can run for that month,
     * on the inputs they read and nothing else:
     *
     *  - the driver's actual for the period less the lag (the base window),
     *    knowable at the end of the period (AsAtSeries, as the route reads it);
     *  - for each forecast year of the governed horizon (fli_forecast_horizon,
     *    as the projection reads it), the driver for the window's month: an
     *    actual knowable at the period when that month is on or before it,
     *    otherwise a value on every scenario's path in the approved set of the
     *    period (year 0 the exact forecast year the route reads; later years
     *    the path's last year held, as the projection holds it);
     *  - and, as a final guard, the route's and the projection's own
     *    calculations run read-only for the period without failing.
     *
     * A stale fit is refused as well: one set aside when another was approved
     * for its period, one whose model version was superseded or retired, and
     * one whose inputs (the driver and the measure as knowable at the end of
     * the period, aligned at its lag, with the governed guardrail settings)
     * are no longer those it was estimated on, which the stored inputs hash
     * shows: a sweep now would record a new fit rather than reuse it.
     *
     * Read-only: nothing is written.
     *
     * @return array{fit:int,period:string,applicable:bool,reasons:list<string>}
     */
    public function fitApplicability(int $fitId): array
    {
        $fit = DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')->where('f.id', $fitId)
            ->first(['f.id', 'f.fli_relationship_id', 'f.reporting_period', 'f.slope', 'f.intercept', 'f.inputs_hash', 'f.computed_at', 'f.approval_status', 'f.approval_note', 'r.statistic_code', 'r.proxy_code', 'r.lag_months'])
            ?? throw new RuntimeException("No fit {$fitId}.");
        $p = AsAtSeries::ym((string) $fit->reporting_period);
        $period = self::label($p);
        $reasons = array_merge($this->staleReasons($fit, $p, $period), $this->missingInputReasons($fit, $p, $period));

        return ['fit' => (int) $fit->id, 'period' => $period, 'applicable' => $reasons === [], 'reasons' => $reasons];
    }

    private function assertApplicable(int $fitId, string $action): void
    {
        $check = $this->fitApplicability($fitId);
        if (! $check['applicable']) {
            throw new RuntimeException("Fit {$fitId} cannot be {$action} for {$check['period']}: " . implode(' ', $check['reasons']));
        }
    }

    /** @return list<string> */
    private function staleReasons(object $fit, string $p, string $period): array
    {
        $reasons = [];
        if ($fit->approval_status === 'REJECTED') {
            $reasons[] = 'It was set aside' . ($fit->approval_note ? " ({$fit->approval_note})" : '') . '; a fit that was replaced is not brought back: re-run the regression and propose the current fit.';
        }
        $replaced = DB::table('fli_model_versions')->where('fli_fit_id', $fit->id)->whereIn('status', ['SUPERSEDED', 'RETIRED', 'REJECTED'])->orderBy('version')->get(['version', 'status', 'effective_from']);
        foreach ($replaced as $v) {
            $reasons[] = "Its model version {$v->version} (in force from {$v->effective_from}) is " . strtolower((string) $v->status) . '; the fit behind a replaced version is not published again.';
        }

        // the inputs it was estimated on against the inputs knowable now at the end of its period
        $code = (string) $fit->statistic_code;
        $proxy = (string) $fit->proxy_code;
        $lag = (int) $fit->lag_months;
        if ($fit->inputs_hash === null || $fit->inputs_hash === '') {
            $reasons[] = "It carries no record of the inputs it was estimated on, so it cannot be shown to rest on the {$code} and {$proxy} data now held for {$period}: re-run the regression for {$period}.";

            return $reasons;
        }
        try {
            $this->engines[$p] ??= new RegressionEngine(DB::getDefaultConnection(), new GovernedValues($p, DB::getDefaultConnection()));
            $now = $this->engines[$p]->currentInputsHash($p, $code, $proxy, $lag);
        } catch (Throwable $e) {
            $reasons[] = "The inputs it was estimated on cannot be checked against the data now held for {$period}: " . rtrim($e->getMessage(), '.') . '.';

            return $reasons;
        }
        if ($now === null) {
            $reasons[] = "The {$code} actuals or the {$proxy} measure it was estimated on are no longer held as known at the end of {$period}: re-run the regression for {$period}.";
        } elseif (! hash_equals((string) $fit->inputs_hash, $now)) {
            $later = DB::table('fli_fits')->where('fli_relationship_id', $fit->fli_relationship_id)->where('reporting_period', $fit->reporting_period)
                ->where('id', '>', $fit->id)->where('inputs_hash', $now)->orderByDesc('id')->first(['id', 'computed_at']);
            $reasons[] = "It is stale: the {$code} and {$proxy} data known at the end of {$period}, or the governed guardrail settings, have changed since it was estimated"
                . ($fit->computed_at ? " on {$fit->computed_at}" : '') . ', so a sweep now would estimate a new fit'
                . ($later ? " (fit {$later->id}, estimated on {$later->computed_at}, is the current one)" : ': re-run the regression for ' . $period) . '.';
        }

        return $reasons;
    }

    /** @return list<string> */
    private function missingInputReasons(object $fit, string $p, string $period): array
    {
        $code = (string) $fit->statistic_code;
        $lag = (int) $fit->lag_months;
        // every series' actuals as known at the end of the period, read once (the route reads the same, one code at a time)
        $actuals = ($this->actualMemo[$p] ??= AsAtSeries::macro(null, $p, ['actual']))[$code] ?? [];
        $lastActual = $actuals === [] ? null : self::label((string) max(array_keys($actuals)));
        $known = $lastActual === null ? "no {$code} actual is known at the end of {$period}" : "the last {$code} actual known at the end of {$period} is for {$lastActual}";
        $reasons = [];

        // the base window: the actual lag months before the period
        $basePeriod = SeriesAligner::shiftPeriod($p, $lag);
        if (! array_key_exists($basePeriod, $actuals)) {
            $reasons[] = "It needs the {$code} actual for " . self::label($basePeriod) . " ({$period} less its lag of {$lag} months, the base the route divides by); {$known}.";
        }

        // the forecast years of the governed horizon, as the projection reads them
        $asOf = CarbonImmutable::parse($period . '-01')->endOfMonth();
        $years = 1;
        try {
            $horizon = $this->governance->get('fli_forecast_horizon', $asOf);
        } catch (Throwable $e) {
            $horizon = null;
            $reasons[] = "No forecast horizon is in force for {$period} (Governance Centre, fli_forecast_horizon), so the forecast years the fit needs cannot be set.";
        }
        if ($horizon !== null) {
            try {
                $years = max(1, FliProjectionService::parseHorizon($horizon)['horizon']);
            } catch (Throwable $e) {
                $reasons[] = rtrim($e->getMessage(), '.') . '.';
            }
        }
        $missingActuals = [];
        $ahead = []; // forecast year => [offset into the scenario path, the window's month]
        for ($y = 0; $y < $years; $y++) {
            $window = SeriesAligner::shiftPeriod($p, $lag - 12 * ($y + 1));
            $m = AsAtSeries::monthsBetween($p, $window);
            if ($m <= 0) {
                if (! array_key_exists($window, $actuals)) {
                    $missingActuals[] = self::label($window) . " (forecast year {$y})";
                }
            } else {
                $ahead[$y] = [intdiv($m - 1, 12), $window];
            }
        }
        if ($missingActuals !== []) {
            $reasons[] = "With a lag of {$lag} months the forecast window" . (count($missingActuals) === 1 ? ' reads' : 's read') . " the {$code} actual for " . implode(', ', $missingActuals) . ", on or before the reporting date; {$known}.";
        }

        $paths = null;
        if ($ahead !== []) {
            $set = DB::table('governed_scenario_sets')->where('reporting_period', $period)->whereIn('status', ['APPROVED', 'LOCKED'])->orderByDesc('version')->first();
            if ($set === null) {
                $reasons[] = "No scenario set is approved for {$period}, so the {$code} path values the fit needs for forecast year" . (count($ahead) === 1 ? ' ' : 's ') . implode(', ', array_keys($ahead)) . ' cannot be read: approve the set on Scenario Sets first.';
            } else {
                $paths = $this->pathMemo[(int) $set->id] ??= $this->sets->paths((int) $set->id);
                $missing = [];
                $carried = array_filter($paths['scenarios'], fn ($sc) => array_filter($sc['path'][$code] ?? [], fn ($v) => $v !== null) !== []);
                if ($paths['scenarios'] !== [] && $carried === []) {
                    $series = array_keys($paths['base'] ?? []);
                    $reasons[] = "The approved scenario set of {$period} (set {$set->id}, version {$set->version}) carries no {$code} path known at the end of {$period}"
                        . ($series === [] ? '' : ' (its series are ' . implode(', ', $series) . ')') . ', so no scenario gives the driver for forecast year'
                        . (count($ahead) === 1 ? ' ' : 's ') . implode(', ', array_map(fn ($y, $a) => "{$y} (" . self::label($a[1]) . ')', array_keys($ahead), $ahead)) . '.';
                    $paths['scenarios'] = [];
                }
                foreach ($paths['scenarios'] as $name => $sc) {
                    $values = array_filter($sc['path'][$code] ?? [], fn ($v) => $v !== null);
                    $last = $values === [] ? null : max(array_keys($values));
                    $gaps = [];
                    foreach ($ahead as $y => [$offset, $window]) {
                        // year 0 is the route's: its exact forecast year; later years hold the path's last year, as the projection does
                        $has = $y === 0 ? array_key_exists($offset, $values) : $last !== null;
                        if (! $has) {
                            $gaps[] = "forecast year {$y} (" . self::label($window) . ')';
                        }
                    }
                    if ($gaps !== []) {
                        $missing[] = "'{$name}' for " . implode(', ', $gaps);
                    }
                }
                if ($missing !== []) {
                    $reasons[] = "The approved scenario set of {$period} (set {$set->id}, version {$set->version}) has no {$code} value known at the end of {$period} on the path of " . implode('; ', $missing) . '.';
                }
            }
        }

        // the final guard: the route's and the projection's own calculations, read-only
        if ($reasons === [] && $paths !== null) {
            $model = (object) ['id' => (int) $fit->id, 'statistic_code' => $code, 'lag_months' => $lag, 'slope' => $fit->slope, 'intercept' => $fit->intercept];
            try {
                $this->regressionAdjustments($period, $model, $paths);
                foreach ($this->adjustmentsByYear($period, $model, $paths, $years) as $name => $rows) {
                    foreach ($rows as $row) {
                        if ($row['adjustment'] === null) {
                            throw new RuntimeException("Scenario '{$name}' has no {$code} value for forecast year {$row['year']} known at {$period}");
                        }
                    }
                }
            } catch (Throwable $e) {
                $reasons[] = rtrim($e->getMessage(), '.') . '.';
            }
        }

        return $reasons;
    }

    /** 'YYYYMM' to 'YYYY-MM'. */
    private static function label(string $ym): string
    {
        return substr($ym, 0, 4) . '-' . substr($ym, 4, 2);
    }

    /**
     * The model in force for a period (FLI module D-F1, D-F2): the latest
     * published model version effective on or before it, with the fit it was
     * published from. The coefficients are the version's own, which never
     * change after publication. A month with no version in force has no model.
     */
    public function approvedFit(string $period): ?object
    {
        $v = $this->registry->inForce($period);
        if ($v === null) {
            return null;
        }
        $r = $v->fli_fit_id === null ? null : DB::table('fli_fits')->where('id', $v->fli_fit_id)->value('correlation_r');

        return (object) ['id' => $v->fli_fit_id === null ? null : (int) $v->fli_fit_id, 'model_version_id' => (int) $v->id, 'version' => (int) $v->version,
            'effective_from' => $v->effective_from, 'calibrated_period' => $v->calibrated_period,
            'slope' => $v->slope, 'intercept' => $v->intercept, 'correlation_r' => $r, 'r_squared' => $v->r_squared, 'n_obs' => $v->n_obs,
            'statistic_code' => $v->statistic_code, 'proxy_code' => $v->proxy_code, 'lag_months' => (int) $v->lag_months];
    }

    /**
     * What the route would do for a period, without writing anything: the
     * inputs in force and the adjustment of each scenario. The Apply to Loan
     * Book screen shows it before anyone presses apply.
     *
     * @return array{route:string,method:string,weighting:string,fit:?object,set:?object,scenarios:array,base:?array,overlays:list<array>,problem:?string}
     */
    public function preview(string $period): array
    {
        $asOf = CarbonImmutable::parse($period . '-01')->endOfMonth();
        $route = $this->setting('fli_adjustment_route', $asOf);
        $weighting = $this->setting('scenario_weighting_method', $asOf);
        $set = DB::table('governed_scenario_sets')->where('reporting_period', $period)->whereIn('status', ['APPROVED', 'LOCKED'])->orderByDesc('version')->first();
        $fit = str_starts_with($route, 'Regression') ? $this->approvedFit($period) : null;
        $scenarios = [];
        $base = null;
        $problem = null;
        if ($fit !== null && $set !== null) {
            try {
                [$base, $scenarios] = $this->regressionAdjustments($period, $fit, $this->sets->paths((int) $set->id));
            } catch (Throwable $e) {
                $problem = $e->getMessage();
            }
        }
        $overlays = str_contains($route, 'overlay') ? $this->overlays->inForce($period) : collect();

        return ['route' => $route, 'method' => $this->methods->inForce(), 'weighting' => $weighting, 'fit' => $fit, 'set' => $set, 'scenarios' => $scenarios, 'base' => $base,
            'overlays' => $overlays->map(fn ($o) => ['id' => (int) $o->id, 'scope' => $o->scope, 'scope_value' => $o->scope_value, 'adjustment' => (float) $o->adjustment])->values()->all(), 'problem' => $problem];
    }

    /**
     * The adjustment of each scenario of a set for a period, as the route
     * would book it (year 0), without writing anything. The Impact screen
     * uses it for a set that is proposed but not yet approved.
     *
     * @return array{0:array<string,mixed>,1:array<string,array<string,mixed>>}
     */
    public function scenarioAdjustments(string $period, object $fit, int $setId): array
    {
        return $this->regressionAdjustments($period, $fit, $this->sets->paths($setId));
    }

    /**
     * The adjustment of each scenario in each forecast year (FLI module D-F4):
     * year y is the twelve months that end 12(y + 1) months after the period,
     * read under the model's lag exactly as the route reads year 0, so year 0
     * here is the adjustment the route books. Years beyond the scenario path
     * hold the path's last year; the caller reverts them to the long-run PD.
     *
     * @return array<string,list<array{year:int,driver:?float,driver_period:string,source:string,predicted_proxy:?float,adjustment:?float}>>
     */
    public function adjustmentsByYear(string $period, object $fit, array $paths, int $years): array
    {
        $p = AsAtSeries::ym($period);
        $code = (string) $fit->statistic_code;
        $lag = (int) $fit->lag_months;
        $actuals = AsAtSeries::macro(null, $p, ['actual'], [$code])[$code] ?? [];
        $basePeriod = SeriesAligner::shiftPeriod($p, $lag);
        if (! array_key_exists($basePeriod, $actuals)) {
            throw new RuntimeException("No {$code} actual for {$basePeriod} is known at the end of {$period}; the model in force cannot be projected.");
        }
        $predBase = (float) $fit->slope * (float) $actuals[$basePeriod] + (float) $fit->intercept;
        if (abs($predBase) <= 1e-12) {
            throw new RuntimeException("The model in force predicts a base of zero for {$period}; no ratio to the base exists.");
        }
        $out = [];
        foreach ($paths['scenarios'] as $name => $sc) {
            $keys = array_keys(array_filter($sc['path'][$code] ?? [], fn ($v) => $v !== null));
            $last = $keys === [] ? null : max($keys);
            for ($y = 0; $y < $years; $y++) {
                $window = SeriesAligner::shiftPeriod($p, $lag - 12 * ($y + 1));
                $ahead = AsAtSeries::monthsBetween($p, $window);
                if ($ahead <= 0) {
                    $driver = $actuals[$window] ?? null;
                    $source = "actual {$window}";
                } else {
                    $offset = intdiv($ahead - 1, 12);
                    $use = $last === null ? null : min($offset, $last);
                    $driver = $use === null ? null : ($sc['path'][$code][$use] ?? null);
                    $source = $last !== null && $offset > $last ? "scenario path, year {$last} held" : "scenario path, forecast year {$offset}";
                }
                $pred = $driver === null ? null : (float) $fit->slope * (float) $driver + (float) $fit->intercept;
                $out[$name][] = ['year' => $y, 'driver' => $driver === null ? null : round((float) $driver, 6), 'driver_period' => $window, 'source' => $source,
                    'predicted_proxy' => $pred === null ? null : round($pred, 6), 'adjustment' => $pred === null ? null : round($pred / $predBase - 1, 6)];
            }
        }

        return $out;
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
        $route = $this->setting('fli_adjustment_route', $asOf);
        $weighting = $this->setting('scenario_weighting_method', $asOf);
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
            // the regression route with nothing approved: the PD holds, in every scenario of the set
            $scenarios = $this->heldScenarios($set, 0.0);
            $note = 'no approved fit for the period: manual overlay at zero' . ($set !== null ? " in each scenario of set {$set->id}" : '') . ', the PD holds';
        }
        $fromFit = $fit !== null && $scenarios !== [] && $set !== null;

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
                $scenarios = $this->heldScenarios($set, $bookAdj);
            } elseif ($bookAdj != 0.0) {
                foreach ($scenarios as $name => $s) {
                    $scenarios[$name]['adjustment'] = round($s['adjustment'] + $bookAdj, 6);
                    $scenarios[$name]['overlay'] = $bookAdj;
                }
            }
            $fitStands = $fromFit;
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
        $hasVersion = DB::getSchemaBuilder()->hasColumn('loan_books', 'fli_model_version_id');
        $adjusted = 0; $held = 0;
        DB::transaction(function () use ($loans, $scenarios, $overlays, $method, $route, $fit, $set, $hasOverlayIds, $hasVersion, &$adjusted, &$held) {
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
                if ($hasVersion) {
                    $row['fli_model_version_id'] = $fit?->model_version_id;
                }
                if ($hasOverlayIds) {
                    $row['fli_overlay_ids'] = $covering->isEmpty() ? null : json_encode($covering->pluck('id')->map(fn ($id) => (int) $id)->values()->all());
                }
                DB::table('loan_books')->where('id', $loan->id)->update($row);
            }
        });
        $result = ['period' => $period, 'route' => $route, 'method' => $method, 'weighting' => $weighting, 'fit' => $fit?->id, 'model_version' => $fit?->version, 'model_version_id' => $fit?->model_version_id, 'fit_relationship' => $fit ? "{$fit->statistic_code} -> {$fit->proxy_code} (lag {$fit->lag_months})" : null, 'base_driver' => $baseDriver,
            'set' => $set?->id, 'scenarios' => $scenarios, 'overlays' => $overlays->map(fn ($o) => ['id' => (int) $o->id, 'scope' => $o->scope, 'scope_value' => $o->scope_value, 'adjustment' => (float) $o->adjustment])->values()->all(),
            'loans' => $loans->count(), 'adjusted' => $adjusted, 'held' => $held, 'note' => $note];
        AuditLoggerService::log('FLI Route Applied', 'loan_books', null, ['reporting_period' => $period, 'rows_affected' => $loans->count(), 'new_values' => array_diff_key($result, ['scenarios' => 1]) + ['scenarios' => array_map(fn ($s) => $s['adjustment'], $scenarios)], 'meta' => ['user' => $userId]]);

        return $result;
    }

    /**
     * The scenarios when no approved fit moves them: the PD holds, or a book-wide
     * overlay moves it alike, in every scenario of the set in force.
     *
     * D-F3 measures the loss once per scenario of the governed set and weights
     * it; the time-phased engine reads each loan's per-scenario PD under the
     * set's names and weights, and the month record weights the per-scenario
     * figures over the same names. A single "Overlay" scenario on a performing
     * loan left the set's scenarios unmeasured on it, so its discounted
     * allowance could not weight back to the one booked (maiic_ifrs9_fliall,
     * December 2025: weighted 2,565,806,380.57 against booked 3,015,683,463.14).
     * Each scenario of the set therefore carries the same adjustment, so each
     * has its own figures and the weights give back the booked allowance. With
     * no set in force there is no scenario to name: the one "Overlay" row
     * stays, and the month record refuses the month for want of a set.
     *
     * @return array<string,array<string,mixed>>
     */
    private function heldScenarios(?object $set, float $adjustment): array
    {
        if ($set === null) {
            return ['Overlay' => ['weight' => 100.0, 'driver' => null, 'predicted_proxy' => null, 'adjustment' => $adjustment]];
        }
        $rows = DB::table('governed_scenarios')->where('set_id', $set->id)->orderBy('order_position')->get(['name', 'weight']);
        if ($rows->isEmpty() || (float) $rows->sum('weight') <= 0) {
            throw new RuntimeException("Scenario set {$set->id} holds no weighted scenario; the forward-looking route cannot measure the loans under it.");
        }
        $out = [];
        foreach ($rows as $r) {
            $out[$r->name] = ['weight' => (float) $r->weight, 'driver' => null, 'predicted_proxy' => null, 'adjustment' => $adjustment];
        }

        return $out;
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

    /**
     * The governed value in force. No value is held in code: with no approved
     * value the route stops with GovernanceSettingMissingException, naming the
     * setting (spec 4.2; it used to fall back to 'Regression' and to weighting
     * the ECL, so a missing setting went unnoticed).
     */
    private function setting(string $key, CarbonImmutable $asOf): string
    {
        return $this->governance->get($key, $asOf);
    }
}
