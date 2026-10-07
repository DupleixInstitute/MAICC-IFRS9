<?php

declare(strict_types=1);

namespace App\Services\Fli;

use App\Support\Fli\ExpectedSignPolicy;
use App\Support\Fli\GovernedValues;
use App\Support\Fli\SeriesAligner;
use App\Support\Fli\Stats;
use Illuminate\Support\Facades\DB;

/**
 * RegressionEngine - single-variable (default) and multivariate OLS, wired to
 * the Guardrail. Every fit is SAVED to fli_fits with slope / intercept /
 * correlation_r / r_squared / p_value / n_obs / sign_ok / inputs_hash and a
 * verdict of 'applied' or 'declined' (with declined_reason). A declined fit
 * QUARANTINES the relationship (PD falls back to pre-FLI); only 'applied' fits
 * may feed an FLI factor.
 *
 * fli_fits requires an fli_relationships parent (FK), so the engine finds-or-
 * creates the governed relationship from the resolved expected sign + cutoff.
 * Context-only drivers (no expected sign, e.g. FX) are NOT fitted here - they
 * remain suggestions-only, flagged usable_with_caveat by Auto-Correlate.
 *
 * FLI_AND_PD_METHODOLOGY.md sections 5, 5.1, 5.2, 6.
 */
final class RegressionEngine
{
    private string $connection;

    private GovernedValues $gov;

    private Guardrail $guardrail;

    public function __construct(string $connection, GovernedValues $gov, ?Guardrail $guardrail = null)
    {
        $this->connection = $connection;
        $this->gov = $gov;
        $this->guardrail = $guardrail ?? new Guardrail();
    }

    private function db()
    {
        return DB::connection($this->connection);
    }

    /**
     * Fit every candidate single-variable relationship over the available
     * macro actuals x credit-loss proxies and persist each fit with its
     * guardrail verdict. Candidates come from the governed regression_definitions
     * plus the seeded expected-sign policy (drivers with a defined sign).
     *
     * @return array{run_id:?int,fitted:int,applied:int,declined:int,declined_by_reason:array<string,int>,skipped_no_data:int,fits:array<int,array<string,mixed>>,note:string}
     */
    public function fitCandidates(string $asOfPeriod): array
    {
        $xSeries = $this->loadSeries('macro_series', 'statistic_code', "value_type = 'actual'");
        $ySeries = $this->loadSeries('credit_loss_series', 'proxy_code', null);
        // Same governed historical horizon as the sweep (fli.max_history_years).
        [$xSeries, $ySeries] = $this->capHorizon($xSeries, $ySeries, max(1, $this->gov->int('fli.max_history_years')));
        $definitions = $this->loadDefinitions();
        $lagGrid = $this->gov->lagGrid();
        $cutoff = $this->gov->float('fli.r2_cutoff.default');
        $minObs = $this->gov->int('stats.min_obs');
        $alpha = $this->gov->float('stats.alpha');

        if ($xSeries === [] || $ySeries === []) {
            return [
                'run_id' => null, 'fitted' => 0, 'applied' => 0, 'declined' => 0,
                'declined_by_reason' => [], 'skipped_no_data' => 0, 'fits' => [],
                'note' => $ySeries === [] ? 'no Y proxy series - nothing to fit (fail-closed).' : 'no X actuals - run fli:import-macro.',
            ];
        }

        $candidates = $this->buildCandidates($xSeries, $ySeries, $definitions, $cutoff);

        $runId = (int) $this->db()->table('analysis_runs')->insertGetId([
            'run_type' => 'regression',
            'reporting_period' => $asOfPeriod,
            'inputs_hash' => hash('sha256', json_encode(array_keys($candidates))),
            'status' => 'complete',
            'run_by' => null,
            'run_at' => now(),
        ]);

        $fitted = 0;
        $applied = 0;
        $declined = 0;
        $declinedBy = [];
        $skipped = 0;
        $fits = [];

        foreach ($candidates as $cand) {
            $xByPeriod = $xSeries[$cand['x']] ?? null;
            $yByPeriod = $ySeries[$cand['y']] ?? null;
            if ($xByPeriod === null || $yByPeriod === null) {
                $skipped++;
                continue;
            }
            [$lag, $aligned] = $this->bestLag($xByPeriod, $yByPeriod, $lagGrid);
            $result = $this->fitOne($asOfPeriod, $cand, $lag, $aligned, $cutoff, $minObs, $alpha);
            $fitted++;
            if ($result['verdict'] === 'applied') {
                $applied++;
            } else {
                $declined++;
                $reason = (string) $result['declined_reason'];
                $declinedBy[$reason] = ($declinedBy[$reason] ?? 0) + 1;
            }
            $fits[] = $result;
        }

        return [
            'run_id' => $runId,
            'fitted' => $fitted,
            'applied' => $applied,
            'declined' => $declined,
            'declined_by_reason' => $declinedBy,
            'skipped_no_data' => $skipped,
            'fits' => $fits,
            'note' => "Fitted {$fitted} relationship(s): {$applied} applied, {$declined} declined (quarantined -> pre-FLI PD).",
        ];
    }

