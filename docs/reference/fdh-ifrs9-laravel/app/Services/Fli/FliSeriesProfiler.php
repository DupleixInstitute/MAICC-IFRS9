<?php

declare(strict_types=1);

namespace App\Services\Fli;

use App\Support\Fli\GovernedValues;
use App\Support\Fli\Stats;
use Illuminate\Support\Facades\DB;

/**
 * Persists the distribution profile of every FLI dataset - each macro driver
 * (X) and credit-loss proxy (Y) - so "the type of distribution for each dataset"
 * is STORED (fli_series_distributions), not recomputed ad hoc
 * (FLI_AND_PD_METHODOLOGY.md section 13).
 *
 * Per series: n, period span, mean/stddev, skewness + excess kurtosis, a
 * normality-shape verdict (against the governed skew/kurtosis limits), and the
 * native Dickey-Fuller unit-root verdict (tau vs MacKinnon finite-sample
 * critical values) with the correlation method the shape can support. A short
 * human label (e.g. "right-skewed, non-stationary") summarises it for the UI.
 * Recomputed whenever a series' observations change and at each Auto-Correlate.
 */
final class FliSeriesProfiler
{
    public function __construct(
        private readonly string $connection,
        private readonly GovernedValues $gov,
    ) {
    }

    private function db()
    {
        return DB::connection($this->connection);
    }

    /** Re-profile every driver + credit-loss series; returns how many were profiled. */
    public function profileAll(): int
    {
        $count = 0;
        foreach ($this->db()->table('macro_series')->distinct()->pluck('statistic_code') as $code) {
            if ($this->profile((string) $code, 'driver') !== null) {
                $count++;
            }
        }
        foreach ($this->db()->table('credit_loss_series')->distinct()->pluck('proxy_code') as $code) {
            if ($this->profile((string) $code, 'credit_loss') !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Profile one series and upsert its stored distribution.
     *
     * @return array<string,mixed>|null null when the series has no observations
     */
    public function profile(string $code, string $kind): ?array
    {
        [$table, $col] = $kind === 'credit_loss'
            ? ['credit_loss_series', 'proxy_code']
            : ['macro_series', 'statistic_code'];

        $rows = $this->db()->table($table)->where($col, $code)
            ->orderBy('observation_period')->get(['observation_period', 'value']);
        if ($rows->isEmpty()) {
            return null;
        }

        $values = $rows->map(fn ($r) => (float) $r->value)->all();
        $periods = $rows->map(fn ($r) => (string) $r->observation_period)->all();
        $n = count($values);

        $skew = Stats::skewness($values);
        $kurt = Stats::excessKurtosis($values);
        $skewLimit = $this->gov->float('stats.skew_limit');
        $kurtLimit = $this->gov->float('stats.kurtosis_limit');
        $nonNormal = ($skew !== null && abs($skew) > $skewLimit) || ($kurt !== null && $kurt > $kurtLimit);
        $normality = $n < 3 ? 'not_run' : ($nonNormal ? 'non_normal' : 'normal_ish');

        $df = Stats::dickeyFuller($values);
        $method = $normality === 'non_normal' ? 'spearman_theilsen' : $this->gov->string('stats.default_method');

        $data = [
            'code' => $code,
            'kind' => $kind,
            'n_obs' => $n,
            'min_period' => $periods[0],
            'max_period' => end($periods),
            'mean' => Stats::mean($values),
            'stddev' => Stats::stddev($values),
            'skewness' => $skew,
            'excess_kurtosis' => $kurt,
            'normality_verdict' => $normality,
            'df_verdict' => $df['verdict'],
            'df_tau' => $df['tau'],
            'df_crit_5pct' => $df['crit_5pct'],
            'recommended_method' => $method,
            'distribution_label' => $this->label($skew, $kurt, $df['verdict']),
            'computed_at' => now(),
        ];

        $this->db()->table('fli_series_distributions')->upsert([$data], ['code', 'kind'], array_keys($data));

        return $data;
    }

    /** Short human summary of the distribution: shape + tail + stationarity. */
    private function label(?float $skew, ?float $kurt, string $df): string
    {
        if ($skew === null) {
            $shape = 'n/a';
        } elseif (abs($skew) < 0.5) {
            $shape = 'near-symmetric';
        } elseif ($skew > 0) {
            $shape = 'right-skewed';
        } else {
            $shape = 'left-skewed';
        }
        if ($kurt !== null && $kurt > 1.0) {
            $shape .= ', heavy-tailed';
        }

        $stat = [
            'stationary' => 'stationary',
            'borderline' => 'borderline stationarity',
            'non_stationary' => 'non-stationary (unit root)',
            'not_run' => 'unit-root n/a',
        ][$df] ?? $df;

        return $shape.'; '.$stat;
    }
}
