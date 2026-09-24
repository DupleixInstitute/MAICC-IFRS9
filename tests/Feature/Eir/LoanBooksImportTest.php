<?php

namespace Tests\Feature\Eir;

use App\Imports\LoanBooksImport;
use App\Models\Import;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The loan book importer's EIR columns: the report's cumulative Repayments
 * counter, which the engine reads cash from (spec v3 decision D14), and the
 * undrawn commitment as approved less disbursed (spec v3 section 6.3).
 */
class LoanBooksImportTest extends TestCase
{
    protected $seed = false;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
        DB::purge('sqlite'); DB::reconnect('sqlite');

        // The importer opens its exception file under storage_path(); keep
        // that out of the repository's storage directory.
        $this->storage = sys_get_temp_dir() . '/maiic-loan-books-' . uniqid();
        File::makeDirectory($this->storage, 0755, true);
        $this->app->useStoragePath($this->storage);

        Schema::create('imports', function (Blueprint $t) { $t->increments('id'); $t->string('name')->nullable(); $t->string('status')->default('pending'); $t->integer('records')->default(0); $t->integer('failed_records')->default(0); $t->text('settings')->nullable(); $t->string('started_at')->nullable(); $t->string('completed_at')->nullable(); $t->timestamps(); });
        Schema::create('clients', function (Blueprint $t) { $t->increments('id'); $t->string('customer_id'); $t->string('name')->nullable(); $t->string('deleted_at')->nullable(); $t->timestamps(); });
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id'); $t->integer('customer_id'); $t->string('customer_name')->nullable(); $t->integer('loan_portfolio_id');
            $t->string('reporting_period'); $t->integer('reporting_year'); $t->integer('reporting_month'); $t->string('contract_id');
            $t->string('industry_code')->nullable(); $t->string('industry_type')->nullable(); $t->string('internal_grade_code')->nullable(); $t->string('product_group')->nullable();
            $t->string('create_date')->nullable(); $t->string('due_date')->nullable(); $t->string('tenor')->nullable(); $t->double('interest_rate')->default(0); $t->double('remaining_tenor')->default(0);
            $t->double('principal_balance')->default(0); $t->double('approved_amount')->default(0); $t->double('disbursed')->default(0); $t->double('repayments')->default(0);
            $t->double('commitments')->default(0); $t->double('carrying_amount')->default(0);
            $t->string('ifrs9stage_pre_qualitative')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable();
            $t->double('arrears_1_to_30')->nullable(); $t->double('arrears_30_to_90')->nullable(); $t->double('arrears_91_to_180')->nullable(); $t->double('arrears_180_to_270')->nullable();
            $t->timestamps();
            $t->unique(['customer_id', 'loan_portfolio_id', 'reporting_period', 'contract_id']);
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    /** One row as the Loan Book Report (Menu ID 3868) spells its headings. */
    private function reportRow(array $overrides = []): array
    {
        return $overrides + [
            'Customer ID' => '93',
            'Contract ID' => '104450000053',
            'Name' => 'Acme Farms',
            'Value Date' => '15/01/2025',
            'Maturity Date' => '15/01/2027',
            'Principal' => '1,000,000.00',
            'Interest Rate' => '24',
            'Approved' => '1,500,000.00',
            'Disbursed' => '1,000,000.00',
            'Repayments' => '250,000.00',
            'Carrying Amount' => '800,000.00',
        ];
    }

    private function importRows(string $importType, array $rows, array $mapping = []): void
    {
        $import = Import::create(['name' => 'Loan book ' . $importType, 'status' => 'pending']);
        $importer = new LoanBooksImport($import, $mapping + ['reporting_period' => '2025-06', 'loan_portfolio_id' => 1], $importType);
        $importer->collection(collect(array_map(fn ($row) => collect($row), $rows)));
    }

    public function test_the_legacy_header_set_populates_repayments_and_the_undrawn_commitment(): void
    {
        $this->importRows('legacy', [$this->reportRow()]);

        $loan = DB::table('loan_books')->where('contract_id', '104450000053')->first();
        $this->assertNotNull($loan);
        $this->assertEqualsWithDelta(250_000, $loan->repayments, 0.001);
        $this->assertEqualsWithDelta(1_500_000, $loan->approved_amount, 0.001);
        $this->assertEqualsWithDelta(1_000_000, $loan->disbursed, 0.001);
        $this->assertEqualsWithDelta(500_000, $loan->commitments, 0.001);
        $this->assertSame(0, (int) Import::first()->failed_records);
    }

    public function test_the_default_header_set_populates_the_same_columns(): void
    {
        $this->importRows('custom', [$this->reportRow(['Approved Amount' => '1,200,000.00', 'Approved' => null])]);

        $loan = DB::table('loan_books')->where('contract_id', '104450000053')->first();
        $this->assertEqualsWithDelta(250_000, $loan->repayments, 0.001);
        $this->assertEqualsWithDelta(1_200_000, $loan->approved_amount, 0.001);
        $this->assertEqualsWithDelta(200_000, $loan->commitments, 0.001);
    }

    public function test_a_re_import_refreshes_the_counter_and_a_fully_drawn_facility_has_no_commitment(): void
    {
        $this->importRows('legacy', [$this->reportRow()]);
        $this->importRows('legacy', [$this->reportRow(['Repayments' => '300,000.00', 'Disbursed' => '1,500,000.00'])]);

        $this->assertSame(1, DB::table('loan_books')->count());
        $loan = DB::table('loan_books')->first();
        $this->assertEqualsWithDelta(300_000, $loan->repayments, 0.001);
        $this->assertEqualsWithDelta(0, $loan->commitments, 0.001);

        // No approved figure at all: the commitment is left at zero rather than invented.
        $this->importRows('legacy', [$this->reportRow(['Contract ID' => '104450000099', 'Approved' => ' -   '])]);
        $this->assertEqualsWithDelta(0, DB::table('loan_books')->where('contract_id', '104450000099')->value('commitments'), 0.001);
    }
}
