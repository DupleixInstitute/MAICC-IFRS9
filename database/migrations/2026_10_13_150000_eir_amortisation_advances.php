<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facilities drawn in tranches (system audit of 13 October 2026). The roll-forward
 * records the advances drawn in the month and the basis its first month opened on:
 * SCHEDULE (the present value of the remaining scheduled cash flows, which already
 * counts every tranche) or LEDGER (the drawn balance in the ledger, to which each
 * later advance is added in the month it is drawn). The row then ties as
 * opening + advances + interest + unwind - cash - derecognised = closing.
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
                if (! Schema::hasColumn($table, 'advances')) {
                    $t->decimal('advances', 20, 2)->default(0)->after('opening_gross');
                }
                if (! Schema::hasColumn($table, 'opening_basis')) {
                    $t->string('opening_basis', 12)->nullable()->after('advances')
                        ->comment('SCHEDULE or LEDGER: what the first month of the roll-forward opened on');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['eir_amortisation', 'eir_amortisation_history'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn(['advances', 'opening_basis']);
                });
            }
        }
    }
};