    /**
     * Fit ONE relationship at a given lag and persist it. Public so a feature
     * test can drive a single weak relationship and assert the declined verdict.
     *
     * @param array{x:string,y:string,expected_sign:?string,cutoff:float,business_unit_id:?int} $cand
     * @param array{x:array<int,float>,y:array<int,float>,periods:array<int,string>,n:int,overlap_start:?string,overlap_end:?string} $aligned
     * @return array<string,mixed>
     */
    public function fitOne(string $asOfPeriod, array $cand, int $lag, array $aligned, float $cutoff, int $minObs, float $alpha): array
    {
        $ols = Stats::olsSimple($aligned['x'], $aligned['y']);
        $r = $ols['r'];
        $verdictData = $this->guardrail->evaluate(
            ['n' => $aligned['n'], 'r2' => $ols['r2'], 'p_value' => $ols['p_value'], 'slope' => $ols['slope']],
            $cand['expected_sign'],
            $cand['cutoff'] ?? $cutoff,
            $minObs,
            $alpha
        );

        $relId = $this->ensureRelationship($cand, $lag);
        $inputsHash = hash('sha256', json_encode([
            'x' => $cand['x'], 'y' => $cand['y'], 'lag' => $lag,
            'periods' => $aligned['periods'], 'xvals' => $aligned['x'], 'yvals' => $aligned['y'],
        ]));

        $nYears = $this->annualEquivalent($aligned['periods'] ?? []);
        $this->db()->table('fli_fits')->insert([
            'fli_relationship_id' => $relId,
            'reporting_period' => $asOfPeriod,
            'slope' => $ols['slope'],
            'intercept' => $ols['intercept'],
            'correlation_r' => $r,
            'r_squared' => $ols['r2'],
            'p_value' => $ols['p_value'],
            'n_obs' => $aligned['n'],
            'n_years' => $nYears,
            'sign_ok' => $verdictData['sign_ok'],
            'verdict' => $verdictData['verdict'],
            'declined_reason' => $verdictData['declined_reason'],
            'inputs_hash' => $inputsHash,
            'supporting_document_id' => null,
            'computed_at' => now(),
        ]);

        return [
            'fli_relationship_id' => $relId,
            'statistic_code' => $cand['x'],
            'proxy_code' => $cand['y'],
            'lag_months' => $lag,
            'n_obs' => $aligned['n'],
            'n_years' => $nYears,
            'slope' => $ols['slope'],
            'r_squared' => $ols['r2'],
            'p_value' => $ols['p_value'],
            'verdict' => $verdictData['verdict'],
            'declined_reason' => $verdictData['declined_reason'],
            'reasons' => $verdictData['reasons'],
        ];
    }

