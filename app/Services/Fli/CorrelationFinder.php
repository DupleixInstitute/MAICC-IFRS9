<?php

declare(strict_types=1);

namespace App\Services\Fli;

use App\Support\Fli\ExpectedSignPolicy;
use App\Support\Fli\GovernedValues;
use App\Support\Fli\SeriesAligner;
use App\Support\Fli\Stats;
use Illuminate\Support\Facades\DB;

/**
 * Auto-Correlate - the selling feature. Sweeps every macro driver X (macro_series
 * actuals) against every credit-loss proxy Y (credit_loss_series) across the
 * governed lag grid, computes rank/robust correlation first (Spearman,
 * Theil-Sen) plus Pearson and a single-variable OLS, ranks each pair by a
 * composite score, and SAVES the ranked fli_suggestions with a human-readable
 * reason - all under one immutable analysis_runs entry, reproducible from the
 * data vintage (inputs_hash).
 *
 * Fail-closed: with no Y data the sweep produces zero suggestions and says so;
 * a pair with too little overlap is rejected with the reason, never fabricated.
 *
 * FLI_AND_PD_METHODOLOGY.md section 12.
 */
final class CorrelationFinder
{
    private string $connection;

    private GovernedValues $gov;

    private Diagnostics $diagnostics;

    public function __construct(string $connection, GovernedValues $gov, ?Diagnostics $diagnostics = null)
    {
        $this->connection = $connection;
        $this->gov = $gov;
        $this->diagnostics = $diagnostics ?? new Diagnostics($gov);
    }

    private function db()
    {
        return DB::connection($this->connection);
    }

    /**
     * Drivers with a credible multi-year public forecast (IMF WEO / World Bank
     * MPO) - section 12.5/12.6. A driver that cannot be projected forward
     * cannot ultimately drive FLI, so this weighs into the rank.
     */
    public static function forecastable(string $statisticCode): bool
    {
        $c = strtolower($statisticCode);
        foreach (['gdp', 'inflation', 'cpi'] as $ok) {
            if (str_contains($c, $ok)) {
                return true;
            }
        }

        return false; // rate / FX / unemployment: no supportable multi-year path
    }

