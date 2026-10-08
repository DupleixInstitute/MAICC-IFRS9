<?php

declare(strict_types=1);

namespace App\Services\Fli;

use App\Support\Fli\GovernedValues;
use App\Support\Fli\Stats;

/**
 * Pre-fit distribution & stationarity diagnostics - FLI_AND_PD_METHODOLOGY.md
 * section 13. Runs as a gate before any fit is trusted, to (a) pick the
 * correlation method the data can support and (b) flag a would-be spurious fit.
 *
 * Native (always available, no sidecar): Pearson, Spearman, skewness, excess
 * kurtosis, and a normality-shape verdict that demotes Pearson to
 * Spearman/Theil-Sen when the series is skewed/heavy-tailed (typical of the
 * short, high-inflation Malawi data).
 *
 * Advanced (Shapiro / ADF / KPSS / Chow / Ljung-Box): delegated to the Python
 * sidecar. When it is not configured the verdicts are recorded 'not_run' -
 * NEVER a silent 'passed'. The structural-events register supplies the exact
 * break dates the Chow test would use, and the overlap window is checked
 * against it so a fit's reason can name the regime it spans.
 *
 * All thresholds (alpha, min_obs, skew/kurtosis limits, shapiro_max_n,
 * default_method) come from governed_parameters via GovernedValues - fail-closed.
 */
final class Diagnostics
{
    private GovernedValues $gov;

    private StatisticsSidecar $sidecar;

    private StructuralEventsRegister $events;

    public function __construct(GovernedValues $gov, ?StatisticsSidecar $sidecar = null, ?StructuralEventsRegister $events = null)
    {
        $this->gov = $gov;
        $this->sidecar = $sidecar ?? new StatisticsSidecar();
        $this->events = $events ?? new StructuralEventsRegister($gov->connection());
    }

