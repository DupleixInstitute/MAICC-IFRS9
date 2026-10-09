<?php

namespace Tests\Feature\Pd;

use App\Models\ExpectedCreditLoss;
use App\Services\Eir\GovernanceService;
use App\Services\Lgd\LgdSegmentationService;
use App\Services\Pd\EclSegmentLineage;
use App\Services\Pd\PdSegmentationService;
use App\Services\Pd\SegmentPdReport;
use Database\Seeders\MaiicTransitionProfileSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The PD and LGD by segment (9 October 2026) on a two-portfolio fixture.
 *
 * Window 2025-08 to 2026-08, every loan 1,000 at the start.
 *   Portfolio 1 "Alpha": Stage 1 ten loans, two reach Stage 3 (20%); Stage 2 one loan, reaches 3;
 *                        Stage 3 six loans (the LGD cohort), two cure.
 *   Portfolio 2 "Beta":  Stage 1 three loans, one reaches Stage 3 (thin); Stage 2 ten loans,
 *                        three reach Stage 3 (30%); Stage 3 one loan (thin LGD cohort).
 *   Pooled book: Stage 1 13 loans, 3 defaults (3/13); Stage 2 11 loans, 4 defaults (4/11).
 * Governed defaults: by portfolio; 10 loans and 1 default per stage; take the parent.
 */
class PdSegmentationTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('email'); $t->timestamps(); });
        DB::table('users')->insert([['id' => 10, 'name' => 'Maker', 'email' => 'm@x.test'], ['id' => 20, 'name' => 'Checker', 'email' => 'c@x.test']]);
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
        Schema::create('loan_portfolios', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        DB::table('loan_portfolios')->insert([['id' => 1, 'name' => 'Alpha'], ['id' => 2, 'name' => 'Beta']]);
        Schema::create('industry_types', function (Blueprint $t) { $t->increments('id'); $t->string('code'); $t->string('name'); $t->timestamps(); });
        DB::table('industry_types')->insert([['code' => '1', 'name' => 'Agriculture'], ['code' => '3', 'name' => 'Manufacturing'], ['code' => '5', 'name' => 'Construction']]);
        Schema::create('transition_profile_definitions', function (Blueprint $t) { $t->increments('id'); $t->string('profile_code'); $t->string('short_name'); $t->string('description')->nullable(); $t->string('start_table'); $t->string('end_table'); $t->string('start_grading_col'); $t->string('end_grading_col'); $t->string('start_value_type'); $t->string('end_value_type'); $t->string('start_client_id_col'); $t->string('end_client_id_col'); $t->string('aggregation_criteria'); $t->timestamps(); });
        Schema::create('transition_profile_options', function (Blueprint $t) { $t->increments('id'); $t->string('category_name'); $t->integer('profile_id'); $t->string('is_start_or_end'); $t->integer('ordering_index'); $t->decimal('min_value', 10, 2); $t->decimal('max_value', 10, 2); $t->string('text_value'); $t->integer('default_value')->default(0); $t->timestamps(); });
        Schema::create('transition_matrices', function (Blueprint $t) { $t->increments('id'); $t->integer('transition_profile_id'); $t->integer('pd_calculation_id')->nullable(); $t->string('pd_calculation_code')->nullable(); $t->string('pd_calculation_level'); $t->string('start_reporting_period')->nullable(); $t->integer('start_year')->nullable(); $t->integer('start_month')->nullable(); $t->string('end_reporting_period')->nullable(); $t->integer('end_year')->nullable(); $t->integer('end_month')->nullable(); $t->integer('transition_years')->nullable(); $t->integer('run_no')->nullable(); $t->integer('records_count_updated')->nullable(); $t->integer('records_count_transitioned')->nullable(); $t->integer('reporting_periods_count')->nullable(); $t->decimal('updated_balance', 20, 2)->nullable(); $t->decimal('transition_balance', 20, 2)->nullable(); $t->timestamp('last_calculation_date')->nullable(); $t->integer('portfolio_count')->nullable(); $t->timestamp('book_updated_at')->nullable(); $t->integer('take_on_flag')->nullable(); $t->text('comments')->nullable(); $t->string('user_name')->nullable(); $t->string('pd_start_stage_total_type')->nullable(); $t->string('calculation_source')->nullable(); $t->string('status')->nullable(); $t->timestamps(); });
        Schema::create('transition_matrices_data', function (Blueprint $t) { $t->increments('id'); $t->integer('calculation_header_id'); $t->integer('is_payments_included')->default(0); $t->string('start_period'); $t->integer('start_year')->nullable(); $t->integer('start_month')->nullable(); $t->string('end_period'); $t->integer('end_year'); $t->integer('end_month'); $t->string('start_stage')->nullable(); $t->string('end_stage')->nullable(); $t->string('stage_transition')->nullable(); $t->integer('default_flag')->nullable(); $t->integer('transition_years')->nullable(); $t->decimal('transition_balance_month', 20, 2)->nullable(); $t->decimal('start_total_balance_month', 20, 2)->nullable(); $t->decimal('transition_probability_month', 12, 6)->nullable(); $t->timestamps(); });
        Schema::create('loss_given_default', function (Blueprint $t) { $t->increments('id'); $t->timestamps(); $t->string('reporting_period'); $t->string('start_period'); $t->string('lgd_calculation_level')->nullable(); $t->integer('lgd_calculation_id')->nullable(); $t->string('lgd_calculation_code')->nullable(); $t->decimal('start_total_stage3', 20, 2); $t->decimal('end_total_stage3', 20, 2); $t->decimal('loss_given_default_percentage', 12, 6); $t->decimal('cured_amount', 20, 2)->nullable(); $t->decimal('cure_rate', 12, 6)->nullable(); $t->decimal('cure_rate_average_monthly', 12, 6)->nullable(); $t->decimal('cure_amount_stage1', 20, 2)->nullable(); $t->decimal('cure_amount_stage2', 20, 2)->nullable(); $t->decimal('recovered_amount', 20, 2)->nullable(); $t->decimal('recovery_rate', 12, 6)->nullable(); $t->decimal('recovery_rate_average_monthly', 12, 6)->nullable(); $t->string('last_reporting_period')->nullable(); $t->string('is_active_or_closed')->nullable(); $t->integer('created_by')->nullable(); $t->integer('updated_by')->nullable(); $t->decimal('partially_recovered_amount', 20, 2)->nullable(); $t->decimal('fully_recovered_amount', 20, 2)->nullable(); $t->decimal('total_disbursments', 20, 2)->nullable(); $t->string('calculation_source')->nullable(); $t->decimal('written_offs', 20, 2)->nullable(); $t->boolean('is_discounting')->nullable(); $t->string('discount_rate_source')->nullable(); $t->decimal('discounted_payment_partly', 20, 2)->nullable(); $t->decimal('total_payment', 20, 2)->nullable(); });
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->integer('loan_portfolio_id'); $t->string('product_group')->nullable();
            $t->string('industry_code')->nullable(); $t->string('industry_type')->nullable(); $t->integer('overdue_days')->nullable(); $t->integer('tenor')->nullable();
            $t->string('ifrs9stage_pre_qualitative')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->string('calculated_ifrs9_stage')->nullable(); $t->string('contract_status')->nullable();
            $t->decimal('carrying_amount', 20, 2)->default(0); $t->decimal('commitments', 20, 2)->nullable(); $t->decimal('facility_utilisation_rate', 8, 4)->nullable(); $t->decimal('remaining_tenor', 8, 2)->nullable();
            $t->decimal('pd_prefli', 16, 8)->nullable(); $t->decimal('pd_post_fli', 16, 8)->nullable(); $t->decimal('12m_pd', 8, 2)->nullable(); $t->decimal('lifetime_pd', 16, 8)->nullable();
            $t->decimal('lgd_value', 16, 8)->nullable(); $t->decimal('collection_lgd', 16, 8)->nullable(); $t->decimal('ecl_value', 20, 2)->nullable();
            $t->string('pd_segment_key')->nullable(); $t->string('pd_applied_segment_key')->nullable(); $t->string('pd_segment_run_id')->nullable(); $t->integer('pd_matrix_id')->nullable();
            $t->string('lgd_applied_segment_key')->nullable(); $t->integer('lgd_source_id')->nullable();
        });
        foreach (['segment_parameter_runs', 'segment_parameter_results'] as $table) {
            Schema::dropIfExists($table);
        }
        (require base_path('database/migrations/2026_10_09_700000_create_segment_parameter_lineage.php'))->upTablesOnly();
        (new MaiicTransitionProfileSeeder())->run();

        // [contract, portfolio, start stage, end stage (null = gone, written off), sector label, E-Banker code]
        $book = [];
        foreach (range(1, 10) as $i) { $book[] = ["A1-{$i}", 1, '1', $i <= 2 ? '3' : '1', '3-3. Manufacturing', '1079']; }
        $book[] = ['A2-1', 1, '2', '3', '3-3. Manufacturing', '1079'];
        foreach (range(1, 6) as $i) { $book[] = ["A3-{$i}", 1, '3', $i <= 2 ? '1' : '3', '1-1. Agriculture, forestry and fishing', '112']; }
        foreach (range(1, 3) as $i) { $book[] = ["B1-{$i}", 2, '1', $i === 1 ? '3' : '1', '5-5.  Construction and Engineering', '4290']; }
        foreach (range(1, 10) as $i) { $book[] = ["B2-{$i}", 2, '2', $i <= 3 ? '3' : '2', '5-5.  Construction and Engineering', '4290']; }
        $book[] = ['B3-1', 2, '3', '3', '5-5.  Construction and Engineering', '4290'];
        foreach ($book as [$id, $p, $s, $e, $type, $code]) {
            $this->loan($id, '2025-08', $p, $s, 1000, $type, $code);
            if ($e !== null) {
                $this->loan($id, '2026-08', $p, $e, $e === '3' ? 1000 : 900, $type, $code);
            }
        }
    }

    private function loan(string $id, string $period, int $portfolio, string $stage, float $ca, string $type, string $code): void
    {
        $dpd = ['1' => 0, '2' => 45, '3' => 200][$stage];
        DB::table('loan_books')->insert(['contract_id' => $id, 'reporting_period' => $period, 'loan_portfolio_id' => $portfolio, 'industry_type' => $type, 'industry_code' => $code,
            'overdue_days' => $dpd, 'tenor' => 36, 'ifrs9stage_pre_qualitative' => $stage, 'ifrs9stage_post_qualitative' => $stage, 'calculated_ifrs9_stage' => $stage,
            'carrying_amount' => $ca, 'remaining_tenor' => 24]);
    }

    private function governed(string $key, string $value): void
    {
        $g = app(GovernanceService::class);
        $p = $g->propose($key, $value, '2026-01-01', 'Test of the PD by segment rules', 10);
        $g->approve($p->id, 20);
    }

    private function pd(string $id): float
    {
        return (float) DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', $id)->value('pd_prefli');
    }

    public function test_each_portfolio_gets_its_own_pd_or_its_parents_with_the_lineage_recorded(): void
    {
        $r = app(PdSegmentationService::class)->run('2026-08', null, 12, 1, 'test');

        $this->assertSame('portfolio', $r['basis']);
        $this->assertSame('2025-08 to 2026-08', $r['window']);
        // Alpha Stage 1: ten loans, two defaults -> its own 20%
        $this->assertEqualsWithDelta(0.2, $this->pd('A1-5'), 1e-8);
        // Beta Stage 1: three loans -> thin -> the pooled book's Stage 1 PD, 3 of 13
        $this->assertEqualsWithDelta(3 / 13, $this->pd('B1-2'), 1e-8);
        // Beta Stage 2: ten loans, three defaults -> its own 30%
        $this->assertEqualsWithDelta(0.3, $this->pd('B2-5'), 1e-8);
        // Stage 3 is in default
        $this->assertEqualsWithDelta(1.0, $this->pd('A3-4'), 1e-8);

        $b1 = DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'B1-2')->first();
        $this->assertSame('portfolio:2', $b1->pd_segment_key);
        $this->assertSame('book', $b1->pd_applied_segment_key);
        $this->assertSame($r['run_id'], $b1->pd_segment_run_id);
        $this->assertSame('book', DB::table('transition_matrices')->where('id', $b1->pd_matrix_id)->value('pd_calculation_code'));
        $this->assertSame('portfolio', DB::table('transition_matrices')->where('pd_calculation_code', 'portfolio:1')->value('pd_calculation_level'));
        $this->assertSame(1, (int) DB::table('transition_matrices')->where('pd_calculation_code', 'portfolio:1')->value('pd_calculation_id'));
        // the matrix cells are the cohort's: Alpha 1 -> 3 is 2,000 of 10,000
        $alpha = DB::table('transition_matrices')->where('pd_calculation_code', 'portfolio:1')->value('id');
        $this->assertEqualsWithDelta(20.0, (float) DB::table('transition_matrices_data')->where('calculation_header_id', $alpha)->where('stage_transition', '1to3')->value('transition_probability_month'), 1e-6);

        // the cells, own and parent, with the reason
        $cell = DB::table('segment_parameter_results')->where('run_id', $r['run_id'])->where('segment_key', 'portfolio:2')->where('stage', 1)->first();
        $this->assertSame('parent', $cell->status);
        $this->assertSame('book', $cell->applied_from_key);
        $this->assertSame(3, (int) $cell->cohort_loans);
        $this->assertStringContainsString('below the minimum of 10 loans and 1 default', $cell->reason);
        $this->assertSame('own', DB::table('segment_parameter_results')->where('run_id', $r['run_id'])->where('segment_key', 'portfolio:2')->where('stage', 2)->value('status'));
        $this->assertSame('applied', DB::table('segment_parameter_runs')->where('run_id', $r['run_id'])->value('status'));
        // an earlier post-FLI PD is cleared: it was built on the old pre-FLI PD
        $this->assertNull(DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'A1-5')->value('pd_post_fli'));
        // the window start is never written
        $this->assertNull(DB::table('loan_books')->where('reporting_period', '2025-08')->whereNotNull('pd_prefli')->value('id'));
    }

    public function test_a_thin_segment_fails_closed_under_the_fail_closed_rule_and_nothing_is_written(): void
    {
        $this->governed('pd_segment_thin_rule', 'Fail closed: stop the run and name the segment');
        try {
            app(PdSegmentationService::class)->run('2026-08', null, 12, 1, 'test');
            $this->fail('The run should stop on the thin Beta Stage 1.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Beta (portfolio:2) Stage 1', $e->getMessage());
            $this->assertStringContainsString('fail closed', $e->getMessage());
        }
        $this->assertSame(0, DB::table('loan_books')->whereNotNull('pd_prefli')->count());
        $this->assertSame(0, DB::table('transition_matrices')->count());
        $this->assertSame('failed', DB::table('segment_parameter_runs')->value('status'));
        $this->assertSame('failed', DB::table('segment_parameter_results')->where('segment_key', 'portfolio:2')->where('stage', 1)->value('status'));
    }

    public function test_the_parent_must_meet_the_minimum_itself(): void
    {
        $this->governed('pd_segment_min_observations', '20 loans and 2 defaults per stage');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no segment above it meets the minimum');
        app(PdSegmentationService::class)->run('2026-08', null, 12, 1, 'test');
    }

    public function test_the_sector_basis_holds_the_default_e_banker_code_apart(): void
    {
        $this->governed('pd_segmentation_basis', 'By RBM sector');
        $this->governed('pd_segment_min_observations', '5 loans and 1 default per stage');
        $r = app(PdSegmentationService::class)->run('2026-08', null, 12, 1, 'test');
        $this->assertSame('sector', $r['basis']);
        // Beta's loans carry 4290: they are the unverified segment, not construction
        $this->assertSame('sector:unverified', DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'B2-5')->value('pd_segment_key'));
        $this->assertSame('sector:3', DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'A1-5')->value('pd_segment_key'));
        // manufacturing Stage 1 is Alpha's ten loans: 20%; unverified Stage 1 is Beta's three: thin, the book's
        $this->assertEqualsWithDelta(0.2, $this->pd('A1-5'), 1e-8);
        $this->assertEqualsWithDelta(3 / 13, $this->pd('B1-2'), 1e-8);
        $this->assertSame('rbm_sector', DB::table('transition_matrices')->where('pd_calculation_code', 'sector:3')->value('pd_calculation_level'));
        // E-Banker's codes are never changed
        $this->assertSame(28, DB::table('loan_books')->where('industry_code', '4290')->count());
    }

    public function test_the_portfolio_and_sector_basis_falls_back_through_the_portfolio(): void
    {
        $this->governed('pd_segmentation_basis', 'By portfolio and RBM sector');
        app(PdSegmentationService::class)->run('2026-08', null, 12, 1, 'test');
        $a = DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'A1-5')->first();
        $this->assertSame('portfolio:1|sector:3', $a->pd_segment_key);
        $this->assertSame('portfolio:1|sector:3', $a->pd_applied_segment_key);
        $this->assertSame('portfolio:1', PdSegmentationService::parentOf('portfolio:1|sector:3'));
        $this->assertSame(['portfolio:2|sector:unverified', 'portfolio:2', 'book'], PdSegmentationService::chain('portfolio:2|sector:unverified'));
        // Beta x unverified Stage 1 (three loans) is thin, Beta Stage 1 too: the book's
        $b = DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'B1-2')->first();
        $this->assertSame('book', $b->pd_applied_segment_key);
        // Beta x unverified Stage 2 (ten loans, three defaults) is its own
        $this->assertSame('portfolio:2|sector:unverified', DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'B2-5')->value('pd_applied_segment_key'));
    }

    public function test_the_lgd_of_a_thin_portfolio_takes_the_pooled_book(): void
    {
        $r = app(LgdSegmentationService::class)->run('2026-08', null, 12, 1);
        // Alpha: six Stage 3 loans, two cure -> its own LGD; Beta: one loan -> thin -> the pooled book
        $alpha = collect($r['portfolios'])->firstWhere('key', 'portfolio:1');
        $beta = collect($r['portfolios'])->firstWhere('key', 'portfolio:2');
        $this->assertSame('own', $alpha['status']);
        $this->assertSame('parent', $beta['status']);
        $this->assertSame(7, $r['pooled']['cohort']);
        $this->assertEqualsWithDelta($r['pooled']['lgd'], (float) DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'B2-5')->value('lgd_value'), 1e-6);
        $this->assertSame('book', DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'B2-5')->value('lgd_applied_segment_key'));
        $this->assertSame('book', DB::table('loss_given_default')->where('id', DB::table('loan_books')->where('contract_id', 'B2-5')->where('reporting_period', '2026-08')->value('lgd_source_id'))->value('lgd_calculation_level'));
        // the cure rate of Alpha: 2,000 of 6,000 cured
        $this->assertEqualsWithDelta(1 / 3, (float) DB::table('loss_given_default')->where('lgd_calculation_code', 'portfolio:1')->value('cure_rate'), 1e-4);
    }

    public function test_the_pd_by_rbm_class_report_counts_the_performing_loans_of_each_class(): void
    {
        $r = app(SegmentPdReport::class)->byRbmClass('2026-08');
        $rows = collect($r['rows'])->keyBy('class');
        // a 36-month facility: Pass to 90 days (Stage 1 and the 45-day Stage 2 loans), Substandard at 200 days
        $pass = $rows['Pass'];
        $this->assertSame(24, $pass['start_loans']);              // 13 Stage 1 + 11 Stage 2
        $this->assertSame(24, $pass['performing']);
        $this->assertSame(7, $pass['defaults']);                  // 3 from Stage 1, 4 from Stage 2
        $this->assertEqualsWithDelta(7 / 24, $pass['pd_count'], 1e-9);
        $this->assertEqualsWithDelta(7 / 24, $pass['pd_balance'], 1e-9);
        $sub = $rows['Substandard'];
        $this->assertSame(7, $sub['start_loans']);
        $this->assertSame(7, $sub['start_stage3']);
        $this->assertSame(0, $sub['performing']);
        $this->assertNull($sub['pd_count']);
        $this->assertSame(5, $sub['stayed_in_default']);          // two of Alpha's cured
        $this->assertEqualsWithDelta(0.20, $sub['minimum'], 1e-9);
        $this->assertSame('2025-08 to 2026-08', $r['window']);
    }

    public function test_an_ecl_row_carries_the_segment_run_of_its_loans(): void
    {
        $r = app(PdSegmentationService::class)->run('2026-08', null, 12, 1, 'test');
        Schema::create('expected_credit_loss', function (Blueprint $t) { $t->increments('id'); $t->string('pd_segment_run_id')->nullable(); $t->text('pd_segment_keys')->nullable(); });
        $row = new ExpectedCreditLoss(['reporting_period' => '2026-08', 'ecl_calculation_level' => 'portfolio', 'ecl_calculation_id' => 2, 'ifrs9_stage' => '1']);
        EclSegmentLineage::stamp($row);
        $this->assertSame($r['run_id'], $row->pd_segment_run_id);
        $this->assertSame('portfolio:2', $row->pd_segment_keys);
        // a loan outside the run makes the row's lineage "mixed": no single run is claimed
        DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'B1-2')->update(['pd_segment_run_id' => null]);
        EclSegmentLineage::stamp($row);
        $this->assertNull($row->pd_segment_run_id);
    }

    public function test_the_governed_options_parse(): void
    {
        $this->assertSame([10, 1], PdSegmentationService::parseMinimum('10 loans and 1 default per stage'));
        $this->assertSame([10, 0], PdSegmentationService::parseMinimum('10 loans per stage, defaults not counted'));
        $this->assertSame(['4290'], PdSegmentationService::parseUnverified('4290 (E-Banker default civil engineering code)'));
        $this->assertSame([], PdSegmentationService::parseUnverified('None held apart'));
        $this->assertSame('9', PdSegmentationService::sectorCode('8-9. Financial and Insurance Activities'));
        $this->assertSame('5', PdSegmentationService::sectorCode('5-5.  Construction and Engineering'));
        $this->assertNull(PdSegmentationService::sectorCode(null));
        foreach (GovernanceService::catalogue()['pd_segmentation_basis']['options'] as $o) {
            $this->assertContains(PdSegmentationService::parseBasis($o), ['portfolio', 'book', 'sector', 'portfolio_sector']);
        }
        foreach (GovernanceService::catalogue()['pd_segment_thin_rule']['options'] as $o) {
            $this->assertContains(PdSegmentationService::parseThinRule($o), ['parent', 'fail']);
        }
    }
}
