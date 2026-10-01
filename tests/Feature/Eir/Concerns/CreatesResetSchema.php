<?php

namespace Tests\Feature\Eir\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The tables phase P5 reads and writes, in the private in-memory sqlite the
 * EIR feature tests use, and the prime-rate series around JAT Group's life
 * (the repaired file, April 2025 to September 2026). Pair with
 * CreatesGovernanceSchema: the reset services read margin_basis,
 * plr_mid_period, day_count and period_rate_basis.
 */
trait CreatesResetSchema
{
    /** The repaired series from April 2025, rate changes and restatements alike. */
    protected const JAT_PRIME_RATES = [
        ['2025-04-14', 25.1], ['2025-05-27', 25.2], ['2025-06-16', 25.1], ['2025-07-08', 25.3], ['2025-07-23', 25.3],
        ['2025-09-03', 25.3], ['2025-10-01', 25.4], ['2025-11-01', 25.3], ['2026-02-04', 24.7], ['2026-03-05', 23.7],
        ['2026-03-09', 22.4], ['2026-04-20', 20.8], ['2026-05-06', 20.6], ['2026-06-03', 20.4], ['2026-07-03', 20.5],
        ['2026-08-05', 20.8], ['2026-09-03', 21.2],
    ];

    protected function createResetSchema(): void
    {
        Schema::create('contract_eir', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id')->unique();
            $t->string('sub_account_no')->nullable();
            $t->string('instrument_type')->default('AMORTISED_LOAN');
            $t->string('portfolio')->nullable();
            $t->string('product_type')->nullable();
            $t->string('interest_policy', 1)->nullable();
            $t->boolean('reprice_flag')->nullable();
            $t->string('origination_date')->nullable();
            $t->string('maturity_date')->nullable();
            $t->double('contractual_rate')->nullable();
            $t->double('markup')->nullable();
            $t->unsignedSmallInteger('payments_per_year')->default(12);
            $t->unsignedSmallInteger('moratorium_months')->default(0);
            $t->string('emi_calc_type', 1)->nullable();
            $t->string('schedule_emi_calc_type', 1)->nullable();
            $t->timestamps();
        });
        Schema::create('reference_rate_series', function (Blueprint $t) {
            $t->increments('id');
            $t->string('index_code')->default('PLR');
            $t->string('effective_date');
            $t->double('rate');
            $t->timestamps();
        });
        Schema::create('rate_reset_events', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('reset_date');
            $t->string('plr_effective_date')->nullable();
            $t->string('index_code')->default('PLR');
            $t->double('old_reference_rate');
            $t->double('new_reference_rate');
            $t->double('spread_over_prime')->nullable();
            $t->double('old_contractual_rate')->nullable();
            $t->double('new_contractual_rate')->nullable();
            $t->unsignedSmallInteger('new_schedule_version')->nullable();
            $t->string('status')->default('DETECTED');
            $t->text('note')->nullable();
            $t->unsignedInteger('recorded_by')->nullable();
            $t->timestamps();
            $t->unique(['contract_id', 'reset_date']);
        });
        Schema::create('eir_rate_periods', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('sub_account_no')->nullable();
            $t->unsignedSmallInteger('period_no');
            $t->string('effective_from');
            $t->string('effective_to')->nullable();
            $t->string('index_code')->default('PLR');
            $t->double('reference_rate')->nullable();
            $t->double('spread_over_prime')->nullable();
            $t->string('spread_basis')->nullable();
            $t->double('contractual_rate')->nullable();
            $t->double('opening_amortised_cost')->nullable();
            $t->double('eir_period')->nullable();
            $t->double('eir_nominal_annual')->nullable();
            $t->double('eir_effective_annual')->nullable();
            $t->double('eir_resolved_effective_annual')->nullable();
            $t->boolean('eir_disagreement_flag')->default(false);
            $t->string('solver_method')->nullable();
            $t->unsignedSmallInteger('solver_iterations')->nullable();
            $t->double('solver_residual')->nullable();
            $t->json('input_snapshot')->nullable();
            $t->unsignedInteger('rate_reset_event_id')->nullable();
            $t->unsignedSmallInteger('schedule_version')->nullable();
            $t->string('status')->default('PENDING_EIR');
            $t->text('note')->nullable();
            $t->timestamp('locked_at')->nullable();
            $t->unsignedInteger('locked_by')->nullable();
            $t->timestamps();
            $t->unique(['contract_id', 'effective_from']);
        });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->unsignedSmallInteger('schedule_version')->default(1);
            $t->string('schedule_type')->default('ORIGINAL');
            $t->string('effective_from')->nullable();
            $t->string('due_date');
            $t->double('principal_due')->default(0);
            $t->double('interest_due')->default(0);
            $t->double('fee_due')->default(0);
            $t->string('schedule_source')->default('IMPORTED');
            $t->string('source_system')->nullable();
            $t->string('source_reference')->nullable();
            $t->string('external_transaction_id')->nullable();
            $t->timestamps();
            $t->unique(['contract_id', 'schedule_version', 'due_date']);
        });
    }

    protected function seedJatPrimeRates(): void
    {
        foreach (self::JAT_PRIME_RATES as [$date, $rate]) {
            DB::table('reference_rate_series')->insert(['index_code' => 'PLR', 'effective_date' => $date, 'rate' => $rate]);
        }
    }
}
