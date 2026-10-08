<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\LoanBookBuildService;
use App\Services\Eir\GovernanceService;
use Database\Seeders\EbankerQuerySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The build of spec v4 sections 6.2 and 6.6 on a small landing zone: two
 * accounts, a ledger over two month-ends, a stored run for the second
 * month only. Method B derives the carrying amount from the ledger and
 * keeps the stored figure beside it; method A copies the stored run and
 * falls back to the derivation, marked, where no run exists; a rebuild
 * prints its differences; the ECL columns are untouched; a locked period
 * is refused; maker-checker holds.
 */
class LoanBookBuildServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        Schema::create('loan_portfolios', function (Blueprint $t) { $t->increments('id'); $t->string('name'); });
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            foreach (['contract_id', 'customer_id', 'customer_name', 'external_identity_id', 'product_group', 'product_code', 'funding_source', 'reporting_period', 'overdue_status', 'contract_status', 'industry_code', 'industry_type', 'arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180', 'arrears_180_to_270', 'arrears_271_to_360', 'build_source_key', 'build_flag', 'ifrs9stage_post_qualitative'] as $c) { $t->string($c)->nullable(); }
            foreach (['loan_portfolio_id', 'reporting_year', 'reporting_month', 'tenor', 'overdue_days', 'is_month_end', 'build_load_id', 'ifrs9_stage'] as $c) { $t->integer($c)->nullable(); }
            foreach (['remaining_tenor', 'interest_rate', 'principal_balance', 'approved_amount', 'disbursed', 'repayments', 'carrying_amount', 'interest_to_date', 'stored_carrying_amount', 'commitments', 'facility_utilisation_rate', 'ead', 'ecl_value', 'pd_post_fli'] as $c) { $t->decimal($c, 20, 4)->nullable(); }
            $t->date('create_date')->nullable(); $t->date('due_date')->nullable(); $t->date('overdue_principal_date')->nullable();
            $t->string('build_method', 1)->nullable(); $t->text('build_basis')->nullable(); $t->timestamps();
            $t->unique(['contract_id', 'reporting_period']);
        });
        Schema::create('loan_book_builds', function (Blueprint $t) { $t->increments('id'); $t->string('method', 1); $t->string('period_from'); $t->string('period_to'); $t->string('status'); $t->integer('requested_by')->nullable(); $t->integer('approved_by')->nullable(); $t->string('approver_label')->nullable(); $t->timestamp('approved_at')->nullable(); $t->timestamp('built_at')->nullable(); $t->text('pack_loads')->nullable(); $t->text('result')->nullable(); $t->text('note')->nullable(); $t->timestamps(); });
        Schema::create('reporting_period_locks', function (Blueprint $t) { $t->increments('id'); $t->string('reporting_period')->unique(); $t->integer('locked_by')->nullable(); $t->timestamp('locked_at'); $t->string('reason'); $t->text('settings_snapshot')->nullable(); $t->timestamps(); });
        $migration = require base_path('database/migrations/2026_10_08_000000_create_ebanker_landing_zone.php');
        $migration->up();
        (new EbankerQuerySeeder())->run();
        DB::table('users')->insert([['id' => 1, 'name' => 'Maker', 'created_at' => now(), 'updated_at' => now()], ['id' => 2, 'name' => 'Checker', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('loan_portfolios')->insert(['id' => 1, 'name' => 'Loans']);
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('a', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        $this->land();
    }

    private function raw(string $q, string $key, ?string $account, ?string $date, array $payload): void
    {
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => $q, 'source_key' => $key, 'account' => $account, 'row_date' => $date,
            'payload' => json_encode($payload), 'row_hash' => md5($key . $q), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Account A: 10m disbursed July, 1m interest August, 3m receipt August. Account B: closed before the first month-end. */
    private function land(): void
    {
        $a = '000104420000005'; $b = '000104420000009';
        $this->raw('P1_02', $a, $a, '2024-07-01', ['NEW_AC_NUMBER' => $a, 'GLCODE' => '1050101', 'CUSTOMER_ID' => '310', 'ACCOUNT_NAME' => 'Micholess Creamery', 'STATUS_CODE' => 'A', 'INTEREST_RATE' => '20.20', 'ACCOUNT_OPEN_DATE' => '7/1/2024']);
        $this->raw('P1_02', $b, $b, '2024-07-01', ['NEW_AC_NUMBER' => $b, 'GLCODE' => '1050102', 'CUSTOMER_ID' => '311', 'ACCOUNT_NAME' => 'Closed Ltd', 'STATUS_CODE' => 'C', 'INTEREST_RATE' => '25.00', 'ACCOUNT_OPEN_DATE' => '7/1/2024']);
        $this->raw('P1_03', $a, $a, '2024-07-01', ['NEW_AC_NUMBER' => $a, 'SANCTION_AMOUNT' => '12000000.00', 'PERIOD_YEARS' => '2', 'PERIOD_MONTHS' => '0', 'EXPIRY_DATE' => '7/1/2026', 'INDUSTRY_CODE' => '113']);
        $this->raw('P1_01', '100', $a, '2024-07-31', ['CUMVOUCH_DET_ID' => '100', 'NEW_AC_NUMBER' => $a, 'TRANSACTION_DATE' => '7/31/2024', 'TRANSAMT' => '-10000000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N']);
        $this->raw('P1_01', '101', $b, '2024-07-15', ['CUMVOUCH_DET_ID' => '101', 'NEW_AC_NUMBER' => $b, 'TRANSACTION_DATE' => '7/15/2024', 'TRANSAMT' => '-500000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N']);
        $this->raw('P1_01', '102', $b, '2024-07-20', ['CUMVOUCH_DET_ID' => '102', 'NEW_AC_NUMBER' => $b, 'TRANSACTION_DATE' => '7/20/2024', 'TRANSAMT' => '500000.00', 'TRANTYPE' => '305', 'DELETE_FLAG' => 'N']);
        $this->raw('P1_01', '103', $a, '2024-08-31', ['CUMVOUCH_DET_ID' => '103', 'NEW_AC_NUMBER' => $a, 'TRANSACTION_DATE' => '8/31/2024', 'TRANSAMT' => '-1000000.00', 'TRANTYPE' => '303', 'DELETE_FLAG' => 'N']);
        $this->raw('P1_01', '104', $a, '2024-08-31', ['CUMVOUCH_DET_ID' => '104', 'NEW_AC_NUMBER' => $a, 'TRANSACTION_DATE' => '8/31/2024', 'TRANSAMT' => '3000000.00', 'TRANTYPE' => '305', 'DELETE_FLAG' => 'N']);
        $this->raw('P1_01', '105', $a, '2024-08-31', ['CUMVOUCH_DET_ID' => '105', 'NEW_AC_NUMBER' => $a, 'TRANSACTION_DATE' => '8/31/2024', 'TRANSAMT' => '-250000.00', 'TRANTYPE' => '303', 'DELETE_FLAG' => 'Y']); // deleted: ignored
        // the stored run for August only; its carrying amount is 1.00 off the ledger on purpose
        $this->raw('P2_08', '5000', $a, '2024-08-31', ['LOAN_BOOK_DET_ID_A' => '5000', 'ASONDATE' => '8/31/2024', 'TRANSACTION_DATE' => '9/2/2024', 'NEW_AC_NUMBER' => $a, 'CUSTOMER_ID' => '310', 'GLCODE' => '1050101',
            'VALUE_DATE' => '7/31/2024', 'MATURITY_DATE' => '7/31/2026', 'TENOR_YRS' => '2', 'INTEREST_RATE' => '21.500000', 'APPROVED' => '12000000.00', 'DISBURSD' => '10000000.00', 'NOT_YET_DISBURS' => '2000000.00',
            'PRINCIPAL' => '7500000.00', 'INTEREST_TO_DATE' => '1000000.00', 'REPAYMENT' => '3000000.00', 'CARRYING_AMOUNT' => '8000001.00', 'ARREAS_TOTAL' => '500000.00',
            'DAY_1_30' => '0.00', 'DAY_31_91' => '500000.00', 'DAY_91_180' => '0.00', 'DAY_181_270' => '0.00', 'DAY_271_360' => '0.00', 'OVERDUE_PRINCI_DATE' => '7/1/2024', 'OVERDUE_PERIOD' => '2.00', 'INDUSTRY_CODE' => '113', 'IND_DESCR' => 'Agriculture', 'SEG_MENT' => '325']);
        $this->raw('P2_08', '4000', $a, '2024-08-31', ['LOAN_BOOK_DET_ID_A' => '4000', 'ASONDATE' => '8/31/2024', 'NEW_AC_NUMBER' => $a, 'GLCODE' => '1050101', 'CARRYING_AMOUNT' => '1.00']); // an earlier run, superseded by 5000
    }

    private function service(): LoanBookBuildService
    {
        return new LoanBookBuildService(new LandingZoneReader(), new GovernanceService());
    }

    public function test_method_b_derives_the_carrying_amount_from_the_ledger_and_keeps_the_stored_figure_beside_it(): void
    {
        $r = $this->service()->build('2024-07', '2024-08', 'B', 1, 2);
        $this->assertSame('BUILT', $r['status']);
        $this->assertSame(['2024-07' => 1, '2024-08' => 1], array_map(fn ($p) => $p['rows'], $r['periods']));
        $jul = DB::table('loan_books')->where('reporting_period', '2024-07')->first();
        $this->assertSame('104420000005', $jul->contract_id);
        $this->assertSame('000104420000005', $jul->external_identity_id);
        $this->assertEquals(10000000, $jul->carrying_amount);
        $this->assertEquals(10000000, $jul->disbursed);
        $this->assertNull($jul->stored_carrying_amount);
        $this->assertSame('B', $jul->build_method);
        $this->assertNull($jul->build_flag);
        $this->assertSame('MAIIC Agricultural Loans', $jul->product_group);
        $this->assertEquals(20.20, $jul->interest_rate); // no run, no set-up: the account master
        $aug = DB::table('loan_books')->where('reporting_period', '2024-08')->first();
        $this->assertEquals(8000000, $aug->carrying_amount);   // 10m + 1m interest - 3m receipt; the deleted row ignored
        $this->assertEquals(1000000, $aug->interest_to_date);
        $this->assertEquals(3000000, $aug->repayments);
        $this->assertEquals(8000001, $aug->stored_carrying_amount);
        $this->assertStringContainsString('differs from the stored run by -1.00', $aug->build_flag);
        $this->assertEquals(21.5, $aug->interest_rate);        // the rate charged, from the run
        $this->assertEquals(500000, $aug->arrears_30_to_90);
        $this->assertSame(61, (int) $aug->overdue_days);        // 1 Jul to 31 Aug under the directive basis
        $this->assertSame('2024-07-01', $aug->overdue_principal_date);
        $this->assertSame(24, (int) $aug->tenor);
        $this->assertEquals(2000000, $aug->commitments);
        $this->assertEquals(10000000, $aug->ead);
        $basis = json_decode($aug->build_basis, true);
        $this->assertSame('P2_08:5000', $basis['stored_run']);
        $this->assertSame(3, $basis['postings']); // the deleted row is not a posting
        $this->assertSame('BUILT', DB::table('loan_book_builds')->first()->status);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Loan Book Built')->count());
    }

    public function test_method_a_copies_the_stored_run_and_derives_marked_where_none_exists(): void
    {
        $this->service()->build('2024-07', '2024-08', 'A', 1, 2);
        $jul = DB::table('loan_books')->where('reporting_period', '2024-07')->first();
        $this->assertSame('A', $jul->build_method);
        $this->assertStringContainsString('derived: no stored run', $jul->build_flag);
        $this->assertEquals(10000000, $jul->carrying_amount);
        $aug = DB::table('loan_books')->where('reporting_period', '2024-08')->first();
        $this->assertSame('A', $aug->build_method);
        $this->assertEquals(8000001, $aug->carrying_amount);   // the report's own figure
        $this->assertEquals(7500000, $aug->principal_balance);
        $this->assertSame('5000', $aug->build_source_key);     // the latest run, not 4000
        $this->assertNull($aug->build_flag);
    }

    public function test_a_rebuild_prints_its_differences_and_leaves_the_ecl_columns_alone(): void
    {
        $this->service()->build('2024-08', '2024-08', 'A', 1, 2);
        DB::table('loan_books')->where('reporting_period', '2024-08')->update(['ecl_value' => 123456.78, 'ifrs9_stage' => 2, 'pd_post_fli' => 0.1234]);
        $preview = $this->service()->build('2024-08', '2024-08', 'B', 1, null, null, true);
        $this->assertSame('DRY_RUN', $preview['status']);
        $this->assertSame(1, $preview['periods']['2024-08']['changed']);
        $this->assertSame(8000001.0, $preview['periods']['2024-08']['differences'][0]['was']);
        $this->assertSame(8000000.0, $preview['periods']['2024-08']['differences'][0]['now']);
        $this->assertSame('A', DB::table('loan_books')->where('reporting_period', '2024-08')->value('build_method')); // dry run wrote nothing
        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $row = DB::table('loan_books')->where('reporting_period', '2024-08')->first();
        $this->assertSame('B', $row->build_method);
        $this->assertEquals(123456.78, $row->ecl_value);
        $this->assertSame(2, (int) $row->ifrs9_stage);
        $this->assertEquals(0.1234, $row->pd_post_fli);
        $this->assertSame(1, DB::table('loan_books')->where('reporting_period', '2024-08')->count());
    }

    public function test_without_an_approver_the_build_is_proposed_and_nothing_is_written(): void
    {
        $r = $this->service()->build('2024-08', '2024-08', 'B', 1);
        $this->assertSame('PROPOSED', $r['status']);
        $this->assertSame(0, DB::table('loan_books')->count());
        $this->assertSame('PROPOSED', DB::table('loan_book_builds')->first()->status);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('different person');
        $this->service()->approve($r['build_id'], 1);
    }

    public function test_a_second_person_approves_a_proposed_build(): void
    {
        $r = $this->service()->build('2024-08', '2024-08', 'B', 1);
        $done = $this->service()->approve($r['build_id'], 2);
        $this->assertSame('BUILT', $done['status']);
        $this->assertSame(1, DB::table('loan_books')->count());
        $this->assertSame('REJECTED', DB::table('loan_book_builds')->where('id', $r['build_id'])->value('status'));
        $this->assertSame(2, (int) DB::table('loan_book_builds')->where('id', $done['build_id'])->value('approved_by'));
    }

    public function test_the_same_person_cannot_request_and_approve(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service()->build('2024-08', '2024-08', 'B', 1, 1);
    }

    public function test_a_locked_period_is_never_restated(): void
    {
        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        DB::table('reporting_period_locks')->insert(['reporting_period' => '2024-08', 'locked_at' => now(), 'reason' => 'audited', 'created_at' => now(), 'updated_at' => now()]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Locked period');
        $this->service()->build('2024-07', '2024-08', 'B', 1, 2);
    }

    public function test_a_month_after_the_last_posting_is_refused_never_estimated(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('refused, never estimated');
        $this->service()->build('2024-09', '2024-09', 'B', 1, 2);
    }

    public function test_retire_stale_removes_old_test_rows_and_keeps_out_of_scope_rows(): void
    {
        $base = ['reporting_period' => '2024-08', 'customer_id' => '0', 'reporting_year' => 2024, 'reporting_month' => 8, 'carrying_amount' => 1, 'created_at' => now(), 'updated_at' => now()];
        DB::table('loan_books')->insert([
            ['contract_id' => '1', 'product_code' => null, 'customer_name' => 'old test import'] + $base,
            ['contract_id' => '104480000001', 'product_code' => '1050301', 'customer_name' => 'Mega Farm borrower'] + $base,
            ['contract_id' => '104420000099', 'product_code' => '1050101', 'customer_name' => 'in-scope GL, not in the pack'] + $base,
        ]);
        $preview = $this->service()->build('2024-08', '2024-08', 'B', 1, null, null, true, true);
        $this->assertEqualsCanonicalizing(['1', '104420000099'], $preview['periods']['2024-08']['stale']);
        $this->assertSame(3, DB::table('loan_books')->whereNull('build_method')->count()); // dry run
        $r = $this->service()->build('2024-08', '2024-08', 'B', 1, 2, null, false, true);
        $this->assertSame(2, $r['periods']['2024-08']['retired']);
        $this->assertSame(['104420000005', '104480000001'], DB::table('loan_books')->orderBy('contract_id')->pluck('contract_id')->all());
        $without = $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $this->assertSame(0, $without['periods']['2024-08']['retired']); // not asked: nothing removed
    }

    public function test_the_bootstrap_label_approves_without_a_second_person(): void
    {
        $r = $this->service()->build('2024-08', '2024-08', null, null, null, LoanBookBuildService::BOOTSTRAP_LABEL);
        $this->assertSame('BUILT', $r['status']);
        $this->assertSame('B', $r['periods']['2024-08']['method']); // no governance table: the recommended method
        $this->assertSame(LoanBookBuildService::BOOTSTRAP_LABEL, DB::table('loan_book_builds')->first()->approver_label);
    }
}
