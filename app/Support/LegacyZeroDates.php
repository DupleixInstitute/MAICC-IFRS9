<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Older MAIIC tables (collateral_types, loan_books, legacy_loanbook) hold '0000-00-00' dates
 * written before strict mode. A column-type ALTER copies the whole table, and strict mode's
 * NO_ZERO_DATE then refuses those rows (SQLSTATE 22007, found migrating the production copy on
 * 17 October 2026). Running the ALTER with the zero-date checks off for this session copies the
 * rows exactly as they are: no stored value changes.
 */
final class LegacyZeroDates
{
    public static function alter(callable $alter): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $alter();

            return;
        }
        $mode = DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
        $relaxed = implode(',', array_diff(explode(',', $mode), ['NO_ZERO_DATE', 'NO_ZERO_IN_DATE']));
        DB::statement('SET SESSION sql_mode = ?', [$relaxed]);
        try {
            $alter();
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
        }
    }
}
