<?php

declare(strict_types=1);

namespace App\Support\Fli;

/**
 * Ported as it is from the Dupleix suite (FDH IFRS 9 Laravel, app/Support/Fli),
 * spec v4 section 14.7: the nine reference methods. Pure functions; no table.
 *

 * The nine FLI-adjustment methods harvested from the abandoned legacy
 * calculator (display_and_apply_regression_results_all_methods.php), revived
 * as governed, selectable producers of an FLI factor -
 * FLI_AND_PD_METHODOLOGY.md section 5A / REVISED_SCHEMA.md section 4.
 *
 * They are pure functions of a fit (slope / intercept / correlation R) crossed
 * with the scenario-weighted driver forecast. Each returns null when its input
 * is undefined (zero denominator, missing base) - never a fabricated number.
 *
 * Output "kind":
 *   statistic  - a driver x-value (weighted forecast)
 *   difference - an absolute year-on-year change in the driver
 *   change     - a relative year-on-year change (fraction)
 *   pd         - an absolute forecast PD (slope*macro + intercept)
 *   factor     - a multiplicative FLI adjustment fli_adj; transmission is
 *                post_PD = pre_PD * (1 + fli_adj)  (section 8, multiplicative)
 */
final class AdjustmentMethods
{
    public const METHODS = [
        'weighted_statistic',
        'annual_diff_inStatistic',
        'annual_change_inStatistic',
        'annual_diff_ByCorr',
        'annual_change_ByCorr',
        'pd_forecast',
        'fli_adj_byPDs',
        'fli_adj_FDH_ByDiff',
        'fli_adj_FDH_ByChange',
    ];

    /** Method 1: scenario-weighted driver forecast = SUM(value * probability). */
    public static function weightedStatistic(array $scenarios): ?float
    {
        if ($scenarios === []) {
            return null;
        }
        $sum = 0.0;
        $wsum = 0.0;
        foreach ($scenarios as $s) {
            $sum += (float) $s['value'] * (float) $s['probability'];
            $wsum += (float) $s['probability'];
        }
        if ($wsum <= 0.0) {
            return null;
        }

        return $sum;
    }

    /** Method 2: year(n) - year(n-1). */
    public static function annualDiff(float $current, float $prior): float
    {
        return $current - $prior;
    }

    /** Method 3: year(n)/year(n-1) - 1. Null if prior == 0. */
    public static function annualChange(float $current, float $prior): ?float
    {
        if ($prior === 0.0) {
            return null;
        }

        return $current / $prior - 1.0;
    }

    /** Method 4: annual_diff x correlation R. */
    public static function annualDiffByCorr(float $current, float $prior, float $r): float
    {
        return self::annualDiff($current, $prior) * $r;
    }

    /** Method 5: annual_change x correlation R. Null if prior == 0. */
    public static function annualChangeByCorr(float $current, float $prior, float $r): ?float
    {
        $c = self::annualChange($current, $prior);

        return $c === null ? null : $c * $r;
    }

    /** Method 6: regression equation slope*macro + intercept (an absolute PD forecast). */
    public static function pdForecast(float $slope, float $intercept, float $macro): float
    {
        return $slope * $macro + $intercept;
    }

    /** Method 7: pd_forecast(n) / base_pd - 1 (multiplicative FLI factor). Null if base_pd == 0. */
    public static function fliAdjByPds(float $pdForecast, float $basePd): ?float
    {
        if ($basePd === 0.0) {
            return null;
        }

        return $pdForecast / $basePd - 1.0;
    }

    /**
     * Method 8: FDH by-difference variant - the correlation-scaled ABSOLUTE
     * driver difference used as the multiplicative FLI factor:
     *   fli_adj = R * (macro_forecast - macro_base)
     * (FDH's "by difference" convention; section 8.)
     */
    public static function fliAdjFdhByDiff(float $macroForecast, float $macroBase, float $r): float
    {
        return $r * ($macroForecast - $macroBase);
    }

