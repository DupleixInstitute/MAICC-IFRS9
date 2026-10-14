<?php

namespace App\Services\Ecl;

use Illuminate\Support\Facades\Schema;

/**
 * The exposure at default the ECL engines measure, in one place (spec v4 3.8).
 *
 * EAD is the carrying amount plus the undrawn commitment at its utilisation
 * rate, floored at nil: a credit balance is owed to the customer, not an
 * exposure.
 *
 * A loan written off to a nil (or credit) balance has no exposure at all. The
 * write-off ends the facility, so the amount once left undrawn on it can no
 * longer be drawn and is not an exposure either. Before 14 October 2026 the
 * engines kept measuring that undrawn amount: 104420000007 (written off in
 * June 2026, 162.6m undrawn at 0.65) carried 49.4m undiscounted and 50.8m
 * discounted ECL on a nil balance at September 2026, and 104420000003
 * (written off in August 2024, 1.76m undrawn at 0.91) carried an allowance
 * every month after it. The write-off is the one the loan book build read
 * from the ledger (build_basis.ledger.written_off, WriteOffClassifier); a
 * row with no build basis (an imported book) carries no such evidence and is
 * measured as it stands.
 */
final class EclExposure
{
    /** The write-off the build recorded on the row, as SQL (MySQL and SQLite both read it). */
    private const WRITTEN_OFF_SQL = "CAST(COALESCE(json_extract(build_basis, '$.ledger.written_off'), 0) AS DECIMAL(20,2))";

    /** Carrying amount plus the undrawn commitment at its utilisation rate, before any floor. */
    public const GROSS_SQL = '(COALESCE(carrying_amount, 0) + COALESCE(commitments, 0) * COALESCE(facility_utilisation_rate, 1))';

    /** Whether loan_books carries the build basis the write-off is read from. */
    public static function hasBasis(): bool
    {
        return Schema::hasColumn('loan_books', 'build_basis');
    }

    /** True (as SQL) for a loan written off to a nil or credit balance. */
    public static function closedByWriteOffSql(?bool $hasBasis = null): string
    {
        return ($hasBasis ?? self::hasBasis())
            ? '(COALESCE(carrying_amount, 0) <= 0 AND ' . self::WRITTEN_OFF_SQL . ' > 0)'
            : '(1 = 0)';
    }

    /** The exposure before the nil floor: nil for a loan written off at nil, else the gross figure. */
    public static function unflooredSql(?bool $hasBasis = null): string
    {
        return '(CASE WHEN ' . self::closedByWriteOffSql($hasBasis) . ' THEN 0 ELSE ' . self::GROSS_SQL . ' END)';
    }

    /** The EAD the engine measures, as SQL: never below nil, nil for a loan written off at nil. */
    public static function sql(?bool $hasBasis = null): string
    {
        $gross = self::GROSS_SQL;

        return '(CASE WHEN ' . self::closedByWriteOffSql($hasBasis) . " THEN 0 WHEN {$gross} > 0 THEN {$gross} ELSE 0 END)";
    }

    /** Whether a loan row was written off to a nil or credit balance. */
    public static function closedByWriteOff(object $loan): bool
    {
        if ((float) ($loan->carrying_amount ?? 0) > 0) {
            return false;
        }
        $basis = $loan->build_basis ?? null;
        if (is_string($basis)) {
            $basis = json_decode($basis, true);
        } elseif (is_object($basis)) {
            $basis = json_decode(json_encode($basis), true);
        }

        return is_array($basis) && (float) ($basis['ledger']['written_off'] ?? 0) > 0;
    }

    /** The EAD the engine measures for one loan row. */
    public static function of(object $loan): float
    {
        if (self::closedByWriteOff($loan)) {
            return 0.0;
        }
        $utilisation = ($loan->facility_utilisation_rate ?? null) === null ? 1.0 : (float) $loan->facility_utilisation_rate;

        return max(0.0, (float) ($loan->carrying_amount ?? 0) + (float) ($loan->commitments ?? 0) * $utilisation);
    }
}