    /**
     * Multivariate OLS of one proxy Y on several macro drivers X at a common
     * lag. Returns the model with per-coefficient p-values and VIF, plus a
     * guardrail verdict on the overall R2 + VIF (not persisted to fli_fits,
     * which is single-parent; multivariate candidate shortlists persist via the
     * fli_model_candidates extension - out of scope for v1, documented).
     *
     * @param array<int,string> $xCodes
     * @return array{ok:bool,error:?string,n:int,r2:?float,adj_r2:?float,coef:array<int,float>,p_values:array<int,?float>,vif:array<int,?float>,vif_ok:bool,verdict:string,reason:string}
     */
    public function fitMultivariate(array $xCodes, string $yCode, int $lag = 0): array
    {
        $xSeries = $this->loadSeries('macro_series', 'statistic_code', "value_type = 'actual'");
        $ySeries = $this->loadSeries('credit_loss_series', 'proxy_code', null);
        $y = $ySeries[$yCode] ?? [];
        $vifMax = $this->gov->float('fli.vif.max');
        $cutoff = $this->gov->float('fli.r2_cutoff.default');

        // Build aligned design: each Y period needs every lagged X present.
        $rows = [];
        $yv = [];
        $yPeriods = array_keys($y);
        sort($yPeriods);
        foreach ($yPeriods as $p) {
            $xp = SeriesAligner::shiftPeriod((string) $p, $lag);
            $row = [];
            $complete = true;
            foreach ($xCodes as $code) {
                if (! isset($xSeries[$code][$xp])) {
                    $complete = false;
                    break;
                }
                $row[] = $xSeries[$code][$xp];
            }
            if ($complete) {
                $rows[] = $row;
                $yv[] = $y[$p];
            }
        }

        $model = Stats::olsMulti($rows, $yv);
        if (! $model['ok']) {
            return array_merge($model, ['vif_ok' => false, 'verdict' => 'declined', 'reason' => 'multivariate model not estimable: ' . $model['error']]);
        }
        $vifOk = true;
        foreach ($model['vif'] as $v) {
            if ($v === null || $v > $vifMax) {
                $vifOk = false;
                break;
            }
        }
        $r2Ok = $model['r2'] !== null && $model['r2'] >= $cutoff;
        $verdict = ($vifOk && $r2Ok) ? 'applied' : 'declined';
        $reason = sprintf(
            'multivariate: R2=%s (cutoff %.2f), VIF %s (max %.1f) -> %s',
            $model['r2'] === null ? 'n/a' : number_format($model['r2'], 3),
            $cutoff,
            $vifOk ? 'ok' : 'exceeds',
            $vifMax,
            $verdict
        );

        return array_merge($model, ['vif_ok' => $vifOk, 'verdict' => $verdict, 'reason' => $reason]);
    }

