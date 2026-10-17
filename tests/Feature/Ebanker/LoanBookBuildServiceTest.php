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
            $t->integer('staging_dpd')->nullable(); $t->string('staging_dpd_basis')->nullable();
            $t->unique(['contract_id', 'reporting_period']);
        });
        Schema::create('loan_book_builds', function (Blueprint $t) { $t->increments('id'); $t->string('method', 1); $t->string('period_from'); $t->string('period_to'); $t->string('status'); $t->integer('requested_by')->nullable(); $t->integer('approved_by')->nullable(); $t->string('approver_label')->nullable(); $t->timestamp('approved_at')->nullable(); $t->timestamp('built_at')->nullable(); $t->text('pack_loads')->nullable(); $t->text('result')->nullable(); $t->text('note')->nullable(); $t->timestamps(); });
        Schema::create('reporting_period_locks', function (Blueprint $t) { $t->increments('id'); $t->string('reporting_period')->unique(); $t->integer('locked_by')->nullable(); $t->timestamp('locked_at'); $t->string('reason'); $t->text('settings_snapshot')->nullable(); $t->timestamps(); });
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        (new EbankerQuerySeeder())->run();
        // the build reads its method and the staging policy (for the staging day count) from the Governance Centre with no value in code
        Schema::create('governance_settings', function (Blueprint $t) { $t->increments('id'); $t->string('key', 60); $t->string('value'); $t->text('options'); $t->string('label'); $t->text('description'); $t->string('effective_from'); $t->integer('set_by')->nullable(); $t->integer('approved_by')->nullable(); $t->string('approved_at')->nullable(); $t->string('reason', 500)->nullable(); $t->string('status', 20)->default('PROPOSED'); $t->timestamps(); });
        foreach (array_merge(['loan_book_build_method', \App\Services\Eir\StagingPolicy::PRESET], array_keys(\App\Services\Eir\StagingPolicy::SETTINGS)) as $key) {
            $def = GovernanceService::catalogue()[$key];
            DB::table('governance_settings')->insert(['key' => $key, 'value' => $def['default'], 'options' => json_encode($def['options']), 'label' => $def['label'], 'description' => '', 'effective_from' => '1900-01-01', 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
        }
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

    public function test_a_mega_farm_account_keeps_the_reported_carrying_amount_and_its_ledger_gap(): void
    {
        $m = '000104480000001';
        $this->raw('MF_01', $m, $m, '2024-07-01', ['NEW_AC_NUMBER' => $m, 'GLCODE' => '1050301', 'CUSTOMER_ID' => '734', 'ACCOUNT_NAME' => 'Mega Farm Grower', 'STATUS_CODE' => 'A', 'INTEREST_RATE' => '10.00', 'ACCOUNT_OPEN_DATE' => '7/1/2024']);
        $this->raw('MF_03', '900', $m, '2024-07-31', ['CUMVOUCH_DET_ID' => '900', 'NEW_AC_NUMBER' => $m, 'AC_GLCODE' => '1050301', 'TRANSACTION_DATE' => '7/31/2024', 'TRANSAMT' => '-5000000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N']);
        $this->raw('MF_05', '7000', $m, '2024-08-31', ['LOAN_BOOK_DET_ID_A' => '7000', 'ASONDATE' => '8/31/2024', 'NEW_AC_NUMBER' => $m, 'GLCODE' => '1050301', 'PRINCIPAL' => '5200000.00', 'CARRYING_AMOUNT' => '5200000.00']);

        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $row = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104480000001')->first();
        $this->assertNotNull($row);
        $this->assertEquals(5200000, $row->carrying_amount);       // E-Banker's report stands for a Mega Farm account
        $this->assertEquals(5200000, $row->stored_carrying_amount);
        $this->assertStringContainsString('Mega Farm: carrying amount from the Loan Book Report; the MF_03 ledger with its government-portion legs derives 5,000,000.00 (difference -200,000.00)', $row->build_flag);
        $this->assertEquals(5000000, json_decode($row->build_basis, true)['ledger_carrying']);
        // the in-scope loan is still derived from its ledger
        $this->assertEquals(8000000, DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104420000005')->value('carrying_amount'));
        // and the EIR's readers do not see the Mega Farm ledger
        $this->assertArrayNotHasKey($m, (new LandingZoneReader())->ledgerByAccount('2024-08-31'));
    }

    /**
     * The Mega Farm interest batch as E-Banker posts it (MF_03, pack 4): each month 33 percent to the
     * loan's own interest-receivable GL (type 303), 67 percent to the government-portion GL (type 400)
     * and that leg's contra to the fund (type 401 on 2071), every leg on the loan's AC_GLCODE. The
     * contra is the fund's side, not the loan's: left out, the ledger rebuilds the Loan Book Report
     * to the cent, the derived figure stands with no flag, and the split is kept per account-month.
     */
    private function megaFarmBatch(string $m, string $key, string $date, string $batch, string $own, string $gov): void
    {
        $iso = \Carbon\CarbonImmutable::createFromFormat('!n/j/Y', $date)->toDateString();
        $leg = ['NEW_AC_NUMBER' => $m, 'AC_GLCODE' => '1050301', 'TRANSACTION_DATE' => $date, 'BATCH_TYPE' => 'INTP', 'BATCH_NUMBER' => $batch, 'DELETE_FLAG' => 'N'];
        $this->raw('MF_03', $key . '1', $m, $iso, ['CUMVOUCH_DET_ID' => $key . '1', 'CB_GLCODE' => '105031', 'TRANSAMT' => '-' . $own, 'TRANTYPE' => '303', 'PARTICULARS' => 'BANK_INT  33% 1050301 ' . $m] + $leg);
        $this->raw('MF_03', $key . '2', $m, $iso, ['CUMVOUCH_DET_ID' => $key . '2', 'CB_GLCODE' => '1060', 'TRANSAMT' => '-' . $gov, 'TRANTYPE' => '400', 'PARTICULARS' => 'GOVT_INT 67% Int Ac: 1050301 ' . $m] + $leg);
        $this->raw('MF_03', $key . '3', $m, $iso, ['CUMVOUCH_DET_ID' => $key . '3', 'CB_GLCODE' => '2071', 'TRANSAMT' => $gov, 'TRANTYPE' => '401', 'PARTICULARS' => 'GOVT_INT 67%  1050301 ' . $m] + $leg);
    }

    public function test_a_mega_farm_account_is_derived_from_its_ledger_with_the_government_portion_legs(): void
    {
        $m = '000104480000001';
        $this->raw('MF_01', $m, $m, '2024-07-01', ['NEW_AC_NUMBER' => $m, 'GLCODE' => '1050301', 'CUSTOMER_ID' => '734', 'ACCOUNT_NAME' => 'Mega Farm Grower', 'STATUS_CODE' => 'A', 'INTEREST_RATE' => '15.00', 'ACCOUNT_OPEN_DATE' => '7/1/2024']);
        $this->raw('MF_03', '900', $m, '2024-07-01', ['CUMVOUCH_DET_ID' => '900', 'NEW_AC_NUMBER' => $m, 'AC_GLCODE' => '1050301', 'CB_GLCODE' => '1050301', 'TRANSACTION_DATE' => '7/1/2024', 'TRANSAMT' => '-5000000.00', 'TRANTYPE' => '301', 'PARTICULARS' => 'Loan Disburse', 'DELETE_FLAG' => 'N']);
        $this->raw('MF_02', $m, $m, '2024-07-01', ['NEW_AC_NUMBER' => $m, 'SANCTION_AMOUNT' => '6000000.00', 'PERIOD_YEARS' => '1', 'PERIOD_MONTHS' => '0', 'EXPIRY_DATE' => '6/30/2025']);
        $this->megaFarmBatch($m, '91', '7/31/2024', '113', '20625.00', '41875.00');
        $this->megaFarmBatch($m, '92', '8/31/2024', '114', '20625.00', '41875.00');
        // E-Banker's report carries the full 15 percent: 5,000,000 + 2 x (20,625 + 41,875)
        $this->raw('MF_05', '7000', $m, '2024-08-31', ['LOAN_BOOK_DET_ID_A' => '7000', 'ASONDATE' => '8/31/2024', 'NEW_AC_NUMBER' => $m, 'GLCODE' => '1050301', 'PRINCIPAL' => '5000000.00', 'CARRYING_AMOUNT' => '5125000.00']);

        $this->service()->build('2024-07', '2024-08', 'B', 1, 2);
        $aug = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104480000001')->first();
        $this->assertEquals(5125000, $aug->carrying_amount);
        $this->assertEquals(5125000, $aug->stored_carrying_amount);
        $this->assertNull($aug->build_flag);                       // the ledger ties: no "report stands" flag
        $basis = json_decode($aug->build_basis, true);
        $this->assertEquals(5125000, $basis['ledger_carrying']);
        $mf = $basis['mega_farm'];
        $this->assertEquals(41250, $mf['interest_own_gl']);        // MAIIC's 33 percent, to date
        $this->assertEquals(83750, $mf['interest_government_portion']); // the fund's 67 percent, to date
        $this->assertEquals(125000, $mf['interest_total']);
        $this->assertEquals(['interest_own_gl' => 20625, 'interest_government_portion' => 41875, 'interest_total' => 62500], $mf['month']);
        $this->assertEquals(83750, $mf['government_portion_contra_excluded']);
        $this->assertSame(['1060'], $mf['government_portion_gls']); // read from the legs, not from a list
        $this->assertSame(['2071'], $mf['contra_gls']);
        $this->assertEquals(0, $basis['ledger']['settled_in_kind']); // the contra is not a settlement in kind
        // the 67 percent is interest, not a drawdown: the disbursed amount and the undrawn commitment do not move with it
        $this->assertEquals(5000000, $aug->disbursed);
        $this->assertEquals(125000, $aug->interest_to_date);       // both shares: the 15 percent the report carries
        $this->assertEquals(1000000, $aug->commitments);           // 6m sanctioned less 5m drawn
        $this->assertEquals(6125000, $aug->ead);
        $this->assertArrayNotHasKey('disbursed_includes_government_portion', $mf);
        // a month with no report run is the ledger with the government portion too
        $jul = DB::table('loan_books')->where('reporting_period', '2024-07')->where('contract_id', '104480000001')->first();
        $this->assertEquals(5062500, $jul->carrying_amount);
        $this->assertNull($jul->stored_carrying_amount);
        // the main-book account is untouched
        $main = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104420000005')->first();
        $this->assertEquals(8000000, $main->carrying_amount);
        $this->assertArrayNotHasKey('mega_farm', json_decode($main->build_basis, true));
    }

    /**
     * A 2024-season Mega Farm loan came into E-Banker at the migration as a type 900 "Opening Account
     * Balance" debit on 31 July 2024: drawn before the take-on, not a receipt, so its sanction is not
     * an undrawn commitment. A later type 900 debit is not a take-on and stays among the receipts.
     */
    public function test_the_opening_balance_taken_on_at_the_migration_counts_as_drawn(): void
    {
        $m = '000105450000001';
        $this->raw('MF_01', $m, $m, '2024-07-01', ['NEW_AC_NUMBER' => $m, 'GLCODE' => '1050305', 'CUSTOMER_ID' => '736', 'ACCOUNT_NAME' => 'Irrigation Grower', 'STATUS_CODE' => 'A', 'INTEREST_RATE' => '15.00', 'ACCOUNT_OPEN_DATE' => '7/1/2024']);
        $this->raw('MF_02', $m, $m, '2024-07-01', ['NEW_AC_NUMBER' => $m, 'SANCTION_AMOUNT' => '6000000.00', 'PERIOD_YEARS' => '5', 'PERIOD_MONTHS' => '0', 'EXPIRY_DATE' => '5/31/2029']);
        $this->raw('MF_03', '960', $m, '2024-07-31', ['CUMVOUCH_DET_ID' => '960', 'NEW_AC_NUMBER' => $m, 'AC_GLCODE' => '1050305', 'CB_GLCODE' => '1050305', 'TRANSACTION_DATE' => '7/31/2024', 'TRANSAMT' => '-5000000.00', 'TRANTYPE' => '900', 'DBCR' => 'DR', 'PARTICULARS' => 'To Trf Opening Account Balance', 'DELETE_FLAG' => 'N']);
        $this->raw('MF_03', '961', $m, '2024-08-20', ['CUMVOUCH_DET_ID' => '961', 'NEW_AC_NUMBER' => $m, 'AC_GLCODE' => '1050305', 'CB_GLCODE' => '1050305', 'TRANSACTION_DATE' => '8/20/2024', 'TRANSAMT' => '-100000.00', 'TRANTYPE' => '900', 'DBCR' => 'DR', 'PARTICULARS' => 'To Trf Returned Payment', 'DELETE_FLAG' => 'N']);

        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $row = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '105450000001')->first();
        $this->assertEquals(5100000, $row->carrying_amount);
        $this->assertEquals(5000000, $row->disbursed);              // the take-on balance was drawn
        $this->assertEquals(-100000, $row->repayments);             // the later type 900 stays a receipt, as before
        $this->assertEquals(1000000, $row->commitments);            // 6m sanctioned less 5m drawn, not the whole 6m
        $this->assertEquals(5000000, json_decode($row->build_basis, true)['takeon_drawn']);
        $this->assertArrayNotHasKey('takeon_drawn', json_decode(DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104420000005')->value('build_basis'), true));
    }

    /**
     * A facility past its expiry can no longer be drawn. Where the report leaves MATURITY_DATE empty
     * (the never-drawn Mega Farm facilities), the loan master's EXPIRY_DATE is the facility's end, so
     * the undrawn sanction is dropped as past maturity, with the source of the date on the row.
     */
    public function test_an_empty_report_maturity_takes_the_loan_master_expiry_and_an_expired_undrawn_sanction_is_no_commitment(): void
    {
        $m = '000104480003247';
        $this->raw('MF_01', $m, $m, '2025-08-27', ['NEW_AC_NUMBER' => $m, 'GLCODE' => '1050301', 'CUSTOMER_ID' => '737', 'ACCOUNT_NAME' => 'Never Drawn', 'STATUS_CODE' => 'A', 'INTEREST_RATE' => '15.00', 'ACCOUNT_OPEN_DATE' => '8/27/2025']);
        $this->raw('MF_02', $m, $m, '2025-06-28', ['NEW_AC_NUMBER' => $m, 'SANCTION_AMOUNT' => '19040000.00', 'PERIOD_YEARS' => '1', 'PERIOD_MONTHS' => '0', 'EXPIRY_DATE' => '8/15/2024']);
        $this->raw('MF_05', '7100', $m, '2024-08-31', ['LOAN_BOOK_DET_ID_A' => '7100', 'ASONDATE' => '8/31/2024', 'NEW_AC_NUMBER' => $m, 'GLCODE' => '1050301', 'MATURITY_DATE' => '', 'PRINCIPAL' => '0.00', 'CARRYING_AMOUNT' => '0.00']);

        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $row = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104480003247')->first();
        $this->assertSame('2024-08-15', $row->due_date);
        $this->assertEquals(0, $row->commitments);
        $this->assertEquals(0, $row->ead);
        $basis = json_decode($row->build_basis, true);
        $this->assertEquals(19040000, $basis['undrawn_after_maturity']);
        $this->assertSame("loan master EXPIRY_DATE: the report's MATURITY_DATE is empty", $basis['due_date_source']);
    }

    public function test_a_report_that_prints_a_mega_farm_credit_balance_as_a_debit_gives_way_to_the_ledger(): void
    {
        $m = '000104480002788';
        $this->raw('MF_01', $m, $m, '2024-07-01', ['NEW_AC_NUMBER' => $m, 'GLCODE' => '1050301', 'CUSTOMER_ID' => '735', 'ACCOUNT_NAME' => 'Overpaid Grower', 'STATUS_CODE' => 'A', 'INTEREST_RATE' => '15.00', 'ACCOUNT_OPEN_DATE' => '7/1/2024']);
        $this->raw('MF_03', '900', $m, '2024-07-01', ['CUMVOUCH_DET_ID' => '900', 'NEW_AC_NUMBER' => $m, 'AC_GLCODE' => '1050301', 'CB_GLCODE' => '1050301', 'TRANSACTION_DATE' => '7/1/2024', 'TRANSAMT' => '-5000000.00', 'TRANTYPE' => '301', 'PARTICULARS' => 'Loan Disburse', 'DELETE_FLAG' => 'N']);
        $this->raw('MF_03', '901', $m, '2024-08-15', ['CUMVOUCH_DET_ID' => '901', 'NEW_AC_NUMBER' => $m, 'AC_GLCODE' => '1050301', 'CB_GLCODE' => '1050301', 'TRANSACTION_DATE' => '8/15/2024', 'TRANSAMT' => '5500000.00', 'TRANTYPE' => '305', 'PARTICULARS' => 'By Trf Loan repayment', 'DELETE_FLAG' => 'N']);
        // the ledger is 500,000 in credit; the report prints it as a debit
        $this->raw('MF_05', '7000', $m, '2024-08-31', ['LOAN_BOOK_DET_ID_A' => '7000', 'ASONDATE' => '8/31/2024', 'NEW_AC_NUMBER' => $m, 'GLCODE' => '1050301', 'PRINCIPAL' => '5000000.00', 'CARRYING_AMOUNT' => '500000.00']);

        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $row = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104480002788')->first();
        $this->assertEquals(-500000, $row->carrying_amount);      // the ledger stands
        $this->assertEquals(500000, $row->stored_carrying_amount);
        $this->assertSame(LoanBookBuildService::MEGA_FARM_SIGN_FLAG, $row->build_flag);
        $basis = json_decode($row->build_basis, true);
        $this->assertEquals(['report_carrying' => 500000, 'ledger_carrying' => -500000], $basis['mega_farm']['report_sign_error']);
        $this->assertEquals(0.0, \App\Services\Ecl\EclExposure::of($row)); // a credit balance is no exposure
    }

    public function test_a_mega_farm_account_month_that_still_differs_keeps_the_report_and_an_unpaired_contra_is_flagged(): void
    {
        $m = '000104480000001';
        $this->raw('MF_01', $m, $m, '2024-07-01', ['NEW_AC_NUMBER' => $m, 'GLCODE' => '1050301', 'CUSTOMER_ID' => '734', 'ACCOUNT_NAME' => 'Mega Farm Grower', 'STATUS_CODE' => 'A', 'INTEREST_RATE' => '15.00', 'ACCOUNT_OPEN_DATE' => '7/1/2024']);
        $this->raw('MF_03', '900', $m, '2024-07-01', ['CUMVOUCH_DET_ID' => '900', 'NEW_AC_NUMBER' => $m, 'AC_GLCODE' => '1050301', 'CB_GLCODE' => '1050301', 'TRANSACTION_DATE' => '7/1/2024', 'TRANSAMT' => '-5000000.00', 'TRANTYPE' => '301', 'PARTICULARS' => 'Loan Disburse', 'DELETE_FLAG' => 'N']);
        $this->megaFarmBatch($m, '91', '8/31/2024', '114', '20625.00', '41875.00');
        // a contra in a batch of its own, with no 67 percent leg against it
        $this->raw('MF_03', '950', $m, '2024-08-31', ['CUMVOUCH_DET_ID' => '950', 'NEW_AC_NUMBER' => $m, 'AC_GLCODE' => '1050301', 'CB_GLCODE' => '2071', 'TRANSACTION_DATE' => '8/31/2024', 'BATCH_TYPE' => 'INTP', 'BATCH_NUMBER' => '115', 'TRANSAMT' => '100.00', 'TRANTYPE' => '401', 'PARTICULARS' => 'GOVT_INT 67%  1050301 ' . $m, 'DELETE_FLAG' => 'N']);
        $this->raw('MF_05', '7000', $m, '2024-08-31', ['LOAN_BOOK_DET_ID_A' => '7000', 'ASONDATE' => '8/31/2024', 'NEW_AC_NUMBER' => $m, 'GLCODE' => '1050301', 'PRINCIPAL' => '5000000.00', 'CARRYING_AMOUNT' => '5070000.00']);

        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $row = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104480000001')->first();
        $this->assertEquals(5070000, $row->carrying_amount);         // still 7,500 apart: the report stands
        $this->assertStringContainsString('Mega Farm: carrying amount from the Loan Book Report; the MF_03 ledger with its government-portion legs derives 5,062,500.00 (difference -7,500.00)', $row->build_flag);
        $this->assertStringContainsString('Mega Farm: 1 government-portion batch(es) whose 67% leg and its contra on the fund do not net to nil (first 2024-08-31|INTP|115)', $row->build_flag);
        $this->assertEquals(5062500, json_decode($row->build_basis, true)['ledger_carrying']);
    }

    /**
     * A loan GL outside MAIIC's own map takes the title the trial balance gives
     * it, so the Mega Farm schemes reach the book named as E-Banker names them
     * (and are staged, segmented and provided as the programme); with no title
     * held the GL code stands, never a guessed name.
     */
    public function test_a_mega_farm_gl_takes_its_trial_balance_title_as_the_product_group(): void
    {
        Schema::create('gl_trial_balance_lines', function (Blueprint $t) { $t->increments('id'); $t->string('period'); $t->string('gl_code'); $t->string('gl_title')->nullable(); });
        DB::table('gl_trial_balance_lines')->insert(['period' => '2024-08', 'gl_code' => '1050302', 'gl_title' => 'Mega Farm Seed Loans']);
        $m = '000104480000002'; $n = '000104480000003';
        $this->raw('MF_01', $m, $m, '2024-07-01', ['NEW_AC_NUMBER' => $m, 'GLCODE' => '1050302', 'CUSTOMER_ID' => '735', 'ACCOUNT_NAME' => 'Seed Grower', 'STATUS_CODE' => 'A', 'INTEREST_RATE' => '15.00', 'ACCOUNT_OPEN_DATE' => '7/1/2024']);
        $this->raw('MF_03', '901', $m, '2024-07-31', ['CUMVOUCH_DET_ID' => '901', 'NEW_AC_NUMBER' => $m, 'AC_GLCODE' => '1050302', 'TRANSACTION_DATE' => '7/31/2024', 'TRANSAMT' => '-100000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N']);
        $this->raw('MF_01', $n, $n, '2024-07-01', ['NEW_AC_NUMBER' => $n, 'GLCODE' => '1050306', 'CUSTOMER_ID' => '736', 'ACCOUNT_NAME' => 'Untitled Scheme', 'STATUS_CODE' => 'A', 'INTEREST_RATE' => '15.00', 'ACCOUNT_OPEN_DATE' => '7/1/2024']);
        $this->raw('MF_03', '902', $n, '2024-07-31', ['CUMVOUCH_DET_ID' => '902', 'NEW_AC_NUMBER' => $n, 'AC_GLCODE' => '1050306', 'TRANSACTION_DATE' => '7/31/2024', 'TRANSAMT' => '-200000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N']);

        $this->service()->build('2024-07', '2024-07', 'B', 1, 2);
        $rows = DB::table('loan_books')->where('reporting_period', '2024-07')->get()->keyBy('contract_id');
        $this->assertSame('Mega Farm Seed Loans', $rows['104480000002']->product_group);
        $this->assertSame('Mega Farm (Government fund)', $rows['104480000002']->funding_source);
        $this->assertSame('GL 1050306', $rows['104480000003']->product_group);
        $this->assertSame('MAIIC Agricultural Loans', $rows['104420000005']->product_group);
        $this->assertSame('MAIIC', $rows['104420000005']->funding_source);
    }

    /**
     * A bullet loan past its maturity that still owes a balance is overdue from
     * maturity, even where E-Banker's report leaves the buckets and the overdue
     * date empty (the Mega Farm loans at December 2025); a loan not yet mature
     * keeps the reported count.
     */
    public function test_a_matured_balance_is_past_due_from_its_maturity_date(): void
    {
        $m = '000104480000004';
        $this->raw('MF_01', $m, $m, '2024-07-01', ['NEW_AC_NUMBER' => $m, 'GLCODE' => '1050301', 'CUSTOMER_ID' => '737', 'ACCOUNT_NAME' => 'Bullet Grower', 'STATUS_CODE' => 'A', 'INTEREST_RATE' => '15.00', 'ACCOUNT_OPEN_DATE' => '7/1/2024']);
        $this->raw('MF_03', '903', $m, '2024-07-01', ['CUMVOUCH_DET_ID' => '903', 'NEW_AC_NUMBER' => $m, 'AC_GLCODE' => '1050301', 'TRANSACTION_DATE' => '7/1/2024', 'TRANSAMT' => '-300000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N']);
        $this->raw('MF_05', '7001', $m, '2024-08-31', ['LOAN_BOOK_DET_ID_A' => '7001', 'ASONDATE' => '8/31/2024', 'NEW_AC_NUMBER' => $m, 'GLCODE' => '1050301', 'MATURITY_DATE' => '7/31/2024',
            'PRINCIPAL' => '300000.00', 'CARRYING_AMOUNT' => '300000.00', 'DAY_1_30' => '0.00', 'DAY_31_91' => '0.00', 'DAY_91_180' => '0.00', 'DAY_181_270' => '0.00', 'DAY_271_360' => '0.00', 'OVERDUE_PRINCI_DATE' => '']);

        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $row = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104480000004')->first();
        $this->assertSame(31, (int) $row->overdue_days);              // 31 Jul to 31 Aug, from maturity
        $this->assertSame('2024-07-31', $row->overdue_principal_date);
        // the in-scope loan matures in 2026: its reported count stands
        $this->assertSame(61, (int) DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104420000005')->value('overdue_days'));
    }

    /**
     * Staging Module Specification v1, section 5 and acceptance 6: the build keeps the
     * directive's count in overdue_days whatever the staging policy, and writes the
     * staging count beside it under the policy in force. The matured bullet loan reads
     * 31 days from maturity for the RBM return under both policies, and both
     * staging policies count it from maturity too (single-ladder on the bucket
     * ageing, as the December 2025 workbooks' loan book showed matured loans past
     * due). The RBM return is the same under both.
     */
    public function test_the_directive_count_and_the_rbm_return_do_not_move_with_the_staging_policy(): void
    {
        $m = '000104480000004';
        $this->raw('MF_01', $m, $m, '2024-07-01', ['NEW_AC_NUMBER' => $m, 'GLCODE' => '1050301', 'CUSTOMER_ID' => '737', 'ACCOUNT_NAME' => 'Bullet Grower', 'STATUS_CODE' => 'A', 'INTEREST_RATE' => '15.00', 'ACCOUNT_OPEN_DATE' => '7/1/2024']);
        $this->raw('MF_03', '903', $m, '2024-07-01', ['CUMVOUCH_DET_ID' => '903', 'NEW_AC_NUMBER' => $m, 'AC_GLCODE' => '1050301', 'TRANSACTION_DATE' => '7/1/2024', 'TRANSAMT' => '-300000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N']);
        $this->raw('MF_05', '7001', $m, '2024-08-31', ['LOAN_BOOK_DET_ID_A' => '7001', 'ASONDATE' => '8/31/2024', 'NEW_AC_NUMBER' => $m, 'GLCODE' => '1050301', 'MATURITY_DATE' => '7/31/2024',
            'PRINCIPAL' => '300000.00', 'CARRYING_AMOUNT' => '300000.00', 'DAY_1_30' => '0.00', 'DAY_31_91' => '0.00', 'DAY_91_180' => '0.00', 'DAY_181_270' => '0.00', 'DAY_271_360' => '0.00', 'OVERDUE_PRINCI_DATE' => '']);
        $book = fn () => DB::table('loan_books')->where('reporting_period', '2024-08')->orderBy('contract_id')->get(['contract_id', 'tenor', 'overdue_days', 'overdue_principal_date', 'staging_dpd', 'staging_dpd_basis'])->keyBy('contract_id');
        $classes = fn ($rows) => $rows->map(fn ($r) => \App\Services\Rbm\RbmReturnService::classify((int) $r->overdue_days, (int) $r->tenor))->all();

        // the seeded default: single-ladder staging
        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $single = $book();
        $this->assertSame(31, (int) $single['104480000004']->overdue_days);
        $this->assertSame(31, (int) $single['104480000004']->staging_dpd);
        $this->assertSame('bucket ageing, core-system report as given, counted from maturity when longer', $single['104480000004']->staging_dpd_basis);
        // every count the staging settings choose between is kept on the row, so any policy can be counted exactly
        $kept = json_decode(DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104480000004')->value('build_basis'), true)['dpd_counts'];
        $this->assertSame(['oldest' => 0, 'oldest_recounted' => 0, 'bucket' => 0, 'bucket_recounted' => 0, 'since_maturity' => 31], $kept);

        DB::table('governance_settings')->where('key', \App\Services\Eir\StagingPolicy::PRESET)->update(['value' => \App\Services\Eir\StagingPolicy::PRESET_DIRECTIVE]);
        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $directive = $book();
        $this->assertSame(31, (int) $directive['104480000004']->staging_dpd);
        $this->assertSame('oldest overdue instalment, recounted against the schedule in force, counted from maturity when longer', $directive['104480000004']->staging_dpd_basis);
        foreach ($directive as $contract => $r) {
            // under the directive-aligned policy the staging count is the directive's own
            $this->assertSame((int) $r->overdue_days, (int) $r->staging_dpd, $contract);
        }

        // acceptance 6: the count, the overdue date and so the RBM class of every loan are the same under both
        $this->assertEquals($single->map(fn ($r) => [$r->overdue_days, $r->overdue_principal_date])->all(), $directive->map(fn ($r) => [$r->overdue_days, $r->overdue_principal_date])->all());
        $this->assertSame($classes($single), $classes($directive));
    }

    /** A loan of 120,000 drawn 1 July 2024, its stored August run saying what $run says, and the chart rows given. */
    private function chartLoan(string $a, array $run, array $chart, array $receipts = [], array $definitions = []): void
    {
        $this->raw('P1_02', $a, $a, '2024-07-01', ['NEW_AC_NUMBER' => $a, 'GLCODE' => '1050101', 'CUSTOMER_ID' => '900', 'ACCOUNT_NAME' => 'Chart Ltd', 'STATUS_CODE' => 'A', 'INTEREST_RATE' => '10.00', 'ACCOUNT_OPEN_DATE' => '7/1/2024']);
        $this->raw('P1_01', $a . 'd', $a, '2024-07-01', ['CUMVOUCH_DET_ID' => '1', 'NEW_AC_NUMBER' => $a, 'TRANSACTION_DATE' => '7/1/2024', 'TRANSAMT' => '-120000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N']);
        foreach ($receipts as $i => [$date, $amount]) {
            $this->raw('P1_01', $a . 'r' . $i, $a, $date, ['CUMVOUCH_DET_ID' => (string) (10 + $i), 'NEW_AC_NUMBER' => $a, 'TRANSACTION_DATE' => $date, 'TRANSAMT' => $amount, 'TRANTYPE' => '305', 'DELETE_FLAG' => 'N']);
        }
        $this->raw('P2_08', $a . 'run', $a, '2024-08-31', $run + ['LOAN_BOOK_DET_ID_A' => '9' . substr($a, -3), 'ASONDATE' => '8/31/2024', 'NEW_AC_NUMBER' => $a, 'GLCODE' => '1050101', 'VALUE_DATE' => '7/1/2024', 'MATURITY_DATE' => '6/1/2026', 'CARRYING_AMOUNT' => '120000.00', 'DAY_91_180' => '0.00', 'DAY_181_270' => '0.00', 'DAY_271_360' => '0.00']);
        foreach ($chart as $i => [$due, $amount, $det, $serial]) {
            $this->raw('P2_10', $a . 'c' . $i, $a, $due, ['INST_CHART_DET_ID' => $a . $i, 'NEW_AC_NUMBER' => $a, 'INST_DET_ID' => $det, 'INST_SR_NO' => (string) $serial, 'INSTALLMENT_DATE' => $due, 'INSTALLMENT_AMT' => $amount, 'ACTIVE_FLAG' => 'Y', 'DELETE_FLAG' => 'N', 'TRANSACTION_DATE' => '7/1/2024']);
        }
        foreach ($definitions as $det => $mode) {
            $this->raw('P3_11', $a . 'def' . $det, $a, '2024-07-01', ['INST_DET_ID' => (string) $det, 'NEW_AC_NUMBER' => $a, 'INSTALLMENT_PAY_MODE' => $mode, 'DELETE_FLAG' => 'N']);
        }
    }

    /**
     * Verification of 14 October 2026: the as-at reports re-run in September
     * 2026 aged monthly instalments on loans whose chart in force had nothing
     * due until 2026 (Namalowe Eco Farm, Mchinji Civil Servants SACCO). No
     * contractual payment had fallen due, so none was past due; the row says
     * why it differs from the report.
     */
    public function test_reported_arrears_are_not_accepted_when_the_chart_in_force_has_nothing_due(): void
    {
        $a = '000104420000064';
        $this->chartLoan($a, ['OVERDUE_PRINCI_DATE' => '7/1/2024', 'ARREAS_TOTAL' => '5000.00', 'DAY_1_30' => '5000.00', 'DAY_31_91' => '5000.00'],
            [['2024-08-01', '0.00', '1', 1], ['2025-03-03', '60000.00', '2', 1], ['2025-09-03', '60000.00', '2', 2]]);
        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $row = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104420000064')->first();
        $this->assertSame(0, (int) $row->overdue_days);
        $this->assertNull($row->overdue_principal_date);
        $this->assertStringContainsString('no instalment falls due until 2025-03-03', $row->build_flag);
        $this->assertStringContainsString(LoanBookBuildService::SCHEDULE_COUNT, $row->build_flag);
        // a loan with no chart keeps the report's count
        $this->assertSame(61, (int) DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104420000005')->value('overdue_days'));
    }

    /** Microloan Foundation, December 2024: an overdue date in the future and the arrears in the buckets, nothing due on the chart. */
    public function test_bucket_ageing_with_nothing_due_on_the_chart_in_force_is_not_accepted_either(): void
    {
        $a = '000104430000069';
        $this->chartLoan($a, ['OVERDUE_PRINCI_DATE' => '9/1/2024', 'ARREAS_TOTAL' => '5000.00', 'DAY_1_30' => '0.00', 'DAY_31_91' => '0.00', 'DAY_181_270' => '5000.00'],
            [['2024-08-01', '0.00', '1', 1], ['2025-12-09', '60000.00', '2', 1]]);
        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $row = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104430000069')->first();
        $this->assertSame(0, (int) $row->overdue_days);
        $this->assertStringContainsString(LoanBookBuildService::SCHEDULE_COUNT, $row->build_flag, 'so staging does not age it by the buckets');
    }

    /**
     * Udon Business and BenCal Technologies: the migrated chart dates the rest
     * of a monthly definition on one day, and from that day the report shows
     * the whole balance overdue from the value date. Dated by the definition,
     * the instalments due are covered by the ledger receipts.
     */
    public function test_a_collapsed_chart_is_counted_on_its_monthly_definition_against_the_ledger(): void
    {
        $a = '000104450000056';
        $chart = [['2024-07-01', '20000.00', '7', 1], ['2024-08-01', '20000.00', '7', 2]];
        foreach (range(3, 6) as $serial) {
            $chart[] = ['2024-08-01', '20000.00', '7', $serial]; // serials 3 to 6 all dated 1 August 2024
        }
        $this->chartLoan($a, ['OVERDUE_PRINCI_DATE' => '7/1/2024', 'ARREAS_TOTAL' => '120000.00', 'DAY_31_91' => '120000.00'], $chart,
            [['2024-07-01', '20000.00'], ['2024-08-01', '20000.00']], ['7' => 'M']);
        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $row = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104450000056')->first();
        $this->assertSame(0, (int) $row->overdue_days, 'July and August are paid; September onward is not yet due');
        $this->assertStringContainsString('dates several instalments on one day', $row->build_flag);
        $this->assertStringContainsString('the report says 61', $row->build_flag);
    }

    /** A facility past its maturity cannot be drawn: its undrawn amount leaves the EAD (Mphanga Holdings, 65m undrawn after May 2025). */
    public function test_an_undrawn_amount_past_maturity_is_no_exposure(): void
    {
        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $live = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104420000005')->first();
        $this->assertEquals(2000000, $live->commitments, 'matures in 2026: 12m approved, 10m drawn, 2m still available');
        DB::table('loan_books')->delete();
        DB::table('ebanker_raw_rows')->where('query_id', 'P1_03')->update(['payload' => json_encode(['NEW_AC_NUMBER' => '000104420000005', 'SANCTION_AMOUNT' => '12000000.00', 'PERIOD_YEARS' => '0', 'PERIOD_MONTHS' => '1', 'EXPIRY_DATE' => '8/1/2024'])]);
        DB::table('ebanker_raw_rows')->where('query_id', 'P2_08')->where('source_key', '5000')->update(['payload' => json_encode(['LOAN_BOOK_DET_ID_A' => '5000', 'ASONDATE' => '8/31/2024', 'NEW_AC_NUMBER' => '000104420000005', 'GLCODE' => '1050101', 'MATURITY_DATE' => '8/1/2024', 'CARRYING_AMOUNT' => '8000000.00', 'PRINCIPAL' => '8000000.00'])]);
        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $matured = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104420000005')->first();
        $this->assertEquals(0, $matured->commitments);
        $this->assertEquals(8000000, $matured->ead);
        $this->assertEquals(2000000, json_decode($matured->build_basis, true)['undrawn_after_maturity']);
    }

    /** A loan written off to nil is closed: its undrawn amount leaves the EAD (104420000007, 162.6m undrawn after its June 2026 write-off). */
    public function test_an_undrawn_amount_on_a_loan_written_off_to_nil_is_no_exposure(): void
    {
        // the 8m balance at the end of August written off (type 300): nil carrying amount, 2m once left undrawn
        $this->raw('P1_01', '106', '000104420000005', '2024-08-31', ['CUMVOUCH_DET_ID' => '106', 'NEW_AC_NUMBER' => '000104420000005', 'TRANSACTION_DATE' => '8/31/2024', 'TRANSAMT' => '8000000.00', 'TRANTYPE' => '300', 'DELETE_FLAG' => 'N']);
        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $row = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104420000005')->first();
        $this->assertEquals(0, $row->carrying_amount);
        $this->assertEquals(0, $row->commitments);
        $this->assertEquals(0, $row->ead);
        $basis = json_decode($row->build_basis, true);
        $this->assertEquals(8000000, $basis['ledger']['written_off']);
        $this->assertEquals(2000000, $basis['undrawn_after_write_off']);
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
        $this->assertSame('5000', $aug->build_source_key);     // the two pulls disagree on the stable fields, so the latest stands, flagged, not resolved silently
        $this->assertStringContainsString('2 pulls on record for this month-end disagree', $aug->build_flag);
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

    /** A build proposed from the command line without --user names no requester: it is approved, not refused as user 0. */
    public function test_a_build_proposed_without_a_named_person_is_approved_by_a_second_person(): void
    {
        $r = $this->service()->build('2024-08', '2024-08', 'B', null);
        $done = $this->service()->approve($r['build_id'], 2);
        $this->assertSame('BUILT', $done['status']);
        $this->assertNull(DB::table('loan_book_builds')->where('id', $done['build_id'])->value('requested_by'));
        $this->assertSame(1, DB::table('loan_books')->count());
    }

    /** A build that fails at approval leaves its proposal awaiting approval: it is never superseded by nothing. */
    public function test_a_failed_approval_keeps_the_proposal(): void
    {
        $r = $this->service()->build('2024-08', '2024-08', 'B', 1);
        DB::table('reporting_period_locks')->insert(['reporting_period' => '2024-08', 'locked_at' => now(), 'reason' => 'audited', 'created_at' => now(), 'updated_at' => now()]);
        try {
            $this->service()->approve($r['build_id'], 2);
            $this->fail('a locked month was built');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Locked period', $e->getMessage());
        }
        $this->assertSame('PROPOSED', DB::table('loan_book_builds')->where('id', $r['build_id'])->value('status'));
        $this->assertSame(0, DB::table('loan_books')->count());
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

    /** The build method and the days-past-due basis are governed with no value in code (audit, 14 October 2026). */
    public function test_the_method_and_the_dpd_basis_fail_closed_without_an_approved_value(): void
    {
        $this->assertSame('B', $this->service()->methodInForce('2024-08-31'));
        DB::table('governance_settings')->where('key', 'loan_book_build_method')->update(['value' => 'Z: not a method']);
        try {
            $this->service()->methodInForce('2024-08-31');
            $this->fail('an unknown method was taken as B');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not one the build knows', $e->getMessage());
        }
        DB::table('governance_settings')->where('key', 'loan_book_build_method')->delete();
        try {
            $this->service()->methodInForce('2024-08-31');
            $this->fail('a missing method was taken as B');
        } catch (\App\Exceptions\GovernanceSettingMissingException $e) {
            $this->assertStringContainsString('loan_book_build_method', $e->getMessage());
        }
    }
}
