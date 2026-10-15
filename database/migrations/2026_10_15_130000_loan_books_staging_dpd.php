<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The staging day count beside the directive's (Staging Module Specification
 * v1, section 5). overdue_days keeps the directive's count, which the RBM
 * return reads whatever the staging policy; staging_dpd is the count under the
 * staging settings in force at the month-end, and staging_dpd_basis says how
 * it was counted. A row built before this migration has none, and staging
 * derives the count from the row and says how many it derived.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_books')) {
            return;
        }
        Schema::table('loan_books', function (Blueprint $t) {
            if (! Schema::hasColumn('loan_books', 'staging_dpd')) {
                $t->integer('staging_dpd')->nullable()->after('overdue_days');
            }
            if (! Schema::hasColumn('loan_books', 'staging_dpd_basis')) {
                $t->string('staging_dpd_basis', 255)->nullable()->after('staging_dpd');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_books')) {
            return;
        }
        Schema::table('loan_books', function (Blueprint $t) {
            foreach (['staging_dpd_basis', 'staging_dpd'] as $c) {
                if (Schema::hasColumn('loan_books', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
