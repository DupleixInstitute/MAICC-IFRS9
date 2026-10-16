<?php

namespace App\Services\Fli;

use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceService;
use App\Services\Scenario\ScenarioSetService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The scenario projection (FLI module specification v1, decision D-F4).
 *
 * For the model version in force and the approved scenario set of a month it
 * projects, per portfolio and per scenario, the conditional PD of each future
 * year, the probability of surviving to it, the marginal PD (defaulting in
 * that year having survived the ones before) and the cumulative PD:
 *
 *   adjustment(s, y)  the model's predicted proxy for year y under scenario s
 *                     over the predicted base, less one; year 0 is the
 *                     adjustment the route books
 *   PD(s, y)          the transmission method in force applied to the
 *                     portfolio's exposure-weighted PD before FLI
 *   survival(y)       product over earlier years of (1 - PD)
 *   marginal(s, y)    survival(y) x PD(s, y)
 *
 * Inside the governed forecast horizon each year takes the scenario's path;
 * after it the adjustment falls in equal steps to nil over the reversion
 * years (IFRS 9 B5.5.50), and the years after that sit at the long-run PD
 * until the longest remaining term of the portfolio's performing loans.
 *
 * The projection is the evidence for Stage 2 lifetime losses year by year and
 * for the scenario workbooks; the booked Stage 2 ECL is still measured by the
 * ECL engine on the twelve-month PD the route writes, and the screen shows
 * the two lifetime PDs side by side.
 */
class FliProjectionService
{
    public const SOURCE = 'fli-projection';

    public function __construct(private FliRouteService $route, private ScenarioSetService $sets, private TransmissionMethodCatalogue $methods, private GovernanceService $governance)
    {
    }

    /** @return array{horizon:int,reversion:int,label:string} */
    public function horizon(string $period): array
    {
        try {
            $v = $this->governance->get('fli_forecast_horizon', CarbonImmutable::parse($period . '-01')->endOfMonth());
        } catch (Throwable $e) {
            throw new RuntimeException('No forecast horizon is in force (Governance Centre, fli_forecast_horizon): the projection cannot run.');
        }

        return self::parseHorizon($v);
    }

    /**
     * The years of scenario path and of reversion a governed horizon names
     * ('3 years, then 2 years back to the long-run PD'). The route reads it
     * too, to know which forecast years a fit needs before it is approved.
     *
     * @return array{horizon:int,reversion:int,label:string}
     */
    public static function parseHorizon(string $v): array
    {
        if (! preg_match('/(\d+)\s*years?,\s*then\s*(\d+)\s*years?/i', $v, $m)) {
            throw new RuntimeException("The forecast horizon in force ('{$v}') does not name the years of the path and of the reversion.");
        }

        return ['horizon' => (int) $m[1], 'reversion' => (int) $m[2], 'label' => $v];
    }

