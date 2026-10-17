<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A staging or gross-amount override reconciles one closed period to its audited figures, so it
 * names that period and only that month's build applies it. Without it, every month built applied
 * the override (found on the fresh bootstrap of 17 October 2026: MUSECO zeroed from July 2024 to
 * September 2026). Every override held before this migration was made on 17 October 2026 for the
 * December 2025 audited workbook, so those rows are dated 2025-12.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['loan_staging_basis_overrides', 'loan_gross_amount_overrides'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'reporting_period')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('reporting_period', 7)->nullable()->after('contract_id')->index();
                });
                DB::table($table)->whereNull('reporting_period')->update(['reporting_period' => '2025-12']);
            }
        }
    }

    public function down(): void
    {
        foreach (['loan_staging_basis_overrides', 'loan_gross_amount_overrides'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'reporting_period')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropIndex($table . '_reporting_period_index');
                    $t->dropColumn('reporting_period');
                });
            }
        }
    }
};
