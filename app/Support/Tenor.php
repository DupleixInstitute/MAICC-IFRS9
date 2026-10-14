<?php

namespace App\Support;

/**
 * One unit for remaining_tenor: months (system audit of 9 October 2026,
 * finding C2). The governed loan-book build writes months
 * (LoanBookBuildService); a reader that needs whole years converts here.
 */
final class Tenor
{
    /**
     * The whole number of years that covers a remaining tenor in months:
     * 1 to 12 months is year 1, 13 to 24 is year 2, and so on. A tenor that
     * is missing, not a number or not positive gives null, never a guess.
     */
    public static function yearsCovering(mixed $months): ?int
    {
        if ($months === null || $months === '' || ! is_numeric($months)) {
            return null;
        }
        $months = (float) $months;
        if ($months <= 0) {
            return null;
        }

        return (int) ceil(round($months, 6) / 12);
    }
}
