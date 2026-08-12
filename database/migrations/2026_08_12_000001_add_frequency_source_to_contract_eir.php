<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records whether payments_per_year was stated by a source, or assumed.
 *
 * The column keeps its DEFAULT 12 — monthly is the right assumption for most
 * of MAIIC's book and a useful starting point for a hand-created contract.
 * What was missing is the ability to tell the two apart: a facility whose
 * Extract A row said "Monthly" and one whose frequency column was blank both
 * stored 12, and both passed the readiness gate's frequency check.
 *
 * That matters most where it is quietest. A quarterly facility with no
 * imported schedule receives a Tier-2 generated schedule built at annual/12 on
 * monthly intervals; the schedule and the frequency are then internally
 * consistent, so the solver returns a perfectly plausible EIR that is not the
 * one the contract implies. Nothing in the resulting numbers looks wrong.
 *
 * ASSUMED is the default because an unproven frequency is the safe assumption
 * for any row created without one — a schedule-import stub, a hand-created
 * contract, or a master row whose frequency column was blank.
 *
 * Follows the established rate_source / schedule_source naming on this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_eir', function (Blueprint $table) {
            $table->enum('frequency_source', ['STATED', 'ASSUMED'])
                ->default('ASSUMED')
                ->after('payments_per_year');
        });
    }

    public function down(): void
    {
        Schema::table('contract_eir', function (Blueprint $table) {
            $table->dropColumn('frequency_source');
        });
    }
};