    /**
     * @param  callable(int,int,string,string,int,array<string,int>,?array<string,mixed>):void|null  $progress
     *         Per-iteration heartbeat: (done, total, xCode, yCode, lag, verdictTally, bestSoFar).
     * @return array{run_id:?int,suggestions:int,pairs_evaluated:int,x_series:int,y_series:int,by_verdict:array<string,int>,ranked:array<int,array<string,mixed>>,note:string}
     */
    public function run(string $asOfPeriod, ?callable $progress = null): array
    {
        $xSeries = $this->loadSeries('macro_series', 'statistic_code', "value_type = 'actual'");
        $ySeries = $this->loadSeries('credit_loss_series', 'proxy_code', null);

        // Governed historical horizon: bivariate search runs over MATCHING data
        // capped to the most recent N years (fli.max_history_years). Lags then
        // sweep within that window (multivariate search is a future extension).
        $maxYears = max(1, $this->gov->int('fli.max_history_years'));
        [$xSeries, $ySeries] = $this->capHorizon($xSeries, $ySeries, $maxYears);

        $definitions = $this->loadDefinitions();
        $lagGrid = $this->gov->lagGrid();
        $cutoff = $this->gov->float('fli.r2_cutoff.default');
        $minObs = $this->gov->int('stats.min_obs');
        $alpha = $this->gov->float('stats.alpha');

        if ($xSeries === [] || $ySeries === []) {
            $note = $ySeries === []
                ? 'no credit-loss proxy (Y) series present - Auto-Correlate produced 0 suggestions (fail-closed; Y data is absent).'
                : 'no macro (X) actuals present - run fli:import-macro first.';

            return [
                'run_id' => null, 'suggestions' => 0, 'pairs_evaluated' => 0,
                'x_series' => count($xSeries), 'y_series' => count($ySeries),
                'by_verdict' => [], 'ranked' => [], 'note' => $note,
            ];
        }

        $inputsHash = hash('sha256', json_encode([
            'x' => $this->vintage($xSeries), 'y' => $this->vintage($ySeries), 'lags' => $lagGrid,
        ]));

        $runId = (int) $this->db()->table('analysis_runs')->insertGetId([
            'run_type' => 'correlation',
            'reporting_period' => $asOfPeriod,
            'inputs_hash' => $inputsHash,
            'status' => 'complete',
            'run_by' => null,
            'run_at' => now(),
        ]);

        $now = now();
        $suggestions = [];
        $pairs = 0;
        $totalIterations = count($xSeries) * count($ySeries) * count($lagGrid);
        $tally = [];
        $bestOverall = null;

        foreach ($xSeries as $xCode => $xByPeriod) {
            foreach ($ySeries as $yCode => $yByPeriod) {
                $best = null;
                foreach ($lagGrid as $lag) {
                    $pairs++;
                    $row = $this->evaluatePair($xCode, (string) $yCode, $xByPeriod, $yByPeriod, $lag, $definitions, $cutoff, $minObs, $alpha);
                    if ($best === null || $row['score'] > $best['score']) {
                        $best = $row;
                    }
                    // Heartbeat: what the sweep is doing RIGHT NOW, the running
                    // verdict tallies and the best find so far - the cockpit
                    // polls this so the lag iterations are visible live.
                    if ($progress !== null) {
                        if ($bestOverall === null || $row['score'] > $bestOverall['score']) {
                            $bestOverall = $row;
                        }
                        $progress($pairs, $totalIterations, $xCode, (string) $yCode, $lag, $tally, [
                            'x' => $bestOverall['statistic_code'],
                            'y' => $bestOverall['proxy_code'],
                            'lag' => $bestOverall['lag_months'],
                            'score' => round((float) $bestOverall['score'], 4),
                            'r2' => $bestOverall['r_squared'],
                            'verdict' => $bestOverall['verdict'] ?? null,
                        ]);
                    }
                }
                if ($best !== null) {
                    $suggestions[] = $best; // one ranked suggestion per (X,Y) at its best lag
                    $tally[$best['verdict']] = ($tally[$best['verdict']] ?? 0) + 1;
                }
            }
        }

        // Rank by composite score, descending.
        usort($suggestions, fn ($a, $b) => $b['score'] <=> $a['score']);

        $byVerdict = [];
        $persist = [];
        foreach ($suggestions as $s) {
            $byVerdict[$s['verdict']] = ($byVerdict[$s['verdict']] ?? 0) + 1;
            $persist[] = [
                'run_id' => $runId,
                'statistic_code' => $s['statistic_code'],
                'proxy_code' => $s['proxy_code'],
                'lag_months' => $s['lag_months'],
                'score' => round($s['score'], 6),
                'r_squared' => $s['r_squared'],
                'sign_ok' => $s['sign_ok'],
                'verdict' => $s['verdict'],
                'reason' => mb_substr($s['reason'], 0, 255),
                // Full pre-fit diagnostics (native Dickey-Fuller stationarity,
                // normality shape, break window, method/transform) so the
                // cockpit can SHOW the tests, not just allude to them.
                'diagnostics' => isset($s['diagnostics']) ? json_encode($s['diagnostics']) : null,
                'created_at' => $now,
            ];
        }
        if ($persist !== []) {
            foreach (array_chunk($persist, 500) as $chunk) {
                $this->db()->table('fli_suggestions')->insert($chunk);
            }
        }

        return [
            'run_id' => $runId,
            'suggestions' => count($suggestions),
            'pairs_evaluated' => $pairs,
            'x_series' => count($xSeries),
            'y_series' => count($ySeries),
            'by_verdict' => $byVerdict,
            'ranked' => $suggestions,
            'note' => 'Auto-Correlate complete: ' . count($suggestions) . ' ranked suggestions from ' . $pairs . ' (X x Y x lag) evaluations.',
        ];
    }

