<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\LoanBookBuildService;
use App\Services\Eir\GovernanceService;
use Database\Seeders\EbankerQuerySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A third, distinct E-Banker defect from the ones section 6 (re-pull drift) and section 6.1
 * (broken buckets) correct: the reported overdue-principal date itself sometimes jumps to an
 * implausible value — one that predates the loan's own value date, or that moves by far more
 * than any ordinary bi-monthly or quarterly instalment schedule would move it since last month's
 * own booked figure (Loan Book Report Point-in-Time Specification v1, section 6.4; found on 6 of
 * the 36 facility mismatches against MAIIC's December 2025 workbook, verification of 17 October
 * 2026). The reported date is kept — never guessed at — but flagged for a person.
 */
class OverdueDateJumpFlagTest extends TestCase
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
        Schema::create('governance_settings', function (Blueprint $t) { $t->increments('id'); $t->string('key', 60); $t->string('value'); $t->text('options'); $t->string('label'); $t->text('description'); $t->string('effective_from'); $t->integer('set_by')->nullable(); $t->integer('approved_by')->nullable(); $t->string('approved_at')->nullable(); $t->string('reason', 500)->nullable(); $t->string('status', 20)->default('PROPOSED'); $t->timestamps(); });
        foreach (array_merge(['loan_book_build_method', \App\Services\Eir\StagingPolicy::PRESET], array_keys(\App\Services\Eir\StagingPolicy::SETTINGS)) as $key) {
            $def = GovernanceService::catalogue()[$key];
            DB::table('governance_settings')->insert(['key' => $key, 'value' => $def['default'], 'options' => json_encode($def['options']), 'label' => $def['label'], 'description' => '', 'effective_from' => '1900-01-01', 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('users')->insert([['id' => 1, 'name' => 'Maker', 'created_at' => now(), 'updated_at' => now()], ['id' => 2, 'name' => 'Checker', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('loan_portfolios')->insert(['id' => 1, 'name' => 'Loans']);
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('a', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function raw(string $q, string $key, string $account, string $date, array $payload): void
    {
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => $q, 'source_key' => $key, 'account' => $account, 'row_date' => $date,
            'payload' => json_encode($payload), 'row_hash' => md5($key . $q), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function service(): LoanBookBuildService
    {
        return new LoanBookBuildService(new LandingZoneReader(), new GovernanceService());
    }

    private function loanBookRun(string $a, string $asOnDate, string $odDate, string $carrying = '8000000.00', string $arrearsTotal = '500000.00'): void
    {
        $iso = \Carbon\CarbonImmutable::createFromFormat('!n/j/Y', $asOnDate)->toDateString();
        $key = $a . '_' . $iso;
        $this->raw('P2_08', $key, $a, $iso, ['LOAN_BOOK_DET_ID_A' => $key, 'ASONDATE' => $asOnDate, 'NEW_AC_NUMBER' => $a, 'GLCODE' => '1050101',
            'VALUE_DATE' => '7/1/2024', 'MATURITY_DATE' => '7/1/2026', 'CARRYING_AMOUNT' => $carrying, 'ARREAS_TOTAL' => $arrearsTotal,
            'OVERDUE_PRINCI_DATE' => $odDate, 'DAY_1_30' => '0.00', 'DAY_31_91' => $arrearsTotal, 'DAY_91_180' => '0.00', 'DAY_181_270' => '0.00', 'DAY_271_360' => '0.00']);
        // method A still needs a ledger posting at or after the month-end, or the build refuses rather than estimate
        $this->raw('P1_01', $key . 'd', $a, $iso, ['CUMVOUCH_DET_ID' => $key . 'd', 'NEW_AC_NUMBER' => $a, 'TRANSACTION_DATE' => $asOnDate, 'TRANSAMT' => '0.00', 'TRANTYPE' => '303', 'DELETE_FLAG' => 'N']);
    }

    public function test_a_backward_jump_of_more_than_150_days_from_last_months_own_booked_figure_is_flagged(): void
    {
        $a = '000104420000005';
        $this->loanBookRun($a, '11/30/2025', '8/1/2025');
        $this->service()->build('2025-11', '2025-11', 'A', 1, 2);
        $this->loanBookRun($a, '12/31/2025', '1/1/2025'); // 212 days earlier than November's own booked date, but after the loan's own value date

        $r = $this->service()->build('2025-12', '2025-12', 'A', 1, 2);
        $dec = DB::table('loan_books')->where('reporting_period', '2025-12')->where('contract_id', '104420000005')->first();
        $this->assertStringContainsString('moved from 2025-08-01 to 2025-01-01 since last month', $dec->build_flag);
        $this->assertStringContainsString('section 6.4', $dec->build_flag);
        // the reported date stands: never guessed at, never silently corrected to last month's figure
        $this->assertSame('2025-01-01', $dec->overdue_principal_date);
    }

    public function test_an_overdue_date_before_the_loans_own_value_date_is_flagged(): void
    {
        $a = '000104420000005';
        // VALUE_DATE in run() is 7/1/2024; this overdue date is two years earlier, impossible
        $this->loanBookRun($a, '12/31/2025', '1/1/2022');

        $r = $this->service()->build('2025-12', '2025-12', 'A', 1, 2);
        $dec = DB::table('loan_books')->where('reporting_period', '2025-12')->where('contract_id', '104420000005')->first();
        $this->assertStringContainsString("before the loan's own value date", $dec->build_flag);
        $this->assertSame('2022-01-01', $dec->overdue_principal_date);
    }

    public function test_an_ordinary_bi_monthly_instalment_shift_of_up_to_150_days_is_not_flagged(): void
    {
        $a = '000104420000005';
        $this->loanBookRun($a, '11/30/2025', '8/1/2025');
        $this->service()->build('2025-11', '2025-11', 'A', 1, 2);
        $this->loanBookRun($a, '12/31/2025', '10/1/2025'); // 61 days later: a normal instalment-to-instalment move

        $this->service()->build('2025-12', '2025-12', 'A', 1, 2);
        $dec = DB::table('loan_books')->where('reporting_period', '2025-12')->where('contract_id', '104420000005')->first();
        $this->assertNull($dec->build_flag);
    }

    public function test_the_first_period_built_has_nothing_to_compare_against_and_is_not_flagged(): void
    {
        $a = '000104420000005';
        $this->loanBookRun($a, '12/31/2025', '1/1/2022'); // implausible, but VALUE_DATE is also 7/1/2024, so this one is also before it

        // use a plausible date instead, to isolate "no prior period" from the value-date check
        DB::table('ebanker_raw_rows')->where('account', $a)->delete();
        $this->loanBookRun($a, '12/31/2025', '10/1/2025');
        $this->service()->build('2025-12', '2025-12', 'A', 1, 2);
        $dec = DB::table('loan_books')->where('reporting_period', '2025-12')->where('contract_id', '104420000005')->first();
        $this->assertNull($dec->build_flag);
    }
}
