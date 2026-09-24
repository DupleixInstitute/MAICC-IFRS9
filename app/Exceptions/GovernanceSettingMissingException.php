<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when no approved governance setting is in force for a key on a date.
 *
 * The engine never falls back to a value written in code: a missing setting
 * stops the calculation and names what has to be approved in the Governance
 * Centre before it can run.
 */
class GovernanceSettingMissingException extends RuntimeException
{
    public static function forKey(string $key, string $asOf): self
    {
        return new self("No approved value is in force for the governance setting '{$key}' on {$asOf}. "
            . 'Approve one in the Governance Centre before running the calculation.');
    }
}
