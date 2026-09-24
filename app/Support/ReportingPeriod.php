<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * The one place that reads loan_books.reporting_period.
 *
 * The column is a free string. It was created as YYYYMM, widened to a
 * nullable string in January 2025, and the importer has since written
 * YYYY-MM and full dates into it depending on the mapping. Three services
 * read it with SUBSTR and REPLACE tricks that only hold if every row uses
 * one shape (branch analysis, section 10 item 8). This helper turns any of
 * the shapes into YYYY-MM once, so a caller compares periods as text and
 * gets the month end as a date without re-deriving the rule.
 */
final class ReportingPeriod
{
    /**
     * Canonical YYYY-MM from YYYYMM, YYYY-MM, YYYY/MM, YYYY-MM-DD, a full
     * timestamp, or the same with a leading or trailing space. Anything else
     * returns null rather than a guess.
     */
    public static function normalise($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^(\d{4})[-\/](\d{1,2})(?:[-\/]\d{1,2})?(?:[ T].*)?$/', $text, $m)
            || preg_match('/^(\d{4})(\d{2})(?:\d{2})?$/', $text, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            if ($year >= 1900 && $year <= 2999 && $month >= 1 && $month <= 12) {
                return sprintf('%04d-%02d', $year, $month);
            }
        }

        return null;
    }

    /** The last calendar day of the period, the date the loan book is struck at. */
    public static function monthEnd(string $period): ?Carbon
    {
        $normalised = self::normalise($period);
        if ($normalised === null) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $normalised . '-01')->endOfMonth()->startOfDay();
    }

    /** True when $value is the same month as $period, whatever shape each is written in. */
    public static function same($value, string $period): bool
    {
        $a = self::normalise($value);

        return $a !== null && $a === self::normalise($period);
    }
}
