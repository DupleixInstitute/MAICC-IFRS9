<?php

namespace App\Support;

use RuntimeException;

/** A governed change whose effective date falls inside a locked month (ReportingPeriodLock::assertNotBackDated). */
final class BackDatedChangeException extends RuntimeException
{
    /** @param list<string> $locked the locked months the change would alter, earliest first */
    public function __construct(public readonly string $what, public readonly string $effectiveFrom, public readonly array $locked)
    {
        $months = count($locked) > 3 ? $locked[0] . ' to ' . end($locked) . ' (' . count($locked) . ' months)' : implode(', ', $locked);
        parent::__construct("{$what} cannot take effect from {$effectiveFrom}: the locked month {$months} would no longer read the values it ran on. "
            . 'Choose an effective date after the last locked month, or, if the restatement is deliberate, reopen the month through the unlock approval '
            . '(Imports & Feeds, Periods Built, or eir:lock-period --unlock) with its supporting documents, and make the change once a second person has approved the unlock.');
    }
}