    /**
     * Method 9: FDH by-change variant - the correlation-scaled RELATIVE driver
     * change used as the multiplicative FLI factor:
     *   fli_adj = R * (macro_forecast / macro_base - 1)
     * Null if macro_base == 0. (FDH's "by change" convention; section 8.)
     */
    public static function fliAdjFdhByChange(float $macroForecast, float $macroBase, float $r): ?float
    {
        if ($macroBase === 0.0) {
            return null;
        }

        return $r * ($macroForecast / $macroBase - 1.0);
    }

    /**
     * Governed dispatcher. Returns the value, its kind and a human-readable
     * note, or a null value with the reason it could not be computed.
     *
     * @param array{scenarios?:array<int,array{value:float,probability:float}>,annual_current?:float,annual_prior?:float,correlation_r?:float,slope?:float,intercept?:float,macro?:float,base_pd?:float,macro_base?:float,macro_forecast?:float} $ctx
     * @return array{method:string,value:?float,kind:string,note:string}
     */
    public static function compute(string $method, array $ctx): array
    {
        $r = (float) ($ctx['correlation_r'] ?? 0.0);
        $cur = (float) ($ctx['annual_current'] ?? 0.0);
        $prior = (float) ($ctx['annual_prior'] ?? 0.0);

        return match ($method) {
            'weighted_statistic' => self::wrap($method, self::weightedStatistic($ctx['scenarios'] ?? []), 'statistic', 'SUM(scenario value x probability)'),
            'annual_diff_inStatistic' => self::wrap($method, self::annualDiff($cur, $prior), 'difference', 'year(n) - year(n-1)'),
            'annual_change_inStatistic' => self::wrap($method, self::annualChange($cur, $prior), 'change', 'year(n)/year(n-1) - 1'),
            'annual_diff_ByCorr' => self::wrap($method, self::annualDiffByCorr($cur, $prior, $r), 'difference', 'annual_diff x R'),
            'annual_change_ByCorr' => self::wrap($method, self::annualChangeByCorr($cur, $prior, $r), 'change', 'annual_change x R'),
            'pd_forecast' => self::wrap($method, self::pdForecast((float) ($ctx['slope'] ?? 0.0), (float) ($ctx['intercept'] ?? 0.0), (float) ($ctx['macro'] ?? 0.0)), 'pd', 'slope x macro + intercept'),
            'fli_adj_byPDs' => self::wrap($method, self::fliAdjByPds((float) ($ctx['macro'] ?? self::pdForecast((float) ($ctx['slope'] ?? 0.0), (float) ($ctx['intercept'] ?? 0.0), (float) ($ctx['macro'] ?? 0.0))), (float) ($ctx['base_pd'] ?? 0.0)), 'factor', 'pd_forecast / base_pd - 1'),
            'fli_adj_FDH_ByDiff' => self::wrap($method, self::fliAdjFdhByDiff((float) ($ctx['macro_forecast'] ?? 0.0), (float) ($ctx['macro_base'] ?? 0.0), $r), 'factor', 'R x (macro_forecast - macro_base)'),
            'fli_adj_FDH_ByChange' => self::wrap($method, self::fliAdjFdhByChange((float) ($ctx['macro_forecast'] ?? 0.0), (float) ($ctx['macro_base'] ?? 0.0), $r), 'factor', 'R x (macro_forecast/macro_base - 1)'),
            default => ['method' => $method, 'value' => null, 'kind' => 'unknown', 'note' => "unknown adjustment method [{$method}]"],
        };
    }

    /** @return array{method:string,value:?float,kind:string,note:string} */
    private static function wrap(string $method, ?float $value, string $kind, string $note): array
    {
        return [
            'method' => $method,
            'value' => $value,
            'kind' => $kind,
            'note' => $value === null ? $note . ' (undefined for these inputs)' : $note,
        ];
    }
}
