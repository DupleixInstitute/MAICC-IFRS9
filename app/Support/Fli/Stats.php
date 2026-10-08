<?php

declare(strict_types=1);

namespace App\Support\Fli;

/**
 * Native-PHP statistical primitives for the FLI analytical engine.
 *
 * These are DEPENDENCY-FREE (no sidecar, no external service) so the single-
 * variable FLI default always works: Pearson, Spearman, Theil-Sen, ordinary
 * least squares (single + multivariate), the Student-t two-tailed p-value
 * (regularized incomplete beta), and the distribution shape stats (skew,
 * excess kurtosis) that the diagnostics use to decide which correlation method
 * the data can support. Every method returns null / a flagged result rather
 * than fabricating a number when the input is degenerate (n too small, zero
 * variance, singular design) - fail-closed, never a fake fit.
 *
 * Grounded in FLI_AND_PD_METHODOLOGY.md sections 5A, 12, 13.
 */
final class Stats
{
    /** @param array<int,float|int> $v */
    public static function mean(array $v): float
    {
        $n = count($v);

        return $n === 0 ? 0.0 : array_sum(array_map('floatval', $v)) / $n;
    }

    /**
     * Sample variance (n-1 denominator). Zero for n<2.
     *
     * @param array<int,float|int> $v
     */
    public static function sampleVariance(array $v): float
    {
        $n = count($v);
        if ($n < 2) {
            return 0.0;
        }
        $m = self::mean($v);
        $ss = 0.0;
        foreach ($v as $x) {
            $d = (float) $x - $m;
            $ss += $d * $d;
        }

        return $ss / ($n - 1);
    }

    /** @param array<int,float|int> $v */
    public static function stddev(array $v): float
    {
        return sqrt(self::sampleVariance($v));
    }

    /**
     * Pearson product-moment correlation. Null when n<3 or either series has
     * zero variance (correlation undefined - never coerced to 0).
     *
     * @param array<int,float|int> $x
     * @param array<int,float|int> $y
     */
    public static function pearson(array $x, array $y): ?float
    {
        $n = count($x);
        if ($n < 3 || $n !== count($y)) {
            return null;
        }
        $mx = self::mean($x);
        $my = self::mean($y);
        $sxy = 0.0;
        $sxx = 0.0;
        $syy = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = (float) $x[$i] - $mx;
            $dy = (float) $y[$i] - $my;
            $sxy += $dx * $dy;
            $sxx += $dx * $dx;
            $syy += $dy * $dy;
        }
        if ($sxx <= 0.0 || $syy <= 0.0) {
            return null;
        }

        $r = $sxy / sqrt($sxx * $syy);