    /**
     * @param array<string,array<string,float>> $xSeries
     * @param array<string,array<string,float>> $ySeries
     * @param array<int,array{statistic_code:string,proxy_code:string,expected_sign:string,r2_cutoff_pct:float}> $definitions
     * @return array<string,array{x:string,y:string,expected_sign:?string,cutoff:float,business_unit_id:?int}>
     */
    private function buildCandidates(array $xSeries, array $ySeries, array $definitions, float $cutoff): array
    {
        // Case-insensitive map from macro code so 'RBM_Policy_Rate' resolves to 'rbm_policy_rate'.
        $xLower = [];
        foreach (array_keys($xSeries) as $code) {
            $xLower[strtolower($code)] = $code;
        }
        $out = [];

        // (a) governed regression_definitions (bank-APPROVED policy).
        foreach ($definitions as $d) {
            $xResolved = $xLower[strtolower($d['statistic_code'])] ?? null;
            if ($xResolved === null || ! isset($ySeries[$d['proxy_code']])) {
                continue;
            }
            $key = $xResolved . '|' . $d['proxy_code'];
            $out[$key] = [
                'x' => $xResolved, 'y' => $d['proxy_code'],
                'expected_sign' => strtolower($d['expected_sign']),
                'cutoff' => $d['r2_cutoff_pct'] > 1 ? $d['r2_cutoff_pct'] / 100.0 : (float) $d['r2_cutoff_pct'],
                'business_unit_id' => null,
                'sign_source' => 'regression_definitions',
                'sign_status' => 'approved',
            ];
        }

        // (b) EVERY macro X x every proxy Y - INCLUDING context drivers (FX) with
        // no pre-defined sign. Bivariate discovery: the pair is fitted regardless;
        // a null (context) sign stores PROVISIONAL 'either' (guardrail sign gate
        // off); a seeded reference sign is also provisional until approved. Only a
        // regression_definitions sign (path a) is 'approved'.
        foreach ($xSeries as $xCode => $_) {
            $sign = ExpectedSignPolicy::resolve($xCode, 'AvgPD', $definitions)['sign']; // may be null
            foreach ($ySeries as $yCode => $__) {
                $key = $xCode . '|' . (string) $yCode;
                if (isset($out[$key])) {
                    continue; // already added by a governed (approved) definition
                }
                $out[$key] = [
                    'x' => $xCode, 'y' => (string) $yCode,
                    'expected_sign' => $sign, 'cutoff' => $cutoff, 'business_unit_id' => null,
                    'sign_source' => $sign === null ? 'either' : 'seeded_ssa_policy',
                    'sign_status' => 'provisional',
                ];
            }
        }

        return $out;
    }

    /**
     * Choose the lag maximizing overlap n, tie-broken by highest |Pearson|.
     *
     * @param array<string,float> $xByPeriod
     * @param array<string,float> $yByPeriod
     * @param array<int,int> $lagGrid
     * @return array{0:int,1:array{x:array<int,float>,y:array<int,float>,periods:array<int,string>,n:int,overlap_start:?string,overlap_end:?string}}
     */
    private function bestLag(array $xByPeriod, array $yByPeriod, array $lagGrid): array
    {
        $bestLag = $lagGrid[0];
        $bestAligned = SeriesAligner::align($xByPeriod, $yByPeriod, $bestLag);
        $bestKey = [$bestAligned['n'], 0.0];
        foreach ($lagGrid as $lag) {
            $al = SeriesAligner::align($xByPeriod, $yByPeriod, $lag);
            $absR = $al['n'] >= 3 ? abs(Stats::pearson($al['x'], $al['y']) ?? 0.0) : 0.0;
            $key = [$al['n'], $absR];
            if ($key > $bestKey) {
                $bestKey = $key;
                $bestLag = $lag;
                $bestAligned = $al;
            }
        }

        return [$bestLag, $bestAligned];
    }

    /**
     * Annual-equivalent sample size = the number of distinct calendar years the
     * matched window spans (frequency-agnostic; 8 annual points = 8 years, 24
     * monthly points = 2 years).
     *
     * @param  array<int,string>  $periods  aligned YYYYMM periods
     */
    private function annualEquivalent(array $periods): float
    {
        $years = [];
        foreach ($periods as $p) {
            $years[substr((string) $p, 0, 4)] = true;
        }

        return (float) count($years);
    }