    /**
     * @param array<string,float> $xByPeriod
     * @param array<string,float> $yByPeriod
     * @param array<int,array{statistic_code:string,proxy_code:string,expected_sign:string}> $definitions
     * @return array<string,mixed>
     */
    private function evaluatePair(string $xCode, string $yCode, array $xByPeriod, array $yByPeriod, int $lag, array $definitions, float $cutoff, int $minObs, float $alpha): array
    {
        $aligned = SeriesAligner::align($xByPeriod, $yByPeriod, $lag);
        $n = $aligned['n'];
        $sign = ExpectedSignPolicy::resolve($xCode, $yCode, $definitions);
        $expected = $sign['sign'];
        $forecastable = self::forecastable($xCode);

        $base = [
            'statistic_code' => $xCode,
            'proxy_code' => $yCode,
            'lag_months' => $lag,
            'n_obs' => $n,
            'overlap_start' => $aligned['overlap_start'],
            'overlap_end' => $aligned['overlap_end'],
            'expected_sign' => $expected,
            'forecastable' => $forecastable,
        ];

        if ($n < 3) {
            return array_merge($base, [
                'pearson' => null, 'spearman' => null, 'theil_sen' => null,
                'r_squared' => null, 'slope' => null, 'p_value' => null,
                'sign_ok' => null, 'realised_sign' => null, 'method_agreement' => null,
                'score' => 0.0, 'verdict' => 'rejected',
                'reason' => sprintf('[rejected] insufficient overlap (n=%d) for %s x %s at lag %dm', $n, $xCode, $yCode, $lag),
                'diagnostics' => null,
            ]);
        }

        $pearson = Stats::pearson($aligned['x'], $aligned['y']);
        $spearman = Stats::spearman($aligned['x'], $aligned['y']);
        $theil = Stats::theilSen($aligned['x'], $aligned['y']);
        $ols = Stats::olsSimple($aligned['x'], $aligned['y']);
        $r2 = $ols['r2'];
        $slope = $ols['slope'];
        $p = $ols['p_value'];

        $realised = $slope === null ? null : ($slope > 0 ? 'positive' : ($slope < 0 ? 'negative' : null));
        $signOk = ($expected === null || $realised === null) ? null : ($realised === $expected);

        $agree = ($pearson !== null && $spearman !== null
            && (($pearson >= 0) === ($spearman >= 0)))
            ? 'Pearson+Spearman agree'
            : 'method disagreement';

        // Composite score with the section-12.2 precedence encoded as gates x weights.
        $signGate = ($expected !== null && $signOk === false) ? 0.10 : 1.0;
        $forecastWeight = $forecastable ? 1.0 : 0.75;
        $strength = $r2 ?? 0.0;
        $sigScore = ($p !== null && $p <= $alpha) ? 1.0 : 0.0;
        $sampleScore = min(1.0, $n / max(1, $minObs));
        $agreeScore = ($agree === 'Pearson+Spearman agree') ? 1.0 : 0.0;
        $score = $signGate * $forecastWeight *
            (0.5 * $strength + 0.2 * $sigScore + 0.2 * $sampleScore + 0.1 * $agreeScore);

        // Verdict.
        if ($expected !== null && $signOk === false) {
            $verdict = 'rejected';
        } elseif ($r2 !== null && $r2 >= $cutoff && $sigScore === 1.0 && $n >= $minObs && $signOk !== false) {
            $verdict = 'recommended';
        } else {
            $verdict = 'usable_with_caveat';
        }

        $diag = $this->diagnostics->diagnose($aligned['x'], $aligned['y'], $aligned['periods']);
        $spanned = $diag['break']['registered_events_in_window'] ?? [];

        $reason = $this->composeReason($verdict, $realised, $expected, $signOk, $r2, $cutoff, $p, $alpha, $n, $minObs, $aligned, $agree, $lag, $forecastable, $spanned, $diag['recommended_method']);

        return array_merge($base, [
            'pearson' => $pearson, 'spearman' => $spearman, 'theil_sen' => $theil,
            'r_squared' => $r2 === null ? null : round($r2, 8), 'slope' => $slope, 'p_value' => $p,
            'sign_ok' => $signOk, 'realised_sign' => $realised, 'method_agreement' => $agree,
            'score' => $score, 'verdict' => $verdict, 'reason' => $reason,
            'diagnostics' => $diag,
        ]);
    }

