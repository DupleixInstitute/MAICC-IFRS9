<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance: composite indexes for the dashboard / loan book / ECL hot
 * paths. expected_credit_loss previously had ONLY a primary key, and
 * loan_books had no usable index on the period+portfolio / period+stage
 * filters every screen uses.
 *
 * Prefix lengths keep the keys small (reporting_period is varchar(199) but
 * holds 7-char 'YYYY-MM' values; the stage column holds '1'/'2'/'3').
 */
return new class extends Migration
{
    private const INDEXES = [
        ['loan_books', 'lb_period_portfolio', '(`reporting_period`(7), `loan_portfolio_id`)'],
        ['loan_books', 'lb_period_stage', '(`reporting_period`(7), `ifrs9stage_post_qualitative`(2))'],
        ['expected_credit_loss', 'ecl_period_level', '(`reporting_period`(7), `ecl_calculation_level`(10), `ecl_calculation_id`)'],
        ['expected_credit_loss', 'ecl_period_stage', '(`reporting_period`(7), `ifrs9_stage`)'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // prefix-index syntax is MySQL-only; sqlite tests skip
        }

        foreach (self::INDEXES as [$table, $name, $columns]) {
            if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
                continue;
            }
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$name}` {$columns}");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::INDEXES as [$table, $name]) {
            if (Schema::hasTable($table) && $this->indexExists($table, $name)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return (int) DB::selectOne(
            'SELECT COUNT(1) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index]
        )->c > 0;
    }
};
