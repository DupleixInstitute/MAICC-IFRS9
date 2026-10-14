<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * No approved staging threshold is in force for a facility at the period end
 * (StagingThreshold::forFacility). Staging stops and names what is missing;
 * it never assumes a ladder. An importer that skips bad rows lets this one
 * through, so a missing rule fails the import instead of dropping its rows.
 */
class StagingRuleMissingException extends RuntimeException
{
}
