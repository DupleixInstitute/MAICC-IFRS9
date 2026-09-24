<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase P4 of the EIR engine (spec v3 sections 7.1 and 12.1): what the
 * schedule generator decided, recorded beside the terms it decided it from.
 *
 * The generator now has shapes to choose between - two moratorium shapes, a
 * level instalment or equal principal, interest and instalments on the
 * approved amount or on the balance - and it reads the day count from the
 * Governance Centre. A reviewer, and later an auditor, has to be able to see
 * which shape a stored schedule was built on without re-running the
 * generator, so each decision is written to the contract row when the draft
 * is generated.
 *
 * schedule_amortising_balance is the balance the instalments retire: the
 * amount drawn, the approved amount where the instalment is sized on the
 * sanction, plus any interest capitalised during a moratorium of type Both.
 * The readiness gate reconciles the scheduled principal to this figure
 * instead of assuming the drawn amount grown at the annual rate over twelve,
 * which was true only of the one moratorium shape the generator used to build.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_eir', function (Blueprint $table) {
            $table->decimal('schedule_amortising_balance', 20, 2)->nullable()->after('schedule_generated_at')
                ->comment('The balance the generated instalments retire');
            $table->string('schedule_moratorium_type', 20)->nullable()->after('schedule_amortising_balance')
                ->comment('PRINCIPAL_ONLY or BOTH, as the generated schedule was built');
            $table->char('schedule_emi_calc_type', 1)->nullable()->after('schedule_moratorium_type')
                ->comment('E level instalment or P equal principal');
            $table->char('schedule_interest_basis', 1)->nullable()->after('schedule_emi_calc_type')
                ->comment('S interest on the approved amount, B on the balance');
            $table->string('schedule_instalment_basis', 20)->nullable()->after('schedule_interest_basis')
                ->comment('SANCTION or DISBURSEMENT');
            $table->string('schedule_day_count', 10)->nullable()->after('schedule_instalment_basis')
                ->comment('The day count in force when the schedule was generated');
            $table->string('schedule_basis_sources', 255)->nullable()->after('schedule_day_count')
                ->comment('Where each shape came from: the contract, its scheme or a named assumption');
        });
    }

    public function down(): void
    {
        Schema::table('contract_eir', function (Blueprint $table) {
            $table->dropColumn([
                'schedule_amortising_balance',
                'schedule_moratorium_type',
                'schedule_emi_calc_type',
                'schedule_interest_basis',
                'schedule_instalment_basis',
                'schedule_day_count',
                'schedule_basis_sources',
            ]);
        });
    }
};