    /** Run the projection for a month and store it; returns the run with its results. */
    public function run(string $period, ?int $userId = null): array
    {
        // the run this month's work belongs to (Staging Module Specification v1, section 8.8): the open month-end
        // or bootstrap run, or one of its own when run from a screen
        if (! \App\Support\RunContext::inside($period, 'Forward-looking projection')) {
            return \App\Support\RunContext::engine($period, 'Forward-looking projection', $userId, fn () => $this->run($period, $userId));
        }
        $fit = $this->route->approvedFit($period) ?? throw new RuntimeException("No model version is in force for {$period}: publish one on Model Versions (approve a fit on Regression) first.");
        $set = DB::table('governed_scenario_sets')->where('reporting_period', $period)->whereIn('status', ['APPROVED', 'LOCKED'])->orderByDesc('version')->first()
            ?? throw new RuntimeException("No approved scenario set for {$period}: approve one on Scenario Sets first.");
        $h = $this->horizon($period);
        $method = $this->methods->inForce();
        $paths = $this->sets->paths((int) $set->id);

        // the performing loans of each portfolio: exposure-weighted PD before FLI and the longest remaining term
        $loans = DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('pd_prefli')
            ->whereRaw("COALESCE(ifrs9stage_post_qualitative, calculated_ifrs9_stage, ifrs9stage_pre_qualitative) <> '3'")
            ->get(['loan_portfolio_id', 'pd_prefli', 'carrying_amount', 'commitments', 'facility_utilisation_rate', 'remaining_tenor',
                DB::raw("COALESCE(ifrs9stage_post_qualitative, calculated_ifrs9_stage, ifrs9stage_pre_qualitative) as stage")]);
        if ($loans->isEmpty()) {
            throw new RuntimeException("No performing loan of {$period} carries a PD before FLI: run the PD engine first.");
        }
        $names = DB::table('loan_portfolios')->pluck('name', 'id')->all();
        $segments = [];
        foreach ($loans->groupBy(fn ($l) => (int) ($l->loan_portfolio_id ?? 0)) as $pid => $g) {
            $ead = 0.0; $wpd = 0.0; $stage2 = 0;
            foreach ($g as $l) {
                $e = (float) ($l->carrying_amount ?? 0) + (float) ($l->commitments ?? 0) * (float) ($l->facility_utilisation_rate ?? 1);
                $ead += $e;
                $wpd += $e * (float) $l->pd_prefli;
                $stage2 += (string) $l->stage === '2' ? 1 : 0;
            }
            $pd = $ead > 0 ? $wpd / $ead : (float) $g->avg('pd_prefli');
            $years = max($h['horizon'] + $h['reversion'], (int) ceil(((float) $g->max('remaining_tenor')) / 12));
            $segments[$pid] = ['portfolio_id' => (int) $pid, 'portfolio' => $names[$pid] ?? ($pid ? "Portfolio {$pid}" : 'No portfolio'), 'loans' => $g->count(), 'stage2_loans' => $stage2, 'ead' => round($ead, 2), 'pd_pre' => round($pd, 8), 'years' => min($years, 30)];
        }
        $maxYears = max(array_column($segments, 'years'));
        $byYear = $this->route->adjustmentsByYear($period, $fit, $paths, $h['horizon']);
        $adjustment = function (string $scenario, int $y) use ($byYear, $h): ?float {
            if ($y < $h['horizon']) {
                return $byYear[$scenario][$y]['adjustment'] ?? null;
            }
            $last = $byYear[$scenario][$h['horizon'] - 1]['adjustment'] ?? null;
            if ($last === null) {
                return null;
            }
            $k = $y - $h['horizon'] + 1;

            return $k <= $h['reversion'] ? $last * (1 - $k / ($h['reversion'] + 1)) : 0.0;
        };

        $runId = (string) Str::uuid();
        $projectionDate = fn (int $y) => CarbonImmutable::parse($period . '-01')->endOfMonth()->addMonthsNoOverflow(12 * ($y + 1))->endOfMonth()->toDateString();
        $terms = [];
        $summary = [];
        foreach ($segments as $pid => $seg) {
            foreach ($byYear as $scenario => $_) {
                $survival = 1.0;
                $cum = 0.0;
                for ($y = 0; $y < $seg['years']; $y++) {
                    $a = $adjustment($scenario, $y);
                    if ($a === null) {
                        throw new RuntimeException("Scenario '{$scenario}' has no driver value for forecast year {$y} known at {$period}; the projection stops (load the forecast or extend the set's path).");
                    }
                    $cond = $this->methods->apply($method, $seg['pd_pre'], ['adjustment' => $a, 'segment_adjustment' => $a]);
                    $cond = max(0.0, min(1.0, (float) ($cond ?? $seg['pd_pre'])));
                    $marginal = $survival * $cond;
                    $cum += $marginal;
                    $terms[] = ['contract_id' => 'portfolio:' . $pid, 'reporting_period' => $period, 'scenario_code' => mb_substr($scenario, 0, 30), 'period_index' => $y + 1, 'projection_date' => $projectionDate($y),
                        'conditional_pd' => round($cond, 10), 'survival_open' => round($survival, 10), 'marginal_pd' => round($marginal, 10), 'cumulative_pd' => round(min(1.0, $cum), 10), 'source' => self::SOURCE, 'created_at' => now(), 'updated_at' => now()];
                    $survival *= (1 - $cond);
                }
                $summary[$pid][$scenario] = ['pd_year1' => $terms[count($terms) - $seg['years']]['conditional_pd'], 'lifetime_pd' => round(min(1.0, $cum), 8)];
            }
        }

        DB::transaction(function () use ($period, $runId, $terms, $segments, $summary, $fit, $set, $h, $method, $byYear, $userId) {
            $old = DB::table('ecl_projection_runs')->where('reporting_period', $period)->where('methodology_version', self::SOURCE)->pluck('id');
            DB::table('ecl_projection_runs')->whereIn('id', $old)->update(['status' => 'SUPERSEDED', 'updated_at' => now()]);
            DB::table('ecl_pd_term_structures')->where('reporting_period', $period)->where('source', self::SOURCE)->delete();
            foreach (array_chunk($terms, 500) as $chunk) {
                DB::table('ecl_pd_term_structures')->insert($chunk);
            }
            DB::table('ecl_projection_runs')->insert(['run_id' => $runId, 'reporting_period' => $period, 'scope_type' => 'portfolio', 'scope_value' => implode(',', array_keys($segments)),
                'methodology_version' => self::SOURCE, 'status' => 'COMPLETED', 'contracts_processed' => array_sum(array_column($segments, 'loans')), 'contracts_unresolved' => 0,
                'input_snapshot' => json_encode(['segments' => array_values(array_map(fn ($seg) => $seg + ['summary' => $summary[$seg['portfolio_id']] ?? []], $segments)),
                    'model_version' => ['id' => $fit->model_version_id, 'version' => $fit->version, 'relationship' => "{$fit->statistic_code} -> {$fit->proxy_code} (lag {$fit->lag_months})", 'effective_from' => $fit->effective_from],
                    'set' => ['id' => (int) $set->id, 'name' => $set->name, 'version' => (int) $set->version], 'horizon' => $h, 'method' => $method, 'adjustments' => $byYear]),
                'created_by' => $userId, 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        });
        AuditLoggerService::log('FLI Scenarios Projected', 'ecl_projection_runs', null, ['reporting_period' => $period, 'rows_affected' => count($terms),
            'new_values' => ['run' => $runId, 'portfolios' => count($segments), 'scenarios' => array_keys($byYear), 'horizon' => $h['label'], 'model_version' => $fit->version, 'set' => (int) $set->id], 'meta' => ['user' => $userId]]);

        return $this->latest($period);
    }

    /** The stored projection of a month, shaped for the screen. */
    public function latest(string $period): ?array
    {
        $run = DB::table('ecl_projection_runs')->where('reporting_period', $period)->where('methodology_version', self::SOURCE)->where('status', 'COMPLETED')->orderByDesc('id')->first();
        if ($run === null) {
            return null;
        }
        $in = json_decode((string) $run->input_snapshot, true);
        $segments = [];
        foreach ($in['segments'] ?? [] as $seg) {
            $rows = DB::table('ecl_pd_term_structures')->where('reporting_period', $period)->where('source', self::SOURCE)->where('contract_id', 'portfolio:' . $seg['portfolio_id'])
                ->orderBy('period_index')->get(['scenario_code', 'period_index', 'projection_date', 'conditional_pd', 'survival_open', 'marginal_pd', 'cumulative_pd']);
            $segments[] = $seg + ['terms' => $rows->groupBy('scenario_code')->map(fn ($g) => $g->values())->all(),
                'engine_lifetime_pd' => $this->engineLifetime((float) $seg['pd_pre'], (int) $seg['years'])];
        }

        usort($segments, fn ($x, $y) => $y['ead'] <=> $x['ead']); // the largest portfolio first

        return ['run_id' => $run->run_id, 'created_at' => (string) $run->completed_at, 'model_version' => $in['model_version'] ?? null, 'set' => $in['set'] ?? null,
            'horizon' => $in['horizon'] ?? null, 'method' => $in['method'] ?? null, 'adjustments' => $in['adjustments'] ?? [], 'segments' => $segments];
    }

    /** The engine's annualised lifetime PD over the same years, for comparison: 1 - (1 - PD12)^years. */
    private function engineLifetime(float $pd12, int $years): float
    {
        return round(1 - (1 - $pd12) ** max(1, $years), 8);
    }
}
