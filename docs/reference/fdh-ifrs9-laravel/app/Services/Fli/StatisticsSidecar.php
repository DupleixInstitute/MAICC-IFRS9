<?php

declare(strict_types=1);

namespace App\Services\Fli;

/**
 * Contract for the Python Statistics/ML sidecar that runs the advanced
 * diagnostics (Shapiro-Wilk, Anderson-Darling, ADF, KPSS, Chow, Ljung-Box) -
 * FLI_AND_PD_METHODOLOGY.md section 13.4.
 *
 * FAIL-CLOSED: in this build there is NO configured sidecar and NO live HTTP
 * call is made. available() returns false unless an operator sets the endpoint
 * (config fli.sidecar.url or env FLI_SIDECAR_URL). When unavailable, the
 * Diagnostics engine records the advanced verdicts as 'not_run' - NEVER a
 * silent 'passed'. The native Pearson/Spearman path always works without it.
 */
final class StatisticsSidecar
{
    public function endpoint(): ?string
    {
        $url = config('fli.sidecar.url') ?? env('FLI_SIDECAR_URL');
        $url = is_string($url) ? trim($url) : '';

        return $url === '' ? null : $url;
    }

    public function available(): bool
    {
        return $this->endpoint() !== null;
    }

    /**
     * POST /diagnose. Intentionally NOT implemented as a live call in this
     * build: it throws if invoked while unconfigured so no caller can mistake
     * an absent sidecar for a successful run.
     *
     * @param array{x:array<int,float>,y:array<int,float>,periods:array<int,string>,alpha:float,min_obs:int,event_dates:array<int,string>} $payload
     * @return array<string,mixed>
     */
    public function diagnose(array $payload): array
    {
        if (! $this->available()) {
            throw new \RuntimeException(
                'Statistics sidecar is not configured; advanced diagnostics cannot run. '
                . 'The FLI engine records them as verdict=not_run (never passed) and falls back to native Pearson/Spearman.'
            );
        }

        // A configured deployment would issue the HTTP POST here. This build
        // performs no live external calls (documented stub).
        throw new \RuntimeException('Live sidecar calls are disabled in this build.');
    }
}
