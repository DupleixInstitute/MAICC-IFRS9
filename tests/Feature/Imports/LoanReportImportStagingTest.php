<?php

namespace Tests\Feature\Imports;

use App\Exceptions\StagingRuleMissingException;
use App\Jobs\ProcessLoanImportJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Documentation audit of 14 October 2026, item 16: the E-Banker loan book
 * report import (ProcessLoanImportJob) stages by the approved staging
 * thresholds in force at the period end (StagingThreshold::forFacility through
 * StagingClassifier), by facility class and term, and fails closed with no
 * rule in force. It had staged with a ladder written in code (Stage 2 from
 * 31 days, Stage 3 from 181), so a short-term loan 91 days late stayed in
 * Stage 2.
 */
class LoanReportImportStagingTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('loan_portfolios', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->boolean('active')->default(true); $t->timestamps(); });
        Schema::create('clients', function (Blueprint $t) { $t->increments('id'); $t->string('customer_id')->nullable(); $t->string('name')->nullable(); $t->timestamps(); $t->softDeletes(); });
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('customer_id')->nullable(); $t->string('customer_name')->nullable();
            $t->string('product_group')->nullable(); $t->string('product_code')->nullable(); $t->integer('loan_portfolio_id')->nullable(); $t->integer('reporting_year')->nullable(); $t->integer('reporting_month')->nullable();
            $t->date('create_date')->nullable(); $t->date('due_date')->nullable(); $t->double('interest_rate')->nullable(); $t->double('remaining_tenor')->nullable();
            $t->double('principal_balance')->nullable(); $t->double('disbursed')->nullable(); $t->double('carrying_amount')->nullable();
            $t->double('arrears_1_to_30')->nullable(); $t->double('arrears_30_to_90')->nullable(); $t->double('arrears_91_to_180')->nullable(); $t->double('arrears_180_to_270')->nullable();
            $t->string('ifrs9stage_pre_qualitative')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->timestamps();
            $t->unique(['contract_id', 'reporting_period']);
        });
        Schema::create('staging_thresholds', function (Blueprint $t) {
            $t->increments('id'); $t->string('facility_class'); $t->integer('min_tenor_months'); $t->integer('stage2_dpd'); $t->integer('stage3_dpd'); $t->text('rebuttal_basis')->nullable();
            $t->date('effective_from'); $t->string('status')->default('APPROVED'); $t->integer('proposed_by')->nullable(); $t->integer('approved_by')->nullable(); $t->timestamp('approved_at')->nullable(); $t->string('reason')->nullable(); $t->timestamps();
        });
        DB::table('loan_portfolios')->insert(['id' => 1, 'name' => 'MAIIC Agricultural']);
    }

    /** A Format 2 report row: serial, customer, -, contract, name, start, maturity, ..., the arrears buckets at 24, 25, 26 and 28. */
    private function row(int $serial, string $contract, string $start, string $due, array $buckets): array
    {
        $r = array_fill(0, 30, '');
        [$r[0], $r[1], $r[3], $r[4], $r[5], $r[6]] = [(string) $serial, 'CUST' . $serial, $contract, 'Customer ' . $serial, $start, $due];
        $r[13] = '1000'; $r[19] = '1000';
        foreach ($buckets as $i => $v) {
            $r[$i] = (string) $v;
        }

        return $r;
    }

    private function import(array $rows): void
    {
        $rel = 'test-import-' . uniqid() . '.csv';
        $f = fopen(storage_path('app/' . $rel), 'w');
        fputcsv($f, ['Loan book report']);
        foreach ($rows as $r) {
            fputcsv($f, $r);
        }
        fclose($f);
        (new ProcessLoanImportJob($rel, 'report.csv', '2026-09', 1))->handle();
    }

    private function stage(string $contract): ?string
    {
        return DB::table('loan_books')->where('contract_id', $contract)->value('ifrs9stage_post_qualitative');
    }

    public function test_the_import_stages_by_the_approved_thresholds_by_class_and_term(): void
    {
        DB::table('staging_thresholds')->insert([
            ['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 91, 'effective_from' => '2018-07-13', 'status' => 'APPROVED'],
            ['facility_class' => 'DEFAULT', 'min_tenor_months' => 13, 'stage2_dpd' => 31, 'stage3_dpd' => 181, 'effective_from' => '2018-07-13', 'status' => 'APPROVED'],
            // a proposal waits for a second person and governs nothing
            ['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 300, 'effective_from' => '2026-01-01', 'status' => 'PROPOSED'],
        ]);

        $this->import([
            $this->row(1, '104430000001', '01/01/2026', '01/01/2027', [26 => 500]),   // 12 months, 91 days late: Stage 3 (the old ladder said 2)
            $this->row(2, '104430000002', '01/01/2025', '01/01/2028', [26 => 500]),   // 36 months, 91 days late: Stage 2
            $this->row(3, '104430000003', '', '', [25 => 500]),                        // in arrears with no term: not guessed
            $this->row(4, '104430000004', '01/01/2025', '01/01/2028', []),            // current: Stage 1
        ]);

        $this->assertSame('3', $this->stage('104430000001'));
        $this->assertSame('2', $this->stage('104430000002'));
        $this->assertNull($this->stage('104430000003'));
        $this->assertSame('1', $this->stage('104430000004'));
        $this->assertStringNotContainsString('classifyIFRS9Stage', file_get_contents(app_path('Jobs/ProcessLoanImportJob.php')));
    }

    public function test_with_no_approved_rule_in_force_the_import_fails_and_names_it(): void
    {
        DB::table('staging_thresholds')->insert(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 91, 'effective_from' => '2027-01-01', 'status' => 'APPROVED']);

        $this->expectException(StagingRuleMissingException::class);
        $this->import([$this->row(1, '104430000001', '01/01/2026', '01/01/2027', [26 => 500])]);
    }

    /** Item 17: the unrouted legacy store() and its ladder (finance_stageing_rules, defaults 30 and 90) are gone. */
    public function test_the_legacy_loan_book_staging_is_removed(): void
    {
        foreach (['store', 'calculateIfrs9Stage', 'calculateExpectedLossProvision'] as $method) {
            $this->assertFalse(method_exists(\App\Http\Controllers\LoanBookController::class, $method), $method);
        }
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            $this->assertNotSame('App\Http\Controllers\LoanBookController@store', $route->getActionName());
        }
    }
}
