<?php
namespace Tests\Feature\Ecl;

use App\Services\Ecl\TimePhasedEclService;
use Carbon\CarbonImmutable;
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

    /** An amortising schedule from the reporting date, one instalment a month. */
    private function amortisingSchedule(string $id,int $months,float $principal=100000):void
    {
        DB::table('contract_eir')->insert(['contract_id'=>$id,'eir_effective_annual'=>.10,'rate_type'=>'FIXED','locked_at'=>'2025-01-01']);
        for($m=1;$m<=$months;$m++){
            DB::table('contract_cashflow_schedule')->insert(['contract_id'=>$id,'schedule_version'=>1,
                'due_date'=>CarbonImmutable::parse('2025-01-31')->addMonthsNoOverflow($m)->endOfMonth()->toDateString(),
                'principal_due'=>$principal/$months]);
        }
    }

    private function project(): array
    {
        return (new TimePhasedEclService())->run(collect(DB::table('loan_books')->get()),'2025-01');
    }

    public function test_a_stage_two_lifetime_compounds_beyond_the_twelve_month_pd():void
    {
        DB::table('loan_books')->insert(['contract_id'=>'C-2','reporting_period'=>'2025-01','ifrs9stage_pre_qualitative'=>2,
            'calculated_ifrs9_stage'=>2,'carrying_amount'=>100000,'pd_post_fli'=>.12,'collection_lgd'=>.5,'customer_lgd'=>.5]);
        $this->amortisingSchedule('C-2',60);
        DB::table('ecl_scenario_assumptions')->insert(['scenario_code'=>'BASE','name'=>'Base','weight'=>1,'pd_multiplier'=>1,'lgd_multiplier'=>1,'ead_multiplier'=>1,'effective_from'=>'2025-01-01','status'=>'APPROVED']);
        $this->project();

        // Five years of a 12% annual hazard, not twelve months of it stretched
        // across sixty. Fitting the hazard to the horizon returned 0.12 here,
        // leaving the lifetime measurement carrying a single year of risk.
        $this->assertEqualsWithDelta(1-pow(.88,5),(float)DB::table('ecl_cashflow_projections')->max('cumulative_pd'),1e-8);
        $this->assertSame('TWELVE_MONTH_PD_CONSTANT_HAZARD',DB::table('ecl_cashflow_projections')->value('pd_source'));
    }

    public function test_a_stage_three_exposure_does_not_amortise_on_a_schedule_nobody_is_paying():void
    {
        DB::table('loan_books')->insert(['contract_id'=>'C-3','reporting_period'=>'2025-01','ifrs9stage_pre_qualitative'=>3,
            'calculated_ifrs9_stage'=>3,'carrying_amount'=>100000,'pd_post_fli'=>.5,'collection_lgd'=>.6,'customer_lgd'=>.6]);
        $this->amortisingSchedule('C-3',24);
        DB::table('ecl_scenario_assumptions')->insert(['scenario_code'=>'BASE','name'=>'Base','weight'=>1,'pd_multiplier'=>1,'lgd_multiplier'=>1,'ead_multiplier'=>1,'effective_from'=>'2025-01-01','status'=>'APPROVED']);
        $result=$this->project();

        // Amortising the exposure wrote the loss off against instalments a
        // defaulted borrower will never pay, and reported 2,500 of 60,000.
        $this->assertEqualsWithDelta(60000,$result['undiscounted'],.01);
        $this->assertSame(100000.0,(float)DB::table('ecl_cashflow_projections')->orderByDesc('period_index')->value('opening_ead'));
        $this->assertSame('DEFAULTED_RESOLUTION_HORIZON',DB::table('ecl_cashflow_projections')->value('pd_source'));
    }

    public function test_a_stage_three_shortfall_is_discounted_from_the_approved_recovery_dates():void
    {
        DB::table('loan_books')->insert(['contract_id'=>'C-4','reporting_period'=>'2025-01','ifrs9stage_pre_qualitative'=>3,
            'calculated_ifrs9_stage'=>3,'carrying_amount'=>100000,'pd_post_fli'=>.5,'collection_lgd'=>.9,'customer_lgd'=>.9]);
        $this->amortisingSchedule('C-4',24);
        DB::table('ecl_scenario_assumptions')->insert(['scenario_code'=>'BASE','name'=>'Base','weight'=>1,'pd_multiplier'=>1,'lgd_multiplier'=>1,'ead_multiplier'=>1,'effective_from'=>'2025-01-01','status'=>'APPROVED']);
        foreach([['2025-07-31',30000],['2026-01-31',10000]] as [$date,$amount]){
            DB::table('ecl_recovery_cashflows')->insert(['contract_id'=>'C-4','reporting_period'=>'2025-01',
                'recovery_date'=>$date,'expected_recovery'=>$amount,'status'=>'APPROVED']);
        }
        $result=$this->project();

        // The plan recovers 40,000 of 100,000, so LGD is its own 0.6 and the
        // tape's 0.9 is superseded. Three quarters of that settles in July.
        $this->assertEqualsWithDelta(60000,$result['undiscounted'],.01);
        $shares=DB::table('ecl_cashflow_projections')->where('marginal_pd','>',0)->orderBy('period_index')->pluck('marginal_pd','period_index');
        $this->assertEqualsWithDelta(.75,(float)$shares[6],1e-8);
        $this->assertEqualsWithDelta(.25,(float)$shares[12],1e-8);

        // Placing all of it at the final recovery date discounted a loss that
        // mostly crystallises six months earlier, and understated it.
        $this->assertGreaterThan(60000/1.1,$result['discounted']);
        $this->assertLessThan(60000,$result['discounted']);
        $this->assertSame('DEFAULTED_RECOVERY_SCHEDULE',DB::table('ecl_cashflow_projections')->value('pd_source'));
    }

    public function test_scenario_weights_combine_into_one_allowance():void
    {
        DB::table('loan_books')->insert(['contract_id'=>'C-5','reporting_period'=>'2025-01','ifrs9stage_pre_qualitative'=>1,
            'calculated_ifrs9_stage'=>1,'carrying_amount'=>100000,'pd_post_fli'=>.12,'collection_lgd'=>.5,'customer_lgd'=>.5]);
        DB::table('contract_eir')->insert(['contract_id'=>'C-5','eir_effective_annual'=>.10,'rate_type'=>'FIXED','locked_at'=>'2025-01-01']);
        DB::table('contract_cashflow_schedule')->insert(['contract_id'=>'C-5','schedule_version'=>1,'due_date'=>'2026-01-31','principal_due'=>100000]);
        DB::table('ecl_scenario_assumptions')->insert([
            ['scenario_code'=>'BASE','name'=>'Base','weight'=>.6,'pd_multiplier'=>1,'lgd_multiplier'=>1,'ead_multiplier'=>1,'effective_from'=>'2025-01-01','status'=>'APPROVED'],
            ['scenario_code'=>'DOWN','name'=>'Downside','weight'=>.4,'pd_multiplier'=>2,'lgd_multiplier'=>1,'ead_multiplier'=>1,'effective_from'=>'2025-01-01','status'=>'APPROVED'],
        ]);
        $result=$this->project();

        // 0.6 x (100,000 x 0.12 x 0.5) + 0.4 x (100,000 x 0.24 x 0.5)
        $this->assertEqualsWithDelta(8400,$result['undiscounted'],.02);
        $this->assertSame(24,DB::table('ecl_cashflow_projections')->count());
    }
}