    /** @param array{x:string,y:string,expected_sign:?string,cutoff:float,business_unit_id:?int,sign_source?:string,sign_status?:string} $cand */
    private function ensureRelationship(array $cand, int $lag): int
    {
        // expected_sign is now NULLABLE: null == 'either' (context driver; the
        // guardrail applies no sign gate), pending later approval.
        $expected = $cand['expected_sign'] ?? null;
        $existing = $this->db()->table('fli_relationships')
            ->where('statistic_code', $cand['x'])
            ->where('proxy_code', $cand['y'])
            ->where('lag_months', $lag);
        if ($cand['business_unit_id'] === null) {
            $existing->whereNull('business_unit_id');
        } else {
            $existing->where('business_unit_id', $cand['business_unit_id']);
        }
        $row = $existing->first();
        if ($row) {
            return (int) $row->id; // keep an existing (possibly approved) relationship
        }

        $source = $cand['sign_source'] ?? ($expected === null ? 'either' : 'seeded_ssa_policy');
        $note = [
            'regression_definitions' => 'bank-approved expected sign (regression_definitions)',
            'seeded_ssa_policy' => 'seeded SSA reference sign - provisional, pending approval',
            'either' => 'context driver: no expected sign (either) - sign gate off, pending approval',
        ][$source] ?? 'provisional, pending approval';

        return (int) $this->db()->table('fli_relationships')->insertGetId([
            'statistic_code' => $cand['x'],
            'proxy_code' => $cand['y'],
            'business_unit_id' => $cand['business_unit_id'],
            'expected_sign' => $expected,
            'sign_status' => $cand['sign_status'] ?? 'provisional',
            'sign_source' => $source,
            'sign_note' => $note,
            'r2_cutoff' => $cand['cutoff'],
            'method' => 'pearson',
            'mode' => 'single',
            'lag_months' => $lag,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Cap X and Y to the most recent N years of matching data (same rule as the
     * CorrelationFinder sweep, FLI_AND_PD_METHODOLOGY.md 12.5).
     *
     * @param  array<string,array<string,float>>  $x
     * @param  array<string,array<string,float>>  $y
     * @return array{0:array<string,array<string,float>>,1:array<string,array<string,float>>}
     */
    private function capHorizon(array $x, array $y, int $maxYears): array
    {
        $maxPeriod = '000000';
        foreach ([$x, $y] as $set) {
            foreach ($set as $byPeriod) {
                foreach (array_keys($byPeriod) as $p) {
                    if ((string) $p > $maxPeriod) {
                        $maxPeriod = (string) $p;
                    }
                }
            }
        }
        if ($maxPeriod === '000000') {
            return [$x, $y];
        }
        $cutoff = sprintf('%04d%s', ((int) substr($maxPeriod, 0, 4)) - $maxYears, substr($maxPeriod, 4, 2));
        $filter = static function (array $set) use ($cutoff): array {
            $out = [];
            foreach ($set as $code => $byPeriod) {
                $kept = array_filter($byPeriod, static fn ($p) => (string) $p >= $cutoff, ARRAY_FILTER_USE_KEY);
                if ($kept !== []) {
                    $out[$code] = $kept;
                }
            }

            return $out;
        };

        return [$filter($x), $filter($y)];
    }

    /** @return array<string,array<string,float>> */
    private function loadSeries(string $table, string $codeCol, ?string $whereRaw): array
    {
        $q = $this->db()->table($table);
        if ($whereRaw !== null) {
            $q->whereRaw($whereRaw);
        }
        $rows = $q->orderBy($codeCol)->orderBy('observation_period')->get([$codeCol, 'observation_period', 'value']);
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->$codeCol][(string) $r->observation_period] = (float) $r->value;
        }

        return $out;
    }

    /** @return array<int,array{statistic_code:string,proxy_code:string,expected_sign:string,r2_cutoff_pct:float}> */
    private function loadDefinitions(): array
    {
        try {
            $rows = $this->db()->table('regression_definitions')->get(['statistic_code', 'proxy_code', 'expected_sign', 'r2_cutoff_pct']);
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'statistic_code' => (string) $r->statistic_code,
                'proxy_code' => (string) $r->proxy_code,
                'expected_sign' => (string) $r->expected_sign,
                'r2_cutoff_pct' => (float) $r->r2_cutoff_pct,
            ];
        }

        return $out;
    }
}
