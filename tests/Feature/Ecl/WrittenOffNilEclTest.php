<?php

namespace Tests\Feature\Ecl;

use App\Http\Controllers\ExpectedCreditLossController;
use App\Services\Ecl\EclExposure;
use App\Services\Ecl\EclRunRefused;
use App\Services\Ecl\TimePhasedEclService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A loan written off to nil carries nil ECL, discounted or not, and a
 * discounted run is the time-phased engine's or is refused (review of 14
 * October 2026).
 *
 * On the September 2026 book 104420000007 (written off in June 2026, a nil
 * balance and 162.6m once left undrawn at 0.65) carried 49.4m undiscounted
 * and 50.8m discounted ECL, and 104420000003 carried an allowance every
 * month after its August 2024 write-off: the engines measured the undrawn
 * amount of a facility the write-off had closed. And a discounted run with
 * neither the governed scenario set on its loans nor the legacy assumptions
 * fell back to a single-horizon proxy instead of being refused.
 *
 * Isolation: a private in-memory sqlite schema, as EclGoldenNumberTest.
 */
class WrittenOffNilEclTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('loan_portfolios', function (Blueprint $t) { $t->increments('id'); $t->string('name')->nullable(); $t->timestamps(); });
        Schema::create('industry_types', function (Blueprint $t) { $t->increments('id'); $t->string('code')->nullable(); $t->string('name')->nullable(); $t->timestamps(); });
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id')->nullable();
            $t->string('reporting_period')->nullable();
            $t->unsignedInteger('loan_portfolio_id')->nullable();
            $t->string('industry_code')->nullable();
            $t->string('ifrs9stage_pre_qualitative')->nullable();
            $t->string('calculated_ifrs9_stage')->nullable();
            $t->string('ifrs9stage_post_qualitative')->nullable();
            $t->double('pd_prefli')->nullable();
            $t->double('pd_post_fli')->nullable();
            $t->double('customer_lgd')->nullable();
            $t->double('collection_lgd')->nullable();
            $t->double('carrying_amount')->nullable();
            $t->double('commitments')->nullable();
            $t->double('facility_utilisation_rate')->nullable();
            $t->double('remaining_tenor')->nullable();
            $t->string('due_date')->nullable();
            $t->text('build_basis')->nullable();
            $t->double('lgd_value')->nullable();
            $t->double('ecl_value')->nullable();
            $t->double('ecl_value_discounted')->nullable();
            $t->double('ecl_discounting_effect')->nullable();
            $t->double('ecl_discount_rate')->nullable();
            $t->string('ecl_discount_rate_source')->nullable();
            $t->string('ecl_discount_status')->default('NOT_CALCULATED');
            $t->double('ecl_discount_horizon_years')->nullable();
            $t->string('ecl_calculation_run_id')->nullable();
            $t->string('ecl_calculated_at')->nullable();
            $t->text('ecl_by_scenario')->nullable();
            $t->timestamps();
        });
        Schema::create('expected_credit_loss', function (Blueprint $t) {
            $t->increments('id');
            $t->string('reporting_period')->nullable();
            $t->string('ifrs9_stage')->nullable();
            $t->string('ecl_calculation_level')->nullable();
            $t->unsignedInteger('ecl_calculation_id')->nullable();
            $t->string('ecl_calculation_code')->nullable();
            $t->double('total_ead')->nullable();
            $t->double('total_ecl')->nullable();
            $t->double('total_ecl_discounted')->nullable();
            $t->double('total_discounting_effect')->nullable();
            $t->string('discount_status')->default('NOT_REQUESTED');
            $t->unsignedInteger('discount_unresolved_loans')->default(0);
            $t->string('ecl_calculation_run_id')->nullable();
            $t->double('lgd_value_used')->nullable();
            $t->double('pd_value_used')->nullable();
            $t->unsignedInteger('total_loans')->nullable();
            $t->string('last_reporting_period')->nullable();
            $t->timestamps();
        });
        Schema::create('reporting_periods', function (Blueprint $t) {
            $t->increments('id');
            $t->string('period')->nullable();
            $t->string('reporting_period')->nullable();
            $t->integer('reporting_year')->nullable();
            $t->integer('reporting_month')->nullable();
            $t->boolean('ecl_calculated')->default(false);
            $t->double('ecl_calculation_time')->nullable();
            $t->string('ecl_calculation_level')->nullable();
            $t->unsignedInteger('ecl_calculation_id')->nullable();
            $t->string('ecl_calculation_code')->nullable();
            $t->timestamps();
        });
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->double('eir_effective_annual')->nullable(); $t->string('rate_type')->default('FIXED'); $t->string('locked_at')->nullable(); });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->integer('schedule_version'); $t->string('due_date'); $t->double('principal_due'); });
        Schema::create('ecl_scenario_assumptions', function (Blueprint $t) { $t->increments('id'); $t->string('scenario_code'); $t->string('name'); $t->double('weight'); $t->double('pd_multiplier'); $t->double('lgd_multiplier'); $t->double('ead_multiplier'); $t->string('effective_from'); $t->string('effective_to')->nullable(); $t->string('status'); });
        Schema::create('ecl_projection_runs', function (Blueprint $t) { $t->increments('id'); $t->string('run_id'); $t->string('reporting_period'); $t->string('methodology_version'); $t->string('status'); $t->integer('contracts_processed')->default(0); $t->integer('contracts_unresolved')->default(0); $t->double('undiscounted_ecl')->default(0); $t->double('discounted_ecl')->default(0); $t->text('input_snapshot')->nullable(); $t->text('exceptions')->nullable(); $t->integer('created_by')->nullable(); $t->string('completed_at')->nullable(); $t->timestamps(); });
        Schema::create('ecl_pd_term_structures', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('scenario_code'); $t->integer('period_index'); $t->string('projection_date'); $t->double('conditional_pd'); $t->double('survival_open'); $t->double('marginal_pd'); $t->double('cumulative_pd'); $t->string('source'); $t->timestamps(); });
        Schema::create('ecl_cashflow_projections', function (Blueprint $t) { $t->increments('id'); $t->string('run_id'); $t->string('contract_id'); $t->string('reporting_period'); $t->integer('ifrs9_stage'); $t->string('scenario_code'); $t->double('scenario_weight'); $t->integer('period_index'); $t->string('projection_date'); $t->double('opening_ead'); $t->double('scheduled_principal'); $t->double('closing_ead'); $t->double('conditional_pd'); $t->double('survival_open'); $t->double('marginal_pd'); $t->double('cumulative_pd'); $t->double('lgd'); $t->double('undiscounted_shortfall'); $t->double('discount_rate'); $t->double('discount_exponent'); $t->double('discount_factor'); $t->double('discounted_shortfall'); $t->double('weighted_discounted_shortfall'); $t->string('rate_source'); $t->string('pd_source'); $t->string('lgd_source'); $t->timestamps(); });
        Schema::create('ecl_recovery_cashflows', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('recovery_date'); $t->double('expected_recovery'); $t->string('status'); });

        DB::table('loan_portfolios')->insert(['id' => 1, 'name' => 'MAIIC Agricultural']);
    }

    /**
     * W: 104420000007 as it stood at September 2026, carrying the allowance of
     * the earlier run. U: a nil balance with a facility still open (no
     * write-off), whose undrawn amount is an exposure. P: a performing loan.
     */
    private function seedBook(): void
    {
        DB::table('loan_books')->insert([
            ['contract_id' => 'W', 'reporting_period' => '2026-09', 'loan_portfolio_id' => 1, 'ifrs9stage_post_qualitative' => '3',
             'pd_prefli' => 1, 'pd_post_fli' => 1, 'collection_lgd' => 0.46725007, 'customer_lgd' => 0.46725007,
             'carrying_amount' => 0, 'commitments' => 162600000, 'facility_utilisation_rate' => 0.65, 'remaining_tenor' => 0,
             'build_basis' => json_encode(['ledger' => ['disbursed' => 297400000, 'written_off' => 5667578.18]]),
             'ecl_value' => 49383659.90, 'ecl_value_discounted' => 50786155.84, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED',
             'ecl_by_scenario' => json_encode(['BASE' => ['weight' => 1, 'undiscounted' => 50786155.84, 'discounted' => 50786155.84]])],
            ['contract_id' => 'U', 'reporting_period' => '2026-09', 'loan_portfolio_id' => 1, 'ifrs9stage_post_qualitative' => '1',
             'pd_prefli' => 0.10, 'pd_post_fli' => 0.10, 'collection_lgd' => 0.5, 'customer_lgd' => 0.5,
             'carrying_amount' => 0, 'commitments' => 1000000, 'facility_utilisation_rate' => 0.5, 'remaining_tenor' => 12,
             'build_basis' => json_encode(['ledger' => ['disbursed' => 1000000, 'written_off' => 0]]),
             'ecl_value' => null, 'ecl_value_discounted' => null, 'ecl_discount_status' => 'NOT_CALCULATED', 'ecl_by_scenario' => null],
            ['contract_id' => 'P', 'reporting_period' => '2026-09', 'loan_portfolio_id' => 1, 'ifrs9stage_post_qualitative' => '1',
             'pd_prefli' => 0.12, 'pd_post_fli' => 0.12, 'collection_lgd' => 0.5, 'customer_lgd' => 0.5,
             'carrying_amount' => 100000, 'commitments' => 0, 'facility_utilisation_rate' => 1, 'remaining_tenor' => 12,
             'build_basis' => null, 'ecl_value' => null, 'ecl_value_discounted' => null, 'ecl_discount_status' => 'NOT_CALCULATED', 'ecl_by_scenario' => null],
        ]);
        foreach (['U', 'P'] as $id) {
            DB::table('contract_eir')->insert(['contract_id' => $id, 'eir_effective_annual' => 0.10, 'rate_type' => 'FIXED', 'locked_at' => '2026-01-01']);
        }
        // the term structure an earlier run wrote for W, which no longer has an exposure
        DB::table('ecl_pd_term_structures')->insert(['contract_id' => 'W', 'reporting_period' => '2026-09', 'scenario_code' => 'BASE', 'period_index' => 0,
            'projection_date' => '2026-09-30', 'conditional_pd' => 1, 'survival_open' => 1, 'marginal_pd' => 1, 'cumulative_pd' => 1, 'source' => 'DEFAULTED_RESOLUTION_HORIZON']);
    }

    private function approveBaseScenario(): void
    {
        DB::table('ecl_scenario_assumptions')->insert(['scenario_code' => 'BASE', 'name' => 'Base', 'weight' => 1, 'pd_multiplier' => 1, 'lgd_multiplier' => 1,
            'ead_multiplier' => 1, 'effective_from' => '2026-01-01', 'status' => 'APPROVED']);
    }

    private function runEcl(string $mode, string $pd = 'pd_post_fli'): void
    {
        app(ExpectedCreditLossController::class)->calculateECL(Request::create('/expected-credit-loss/calculations', 'POST', [
            'ecl_calculation_level' => 'portfolio', 'ecl_calculation_id' => 1, 'reporting_period' => '2026-09-01',
            'pd_type' => $pd, 'lgd_type' => 'collection_lgd', 'discounting_mode' => $mode,
        ]));
    }

    private function loan(string $id): object
    {
        return DB::table('loan_books')->where('contract_id', $id)->first();
    }

    public function test_a_loan_written_off_to_nil_carries_nil_ecl_discounted_and_undiscounted_and_its_stale_figures_go(): void
    {
        $this->seedBook();
        $this->approveBaseScenario();
        $this->runEcl('discounted');

        $w = $this->loan('W');
        $this->assertSame(0.0, (float) $w->ecl_value, 'the undrawn amount of a facility the write-off closed is no exposure');
        $this->assertSame(0.0, (float) $w->ecl_value_discounted, 'the 50.8m of the earlier run is replaced, not carried');
        $this->assertSame(0.0, (float) $w->ecl_discounting_effect);
        $this->assertSame('CALCULATED_TIME_PHASED', $w->ecl_discount_status, 'measured nil, not unresolved');
        $this->assertSame('NO_EXPOSURE_WRITTEN_OFF', $w->ecl_discount_rate_source);
        $this->assertSame([], json_decode($w->ecl_by_scenario, true));
        $this->assertSame(0, DB::table('ecl_pd_term_structures')->where('contract_id', 'W')->count(), 'no term structure is left from the earlier run');
        $this->assertSame(0, DB::table('ecl_cashflow_projections')->where('contract_id', 'W')->count());

        // a nil balance on a facility still open is measured on its undrawn amount: 0.10 x 0.5 x 1,000,000 x 0.5
        $u = $this->loan('U');
        $this->assertEqualsWithDelta(25000, (float) $u->ecl_value, 0.01);
        $this->assertGreaterThan(0, (float) $u->ecl_value_discounted);
        $this->assertLessThan(25000, (float) $u->ecl_value_discounted);

        $s3 = DB::table('expected_credit_loss')->where('reporting_period', '2026-09')->where('ifrs9_stage', '3')->first();
        $this->assertSame(0.0, (float) $s3->total_ead);
        $this->assertSame(0.0, (float) $s3->total_ecl);
        $this->assertSame(0.0, (float) $s3->total_ecl_discounted);
        $s1 = DB::table('expected_credit_loss')->where('reporting_period', '2026-09')->where('ifrs9_stage', '1')->first();
        $this->assertEqualsWithDelta(600000, (float) $s1->total_ead, 0.01);
        $this->assertEqualsWithDelta(31000, (float) $s1->total_ecl, 0.01); // 25,000 + 0.12 x 0.5 x 100,000

        // no loan of the month with a nil balance and nothing undrawn, or written off to nil, carries ECL
        $this->assertSame(0, DB::table('loan_books')->where('reporting_period', '2026-09')->whereRaw(EclExposure::sql(true) . ' <= 0')
            ->where(fn ($q) => $q->where('ecl_value', '<>', 0)->orWhere('ecl_value_discounted', '<>', 0))->count());
    }

    public function test_an_undiscounted_run_clears_what_an_earlier_discounted_run_left_on_the_loans(): void
    {
        $this->seedBook();
        $this->runEcl('undiscounted');

        $w = $this->loan('W');
        $this->assertSame(0.0, (float) $w->ecl_value);
        $this->assertNull($w->ecl_value_discounted);
        $this->assertNull($w->ecl_by_scenario);
        $this->assertSame('NOT_REQUESTED', $w->ecl_discount_status);
    }

    public function test_a_discounted_run_without_a_scenario_set_is_refused_whole_with_no_proxy(): void
    {
        $this->seedBook();
        try {
            $this->runEcl('discounted');
            $this->fail('the run was not refused');
        } catch (EclRunRefused $e) {
            $this->assertStringContainsString('no approved scenario set covers the reporting period', $e->getMessage());
            $this->assertStringContainsString('no proxy is applied', $e->getMessage());
        }
        // rolled back whole: the loans keep what they had, no stage total and no projection run is written
        $this->assertEqualsWithDelta(49383659.90, (float) $this->loan('W')->ecl_value, 0.001);
        $this->assertNull($this->loan('P')->ecl_value);
        $this->assertNull($this->loan('P')->ecl_value_discounted);
        $this->assertSame(0, DB::table('expected_credit_loss')->count());
        $this->assertSame(0, DB::table('ecl_projection_runs')->count());
        $this->assertSame(0, DB::table('loan_books')->whereIn('ecl_discount_status', ['CALCULATED_HORIZON_PROXY', 'FLOATING_RATE_PROXY'])->count());
    }

    public function test_a_discounted_run_without_the_time_phased_engine_is_refused_before_anything_is_written(): void
    {
        $this->seedBook();
        $this->approveBaseScenario();
        Schema::drop('ecl_projection_runs');
        try {
            $this->runEcl('discounted');
            $this->fail('the run was not refused');
        } catch (EclRunRefused $e) {
            $this->assertStringContainsString('ecl_projection_runs', $e->getMessage());
            $this->assertStringContainsString('No proxy is applied', $e->getMessage());
        }
        $this->assertNull($this->loan('P')->ecl_value);
        $this->assertSame(0, DB::table('expected_credit_loss')->count());
    }

    public function test_the_time_phased_engine_names_a_loan_with_no_pd_or_lgd_never_reading_it_as_nil(): void
    {
        $this->seedBook();
        $this->approveBaseScenario();
        DB::table('loan_books')->where('contract_id', 'P')->update(['pd_post_fli' => null]);
        DB::table('loan_books')->where('contract_id', 'U')->update(['collection_lgd' => null]);

        $result = (new TimePhasedEclService())->run(DB::table('loan_books')->orderBy('id')->get(), '2026-09');

        $this->assertSame('The PD (pd_post_fli) is unavailable.', $result['exceptions']['P']);
        $this->assertSame('The LGD (collection_lgd) is unavailable.', $result['exceptions']['U']);
        $this->assertSame('TIME_PHASED_UNRESOLVED', $this->loan('P')->ecl_discount_status);
        $this->assertNull($this->loan('P')->ecl_value_discounted);
        // the written-off loan needs neither: it has no exposure
        $this->assertSame(0.0, (float) $this->loan('W')->ecl_value_discounted);
    }

    public function test_the_exposure_rule_reads_the_write_off_the_build_recorded(): void
    {
        $closed = (object) ['carrying_amount' => 0, 'commitments' => 1760000, 'facility_utilisation_rate' => 0.91, 'build_basis' => '{"ledger":{"written_off":10244624.69}}'];
        $open = (object) ['carrying_amount' => 0, 'commitments' => 1760000, 'facility_utilisation_rate' => 0.91, 'build_basis' => '{"ledger":{"written_off":0}}'];
        $partly = (object) ['carrying_amount' => 500, 'commitments' => 1000, 'facility_utilisation_rate' => 0.5, 'build_basis' => '{"ledger":{"written_off":100}}'];
        $credit = (object) ['carrying_amount' => -8620687.83, 'commitments' => 144377581.24, 'facility_utilisation_rate' => 0.06, 'build_basis' => null];
        $this->assertSame(0.0, EclExposure::of($closed));
        $this->assertEqualsWithDelta(1601600, EclExposure::of($open), 0.001);
        $this->assertEqualsWithDelta(1000, EclExposure::of($partly), 0.001, 'a balance left after a partial write-off is still an exposure');
        $this->assertEqualsWithDelta(41967.04, EclExposure::of($credit), 0.01);
    }
}