    /** @param array<int,string> $spanned */
    private function composeReason(string $verdict, ?string $realised, ?string $expected, ?bool $signOk, ?float $r2, float $cutoff, ?float $p, float $alpha, int $n, int $minObs, array $aligned, string $agree, int $lag, bool $forecastable, array $spanned, string $recMethod): string
    {
        $parts = [];
        $parts[] = '[' . $verdict . ']';
        if ($signOk === null) {
            $parts[] = $expected === null ? 'sign n/a (context driver)' : 'sign indeterminate';
        } else {
            $parts[] = $signOk ? sprintf('correct sign (%s)', $realised) : sprintf('WRONG sign %s vs %s', $realised, $expected);
        }
        $parts[] = $r2 === null ? 'R2 n/a' : sprintf('R2 %.3f%s%.2f', $r2, $r2 >= $cutoff ? '>=' : '<', $cutoff);
        $parts[] = $p === null ? 'p n/a' : sprintf('p=%.3f%s%.2f', $p, $p <= $alpha ? '<=' : '>', $alpha);
        $parts[] = sprintf('%d obs %s-%s%s', $n, $aligned['overlap_start'] ?? '?', $aligned['overlap_end'] ?? '?', $n >= $minObs ? '' : ' (<min)');
        $parts[] = $agree;
        $parts[] = 'best lag ' . $lag . 'm';
        $parts[] = $forecastable ? 'forecastable (IMF WEO)' : 'no forward path - caveat';
        if ($spanned !== []) {
            $parts[] = 'spans ' . implode('/', $spanned);
        }
        $parts[] = 'method=' . $recMethod;

        return implode('; ', $parts);
    }

    /**
     * Load a period-keyed value series per group code from a table.
     *
     * @return array<string,array<string,float>>
     */
    private function loadSeries(string $table, string $codeCol, ?string $whereRaw): array
    {
        $q = $this->db()->table($table);
        if ($whereRaw !== null) {
            $q->whereRaw($whereRaw);
        }
        $rows = $q->orderBy($codeCol)->orderBy('observation_period')
            ->get([$codeCol, 'observation_period', 'value']);
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->$codeCol][(string) $r->observation_period] = (float) $r->value;
        }

        return $out;
    }

    /**
     * Cap every X and Y series to the most recent N years of MATCHING data: the
     * cutoff is the latest observed period across all series minus N years;
     * earlier points are dropped so a correlation only spans the governed
     * historical horizon (FLI_AND_PD_METHODOLOGY.md 12.5). YYYYMM strings compare
     * lexically, so a plain string comparison is the period comparison.
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

    /**
     * @return array<int,array{statistic_code:string,proxy_code:string,expected_sign:string}>
     */
    private function loadDefinitions(): array
    {
        try {
            $rows = $this->db()->table('regression_definitions')->get(['statistic_code', 'proxy_code', 'expected_sign']);
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'statistic_code' => (string) $r->statistic_code,
                'proxy_code' => (string) $r->proxy_code,
                'expected_sign' => (string) $r->expected_sign,
            ];
        }

        return $out;
    }

    /** @param array<string,array<string,float>> $series */
    private function vintage(array $series): array
    {
        $v = [];
        foreach ($series as $code => $byPeriod) {
            $v[$code] = count($byPeriod);
        }
        ksort($v);

        return $v;
    }
}
