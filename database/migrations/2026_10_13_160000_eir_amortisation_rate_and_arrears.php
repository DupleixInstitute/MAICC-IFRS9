<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-estimation of floating-rate loans and arrears at the opening (13 October 2026,
 * specification v4 decisions D38 and D39). Each roll-forward row records the
 * effective annual EIR it accrued at, which for a floating-rate loan moves with the
 * rate E-Banker charged that month (IFRS 9 B5.4.5), and the first row records the
 * arrears added to its opening: what the ledger owed at the end of the month before
 * beyond the balance the schedule expected.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['eir_amortisation', 'eir_amortisation_history'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'eir_annual')) {
                    $t->decimal('eir_annual', 12, 8)->nullable()->after('opening_basis')
                        ->comment('The effective annual EIR the month accrued at');
                }
                if (! Schema::hasColumn($table, 'opening_arrears')) {
                    $t->decimal('opening_arrears', 20, 2)->default(0)->after('eir_annual')
                        ->comment('First month only: arrears added to the opening');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['eir_amortisation', 'eir_amortisation_history'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn(['eir_annual', 'opening_arrears']);
                });
            }
        }
    }
};
