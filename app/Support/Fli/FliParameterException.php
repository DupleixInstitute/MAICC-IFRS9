<?php

declare(strict_types=1);

namespace App\Support\Fli;

use RuntimeException;

/**
 * Thrown when a required governed FLI parameter cannot be resolved from an
 * approved, in-force row in governed_parameters.
 *
 * The FLI cockpit is fail-closed: there is NO default branch in the resolver.
 * A missing cutoff, alpha, lag grid or method switch STOPS the analysis rather
 * than being silently defaulted - the same discipline the ECL engine applies
 * (see App\Support\Engine\EngineParameterException).
 */
final class FliParameterException extends RuntimeException
{
    public static function missing(string $key, string $period): self
    {
        return new self(sprintf(
            'Required FLI parameter [%s] has no approved, in-force row in governed_parameters '
            . 'for period %s. The FLI engine is fail-closed and will not default it. '
            . 'Run fli:import-macro (which seeds the governed FLI defaults) or approve the parameter, then re-run.',
            $key,
            $period
        ));
    }
}
