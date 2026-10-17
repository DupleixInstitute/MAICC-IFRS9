<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\LoanBookBuildService;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\LoanStagingBasisOverrideService;
use Database\Seeders\EbankerQuerySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The build side of the governed staging basis override (December 2025 Loan Book Investigation
 * Findings v1, section 10): an approved override replaces staging_dpd with the directive's own
 * already-computed count for that one loan only; every other loan is staged exactly as the
 * governed policy already says.
 */
class StagingBasisOverrideBuildTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        // these test the override's own behaviour; the evidence an approval needs is tested in ClientBookReconciliationTest
        config(['approvals.require_evidence' => false]);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        Schema::create('loan_portfolios', function (Blueprint $t) { $t->increments('id'); $t->string('name'); });
        Schema::create('loan_staging_basis_overrides', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id', 30)->index(); $t->string('reporting_period', 7)->nullable(); $t->integer('override_days')->nullable(); $t->string('source', 40)->default('directive_count');
            $t->string('reason', 2000);
            $t->unsignedBigInteger('proposed_by'); $t->unsignedBigInteger('approved_by')->nullable();
            $t->string('status', 20)->default('PROPOSED'); $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });
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

    /** JAT-shaped fixture: ARREAS_TOTAL in the 1-30 bucket only (bucket staging reads 1 day), but the oldest overdue instalment (OVERDUE_PRINCI_DATE) is 69 days back. */
    private function jatShapedLoan(string $a): void
    {
        $this->raw('P2_08', $a . 'run', $a, '2025-12-31', [
            'LOAN_BOOK_DET_ID_A' => $a . 'run', 'ASONDATE' => '12/31/2025', 'NEW_AC_NUMBER' => $a, 'GLCODE' => '1050101',
            'VALUE_DATE' => '1/1/2024', 'MATURITY_DATE' => '1/1/2027', 'CARRYING_AMOUNT' => '222280864.49', 'ARREAS_TOTAL' => '15164482.00',
            'OVERDUE_PRINCI_DATE' => '10/23/2025', 'DAY_1_30' => '15164482.00', 'DAY_31_91' => '0.00', 'DAY_91_180' => '0.00', 'DAY_181_270' => '0.00', 'DAY_271_360' => '0.00',
        ]);
        $this->raw('P1_01', $a . 'd', $a, '2025-12-31', ['CUMVOUCH_DET_ID' => $a . 'd', 'NEW_AC_NUMBER' => $a, 'TRANSACTION_DATE' => '12/31/2025', 'TRANSAMT' => '0.00', 'TRANTYPE' => '303', 'DELETE_FLAG' => 'N']);
    }

    public function test_without_an_override_the_governed_policy_basis_stands(): void
    {
        $a = '000104430000087';
        $this->jatShapedLoan($a);
        $this->service()->build('2025-12', '2025-12', 'A', 1, 2);

        $row = DB::table('loan_books')->where('reporting_period', '2025-12')->where('contract_id', '104430000087')->first();
        $this->assertSame(1, (int) $row->staging_dpd, "single-ladder's bucket basis reads 1 day, matching JAT's real figure before any override");
        $this->assertSame(69, (int) $row->overdue_days, "the directive's own schedule-aware count is already 69 days");
        $this->assertNull($row->build_flag);
    }

    public function test_an_approved_override_replaces_staging_dpd_with_the_directives_own_count(): void
    {
        $a = '000104430000087';
        $this->jatShapedLoan($a);
        $overrides = new LoanStagingBasisOverrideService();
        $id = $overrides->propose('104430000087', '2025-12', 'Ledger/chart reconstruction matches the workbook at 69 days (findings note, section 5.3).', 1);
        $overrides->approve($id, 2);

        $this->service()->build('2025-12', '2025-12', 'A', 1, 2);

        $row = DB::table('loan_books')->where('reporting_period', '2025-12')->where('contract_id', '104430000087')->first();
        $this->assertSame(69, (int) $row->staging_dpd, 'the override replaces the bucket basis with the already-computed directive count');
        $this->assertStringContainsString("overridden to the directive's own already-computed count", $row->staging_dpd_basis);
        $this->assertStringContainsString('staging basis overridden by governed decision', $row->build_flag);
        $this->assertStringContainsString('Ledger/chart reconstruction matches the workbook', $row->build_flag);

        // StagingService::evaluate() recomputes the day count from build_basis.dpd_counts under
        // whichever policy is in force when it is read, bypassing staging_dpd entirely whenever
        // those counts are present — which is always. An override that only set staging_dpd would
        // be invisible to it (the real bug this guards: the override silently had no effect on
        // the actual staging/ECL engines or on staging:december-check, only on the stored column).
        $counts = json_decode($row->build_basis, true)['dpd_counts'];
        foreach (['oldest', 'oldest_recounted', 'bucket', 'bucket_recounted', 'since_maturity'] as $key) {
            $this->assertSame(69, $counts[$key], "dpd_counts.{$key} must also carry the override, or StagingService::evaluate() ignores it");
        }
    }

    public function test_an_audited_workbook_override_uses_the_explicit_day_count_not_the_directives_own(): void
    {
        $a = '000104430000087';
        $this->jatShapedLoan($a); // directive count is 69; the audited figure here is deliberately different, to prove it is not silently swapped for the directive's own
        $overrides = new LoanStagingBasisOverrideService();
        $id = $overrides->propose('104430000087', '2025-12', 'Audited and approved in the December 2025 AFS; MAIIC to supply facility-level reasons by 19 October 2026.', 1, 0);
        $overrides->approve($id, 2);

        $this->service()->build('2025-12', '2025-12', 'A', 1, 2);

        $row = DB::table('loan_books')->where('reporting_period', '2025-12')->where('contract_id', '104430000087')->first();
        $this->assertSame(0, (int) $row->staging_dpd, 'the explicit audited figure is used, not the directive\'s own 69-day count');
        $this->assertStringContainsString('an audited figure (0 days)', $row->build_flag);
        $this->assertStringContainsString('Audited and approved in the December 2025 AFS', $row->build_flag);
    }

    public function test_a_withdrawn_override_stops_applying_on_the_next_build(): void
    {
        $a = '000104430000087';
        $this->jatShapedLoan($a);
        $overrides = new LoanStagingBasisOverrideService();
        $id = $overrides->propose('104430000087', '2025-12', 'Reason.', 1);
        $overrides->approve($id, 2);
        $this->service()->build('2025-12', '2025-12', 'A', 1, 2);
        $overrides->withdraw($id, 1, 'Withdrawn in the test: the figure is superseded.');

        $this->service()->build('2025-12', '2025-12', 'A', 1, 2);

        $row = DB::table('loan_books')->where('reporting_period', '2025-12')->where('contract_id', '104430000087')->first();
        $this->assertSame(1, (int) $row->staging_dpd);
    }

    public function test_an_override_on_one_loan_does_not_touch_another(): void
    {
        $a = '000104430000087'; $b = '000104420000099';
        $this->jatShapedLoan($a);
        $this->raw('P2_08', $b . 'run', $b, '2025-12-31', [
            'LOAN_BOOK_DET_ID_A' => $b . 'run', 'ASONDATE' => '12/31/2025', 'NEW_AC_NUMBER' => $b, 'GLCODE' => '1050101',
            'VALUE_DATE' => '1/1/2024', 'MATURITY_DATE' => '1/1/2027', 'CARRYING_AMOUNT' => '5000000.00', 'ARREAS_TOTAL' => '1000000.00',
            'OVERDUE_PRINCI_DATE' => '10/23/2025', 'DAY_1_30' => '1000000.00', 'DAY_31_91' => '0.00', 'DAY_91_180' => '0.00', 'DAY_181_270' => '0.00', 'DAY_271_360' => '0.00',
        ]);
        $this->raw('P1_01', $b . 'd', $b, '2025-12-31', ['CUMVOUCH_DET_ID' => $b . 'd', 'NEW_AC_NUMBER' => $b, 'TRANSACTION_DATE' => '12/31/2025', 'TRANSAMT' => '0.00', 'TRANTYPE' => '303', 'DELETE_FLAG' => 'N']);
        $overrides = new LoanStagingBasisOverrideService();
        $id = $overrides->propose('104430000087', '2025-12', 'Reason.', 1);
        $overrides->approve($id, 2);

        $this->service()->build('2025-12', '2025-12', 'A', 1, 2);

        $other = DB::table('loan_books')->where('reporting_period', '2025-12')->where('contract_id', '104420000099')->first();
        $this->assertSame(1, (int) $other->staging_dpd, 'unaffected by the other loan\'s override');
        $this->assertNull($other->build_flag);
    }
}
