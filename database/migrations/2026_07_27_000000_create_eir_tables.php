<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| EIR module schema — see docs/EIR_Build.md and docs/Development_of_EIR.md
|--------------------------------------------------------------------------
| The EIR is a once-per-contract fact locked at origination, so it lives on
| contract-keyed tables that the monthly loan_books snapshots JOIN to.
| contract_id is a plain indexed string (no FK): schedule files and loan
| tapes arrive in either order, so a hard constraint would fail whichever
| import lands first. Integrity is enforced by import validation and the
| orphan-check report instead.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_eir', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id')->unique();
            $table->enum('instrument_type', ['AMORTISED_LOAN', 'PREF_SHARE', 'EQUITY_EXCLUDED'])
                  ->default('AMORTISED_LOAN');
            $table->enum('rate_type', ['FIXED', 'FLOATING'])->default('FIXED');
            $table->decimal('reference_rate_at_origination', 8, 5)->nullable();
            $table->decimal('markup', 8, 5)->nullable();
            // For FLOATING contracts only the fee spread (EIR minus all-in
            // contractual rate) is locked; the index component re-estimates
            // at each reset (IFRS 9 B5.4.5).
            $table->decimal('fee_spread', 8, 5)->nullable();
            $table->date('origination_date')->nullable();
            $table->decimal('approved_amount', 20, 2)->nullable();
            $table->decimal('drawn_amount', 20, 2)->nullable();
            $table->unsignedTinyInteger('moratorium_months')->default(0);
            $table->unsignedTinyInteger('payments_per_year')->default(12);
            $table->decimal('eir_period', 10, 7)->nullable()
                  ->comment('Solved IRR per payment period (decimal)');
            $table->decimal('eir_nominal_annual', 10, 7)->nullable()
                  ->comment('eir_period * payments_per_year');
            $table->decimal('eir_effective_annual', 10, 7)->nullable()
                  ->comment('(1+eir_period)^payments_per_year - 1');
            $table->enum('rate_source', ['SOLVED_EIR', 'CONTRACTUAL_PROXY'])
                  ->default('CONTRACTUAL_PROXY');
            $table->enum('schedule_source', ['IMPORTED', 'GENERATED'])->nullable();
            $table->boolean('below_market_flag')->default(false)
                  ->comment('FinES concessional pricing: day-1 fair value question');
            $table->unsignedSmallInteger('solver_iterations')->nullable();
            $table->double('solver_residual')->nullable();
            $table->json('input_snapshot')->nullable()
                  ->comment('Exact cash-flow vector solved, for auditor reperformance');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('contract_cashflow_schedule', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id')->index();
            $table->unsignedSmallInteger('schedule_version')->default(1);
            $table->date('effective_from')->nullable();
            $table->date('due_date');
            $table->decimal('principal_due', 20, 2)->default(0);
            $table->decimal('interest_due', 20, 2)->default(0);
            $table->decimal('fee_due', 20, 2)->default(0);
            $table->enum('schedule_source', ['IMPORTED', 'GENERATED'])->default('IMPORTED');
            $table->timestamps();
            // Restructures append a new version; version 1 is never
            // overwritten (modification accounting needs both).
            $table->unique(['contract_id', 'schedule_version', 'due_date'], 'uq_schedule_contract_version_date');
        });

        Schema::create('contract_fees', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id')->index();
            $table->string('fee_type', 50)
                  ->comment('arrangement | legal | appraisal | default | levy | other');
            // Signed: netting lines exist on real contracts (e.g. legal cost
            // netted against legal fee).
            $table->decimal('amount', 20, 2);
            $table->enum('basis', ['ON_APPROVED', 'ON_DRAWN'])->default('ON_APPROVED');
            $table->boolean('integral')->default(true)
                  ->comment('IFRS 9 B5.4.1: inside the EIR if directly attributable');
            $table->string('gl_account_ref', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('eir_amortisation', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id');
            $table->string('reporting_period', 7)->comment('YYYY-MM');
            $table->decimal('opening_gross', 20, 2)->default(0);
            $table->decimal('interest_accrued', 20, 2)->default(0);
            $table->enum('interest_basis', ['GROSS', 'NET'])->default('GROSS');
            $table->decimal('unwind_amount', 20, 2)->default(0)
                  ->comment('Stage 3: time-value growth of the allowance');
            $table->decimal('cash_received', 20, 2)->default(0);
            $table->enum('cash_source', ['DERIVED', 'IMPORTED'])->default('DERIVED');
            $table->decimal('modification_gain_loss', 20, 2)->default(0);
            $table->decimal('closing_gross', 20, 2)->default(0);
            $table->decimal('ecl_allowance', 20, 2)->default(0);
            $table->timestamps();
            $table->unique(['contract_id', 'reporting_period'], 'uq_amort_contract_period');
            $table->index('reporting_period');
        });

        Schema::create('rate_reset_events', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id')->index();
            $table->date('reset_date');
            $table->decimal('old_reference_rate', 8, 5);
            $table->decimal('new_reference_rate', 8, 5);
            $table->unsignedSmallInteger('new_schedule_version');
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('import_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('import_type', 50)
                  ->comment('schedule | fees | loan_book | repayments_actual');
            $table->string('source_header');
            $table->string('target_field');
            $table->string('transform', 100)->nullable()
                  ->comment('e.g. date:d/m/Y | percent | strip_commas');
            $table->timestamps();
            $table->unique(['import_type', 'source_header'], 'uq_mapping_type_header');
        });

        Schema::create('staging_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('facility_class', 50)->default('DEFAULT')
                  ->comment('DEFAULT | LONG_TERM | ... matched against loan attributes');
            $table->unsignedSmallInteger('min_tenor_months')->default(0);
            $table->unsignedSmallInteger('stage2_dpd');
            $table->unsignedSmallInteger('stage3_dpd');
            // A rebuttal of the 30-DPD presumption (IFRS 9 B5.5.37) is only
            // legitimate with documented evidence — store it with the rule.
            $table->text('rebuttal_basis')->nullable();
            $table->date('effective_from');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staging_thresholds');
        Schema::dropIfExists('import_mappings');
        Schema::dropIfExists('rate_reset_events');
        Schema::dropIfExists('eir_amortisation');
        Schema::dropIfExists('contract_fees');
        Schema::dropIfExists('contract_cashflow_schedule');
        Schema::dropIfExists('contract_eir');
    }
};