        return max(-1.0, min(1.0, $r));
    }

    /**
     * Fractional ranks with average-of-ties (the rank basis Spearman needs).
     *
     * @param array<int,float|int> $v
     * @return array<int,float>
     */
    public static function ranks(array $v): array
    {
        $n = count($v);
        $idx = range(0, $n - 1);
        usort($idx, fn ($a, $b) => (float) $v[$a] <=> (float) $v[$b]);
        $ranks = array_fill(0, $n, 0.0);
        $i = 0;
        while ($i < $n) {
            $j = $i;
            while ($j + 1 < $n && (float) $v[$idx[$j + 1]] === (float) $v[$idx[$i]]) {
                $j++;
            }
            // average rank (1-based) across the tie block [i..j]
            $avg = (($i + 1) + ($j + 1)) / 2.0;
            for ($k = $i; $k <= $j; $k++) {
                $ranks[$idx[$k]] = $avg;
            }
            $i = $j + 1;
        }

        return $ranks;
    }

    /**
     * Spearman rank correlation (Pearson of the fractional ranks) - the robust
     * cross-check preferred on short, outlier-heavy Malawi series (section 12).
     *
     * @param array<int,float|int> $x
     * @param array<int,float|int> $y
     */
    public static function spearman(array $x, array $y): ?float
    {
        $n = count($x);
        if ($n < 3 || $n !== count($y)) {
            return null;
        }

        return self::pearson(self::ranks($x), self::ranks($y));
    }

    /**
     * Theil-Sen robust slope: median of all pairwise slopes. Null for n<3.
     *
     * @param array<int,float|int> $x
     * @param array<int,float|int> $y
     */
    public static function theilSen(array $x, array $y): ?float
    {
        $n = count($x);
        if ($n < 3 || $n !== count($y)) {
            return null;
        }
        $slopes = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $dx = (float) $x[$j] - (float) $x[$i];
                if ($dx === 0.0) {
                    continue;
                }
                $slopes[] = ((float) $y[$j] - (float) $y[$i]) / $dx;
            }
        }
        if ($slopes === []) {
            return null;
        }

        return self::median($slopes);
    }

    /** @param array<int,float> $v */
    public static function median(array $v): float
    {
        sort($v);
        $n = count($v);
        $mid = intdiv($n, 2);

        return $n % 2 === 1 ? $v[$mid] : ($v[$mid - 1] + $v[$mid]) / 2.0;
    }

    /**
     * Sample skewness (bias-corrected g1). Null for n<3 or zero variance.
     *
     * @param array<int,float|int> $v
     */
    public static function skewness(array $v): ?float
    {
        $n = count($v);
        if ($n < 3) {
            return null;
        }
        $m = self::mean($v);
        $sd = self::stddev($v);
        if ($sd <= 0.0) {
            return null;
        }
        $s = 0.0;
        foreach ($v as $x) {
            $s += (((float) $x - $m) / $sd) ** 3;
        }

        return ($n / (($n - 1) * ($n - 2))) * $s;
    }

    /**
     * Sample excess kurtosis (g2, normal == 0). Null for n<4 or zero variance.
     *
     * @param array<int,float|int> $v
     */
    public static function excessKurtosis(array $v): ?float
    {
        $n = count($v);
        if ($n < 4) {
            return null;
        }
        $m = self::mean($v);
        $sd = self::stddev($v);
        if ($sd <= 0.0) {
            return null;
        }
        $s = 0.0;
        foreach ($v as $x) {
            $s += (((float) $x - $m) / $sd) ** 4;
        }
        $a = ($n * ($n + 1)) / (($n - 1) * ($n - 2) * ($n - 3));
        $b = (3 * ($n - 1) ** 2) / (($n - 2) * ($n - 3));

        return $a * $s - $b;
    }

    /**
     * Single-variable OLS: y = slope*x + intercept, with R2, adjusted R2, the
     * slope standard error, its t-stat and two-tailed p-value.
     *
     * @param array<int,float|int> $x
     * @param array<int,float|int> $y
     * @return array{n:int,slope:?float,intercept:?float,r:?float,r2:?float,adj_r2:?float,se_slope:?float,t_stat:?float,p_value:?float,residual_se:?float}
     */
    public static function olsSimple(array $x, array $y): array
    {
        $n = count($x);
        $null = [
            'n' => $n, 'slope' => null, 'intercept' => null, 'r' => null, 'r2' => null,
            'adj_r2' => null, 'se_slope' => null, 't_stat' => null, 'p_value' => null, 'residual_se' => null,
        ];
        if ($n < 3 || $n !== count($y)) {
            return $null;
        }
        $mx = self::mean($x);
        $my = self::mean($y);
        $sxx = 0.0;
        $sxy = 0.0;
        $syy = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = (float) $x[$i] - $mx;
            $dy = (float) $y[$i] - $my;
            $sxx += $dx * $dx;
            $sxy += $dx * $dy;
            $syy += $dy * $dy;
        }
        if ($sxx <= 0.0) {
            return $null;
        }
        $slope = $sxy / $sxx;
        $intercept = $my - $slope * $mx;
        $r = ($syy > 0.0) ? max(-1.0, min(1.0, $sxy / sqrt($sxx * $syy))) : null;
        $r2 = $r === null ? null : $r * $r;

        // residual sum of squares
        $sse = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $e = (float) $y[$i] - ($slope * (float) $x[$i] + $intercept);
            $sse += $e * $e;
        }
        $df = $n - 2;
        $adjR2 = ($r2 === null || $df <= 0) ? null : 1.0 - (1.0 - $r2) * (($n - 1) / $df);
        $residualSe = $df > 0 ? sqrt($sse / $df) : null;
        $seSlope = ($residualSe !== null && $sxx > 0.0) ? $residualSe / sqrt($sxx) : null;
        $t = ($seSlope !== null && $seSlope > 0.0) ? $slope / $seSlope : null;
        $p = ($t !== null && $df > 0) ? self::studentTwoTailedP($t, $df) : null;

        return [
            'n' => $n, 'slope' => $slope, 'intercept' => $intercept, 'r' => $r, 'r2' => $r2,
            'adj_r2' => $adjR2, 'se_slope' => $seSlope, 't_stat' => $t, 'p_value' => $p, 'residual_se' => $residualSe,
        ];
    }

    /**
     * Multivariate OLS via the normal equations beta = (X'X)^-1 X'y (an
     * intercept column is prepended internally). Returns per-coefficient
     * p-values and the VIF per predictor. ok=false with a reason when the
     * design is rank-deficient / singular - never a fabricated coefficient.
     *
     * @param array<int,array<int,float|int>> $rows predictor rows (k columns, no intercept)
     * @param array<int,float|int> $y
     * @return array{ok:bool,error:?string,n:int,k:int,intercept:?float,coef:array<int,float>,r2:?float,adj_r2:?float,p_values:array<int,?float>,vif:array<int,?float>}
     */
    public static function olsMulti(array $rows, array $y): array
    {
        $n = count($rows);
        $k = $n > 0 ? count($rows[0]) : 0;
        $fail = fn (string $m): array => [
            'ok' => false, 'error' => $m, 'n' => $n, 'k' => $k, 'intercept' => null,
            'coef' => [], 'r2' => null, 'adj_r2' => null, 'p_values' => [], 'vif' => [],
        ];
        if ($k < 1) {
            return $fail('no predictors');
        }
        if ($n < $k + 2) {
            return $fail("insufficient observations (n={$n}) for {$k} predictor(s) plus intercept");
        }

        // Design matrix with leading intercept column.
        $X = [];
        foreach ($rows as $i => $row) {
            if (count($row) !== $k) {
                return $fail('ragged predictor matrix');
            }
            $X[$i] = array_merge([1.0], array_map('floatval', $row));
        }
        $p = $k + 1;

        $xtx = self::gram($X, $p);
        $inv = self::invert($xtx);
        if ($inv === null) {
            return $fail('singular X\'X (perfect multicollinearity)');
        }
        $xty = self::matVec(self::transpose($X), array_map('floatval', $y));
        $beta = self::matVec($inv, $xty);

        // Fitted values, residuals, R2.
        $yMean = self::mean($y);
        $sse = 0.0;
        $sst = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $fit = 0.0;
            for ($j = 0; $j < $p; $j++) {
                $fit += $beta[$j] * $X[$i][$j];
            }
            $e = (float) $y[$i] - $fit;
            $sse += $e * $e;
            $dy = (float) $y[$i] - $yMean;
            $sst += $dy * $dy;
        }
        $r2 = $sst > 0.0 ? 1.0 - $sse / $sst : null;
        $dfRes = $n - $p;
        $adjR2 = ($r2 === null || $dfRes <= 0) ? null : 1.0 - (1.0 - $r2) * (($n - 1) / $dfRes);
        $sigma2 = $dfRes > 0 ? $sse / $dfRes : null;

        // Per-coefficient p-values from sigma^2 * diag((X'X)^-1).
        $pValues = [];
        for ($j = 1; $j < $p; $j++) { // skip intercept (index 0)
            if ($sigma2 === null || $inv[$j][$j] <= 0.0) {
                $pValues[$j - 1] = null;
                continue;
            }
            $se = sqrt($sigma2 * $inv[$j][$j]);
            $t = $se > 0.0 ? $beta[$j] / $se : null;
            $pValues[$j - 1] = ($t !== null) ? self::studentTwoTailedP($t, $dfRes) : null;
        }

        // VIF per predictor: regress each predictor column on the others.
        $vif = self::vif($rows, $k, $n);

        $coef = [];
        for ($j = 1; $j < $p; $j++) {
            $coef[$j - 1] = $beta[$j];
        }

        return [
            'ok' => true, 'error' => null, 'n' => $n, 'k' => $k, 'intercept' => $beta[0],
            'coef' => $coef, 'r2' => $r2, 'adj_r2' => $adjR2, 'p_values' => $pValues, 'vif' => $vif,
        ];
    }

    /**
     * Variance inflation factors: VIF_j = 1/(1-R2_j) where R2_j comes from
     * regressing predictor j on all other predictors. Null when undefined.
     *
     * @param array<int,array<int,float|int>> $rows
     * @return array<int,?float>
     */
    public static function vif(array $rows, int $k, int $n): array
    {
        $out = [];
        if ($k < 2) {
            // A single predictor has no collinearity partners.
            for ($j = 0; $j < $k; $j++) {
                $out[$j] = 1.0;
            }

            return $out;
        }
        for ($j = 0; $j < $k; $j++) {
            $target = [];
            $others = [];
            foreach ($rows as $i => $row) {
                $target[$i] = (float) $row[$j];
                $rest = [];
                for ($c = 0; $c < $k; $c++) {
                    if ($c !== $j) {
                        $rest[] = (float) $row[$c];
                    }
                }
                $others[$i] = $rest;
            }
            $fit = self::olsMulti($others, $target);
            $r2 = $fit['ok'] ? $fit['r2'] : null;
            if ($r2 === null || $r2 >= 1.0) {
                $out[$j] = null; // undefined / perfect collinearity
            } else {
                $out[$j] = 1.0 / (1.0 - $r2);
            }
        }

        return $out;
    }

    /**
     * Two-tailed Student-t p-value via the regularized incomplete beta:
     * p = I_{df/(df+t^2)}(df/2, 1/2). Null for df<1.
     */
    public static function studentTwoTailedP(float $t, int $df): ?float
    {
        if ($df < 1) {
            return null;
        }
        $x = $df / ($df + $t * $t);

        return self::betai($df / 2.0, 0.5, $x);
    }

    // ---- internal linear-algebra + special functions ----

    /** @param array<int,array<int,float>> $X @return array<int,array<int,float>> */
    private static function transpose(array $X): array
    {
        $rows = count($X);
        $cols = $rows > 0 ? count($X[0]) : 0;
        $t = [];
        for ($j = 0; $j < $cols; $j++) {
            for ($i = 0; $i < $rows; $i++) {
                $t[$j][$i] = $X[$i][$j];
            }
        }

        return $t;
    }

    /** X'X for a design matrix with $p columns. @param array<int,array<int,float>> $X @return array<int,array<int,float>> */
    private static function gram(array $X, int $p): array
    {
        $g = [];
        for ($a = 0; $a < $p; $a++) {
            for ($b = 0; $b < $p; $b++) {
                $s = 0.0;
                foreach ($X as $row) {
                    $s += $row[$a] * $row[$b];
                }
                $g[$a][$b] = $s;
            }
        }

        return $g;
    }

    /** @param array<int,array<int,float>> $m @param array<int,float> $v @return array<int,float> */
    private static function matVec(array $m, array $v): array
    {
        $out = [];
        foreach ($m as $i => $row) {
            $s = 0.0;
            foreach ($row as $j => $val) {
                $s += $val * ($v[$j] ?? 0.0);
            }
            $out[$i] = $s;
        }

        return $out;
    }

    /**
     * Gauss-Jordan inverse with partial pivoting. Null when singular.
     *
     * @param array<int,array<int,float>> $m
     * @return array<int,array<int,float>>|null
     */
    private static function invert(array $m): ?array
    {
        $n = count($m);
        $a = [];
        for ($i = 0; $i < $n; $i++) {
            $a[$i] = array_map('floatval', $m[$i]);
            for ($j = 0; $j < $n; $j++) {
                $a[$i][$n + $j] = ($i === $j) ? 1.0 : 0.0;
            }
        }
        for ($col = 0; $col < $n; $col++) {
            $pivot = $col;
            $max = abs($a[$col][$col]);
            for ($r = $col + 1; $r < $n; $r++) {
                if (abs($a[$r][$col]) > $max) {
                    $max = abs($a[$r][$col]);
                    $pivot = $r;
                }
            }
            if ($max < 1e-12) {
                return null;
            }
            if ($pivot !== $col) {
                [$a[$col], $a[$pivot]] = [$a[$pivot], $a[$col]];
            }
            $pv = $a[$col][$col];
            for ($j = 0; $j < 2 * $n; $j++) {
                $a[$col][$j] /= $pv;
            }
            for ($r = 0; $r < $n; $r++) {
                if ($r === $col) {
                    continue;
                }
                $factor = $a[$r][$col];
                if ($factor === 0.0) {
                    continue;
                }
                for ($j = 0; $j < 2 * $n; $j++) {
                    $a[$r][$j] -= $factor * $a[$col][$j];
                }
            }
        }
        $inv = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $inv[$i][$j] = $a[$i][$n + $j];
            }
        }

        return $inv;
    }

    /** Log-gamma (Lanczos). */
    private static function gammln(float $xx): float
    {
        static $cof = [
            76.18009172947146, -86.50532032941677, 24.01409824083091,
            -1.231739572450155, 0.1208650973866179e-2, -0.5395239384953e-5,
        ];
        $x = $xx;
        $y = $xx;
        $tmp = $x + 5.5;
        $tmp -= ($x + 0.5) * log($tmp);
        $ser = 1.000000000190015;
        foreach ($cof as $c) {
            $y += 1.0;
            $ser += $c / $y;
        }

        return -$tmp + log(2.5066282746310005 * $ser / $x);
    }

    /** Regularized incomplete beta I_x(a,b). */
    private static function betai(float $a, float $b, float $x): float
    {
        if ($x <= 0.0) {
            return 0.0;
        }
        if ($x >= 1.0) {
            return 1.0;
        }
        $bt = exp(
            self::gammln($a + $b) - self::gammln($a) - self::gammln($b)
            + $a * log($x) + $b * log(1.0 - $x)
        );
        if ($x < ($a + 1.0) / ($a + $b + 2.0)) {
            return $bt * self::betacf($a, $b, $x) / $a;
        }

        return 1.0 - $bt * self::betacf($b, $a, 1.0 - $x) / $b;
    }

    /** Continued fraction for the incomplete beta (Lentz). */
    private static function betacf(float $a, float $b, float $x): float
    {
        $maxIt = 200;
        $eps = 3.0e-12;
        $fpmin = 1.0e-30;
        $qab = $a + $b;
        $qap = $a + 1.0;
        $qam = $a - 1.0;
        $c = 1.0;
        $d = 1.0 - $qab * $x / $qap;
        if (abs($d) < $fpmin) {
            $d = $fpmin;
        }
        $d = 1.0 / $d;
        $h = $d;
        for ($mIt = 1; $mIt <= $maxIt; $mIt++) {
            $m2 = 2 * $mIt;
            $aa = $mIt * ($b - $mIt) * $x / (($qam + $m2) * ($a + $m2));
            $d = 1.0 + $aa * $d;
            if (abs($d) < $fpmin) {
                $d = $fpmin;
            }
            $c = 1.0 + $aa / $c;
            if (abs($c) < $fpmin) {
                $c = $fpmin;
            }
            $d = 1.0 / $d;
            $h *= $d * $c;
            $aa = -($a + $mIt) * ($qab + $mIt) * $x / (($a + $m2) * ($qap + $m2));
            $d = 1.0 + $aa * $d;
            if (abs($d) < $fpmin) {
                $d = $fpmin;
            }
            $c = 1.0 + $aa / $c;
            if (abs($c) < $fpmin) {
                $c = $fpmin;
            }
            $d = 1.0 / $d;
            $del = $d * $c;
            $h *= $del;
            if (abs($del - 1.0) < $eps) {
                break;
            }
        }

        return $h;
    }

    /**
     * Native Dickey-Fuller unit-root test (constant, no trend, lag 0):
     *
     *   dy_t = alpha + rho * y_{t-1} + e_t,   tau = t(rho)
     *
     * H0: unit root (non-stationary). tau is compared against the MacKinnon
     * (2010) response-surface critical values for the constant-only case,
     * evaluated at the REGRESSION sample size so short Malawi series get the
     * correct finite-sample thresholds rather than asymptotic ones:
     *
     *   cv(p) = b_inf + b1/T + b2/T^2
     *     1%:  -3.43035  -6.5393/T  -16.786/T^2
     *     5%:  -2.86154  -2.8903/T   -4.234/T^2
     *     10%: -2.56677  -1.5384/T   -2.809/T^2
     *
     * Verdict: 'stationary' (tau < 5% cv), 'borderline' (tau < 10% cv),
     * 'non_stationary' otherwise; 'not_run' when fewer than 6 usable
     * differences exist (too short to regress honestly). Low power on short
     * samples is flagged in the reason - reported, never hidden.
     *
     * @param  array<int,float>  $series  observation values in period order
     * @return array{test:string,verdict:string,tau:?float,n:?int,crit_1pct:?float,crit_5pct:?float,crit_10pct:?float,reason:string}
     */
    public static function dickeyFuller(array $series): array
    {
        $series = array_values(array_map('floatval', $series));
        $t = count($series) - 1; // usable differences

        if ($t < 6) {
            return [
                'test' => 'df_native(constant,lag0)', 'verdict' => 'not_run',
                'tau' => null, 'n' => $t > 0 ? $t : 0,
                'crit_1pct' => null, 'crit_5pct' => null, 'crit_10pct' => null,
                'reason' => 'series too short for a unit-root regression (n_diff='.max(0, $t).' < 6); recorded not_run - never assumed stationary',
            ];
        }

        // Build dy_t and y_{t-1}.
        $lagged = [];
        $dy = [];
        for ($i = 1, $len = count($series); $i < $len; $i++) {
            $lagged[] = $series[$i - 1];
            $dy[] = $series[$i] - $series[$i - 1];
        }

        $ols = self::olsSimple($lagged, $dy);
        $tau = $ols['t_stat'];
        if ($tau === null) {
            return [
                'test' => 'df_native(constant,lag0)', 'verdict' => 'not_run',
                'tau' => null, 'n' => $t,
                'crit_1pct' => null, 'crit_5pct' => null, 'crit_10pct' => null,
                'reason' => 'unit-root regression degenerate (constant lagged level); recorded not_run',
            ];
        }

        // MacKinnon (2010) finite-sample response surface, constant only.
        $cv = fn (float $bInf, float $b1, float $b2): float => round($bInf + $b1 / $t + $b2 / ($t * $t), 4);
        $c1 = $cv(-3.43035, -6.5393, -16.786);
        $c5 = $cv(-2.86154, -2.8903, -4.234);
        $c10 = $cv(-2.56677, -1.5384, -2.809);

        if ($tau < $c5) {
            $verdict = 'stationary';
            $vs = sprintf('tau %.3f < 5%% cv %.3f -> reject unit root', $tau, $c5);
        } elseif ($tau < $c10) {
            $verdict = 'borderline';
            $vs = sprintf('tau %.3f < 10%% cv %.3f only -> weak evidence against a unit root', $tau, $c10);
        } else {
            $verdict = 'non_stationary';
            $vs = sprintf('tau %.3f >= 10%% cv %.3f -> cannot reject unit root', $tau, $c10);
        }

        $power = $t < 20 ? '; low power at n='.$t.' (short sample) - treat as indicative' : '';

        return [
            'test' => 'df_native(constant,lag0)',
            'verdict' => $verdict,
            'tau' => round($tau, 4),
            'n' => $t,
            'crit_1pct' => $c1, 'crit_5pct' => $c5, 'crit_10pct' => $c10,
            'reason' => $vs.$power,
        ];
    }
}
