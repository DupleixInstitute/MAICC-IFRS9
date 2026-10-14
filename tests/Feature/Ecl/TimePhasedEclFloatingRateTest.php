<?php

namespace Tests\Feature\Ecl;

use App\Services\Ecl\TimePhasedEclService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A floating-rate loan's expected credit loss is discounted at its current EIR
 * (IFRS 9 B5.5.44), the EIR in force at the reporting date as the revenue
 * engine re-estimates it from the rate charged (decision D38). Where it cannot
 * be determined the loan is unresolved with the reason, never discounted at the
 * original EIR in its place (it was, as EIR_ORIGINAL_FLOATING_PROXY, until
 * 14 October 2026).
 */
class TimePhasedEclFloatingRateTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->integer('ifrs9stage_pre_qualitative')->nullable(); $t->integer('calculated_ifrs9_stage')->nullable();
            $t->integer('ifrs9stage_post_qualitative')->nullable(); $t->double('carrying_amount'); $t->double('commitments')->default(0); $t->double('facility_utilisation_rate')->default(1);
            $t->double('pd_post_fli')->nullable(); $t->double('pd_prefli')->nullable(); $t->double('collection_lgd'); $t->double('customer_lgd')->nullable(); $t->double('remaining_tenor')->nullable();
            $t->double('interest_rate')->nullable();
            $t->double('ecl_value')->nullable(); $t->double('ecl_value_discounted')->nullable(); $t->double('ecl_discounting_effect')->nullable(); $t->double('ecl_discount_rate')->nullable();
            $t->string('ecl_discount_rate_source')->nullable(); $t->string('ecl_discount_status')->nullable(); $t->double('ecl_discount_horizon_years')->nullable(); $t->string('ecl_calculation_run_id')->nullable(); $t->string('ecl_calculated_at')->nullable();
        });
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->double('eir_effective_annual')->nullable(); $t->double('contractual_rate')->nullable(); $t->json('input_snapshot')->nullable(); $t->string('rate_type'); $t->string('locked_at')->nullable(); $t->timestamps(); });
        Schema::create('eir_amortisation', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period', 7); $t->double('eir_annual')->nullable(); $t->timestamps(); });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->integer('schedule_version'); $t->string('due_date'); $t->double('principal_due'); });
        Schema::create('ecl_scenario_assumptions', function (Blueprint $t) { $t->increments('id'); $t->string('scenario_code'); $t->string('name'); $t->double('weight'); $t->double('pd_multiplier'); $t->double('lgd_multiplier'); $t->double('ead_multiplier'); $t->string('effective_from'); $t->string('effective_to')->nullable(); $t->string('status'); });
        Schema::create('governed_scenarios', function (Blueprint $t) { $t->increments('id'); $t->integer('set_id'); $t->string('name'); $t->double('weight'); $t->integer('order_position'); $t->boolean('is_base')->default(false); });
        Schema::create('ecl_projection_runs', function (Blueprint $t) { $t->increments('id'); $t->string('run_id'); $t->string('reporting_period'); $t->string('methodology_version'); $t->string('status'); $t->integer('contracts_processed')->default(0); $t->integer('contracts_unresolved')->default(0); $t->double('undiscounted_ecl')->default(0); $t->double('discounted_ecl')->default(0); $t->text('input_snapshot')->nullable(); $t->text('exceptions')->nullable(); $t->integer('created_by')->nullable(); $t->string('completed_at')->nullable(); $t->timestamps(); });
        Schema::create('ecl_pd_term_structures', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('scenario_code'); $t->integer('period_index'); $t->string('projection_date'); $t->double('conditional_pd'); $t->double('survival_open'); $t->double('marginal_pd'); $t->double('cumulative_pd'); $t->string('source'); $t->timestamps(); });
        Schema::create('ecl_cashflow_projections', function (Blueprint $t) { $t->increments('id'); $t->string('run_id'); $t->string('contract_id'); $t->string('reporting_period'); $t->integer('ifrs9_stage'); $t->string('scenario_code'); $t->double('scenario_weight'); $t->integer('period_index'); $t->string('projection_date'); $t->double('opening_ead'); $t->double('scheduled_principal'); $t->double('closing_ead'); $t->double('conditional_pd'); $t->double('survival_open'); $t->double('marginal_pd'); $t->double('cumulative_pd'); $t->double('lgd'); $t->double('undiscounted_shortfall'); $t->double('discount_rate'); $t->double('discount_exponent'); $t->double('discount_factor'); $t->double('discounted_shortfall'); $t->double('weighted_discounted_shortfall'); $t->string('rate_source'); $t->string('pd_source'); $t->string('lgd_source'); $t->timestamps(); });
        Schema::create('ecl_recovery_cashflows', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('recovery_date'); $t->double('expected_recovery'); $t->string('status'); });
        DB::table('ecl_scenario_assumptions')->insert(['scenario_code' => 'BASE', 'name' => 'Base', 'weight' => 1, 'pd_multiplier' => 1, 'lgd_multiplier' => 1, 'ead_multiplier' => 1, 'effective_from' => '2024-01-01', 'status' => 'APPROVED']);
    }

    /** A Stage 1 floating loan: locked EIR 10 percent on a schedule built at 9 percent, charged $charged percent in January 2025. */
    private function floating(string $id, ?float $charged): void
    {
        DB::table('loan_books')->insert(['contract_id' => $id, 'reporting_period' => '2025-01', 'ifrs9stage_pre_qualitative' => 1, 'calculated_ifrs9_stage' => 1, 'ifrs9stage_post_qualitative' => 1,
            'carrying_amount' => 100000, 'pd_post_fli' => .10, 'pd_prefli' => .09, 'collection_lgd' => .5, 'interest_rate' => $charged]);
        DB::table('contract_eir')->insert(['contract_id' => $id, 'eir_effective_annual' => .10, 'contractual_rate' => .09, 'rate_type' => 'FLOATING', 'locked_at' => '2024-12-01', 'created_at' => now(), 'updated_at' => now()]);
        for ($m = 1; $m <= 24; $m++) {
            DB::table('contract_cashflow_schedule')->insert(['contract_id' => $id, 'schedule_version' => 1,
                'due_date' => CarbonImmutable::parse('2025-01-31')->addMonthsNoOverflow($m)->endOfMonth()->toDateString(), 'principal_due' => 100000 / 24]);
        }
    }

    private function measure(): array
    {
        return (new TimePhasedEclService())->run(collect(DB::table('loan_books')->get()), '2025-01', 'collection_lgd');
    }

    public function test_a_floating_loan_is_discounted_at_the_eir_in_force_at_the_reporting_date(): void
    {
        $this->floating('F1', 12.0);
        $r = $this->measure();
        $this->assertSame(1, $r['calculated'], json_encode($r['exceptions']));

        $l = DB::table('loan_books')->first();
        $this->assertSame('EIR_CURRENT_FLOATING', $l->ecl_discount_rate_source);
        // D38: the locked nominal EIR plus the change from the 9 percent the schedule was built on to the 12 charged
        $current = pow(1 + (12 * (pow(1.10, 1 / 12) - 1) + (0.12 - 0.09)) / 12, 12) - 1;
        $this->assertEqualsWithDelta($current, (float) $l->ecl_discount_rate, 1e-9);
        $this->assertGreaterThan(0.12, (float) $l->ecl_discount_rate, 'not the original 10 percent');
    }

    public function test_the_eir_the_roll_forward_accrued_at_for_the_month_is_the_current_eir(): void
    {
        $this->floating('F1', 12.0);
        DB::table('eir_amortisation')->insert(['contract_id' => 'F1', 'reporting_period' => '2025-01', 'eir_annual' => 0.1325]);
        $this->measure();

        $this->assertEqualsWithDelta(0.1325, (float) DB::table('loan_books')->value('ecl_discount_rate'), 1e-12);
    }

    public function test_a_floating_loan_with_no_rate_charged_for_the_month_is_unresolved_never_discounted_at_the_original_eir(): void
    {
        $this->floating('F2', null);
        $r = $this->measure();

        $this->assertSame(0, $r['calculated']);
        $this->assertStringContainsString('B5.5.44', $r['exceptions']['F2']);
        $l = DB::table('loan_books')->first();
        $this->assertSame('TIME_PHASED_UNRESOLVED', $l->ecl_discount_status);
        $this->assertNull($l->ecl_discount_rate);
    }
}
