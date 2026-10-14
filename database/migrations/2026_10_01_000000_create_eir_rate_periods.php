<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase P5, floating-rate resets (spec v3 sections 6.3 and 7.3, decision D8).
 *
 * A loan that follows the Reserve Bank prime rate has one EIR per stretch of
 * time between rate changes, not one for its life (IFRS 9 B5.4.5). Revenue
 * for a past month must be accrued at the EIR of that month, the ECL is
 * discounted at the EIR in force at the reporting date, and an auditor asks
 * which EIR applied when and why. contract_eir keeps the origination record
 * and is never overwritten; each rate period is a row here.
 *
 * Rate periods and schedule versions are not the same thing: a reset opens a
 * rate period and a schedule version, but arrears (P6) open a schedule
 * version and keep the EIR. So the period has its own table, and each
 * schedule row says what kind of version it belongs to.
 *
 * Units, as elsewhere in the engine: reference_rate and spread_over_prime in
 * percentage points as the rate series and the loan books state them (21.20,
 * 8.00); contractual_rate and the EIR columns as fractions, as on contract_eir
 * (0.2920).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eir_rate_periods', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id')->index();
            $table->string('sub_account_no', 20)->nullable();
            $table->unsignedSmallInteger('period_no')->comment('1 at origination, then one per reset');
            $table->date('effective_from');
            $table->date('effective_to')->nullable()->comment('Null for the period in force');
            $table->string('index_code', 20)->default('PLR');
            $table->decimal('reference_rate', 8, 5)->nullable()->comment('Prime rate in force, percentage points');
            $table->decimal('spread_over_prime', 8, 5)->nullable()->comment('Locked spread, percentage points');
            $table->string('spread_basis', 30)->nullable()->comment('Governance margin_basis the spread was taken on');
            $table->decimal('contractual_rate', 10, 7)->nullable()->comment('Reference + spread, as a fraction');
            $table->decimal('opening_amortised_cost', 20, 2)->nullable();
            $table->decimal('eir_period', 12, 10)->nullable();
            $table->decimal('eir_nominal_annual', 12, 10)->nullable();
            $table->decimal('eir_effective_annual', 12, 10)->nullable();
            $table->decimal('eir_resolved_effective_annual', 12, 10)->nullable()->comment('Re-solve on the new flows, the test oracle');
            $table->boolean('eir_disagreement_flag')->default(false);
            $table->string('solver_method', 30)->nullable();
            $table->unsignedSmallInteger('solver_iterations')->nullable();
            $table->decimal('solver_residual', 20, 10)->nullable();
            $table->json('input_snapshot')->nullable();
            $table->foreignId('rate_reset_event_id')->nullable()->constrained('rate_reset_events')->nullOnDelete();
            $table->unsignedSmallInteger('schedule_version')->nullable()->comment('The schedule version built for this period');
            $table->string('status', 20)->default('PENDING_EIR')->comment('PENDING_EIR | SOLVED | BLOCKED | LOCKED');
            $table->text('note')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['contract_id', 'effective_from'], 'uq_rate_period_contract_from');
        });

        Schema::table('rate_reset_events', function (Blueprint $table) {
            $table->date('plr_effective_date')->nullable()->after('reset_date')
                ->comment('The Reserve Bank date; reset_date is when the engine applies it (plr_mid_period)');
            $table->string('index_code', 20)->default('PLR')->after('plr_effective_date');
            $table->decimal('spread_over_prime', 8, 5)->nullable()->after('new_reference_rate');
            $table->decimal('old_contractual_rate', 10, 7)->nullable()->after('spread_over_prime');
            $table->decimal('new_contractual_rate', 10, 7)->nullable()->after('old_contractual_rate');
            $table->string('status', 20)->default('DETECTED')->after('new_schedule_version')
                ->comment('DETECTED | SCHEDULED | SOLVED | BLOCKED');
            $table->text('note')->nullable()->after('status');
            $table->unique(['contract_id', 'reset_date'], 'uq_reset_contract_date');
        });
        // A reset is detected before its schedule exists; the version is
        // written when the reset schedule is built (step 3).
        Schema::table('rate_reset_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('new_schedule_version')->nullable()->change();
        });

        Schema::table('contract_cashflow_schedule', function (Blueprint $table) {
            $table->string('schedule_type', 20)->default('ORIGINAL')->after('schedule_version')
                ->comment('ORIGINAL | RESET | ARREARS | RESTRUCTURE');
        });
    }

    public function down(): void
    {
        Schema::table('contract_cashflow_schedule', function (Blueprint $table) {
            $table->dropColumn('schedule_type');
        });
        Schema::table('rate_reset_events', function (Blueprint $table) {
            $table->dropUnique('uq_reset_contract_date');
            $table->dropColumn(['plr_effective_date', 'index_code', 'spread_over_prime',
                'old_contractual_rate', 'new_contractual_rate', 'status', 'note']);
        });
        Schema::dropIfExists('eir_rate_periods');
    }
};