    /**
     * @param array<int,float> $x
     * @param array<int,float> $y
     * @param array<int,string> $periods aligned YYYYMM periods (for the break-window check)
     * @return array{n:int,sidecar:string,normality:array<string,mixed>,stationarity_x:array<string,mixed>,stationarity_y:array<string,mixed>,break:array<string,mixed>,autocorrelation:array<string,mixed>,recommended_method:string,recommended_transform:string,reasons:array<int,string>}
     */
    public function diagnose(array $x, array $y, array $periods = []): array
    {
        $n = count($x);
        $alpha = $this->gov->float('stats.alpha');
        $minObs = $this->gov->int('stats.min_obs');
        $skewLimit = $this->gov->float('stats.skew_limit');
        $kurtLimit = $this->gov->float('stats.kurtosis_limit');
        $defaultMethod = $this->gov->string('stats.default_method');

        $reasons = [];
        $sidecarStatus = $this->sidecar->available() ? 'ok' : 'not_configured';

        // ---- native normality shape (skew / excess kurtosis) ----
        $skewX = Stats::skewness($x);
        $kurtX = Stats::excessKurtosis($x);
        $skewY = Stats::skewness($y);
        $kurtY = Stats::excessKurtosis($y);

        $nonNormal = false;
        foreach ([['x', $skewX, $kurtX], ['y', $skewY, $kurtY]] as [$lbl, $sk, $ku]) {
            if ($sk !== null && abs($sk) > $skewLimit) {
                $nonNormal = true;
                $reasons[] = sprintf('%s skew %.2f exceeds limit %.2f -> demote Pearson', $lbl, $sk, $skewLimit);
            }
            if ($ku !== null && $ku > $kurtLimit) {
                $nonNormal = true;
                $reasons[] = sprintf('%s excess-kurtosis %.2f exceeds limit %.2f -> demote Pearson', $lbl, $ku, $kurtLimit);
            }
        }

        if ($n < 3) {
            $normalityVerdict = 'not_run';
            $reasons[] = "normality shape not_run (n={$n} < 3)";
        } else {
            $normalityVerdict = $nonNormal ? 'non_normal' : 'normal_ish';
        }

        // Method choice: shape drives it; small samples add a robust caveat.
        $recommendedMethod = ($normalityVerdict === 'non_normal') ? 'spearman_theilsen' : $defaultMethod;
        if ($n < $minObs) {
            $reasons[] = sprintf('n=%d below governed min_obs=%d (short-sample spurious-fit risk); robust cross-check advised', $n, $minObs);
        }

        // ---- advanced tests: fail-closed to not_run without a sidecar ----
        $notRun = fn (string $test): array => [
            'test' => $test, 'verdict' => 'not_run',
            'reason' => 'statistics sidecar not configured; recorded not_run (never passed)',
        ];

        // Stationarity: the NATIVE Dickey-Fuller (constant, lag 0, MacKinnon
        // 2010 finite-sample critical values) always runs, so the unit-root
        // evidence is visible even without the Python sidecar. The sidecar's
        // full ADF+KPSS supersedes it when configured; the native tau and its
        // critical values are reported either way (distribution: DF tau under
        // H0, NOT Student-t - hence the MacKinnon surface).
        $stationarityX = Stats::dickeyFuller($x);
        $stationarityY = Stats::dickeyFuller($y);
        foreach ([['x', $stationarityX], ['y', $stationarityY]] as [$lbl, $st]) {
            if (in_array($st['verdict'], ['non_stationary', 'borderline'], true)) {
                $reasons[] = sprintf('%s unit-root: %s (%s)', $lbl, $st['verdict'], $st['reason']);
            }
        }
        $autocorrelation = $notRun('ljung_box');

        // ---- structural-break window check (dates from the register) ----
        $spanned = $this->events->eventsInWindow($periods[0] ?? null, $periods[count($periods) - 1] ?? null);
        $codes = array_map(fn ($e) => (string) $e['code'], $spanned);
        $break = [
            'test' => 'chow',
            'verdict' => 'not_run',
            'reason' => 'Chow test needs the sidecar; recorded not_run',
            'registered_events_in_window' => $codes,
            'flag' => $codes !== [],
            'source' => $this->events->usingTable() ? 'structural_events table' : 'governed seed stub',
        ];
        if ($codes !== []) {
            $reasons[] = 'overlap window spans registered event(s): ' . implode(', ', $codes);
        }

        // Transform recommendation now follows the native DF evidence: a unit
        // root on either side -> difference before trusting a levels fit.
        $vx = $stationarityX['verdict'];
        $vy = $stationarityY['verdict'];
        if ($vx === 'non_stationary' || $vy === 'non_stationary') {
            $recommendedTransform = 'first_difference';
            $reasons[] = 'unit root indicated -> recommended_transform=first_difference (levels fit risks spurious regression)';
        } elseif ($vx === 'stationary' && $vy === 'stationary') {
            $recommendedTransform = 'levels';
        } elseif ($vx === 'not_run' || $vy === 'not_run') {
            $recommendedTransform = 'not_run';
            $reasons[] = 'series too short for the unit-root test -> recommended_transform=not_run';
        } else {
            $recommendedTransform = 'levels_with_caveat';
        }

        return [
            'n' => $n,
            'sidecar' => $sidecarStatus,
            'normality' => [
                'test' => $n <= $this->gov->int('stats.shapiro_max_n') ? 'native_shape(shapiro_when_sidecar)' : 'native_shape(dagostino_when_sidecar)',
                'skew_x' => $skewX, 'kurtosis_x' => $kurtX, 'skew_y' => $skewY, 'kurtosis_y' => $kurtY,
                'verdict' => $normalityVerdict,
                'alpha' => $alpha,
            ],
            'stationarity_x' => $stationarityX,
            'stationarity_y' => $stationarityY,
            'break' => $break,
            'autocorrelation' => $autocorrelation,
            'recommended_method' => $recommendedMethod,
            'recommended_transform' => $recommendedTransform,
            'reasons' => $reasons,
        ];
    }
}
