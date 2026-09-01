<?php
namespace Tests\Feature\Ecl;

use App\Services\Ecl\TimePhasedEclService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TimePhasedEclServiceTest extends TestCase
{
    protected $seed=false;
    protected function setUp():void
    {
        parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);DB::purge('sqlite');DB::reconnect('sqlite');
        Schema::create('loan_books',function(Blueprint $t){$t->increments('id');$t->string('contract_id');$t->string('reporting_period');$t->integer('ifrs9stage_pre_qualitative');$t->integer('calculated_ifrs9_stage')->nullable();$t->double('carrying_amount');$t->double('commitments')->default(0);$t->double('facility_utilisation_rate')->default(1);$t->double('pd_post_fli');$t->double('pd_prefli')->nullable();$t->double('collection_lgd');$t->double('customer_lgd');$t->double('remaining_tenor')->nullable();$t->string('due_date')->nullable();$t->double('ecl_value')->nullable();$t->double('ecl_value_discounted')->nullable();$t->double('ecl_discounting_effect')->nullable();$t->double('ecl_discount_rate')->nullable();$t->string('ecl_discount_rate_source')->nullable();$t->string('ecl_discount_status')->nullable();$t->double('ecl_discount_horizon_years')->nullable();$t->string('ecl_calculation_run_id')->nullable();$t->string('ecl_calculated_at')->nullable();});
        Schema::create('contract_eir',function(Blueprint $t){$t->increments('id');$t->string('contract_id');$t->double('eir_effective_annual');$t->string('rate_type');$t->string('locked_at');});
        Schema::create('contract_cashflow_schedule',function(Blueprint $t){$t->increments('id');$t->string('contract_id');$t->integer('schedule_version');$t->string('due_date');$t->double('principal_due');});
        Schema::create('ecl_scenario_assumptions',function(Blueprint $t){$t->increments('id');$t->string('scenario_code');$t->string('name');$t->double('weight');$t->double('pd_multiplier');$t->double('lgd_multiplier');$t->double('ead_multiplier');$t->string('effective_from');$t->string('effective_to')->nullable();$t->string('status');});
        Schema::create('ecl_projection_runs',function(Blueprint $t){$t->increments('id');$t->string('run_id');$t->string('reporting_period');$t->string('methodology_version');$t->string('status');$t->integer('contracts_processed')->default(0);$t->integer('contracts_unresolved')->default(0);$t->double('undiscounted_ecl')->default(0);$t->double('discounted_ecl')->default(0);$t->text('input_snapshot')->nullable();$t->text('exceptions')->nullable();$t->integer('created_by')->nullable();$t->string('completed_at')->nullable();$t->timestamps();});
        Schema::create('ecl_pd_term_structures',function(Blueprint $t){$t->increments('id');$t->string('contract_id');$t->string('reporting_period');$t->string('scenario_code');$t->integer('period_index');$t->string('projection_date');$t->double('conditional_pd');$t->double('survival_open');$t->double('marginal_pd');$t->double('cumulative_pd');$t->string('source');$t->timestamps();});
        Schema::create('ecl_cashflow_projections',function(Blueprint $t){$t->increments('id');$t->string('run_id');$t->string('contract_id');$t->string('reporting_period');$t->integer('ifrs9_stage');$t->string('scenario_code');$t->double('scenario_weight');$t->integer('period_index');$t->string('projection_date');$t->double('opening_ead');$t->double('scheduled_principal');$t->double('closing_ead');$t->double('conditional_pd');$t->double('survival_open');$t->double('marginal_pd');$t->double('cumulative_pd');$t->double('lgd');$t->double('undiscounted_shortfall');$t->double('discount_rate');$t->double('discount_exponent');$t->double('discount_factor');$t->double('discounted_shortfall');$t->double('weighted_discounted_shortfall');$t->string('rate_source');$t->string('pd_source');$t->string('lgd_source');$t->timestamps();});
        Schema::create('ecl_recovery_cashflows',function(Blueprint $t){$t->increments('id');$t->string('contract_id');$t->string('reporting_period');$t->string('recovery_date');$t->double('expected_recovery');$t->string('status');});
    }

    public function test_stage_one_builds_a_twelve_month_marginal_pd_curve_and_discounts_each_shortfall():void
    {
        DB::table('loan_books')->insert(['contract_id'=>'C-1','reporting_period'=>'2025-01','ifrs9stage_pre_qualitative'=>1,'carrying_amount'=>100000,'pd_post_fli'=>.12,'collection_lgd'=>.5,'customer_lgd'=>.5]);
        DB::table('contract_eir')->insert(['contract_id'=>'C-1','eir_effective_annual'=>.10,'rate_type'=>'FIXED','locked_at'=>'2025-01-01']);
        DB::table('contract_cashflow_schedule')->insert(['contract_id'=>'C-1','schedule_version'=>1,'due_date'=>'2026-01-31','principal_due'=>100000]);
        DB::table('ecl_scenario_assumptions')->insert(['scenario_code'=>'BASE','name'=>'Base','weight'=>1,'pd_multiplier'=>1,'lgd_multiplier'=>1,'ead_multiplier'=>1,'effective_from'=>'2025-01-01','status'=>'APPROVED']);
        $loan=DB::table('loan_books')->first();$result=(new TimePhasedEclService())->run(collect([$loan]),'2025-01');
        $this->assertSame(1,$result['calculated']);$this->assertSame(0,$result['unresolved']);
        $this->assertSame(12,DB::table('ecl_cashflow_projections')->count());
        $this->assertEqualsWithDelta(.12,(float)DB::table('ecl_cashflow_projections')->sum('marginal_pd'),1e-8);
        $this->assertEqualsWithDelta(6000,$result['undiscounted'],.02);
        $this->assertLessThan(6000,$result['discounted']);
        $this->assertSame('CALCULATED_TIME_PHASED',DB::table('loan_books')->value('ecl_discount_status'));
    }
}
