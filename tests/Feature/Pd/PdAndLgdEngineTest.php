<?php

namespace Tests\Feature\Pd;

use App\Services\Lgd\LgdEngineService;
use App\Services\Pd\PdEngineService;
use Database\Seeders\MaiicTransitionProfileSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The PD and LGD engines as services (spec v4 section 6.10.1 steps 4 and 5)
 * on a four-loan fixture: the transition matrix over a one-year window gives
 * the probability of reaching Stage 3 from each stage and writes it to the
 * period's loan book; the cohort workout follows the Stage 3 loans of a
 * year earlier and gives LGD = (1 - cure rate)(1 - recovery rate).
 */
class PdAndLgdEngineTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        Schema::create('transition_profile_definitions', function (Blueprint $t) { $t->increments('id'); $t->string('profile_code'); $t->string('short_name'); $t->string('description')->nullable(); $t->string('start_table'); $t->string('end_table'); $t->string('start_grading_col'); $t->string('end_grading_col'); $t->string('start_value_type'); $t->string('end_value_type'); $t->string('start_client_id_col'); $t->string('end_client_id_col'); $t->string('aggregation_criteria'); $t->timestamps(); });
        Schema::create('transition_profile_options', function (Blueprint $t) { $t->increments('id'); $t->string('category_name'); $t->integer('profile_id'); $t->string('is_start_or_end'); $t->integer('ordering_index'); $t->decimal('min_value', 10, 2); $t->decimal('max_value', 10, 2); $t->string('text_value'); $t->integer('default_value')->default(0); $t->timestamps(); });
        Schema::create('transition_matrices', function (Blueprint $t) { $t->increments('id'); $t->integer('transition_profile_id'); $t->integer('pd_calculation_id')->nullable(); $t->string('pd_calculation_code')->nullable(); $t->string('pd_calculation_level'); $t->string('start_reporting_period')->nullable(); $t->integer('start_year')->nullable(); $t->integer('start_month')->nullable(); $t->string('end_reporting_period')->nullable(); $t->integer('end_year')->nullable(); $t->integer('end_month')->nullable(); $t->integer('transition_years')->nullable(); $t->integer('run_no')->nullable(); $t->integer('records_count_updated')->nullable(); $t->integer('records_count_transitioned')->nullable(); $t->integer('reporting_periods_count')->nullable(); $t->decimal('updated_balance', 20, 2)->nullable(); $t->decimal('transition_balance', 20, 2)->nullable(); $t->timestamp('last_calculation_date')->nullable(); $t->integer('portfolio_count')->nullable(); $t->timestamp('book_updated_at')->nullable(); $t->integer('take_on_flag')->nullable(); $t->text('comments')->nullable(); $t->string('user_name')->nullable(); $t->string('pd_start_stage_total_type')->nullable(); $t->integer('portfolio_group_id')->nullable(); $t->string('calculation_source')->nullable(); $t->text('description')->nullable(); $t->string('external_file_path')->nullable(); $t->string('status')->nullable(); $t->string('supporting_file')->nullable(); $t->timestamps(); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('transition_matrices_data', function (Blueprint $t) { $t->increments('id'); $t->integer('calculation_header_id'); $t->integer('is_payments_included')->default(0); $t->string('start_period'); $t->integer('start_year')->nullable(); $t->integer('start_month')->nullable(); $t->string('end_period'); $t->integer('end_year'); $t->integer('end_month'); $t->string('start_stage')->nullable(); $t->string('end_stage')->nullable(); $t->string('stage_transition')->nullable(); $t->integer('default_flag')->nullable(); $t->integer('transition_years')->nullable(); $t->decimal('transition_balance_month', 20, 2)->nullable(); $t->decimal('transition_balance_YTD', 20, 2)->nullable(); $t->decimal('transition_balance_n_months', 20, 2)->nullable(); $t->decimal('start_total_balance_month', 20, 2)->nullable(); $t->decimal('start_total_balance_YTD', 20, 2)->nullable(); $t->decimal('start_total_balance_n_months', 20, 2)->nullable(); $t->decimal('transition_probability_month', 12, 6)->nullable(); $t->decimal('transition_probability_avg_YTD', 12, 6)->nullable(); $t->decimal('transition_probability_avg_n_months', 12, 6)->nullable(); $t->integer('n_months_count')->nullable(); $t->integer('n_months_setting')->nullable(); $t->decimal('pd_mgt_overlay', 12, 6)->nullable(); $t->date('transaction_date')->nullable(); $t->integer('take_on_flag')->nullable(); $t->string('user_name')->nullable(); $t->timestamps(); $t->unique(['calculation_header_id', 'start_period', 'end_period', 'stage_transition']); });
        Schema::create('loss_given_default', function (Blueprint $t) { $t->increments('id'); $t->timestamps(); $t->string('reporting_period'); $t->string('start_period'); $t->string('lgd_calculation_level')->nullable(); $t->integer('lgd_calculation_id')->nullable(); $t->string('lgd_calculation_code')->nullable(); $t->decimal('start_total_stage3', 20, 2); $t->decimal('end_total_stage3', 20, 2); $t->decimal('loss_given_default_percentage', 12, 6); $t->decimal('cured_amount', 20, 2)->nullable(); $t->decimal('cure_rate', 12, 6)->nullable(); $t->decimal('cure_rate_average_monthly', 12, 6)->nullable(); $t->decimal('cure_amount_stage1', 20, 2)->nullable(); $t->decimal('cure_amount_stage2', 20, 2)->nullable(); $t->decimal('recovered_amount', 20, 2)->nullable(); $t->decimal('recovery_rate', 12, 6)->nullable(); $t->decimal('recovery_rate_average_monthly', 12, 6)->nullable(); $t->string('last_reporting_period')->nullable(); $t->integer('run_no')->nullable(); $t->string('type_lgd')->nullable(); $t->string('is_active_or_closed')->nullable(); $t->string('supporting_file')->nullable(); $t->integer('created_by')->nullable(); $t->integer('updated_by')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->decimal('partially_recovered_amount', 20, 2)->nullable(); $t->decimal('fully_recovered_amount', 20, 2)->nullable(); $t->decimal('total_disbursments', 20, 2)->nullable(); $t->string('calculation_source')->nullable(); $t->decimal('written_offs', 20, 2)->nullable(); $t->boolean('is_discounting')->nullable(); $t->string('discount_rate_source')->nullable(); $t->decimal('interest_rate', 10, 4)->nullable(); $t->decimal('discounted_payment_partly', 20, 2)->nullable(); $t->decimal('discounted_payment_full', 20, 2)->nullable(); $t->decimal('discount_loss', 20, 2)->nullable(); $t->decimal('total_payment', 20, 2)->nullable(); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->integer('loan_portfolio_id'); $t->string('ifrs9stage_pre_qualitative')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->string('calculated_ifrs9_stage')->nullable(); $t->string('contract_status')->nullable(); $t->decimal('carrying_amount', 20, 2)->default(0); $t->decimal('remaining_tenor', 8, 2)->nullable(); $t->decimal('pd_prefli', 16, 8)->nullable(); $t->decimal('12m_pd', 8, 2)->nullable(); $t->decimal('lifetime_pd', 8, 2)->nullable(); $t->decimal('lgd_value', 16, 8)->nullable(); $t->decimal('collection_lgd', 16, 8)->nullable(); });
        (new MaiicTransitionProfileSeeder())->run();
        // MySQL matches 'Start' and 'start' alike; SQLite does not, and the engine asks for the capitalised form
        DB::table('transition_profile_options')->where('is_start_or_end', 'start')->update(['is_start_or_end' => 'Start']);
        DB::table('transition_profile_options')->where('is_start_or_end', 'end')->update(['is_start_or_end' => 'End']);
        // five loans: A stays in Stage 1; B goes 1 -> 3; C is Stage 3 and cures to 2; D is Stage 3 and is paid down by half;
        // E is Stage 3 and is absent from the end book with a balance still owing (a write-off, audit H7)
        $start = [['A', '1', 1000], ['B', '1', 1000], ['C', '3', 500], ['D', '3', 500], ['E', '3', 500]];
        $end = [['A', '1', 1000], ['B', '3', 1000], ['C', '2', 500], ['D', '3', 250]];
        foreach ([['2025-08', $start], ['2026-08', $end]] as [$p, $rows]) {
            foreach ($rows as [$id, $stage, $ca]) {
                $this->loan($id, $p, $stage, $stage, $ca);
            }
        }
    }

    /** A staged book row: the DPD stage and the measured (post-qualitative) stage may differ. */
    private function loan(string $id, string $period, ?string $pre, ?string $post, float $carrying, ?string $status = null, ?string $calculated = 'same'): void
    {
        DB::table('loan_books')->insert(['contract_id' => $id, 'reporting_period' => $period, 'loan_portfolio_id' => 1, 'ifrs9stage_pre_qualitative' => $pre, 'ifrs9stage_post_qualitative' => $post,
            'calculated_ifrs9_stage' => $calculated === 'same' ? $post : $calculated, 'contract_status' => $status, 'carrying_amount' => $carrying, 'remaining_tenor' => 24]);
    }

    public function test_the_pd_engine_writes_the_stage_3_probabilities_to_the_period(): void
    {
        $r = (new PdEngineService())->run('2026-08', 1, 12, 'test', null);
        $this->assertSame('2025-08 to 2026-08', $r['window']);
        $this->assertEqualsWithDelta(0.5, $r['pds'][1], 1e-6);   // of the 2,000 in Stage 1, 1,000 reached Stage 3
        $this->assertEqualsWithDelta(1.0, $r['pds'][3], 1e-6);
        $this->assertSame(4, $r['updated']);
        $a = DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'A')->first();
        $this->assertEqualsWithDelta(0.5, (float) $a->pd_prefli, 1e-6);
        $this->assertEqualsWithDelta(50.0, (float) $a->{'12m_pd'}, 1e-6);
        $this->assertEqualsWithDelta(1 - 0.5 ** 2, (float) $a->lifetime_pd, 1e-6); // 24 months = 2 years
        $this->assertNull(DB::table('loan_books')->where('reporting_period', '2025-08')->where('contract_id', 'A')->value('pd_prefli'));
        $this->assertSame('closed', DB::table('transition_matrices')->where('id', $r['matrix_id'])->value('status'));
    }

    /**
     * Audit M10: the matrix grades on the measured stage. A loan the
     * instalment trigger moved to Stage 3 (DPD stage 1, post-qualitative 3)
     * is a default at the end of the window and a Stage 3 start at the start;
     * the PD is then assigned by the same measured stage.
     */
    public function test_the_matrix_and_the_pd_read_the_post_qualitative_stage(): void
    {
        // F: current by days past due, in default by the instalment trigger at the end
        $this->loan('F', '2025-08', '1', '1', 1000);
        $this->loan('F', '2026-08', '1', '3', 1000);
        // G: in default by the trigger at the start, still so at the end: a Stage 3 start, not a Stage 1 one
        $this->loan('G', '2025-08', '1', '3', 700);
        $this->loan('G', '2026-08', '1', '3', 700);
        $r = (new PdEngineService())->run('2026-08', 1, 12, 'test', null);
        // Stage 1 at the start: A, B, F (3,000); reached Stage 3: B and F (2,000)
        $this->assertEqualsWithDelta(2 / 3, $r['pds'][1], 1e-6);
        $f = DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'F')->first();
        $this->assertEqualsWithDelta(1.0, (float) $f->pd_prefli, 1e-6, 'a loan in default by the trigger carries a PD of one');
        $this->assertEqualsWithDelta(2 / 3, (float) DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'A')->value('pd_prefli'), 1e-6);
        $this->assertSame(6, $r['updated']);
    }

    /** Audit M10: a row with no post-qualitative stage is graded by its calculated stage, then by its DPD stage. */
    public function test_a_row_without_a_measured_stage_falls_back_to_the_calculated_then_the_dpd_stage(): void
    {
        $this->loan('H', '2025-08', '1', null, 1000, null, '2');   // calculated says Stage 2
        $this->loan('H', '2026-08', '3', null, 1000, null, null);  // nothing but the DPD stage: 3
        $r = (new PdEngineService())->run('2026-08', 1, 12, 'test', null);
        $this->assertEqualsWithDelta(1.0, $r['pds'][2], 1e-6, 'H is the only Stage 2 start and it reached Stage 3');
        $this->assertEqualsWithDelta(1.0, (float) DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'H')->value('pd_prefli'), 1e-6);
    }

    /**
     * Audit M10: a loan that leaves the book inside the window is "Paid"
     * only when its last row shows it settled (a zero balance or a closed or
     * paid status); one that vanishes with a balance owing is in the default
     * bucket, not the denominator's good news.
     */
    public function test_a_loan_that_leaves_the_book_is_paid_only_when_its_last_row_shows_it_settled(): void
    {
        $this->loan('P', '2025-08', '1', '1', 1000);
        $this->loan('P', '2026-02', '1', '1', 0);                     // paid down to nothing, then gone
        $this->loan('Q', '2025-08', '1', '1', 1000);
        $this->loan('Q', '2026-03', '1', '1', 400, 'Closed');         // closed with a balance the system wrote back
        $this->loan('W', '2025-08', '1', '1', 1000);
        $this->loan('W', '2026-01', '2', '2', 900);                   // last seen owing 900, then gone: written off
        $this->loan('X', '2025-08', '1', '1', 1000);                  // gone right after the start, owing
        $r = (new PdEngineService())->run('2026-08', 1, 12, 'test', null);
        // Stage 1 starts: A, B, P, Q, W, X (6,000); reached Stage 3: B, W, X (3,000); P and Q are Paid
        $this->assertEqualsWithDelta(0.5, $r['pds'][1], 1e-6);
        $cells = DB::table('transition_matrices_data')->where('calculation_header_id', $r['matrix_id'])->where('start_stage', '1')->pluck('transition_balance_month', 'end_stage');
        $this->assertEqualsWithDelta(2000.0, (float) $cells['Paid'], 1e-6);
        $this->assertEqualsWithDelta(3000.0, (float) $cells['3'], 1e-6);
    }

    /** Audit H8: a default rate measured over three months is annualised before it is written as a twelve-month PD. */
    public function test_a_short_window_is_annualised(): void
    {
        // the same four loans, but the only earlier book is three months before the period
        DB::table('loan_books')->where('reporting_period', '2025-08')->update(['reporting_period' => '2026-05']);
        $r = (new PdEngineService())->run('2026-08', 1, 12, 'test', null);
        $this->assertSame(3, $r['window_months']);
        $this->assertTrue($r['annualised']);
        $this->assertEqualsWithDelta(1 - pow(1 - 0.5, 12 / 3), $r['pds'][1], 1e-6); // 50 percent in three months is 93.75 percent in a year
    }

    public function test_the_lgd_engine_follows_the_stage_3_cohort(): void
    {
        $r = (new LgdEngineService())->run('2026-08', 1, 12, null, 'test');
        $this->assertSame(3, $r['cohort']);
        $this->assertEquals(1500.0, $r['start_balance']);
        $this->assertEqualsWithDelta(1 / 3, $r['cure_rate'], 1e-6);     // C (500 of 1,500) cured to Stage 2
        // of the 1,000 that did not cure, D paid 250 and E (absent, still owing) recovered nothing: 25 percent.
        // Before the audit E counted as fully recovered and the cured loan sat in the recovery denominator.
        $this->assertEqualsWithDelta(0.25, $r['recovery_rate'], 1e-6);
        $this->assertEqualsWithDelta(0.5, $r['lgd'], 1e-6);             // (1 - 1/3)(1 - 0.25)
        $this->assertSame(4, $r['updated']);
        $this->assertEqualsWithDelta(500.0, (float) DB::table('loss_given_default')->where('id', $r['lgd_id'])->value('written_offs'), 1e-6);
        $this->assertEqualsWithDelta(0.5, (float) DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'A')->value('collection_lgd'), 1e-6);
        $this->assertSame('closed', DB::table('loss_given_default')->where('id', $r['lgd_id'])->value('is_active_or_closed'));
        $this->assertSame('system', DB::table('loss_given_default')->where('id', $r['lgd_id'])->value('calculation_source'));
    }

    public function test_an_empty_cohort_is_refused_rather_than_an_lgd_invented(): void
    {
        DB::table('loan_books')->where('reporting_period', '2025-08')->update(['calculated_ifrs9_stage' => '1']);
        $this->expectException(RuntimeException::class);
        (new LgdEngineService())->run('2026-08', 1, 12);
    }
}
