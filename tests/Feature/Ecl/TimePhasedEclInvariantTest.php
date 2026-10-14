<?php

namespace Tests\Feature\Ecl;

use App\Services\Ecl\TimePhasedEclService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The time-phased engine on the governed scenario set (UI end-to-end audit of
 * 14 October 2026, item 4). On maiic_ifrs9_uat the discounted ECL of
 * portfolio 2 (2.725bn) stood above its undiscounted ECL (2.650bn): the
 * Stage 3 loans, which the route writes no per-scenario PD, were measured on
 * the legacy assumptions table (LGD x0.9 / x1.2, EAD x0.98 / x1.05), 2.84
 * percent above the engine measure. On the set every loan is measured under
 * the set, and discounting at a rate of nil or more never raises a loss.
 */
class TimePhasedEclInvariantTest extends TestCase
{
    protected $seed = false;

    private const SET = '{"Base":{"weight":50,"pd":0.10},"Upside":{"weight":15,"pd":0.08},"Downside":{"weight":25,"pd":0.14},"Severe":{"weight":10,"pd":0.20}}';

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
            $t->text('fli_by_scenario')->nullable(); $t->integer('fli_set_id')->nullable(); $t->text('ecl_by_scenario')->nullable();
            $t->double('ecl_value')->nullable(); $t->double('ecl_value_discounted')->nullable(); $t->double('ecl_discounting_effect')->nullable(); $t->double('ecl_discount_rate')->nullable();
            $t->string('ecl_discount_rate_source')->nullable(); $t->string('ecl_discount_status')->nullable(); $t->double('ecl_discount_horizon_years')->nullable(); $t->string('ecl_calculation_run_id')->nullable(); $t->string('ecl_calculated_at')->nullable();
        });
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->double('eir_effective_annual'); $t->string('rate_type'); $t->string('locked_at'); });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->integer('schedule_version'); $t->string('due_date'); $t->double('principal_due'); });
        Schema::create('ecl_scenario_assumptions', function (Blueprint $t) { $t->increments('id'); $t->string('scenario_code'); $t->string('name'); $t->double('weight'); $t->double('pd_multiplier'); $t->double('lgd_multiplier'); $t->double('ead_multiplier'); $t->string('effective_from'); $t->string('effective_to')->nullable(); $t->string('status'); });
        Schema::create('governed_scenarios', function (Blueprint $t) { $t->increments('id'); $t->integer('set_id'); $t->string('name'); $t->double('weight'); $t->integer('order_position'); $t->boolean('is_base')->default(false); });
        Schema::create('ecl_projection_runs', function (Blueprint $t) { $t->increments('id'); $t->string('run_id'); $t->string('reporting_period'); $t->string('methodology_version'); $t->string('status'); $t->integer('contracts_processed')->default(0); $t->integer('contracts_unresolved')->default(0); $t->double('undiscounted_ecl')->default(0); $t->double('discounted_ecl')->default(0); $t->text('input_snapshot')->nullable(); $t->text('exceptions')->nullable(); $t->integer('created_by')->nullable(); $t->string('completed_at')->nullable(); $t->timestamps(); });
        Schema::create('ecl_pd_term_structures', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('scenario_code'); $t->integer('period_index'); $t->string('projection_date'); $t->double('conditional_pd'); $t->double('survival_open'); $t->double('marginal_pd'); $t->double('cumulative_pd'); $t->string('source'); $t->timestamps(); });
        Schema::create('ecl_cashflow_projections', function (Blueprint $t) { $t->increments('id'); $t->string('run_id'); $t->string('contract_id'); $t->string('reporting_period'); $t->integer('ifrs9_stage'); $t->string('scenario_code'); $t->double('scenario_weight'); $t->integer('period_index'); $t->string('projection_date'); $t->double('opening_ead'); $t->double('scheduled_principal'); $t->double('closing_ead'); $t->double('conditional_pd'); $t->double('survival_open'); $t->double('marginal_pd'); $t->double('cumulative_pd'); $t->double('lgd'); $t->double('undiscounted_shortfall'); $t->double('discount_rate'); $t->double('discount_exponent'); $t->double('discount_factor'); $t->double('discounted_shortfall'); $t->double('weighted_discounted_shortfall'); $t->string('rate_source'); $t->string('pd_source'); $t->string('lgd_source'); $t->timestamps(); });
        Schema::create('ecl_recovery_cashflows', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('recovery_date'); $t->double('expected_recovery'); $t->string('status'); });

        // the legacy table holds shocked multipliers; on the set it must never be read
        foreach ([['BASE', .6, 1, 1, 1], ['UPSIDE', .2, .75, .9, .98], ['DOWNSIDE', .2, 1.5, 1.2, 1.05]] as [$c, $w, $pd, $lgd, $ead]) {
            DB::table('ecl_scenario_assumptions')->insert(['scenario_code' => $c, 'name' => $c, 'weight' => $w, 'pd_multiplier' => $pd, 'lgd_multiplier' => $lgd, 'ead_multiplier' => $ead, 'effective_from' => '2024-01-01', 'status' => 'APPROVED']);
        }
        foreach (['Base' => 50, 'Upside' => 15, 'Downside' => 25, 'Severe' => 10] as $i => $w) {
            DB::table('governed_scenarios')->insert(['set_id' => 1, 'name' => $i, 'weight' => $w, 'order_position' => count(DB::table('governed_scenarios')->get())]);
        }
    }

    private function loan(string $id, int $stage, array $over = []): void
    {
        DB::table('loan_books')->insert(array_merge(['contract_id' => $id, 'reporting_period' => '2025-01', 'ifrs9stage_pre_qualitative' => $stage, 'calculated_ifrs9_stage' => $stage,
            'ifrs9stage_post_qualitative' => $stage, 'carrying_amount' => 100000, 'pd_post_fli' => $stage === 3 ? 1 : .10, 'pd_prefli' => $stage === 3 ? 1 : .09, 'collection_lgd' => .5,
            'fli_set_id' => 1, 'fli_by_scenario' => $stage === 3 ? null : self::SET], $over));
    }

    private function schedule(string $id, int $months, float $rate = .10): void
    {
        DB::table('contract_eir')->insert(['contract_id' => $id, 'eir_effective_annual' => $rate, 'rate_type' => 'FIXED', 'locked_at' => '2024-12-01']);
        for ($m = 1; $m <= $months; $m++) {
            DB::table('contract_cashflow_schedule')->insert(['contract_id' => $id, 'schedule_version' => 1,
                'due_date' => CarbonImmutable::parse('2025-01-31')->addMonthsNoOverflow($m)->endOfMonth()->toDateString(), 'principal_due' => 100000 / $months]);
        }
    }

    private function measure(string $pd = 'pd_post_fli'): array
    {
        return (new TimePhasedEclService())->run(collect(DB::table('loan_books')->get()), '2025-01', 'collection_lgd', null, $pd);
    }

    public function test_a_defaulted_loan_on_the_set_is_measured_unshocked_never_on_the_legacy_table(): void
    {
        $this->loan('S3', 3);
        $this->loan('S1', 1);
        $this->schedule('S1', 24);
        $r = $this->measure();
        $this->assertSame(2, $r['calculated'], json_encode($r['exceptions']));
        $s3 = DB::table('loan_books')->where('contract_id', 'S3')->first();
        // EAD x LGD at the reporting date: 100,000 x 0.5, not 1.0284 times it
        $this->assertEqualsWithDelta(50000.0, (float) $s3->ecl_value_discounted, 0.005);
        $this->assertEqualsWithDelta(0.0, (float) $s3->ecl_discounting_effect, 0.005);
        $by = json_decode($s3->ecl_by_scenario, true);
        $this->assertSame(['Base', 'Upside', 'Downside', 'Severe'], array_keys($by));
        $this->assertEqualsWithDelta(50000.0, $by['Severe']['discounted'], 1e-6);
        $this->assertSame(0, DB::table('ecl_cashflow_projections')->whereIn('scenario_code', ['BASE', 'UPSIDE', 'DOWNSIDE'])->count());
    }

    public function test_discounted_never_exceeds_undiscounted_per_loan_and_the_scenarios_weight_back_to_it(): void
    {
        $this->loan('S1', 1);
        $this->schedule('S1', 36);
        $this->loan('S2', 2, ['remaining_tenor' => 60]);
        $this->schedule('S2', 60, .25);
        $this->loan('S3', 3);
        $r = $this->measure();
        $this->assertSame(3, $r['calculated'], json_encode($r['exceptions']));
        foreach (DB::table('loan_books')->get() as $l) {
            $undiscounted = (float) $l->ecl_value_discounted + (float) $l->ecl_discounting_effect;
            $this->assertLessThanOrEqual($undiscounted + 0.01, (float) $l->ecl_value_discounted, $l->contract_id);
            $this->assertGreaterThanOrEqual(-0.005, (float) $l->ecl_discounting_effect, $l->contract_id);
            $weighted = 0.0;
            foreach (json_decode($l->ecl_by_scenario, true) as $s) {
                $this->assertLessThanOrEqual($s['undiscounted'] + 1e-6, $s['discounted']);
                $weighted += $s['weight'] * $s['discounted'];
            }
            $this->assertEqualsWithDelta((float) $l->ecl_value_discounted, $weighted, 0.006, $l->contract_id);
        }
        // a worse scenario carries a larger loss
        $by = json_decode(DB::table('loan_books')->where('contract_id', 'S2')->value('ecl_by_scenario'), true);
        $this->assertGreaterThan($by['Base']['discounted'], $by['Severe']['discounted']);
    }

    public function test_the_stage_is_the_one_the_engine_measures_post_qualitative(): void
    {
        // calculated Stage 1, moved to Stage 2 by a qualitative trigger: lifetime, as the ECL engine measures it
        $this->loan('Q', 1, ['ifrs9stage_post_qualitative' => 2, 'remaining_tenor' => 36]);
        $this->schedule('Q', 36);
        $this->measure();
        $this->assertSame(36, DB::table('ecl_cashflow_projections')->where('contract_id', 'Q')->where('scenario_code', 'Base')->count());
    }

    public function test_a_performing_loan_on_the_set_without_its_scenario_pds_is_refused(): void
    {
        $this->loan('NOPD', 1, ['fli_by_scenario' => null]);
        $this->schedule('NOPD', 12);
        $r = $this->measure();
        $this->assertSame(1, $r['unresolved']);
        $this->assertStringContainsString('per-scenario PD', $r['exceptions']['NOPD']);
        $this->assertNull(DB::table('loan_books')->value('ecl_value_discounted'));
    }

    public function test_a_negative_eir_is_refused_and_a_nil_exposure_is_nil_not_unresolved(): void
    {
        $this->loan('NEG', 1);
        $this->schedule('NEG', 12, -.05);
        $this->loan('NIL', 1, ['carrying_amount' => -250]);
        $r = $this->measure();
        $this->assertStringContainsString('negative', $r['exceptions']['NEG'] ?? '');
        $nil = DB::table('loan_books')->where('contract_id', 'NIL')->first();
        $this->assertSame('CALCULATED_TIME_PHASED', $nil->ecl_discount_status);
        $this->assertSame(0.0, (float) $nil->ecl_value_discounted);
        $this->assertSame(0, DB::table('ecl_cashflow_projections')->where('contract_id', 'NEG')->count());
    }

    public function test_a_run_before_fli_measures_the_pd_before_fli_with_no_scenario(): void
    {
        $this->loan('PRE', 1);
        $this->schedule('PRE', 12);
        $this->measure('pd_prefli');
        $this->assertSame(['Before FLI'], DB::table('ecl_cashflow_projections')->distinct()->pluck('scenario_code')->all());
        $this->assertEqualsWithDelta(.09, (float) DB::table('ecl_cashflow_projections')->max('cumulative_pd'), 1e-8);
    }

    public function test_a_planned_recovery_is_the_one_case_discounting_raises_the_loss(): void
    {
        // EAD less the present value of a later recovery: the later it comes the larger the loss (B5.5.33)
        $this->loan('PLAN', 3);
        DB::table('contract_eir')->insert(['contract_id' => 'PLAN', 'eir_effective_annual' => .10, 'rate_type' => 'FIXED', 'locked_at' => '2024-12-01']);
        DB::table('ecl_recovery_cashflows')->insert(['contract_id' => 'PLAN', 'reporting_period' => '2025-01', 'recovery_date' => '2026-01-31', 'expected_recovery' => 60000, 'status' => 'APPROVED']);
        $r = $this->measure();
        $this->assertSame(1, $r['calculated'], json_encode($r['exceptions']));
        $l = DB::table('loan_books')->first();
        // the reporting date is the last instant of 31 January, so the recovery is 364 days out (TimePhasedEclServiceTest)
        $this->assertEqualsWithDelta(100000 - 60000 / pow(1.1, 364 / 365), (float) $l->ecl_value_discounted, 0.01);
        $this->assertLessThan(0, (float) $l->ecl_discounting_effect);
    }
}
