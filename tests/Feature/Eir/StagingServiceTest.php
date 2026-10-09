<?php

namespace Tests\Feature\Eir;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\StagingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * Staging of a built period (spec v4 section 3.6, D31): the directive's
 * 91 days for a short-term facility and 181 for a longer one, Stage 2 from
 * 31 in both; a Mega Farm row with no day count ages by its bucket; the
 * missed-instalment trigger lifts a current loan to Stage 3 when four
 * instalments due are unpaid by the receipts in the ledger.
 */
class StagingServiceTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        // the missed-instalment trigger is a governed value with no default in code (audit H11)
        $this->createGovernanceSchema(); $this->seedGovernanceDefaults();
        if (! Schema::hasTable('users')) { Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); }); }
        if (! Schema::hasTable('audit_logs')) Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        Schema::create('staging_thresholds', function (Blueprint $t) { $t->increments('id'); $t->string('facility_class'); $t->integer('min_tenor_months'); $t->integer('stage2_dpd'); $t->integer('stage3_dpd'); $t->text('rebuttal_basis')->nullable(); $t->string('approved_by')->nullable(); $t->date('approved_at')->nullable(); $t->date('effective_from'); $t->timestamps(); });
        DB::table('staging_thresholds')->insert([
            ['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 91, 'effective_from' => '2018-07-13', 'created_at' => now(), 'updated_at' => now()],
            ['facility_class' => 'DEFAULT', 'min_tenor_months' => 13, 'stage2_dpd' => 31, 'stage3_dpd' => 181, 'effective_from' => '2018-07-13', 'created_at' => now(), 'updated_at' => now()],
            ['facility_class' => 'MEGA_FARM', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 91, 'effective_from' => '2018-07-13', 'created_at' => now(), 'updated_at' => now()],
        ]);
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('external_identity_id')->nullable(); $t->string('reporting_period'); $t->string('product_group')->nullable(); $t->integer('tenor')->default(0); $t->integer('overdue_days')->default(0); $t->integer('sicr')->default(0); $t->integer('ifrs9_stage')->default(0); $t->string('ifrs9stage_pre_qualitative')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->string('calculated_ifrs9_stage')->nullable(); foreach (['arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180', 'arrears_180_to_270', 'arrears_271_to_360'] as $c) { $t->string($c)->nullable(); } });
        $migration = require base_path('database/migrations/2026_10_08_000000_create_ebanker_landing_zone.php');
        $migration->up();
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('b', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function raw(string $q, string $key, string $account, string $date, array $payload): void
    {
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => $q, 'source_key' => $key, 'account' => $account, 'row_date' => $date, 'payload' => json_encode($payload), 'row_hash' => md5($q . $key), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_directive_thresholds_by_tenor_class_and_the_bucket_fallback(): void
    {
        $base = ['reporting_period' => '2025-12', 'product_group' => null, 'arrears_91_to_180' => null, 'sicr' => 0];
        foreach ([
            ['contract_id' => 'short-100', 'tenor' => 12, 'overdue_days' => 100],
            ['contract_id' => 'long-100', 'tenor' => 36, 'overdue_days' => 100],
            ['contract_id' => 'long-200', 'tenor' => 36, 'overdue_days' => 200],
            ['contract_id' => 'current', 'tenor' => 36, 'overdue_days' => 0],
            ['contract_id' => 'mega-bucket', 'product_group' => 'Mega Farm Seed Loans', 'tenor' => 0, 'overdue_days' => 0, 'arrears_91_to_180' => '7471500'],
            ['contract_id' => 'sicr-flag', 'tenor' => 36, 'overdue_days' => 0, 'sicr' => 1],
        ] as $row) {
            DB::table('loan_books')->insert($row + $base);
        }
        $c = (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $stage = DB::table('loan_books')->pluck('ifrs9stage_post_qualitative', 'contract_id')->all();
        $this->assertSame(['short-100' => '3', 'long-100' => '2', 'long-200' => '3', 'current' => '1', 'mega-bucket' => '3', 'sicr-flag' => '2'], $stage);
        $this->assertSame('1', DB::table('loan_books')->where('contract_id', 'sicr-flag')->value('ifrs9stage_pre_qualitative'));
        $this->assertSame(['rows' => 6, 'stage1' => 1, 'stage2' => 2, 'stage3' => 3, 'by_dpd' => 4, 'by_instalments' => 0, 'by_sicr' => 1, 'by_bucket' => 1], array_intersect_key($c, array_flip(['rows', 'stage1', 'stage2', 'stage3', 'by_dpd', 'by_instalments', 'by_sicr', 'by_bucket'])));
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Loan Book Staged')->count());
    }

    public function test_four_unpaid_instalments_lift_a_current_loan_to_stage_3(): void
    {
        $a = '000104420000005';
        DB::table('loan_books')->insert(['contract_id' => '104420000005', 'external_identity_id' => $a, 'reporting_period' => '2025-12', 'tenor' => 24, 'overdue_days' => 0]);
        foreach (['2025-07-31', '2025-08-31', '2025-09-30', '2025-10-31', '2025-11-30', '2025-12-31'] as $i => $due) {
            $this->raw('P2_10', (string) (100 + $i), $a, $due, ['INSTALLMENT_DATE' => $due, 'INSTALLMENT_AMT' => '100000.00', 'ACTIVE_FLAG' => 'Y', 'DELETE_FLAG' => 'N']);
        }
        // receipts cover the first two instalments only
        $this->raw('P1_01', '1', $a, '2025-08-31', ['TRANSAMT' => '200000.00', 'TRANTYPE' => '305', 'DELETE_FLAG' => 'N']);
        (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $row = DB::table('loan_books')->first();
        $this->assertSame('1', $row->ifrs9stage_pre_qualitative);
        $this->assertSame('3', $row->ifrs9stage_post_qualitative);
        // one more receipt leaves only three unpaid: below the trigger
        $this->raw('P1_01', '2', $a, '2025-09-30', ['TRANSAMT' => '100000.00', 'TRANTYPE' => '305', 'DELETE_FLAG' => 'N']);
        (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $this->assertSame('1', DB::table('loan_books')->first()->ifrs9stage_post_qualitative);
    }

    /**
     * The cure period (system audit of 9 October 2026, finding M11): a loan
     * that was Stage 3 in September and is current from October is held at
     * Stage 3 for October and November and released in December, the third
     * consecutive month-end below the threshold under the seeded three
     * months; a loan whose arrears grow moves up at once.
     */
    public function test_a_cured_loan_is_held_at_its_prior_stage_for_the_governed_months_and_a_move_up_is_immediate(): void
    {
        $row = fn (string $period, int $dpd, ?string $pre = null, ?string $post = null) => ['contract_id' => 'cured', 'reporting_period' => $period, 'tenor' => 36, 'overdue_days' => $dpd, 'ifrs9stage_pre_qualitative' => $pre, 'ifrs9stage_post_qualitative' => $post];
        DB::table('loan_books')->insert($row('2025-09', 200, '3', '3'));
        $stage = fn (string $period) => (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage($period, 1);

        DB::table('loan_books')->insert($row('2025-10', 0));
        $c = $stage('2025-10');
        $this->assertSame(['pre' => '1', 'post' => '3'], $this->stages('2025-10'));
        $this->assertSame(1, $c['held_by_cure']);
        $this->assertSame(3, $c['cure_months']);

        DB::table('loan_books')->insert($row('2025-11', 0));
        $stage('2025-11');
        $this->assertSame(['pre' => '1', 'post' => '3'], $this->stages('2025-11'));

        DB::table('loan_books')->insert($row('2025-12', 0));
        $c = $stage('2025-12');
        $this->assertSame(['pre' => '1', 'post' => '1'], $this->stages('2025-12'));
        $this->assertSame(0, $c['held_by_cure']);

        // arrears again in January: the move up to Stage 2 is immediate
        DB::table('loan_books')->insert($row('2026-01', 45));
        $stage('2026-01');
        $this->assertSame(['pre' => '2', 'post' => '2'], $this->stages('2026-01'));

        // no February row: there is no prior-month stage to hold March at
        DB::table('loan_books')->insert($row('2026-03', 0));
        $stage('2026-03');
        $this->assertSame(['pre' => '1', 'post' => '1'], $this->stages('2026-03'), 'no prior-month row: nothing to hold the loan at');
    }

    public function test_a_cure_period_of_zero_months_releases_a_cured_loan_at_once(): void
    {
        DB::table('governance_settings')->where('key', 'stage_cure_months')->update(['value' => '0 months (no probation)']);
        DB::table('loan_books')->insert(['contract_id' => 'cured', 'reporting_period' => '2025-09', 'tenor' => 36, 'overdue_days' => 200, 'ifrs9stage_pre_qualitative' => '3', 'ifrs9stage_post_qualitative' => '3']);
        DB::table('loan_books')->insert(['contract_id' => 'cured', 'reporting_period' => '2025-10', 'tenor' => 36, 'overdue_days' => 0]);
        $c = (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-10', 1);
        $this->assertSame(['pre' => '1', 'post' => '1'], $this->stages('2025-10'));
        $this->assertSame(0, $c['held_by_cure']);
        $this->assertSame(0, $c['cure_months']);
    }

    /** @return array{pre:string,post:string} */
    private function stages(string $period): array
    {
        $r = DB::table('loan_books')->where('contract_id', 'cured')->where('reporting_period', $period)->first();

        return ['pre' => $r->ifrs9stage_pre_qualitative, 'post' => $r->ifrs9stage_post_qualitative];
    }
}
