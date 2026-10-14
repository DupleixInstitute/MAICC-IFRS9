<?php

namespace Tests\Feature\Reports;

use App\Http\Controllers\ReportsController;
use App\Services\Ecl\EclExposure;
use App\Services\Reports\BookedEcl;
use App\Services\Reports\ECLExportService;
use App\Services\Reports\EclBuildUpService;
use App\Services\Reports\LoanBookExportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Documentation audit of 14 October 2026:
 *  - item 2: the /report screens and downloads ask for the reports permission
 *    their hub tiles name, not only a login;
 *  - item 3: the ECL and loan book exports carry the booked allowance with the
 *    undiscounted ECL beside it, by the stage after the qualitative triggers;
 *  - item 11: the basis labels name the EIR a floating-rate loan is discounted at;
 *  - item 14: the reports' EAD is the engine's (EclExposure), floored at nil
 *    and nil for a loan written off to nil.
 *
 * The book, September 2026, portfolio 1:
 *  A  calculated stage 1, after the triggers stage 2; gross 1,000; ECL 50, discounted 52
 *  B  stage 3; gross 400; ECL 200, no discounted figure (no locked EIR)
 *  C  stage 1; a credit balance of -30 with 100 undrawn at 0.5; ECL nil
 *  D  stage 3; written off to nil in the build, 200 undrawn at 1.0; no ECL
 */
class ReportBasisAndAccessTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::create('loan_portfolios', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->boolean('active')->default(true); });
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id'); $t->integer('loan_portfolio_id')->nullable(); $t->string('contract_id'); $t->string('reporting_period'); $t->string('product_code')->nullable();
            $t->decimal('carrying_amount', 20, 2)->default(0); $t->decimal('commitments', 20, 2)->nullable(); $t->decimal('facility_utilisation_rate', 10, 4)->nullable();
            $t->decimal('ecl_value', 20, 2)->nullable(); $t->decimal('ecl_value_discounted', 20, 2)->nullable(); $t->decimal('ecl_discounting_effect', 20, 2)->nullable(); $t->string('ecl_discount_status')->nullable();
            $t->string('ifrs9stage_post_qualitative')->nullable(); $t->string('calculated_ifrs9_stage')->nullable(); $t->string('ifrs9stage_pre_qualitative')->nullable();
            $t->double('pd_post_fli')->nullable(); $t->double('pd_prefli')->nullable(); $t->double('pd_value')->nullable(); $t->double('lgd_value')->nullable();
            $t->string('external_identity_id')->nullable(); $t->date('create_date')->nullable(); $t->date('due_date')->nullable(); $t->string('contract_status')->nullable(); $t->integer('overdue_days')->nullable();
            $t->text('build_basis')->nullable();
        });
        DB::table('loan_portfolios')->insert(['id' => 1, 'name' => 'MAIIC Agricultural']);
        $row = fn (array $r) => DB::table('loan_books')->insert($r + ['loan_portfolio_id' => 1, 'reporting_period' => '2026-09', 'product_code' => '1050101', 'pd_post_fli' => 0.1, 'lgd_value' => 0.5]);
        $row(['contract_id' => 'A', 'calculated_ifrs9_stage' => '1', 'ifrs9stage_post_qualitative' => '2', 'carrying_amount' => 1000, 'ecl_value' => 50, 'ecl_value_discounted' => 52, 'ecl_discount_status' => BookedEcl::DISCOUNTED_STATUS]);
        $row(['contract_id' => 'B', 'calculated_ifrs9_stage' => '3', 'ifrs9stage_post_qualitative' => '3', 'carrying_amount' => 400, 'ecl_value' => 200, 'ecl_discount_status' => 'TIME_PHASED_UNRESOLVED']);
        $row(['contract_id' => 'C', 'calculated_ifrs9_stage' => '1', 'ifrs9stage_post_qualitative' => '1', 'carrying_amount' => -30, 'commitments' => 100, 'facility_utilisation_rate' => 0.5, 'ecl_value' => 0]);
        $row(['contract_id' => 'D', 'calculated_ifrs9_stage' => '3', 'ifrs9stage_post_qualitative' => '3', 'carrying_amount' => 0, 'commitments' => 200, 'facility_utilisation_rate' => 1,
            'build_basis' => json_encode(['ledger' => ['written_off' => 500]])]);
    }

    public function test_the_report_screens_ask_for_the_reports_permission_their_tiles_name(): void
    {
        $gate = collect((new ReportsController())->getMiddleware())->firstWhere('middleware', 'permission:reports');
        $this->assertNotNull($gate);
        $this->assertSame([], $gate['options'], 'every action, not only the redirect');

        $hub = new \ReflectionMethod(\App\Http\Controllers\Reports\Ifrs9ReportsController::class, 'hub');
        $hub->setAccessible(true);
        $tiles = collect($hub->invoke(new \App\Http\Controllers\Reports\Ifrs9ReportsController()))->flatMap(fn ($c) => $c['reports'])
            ->filter(fn ($t) => str_starts_with($t['route'], 'reports.'));
        $this->assertCount(4, $tiles);
        foreach ($tiles as $tile) {
            $this->assertSame('reports', $tile['permission'], $tile['route']);
        }
    }

    public function test_the_ecl_export_carries_the_booked_allowance_and_the_stage_after_the_triggers(): void
    {
        $rows = collect(ECLExportService::generate(['portfolio_id' => 1, 'reporting_period' => '2026-09', 'mode' => 'summary']))->keyBy('Stage');

        // A sits in Stage 2 after the triggers, at its discounted 52; B is shown at its undiscounted 200 and counted
        $this->assertEqualsWithDelta(52, $rows['2']['Loss allowance (booked)'], 0.01);
        $this->assertEqualsWithDelta(50, $rows['2']['ECL undiscounted'], 0.01);
        $this->assertEqualsWithDelta(200, $rows['3']['Loss allowance (booked)'], 0.01);
        $this->assertSame(1, $rows['3']['Loans without a discounted allowance']);
        $this->assertEqualsWithDelta(252, $rows['Total']['Loss allowance (booked)'], 0.01);
        $this->assertEqualsWithDelta(250, $rows['Total']['ECL undiscounted'], 0.01);
        // the engine's EAD: C's credit balance with 50 drawn of its undrawn is 20, D written off to nil is nil
        $this->assertEqualsWithDelta(20, $rows['1']['EAD'], 0.01);
        $this->assertEqualsWithDelta(400, $rows['3']['EAD'], 0.01);

        $loans = collect(ECLExportService::generate(['portfolio_id' => 1, 'reporting_period' => '2026-09', 'mode' => 'totalLoanBook']))->keyBy('contract_id');
        $this->assertEqualsWithDelta(52, $loans['A']['ecl_booked'], 0.01);
        $this->assertEqualsWithDelta(50, $loans['A']['ecl_value'], 0.01);
        $this->assertSame('discounted', $loans['A']['ecl_basis']);
        $this->assertSame('2', (string) $loans['A']['stage']);
        $this->assertSame('undiscounted', $loans['B']['ecl_basis']);
        $this->assertArrayNotHasKey('calculated_ifrs9_stage', $loans['A']);

        // a column outside the offered list never reaches the query
        $picked = ECLExportService::generate(['portfolio_id' => 1, 'reporting_period' => '2026-09', 'mode' => 'totalLoanBook', 'columns' => ['contract_id', 'ecl_booked', 'build_basis']]);
        $this->assertSame(['id', 'contract_id', 'ecl_booked'], array_keys($picked[0]));
    }

    public function test_the_loan_book_export_splits_by_the_stage_after_the_triggers_with_the_allowance(): void
    {
        $summary = LoanBookExportService::generate(['start_period' => '2026-09', 'end_period' => '2026-09', 'mode' => 'summary'])[0];
        $this->assertSame('2026-09', $summary['reporting_period']);
        $this->assertSame(1, (int) $summary['stage_2_count']);
        $this->assertEqualsWithDelta(1000, $summary['stage_2_balance'], 0.01);
        $this->assertSame(1, (int) $summary['stage_1_count']);
        $this->assertEqualsWithDelta(252, $summary['allowance_booked'], 0.01);
        $this->assertEqualsWithDelta(250, $summary['ecl_undiscounted'], 0.01);
        $this->assertSame(1, (int) $summary['not_booked_count']);

        $detail = collect(LoanBookExportService::generate(['start_period' => '2026-09', 'end_period' => '2026-09', 'mode' => 'detailed']))->keyBy('contract_id');
        $this->assertSame('2', (string) $detail['A']['stage']);
        $this->assertEqualsWithDelta(52, $detail['A']['allowance_booked'], 0.01);
        $this->assertSame('none', $detail['D']['allowance_basis']);
    }

    public function test_the_reports_ead_is_the_engines_one_rule(): void
    {
        $engine = DB::table('loan_books')->get()->sum(fn ($l) => EclExposure::of($l));
        $this->assertEqualsWithDelta(1420, $engine, 0.01); // 1,000 + 400 + 20 + nil

        $ead = new \ReflectionMethod(\App\Http\Controllers\Reports\Ifrs9ReportsController::class, 'ead');
        $ead->setAccessible(true);
        $pd = new \ReflectionMethod(\App\Services\Pd\SegmentPdReport::class, 'ead');
        $pd->setAccessible(true);
        foreach (['reports hub' => $ead->invoke(null), 'build-up' => EclBuildUpService::eadSql(), 'segment PD report' => $pd->invoke(null)] as $where => $sql) {
            $this->assertEqualsWithDelta($engine, (float) DB::table('loan_books')->selectRaw("SUM({$sql}) AS ead")->value('ead'), 0.01, $where);
        }
        $this->assertFalse(defined(EclBuildUpService::class . '::EAD_SQL'), 'no second rule left behind');
        $this->assertFalse(defined(\App\Services\Stress\StressTestService::class . '::EAD'));
    }

    public function test_the_basis_labels_name_the_current_eir_of_a_floating_rate_loan(): void
    {
        $this->assertStringContainsString('current for a floating-rate loan', BookedEcl::LABELS['discounted']);
        $this->assertStringNotContainsString('discounted at the original EIR', BookedEcl::LABELS['discounted']);
        $summary = BookedEcl::summary('2026-09');
        $this->assertStringContainsString('original or, for a floating-rate loan, current', (string) $summary['message']);
    }
}
