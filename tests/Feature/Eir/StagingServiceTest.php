<?php

namespace Tests\Feature\Eir;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\StagingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\Feature\Eir\Concerns\CreatesSicrSchema;
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
    use CreatesSicrSchema;

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
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('external_identity_id')->nullable(); $t->string('customer_id')->nullable(); $t->string('reporting_period'); $t->string('product_group')->nullable(); $t->string('product_code')->nullable(); $t->integer('tenor')->default(0); $t->integer('overdue_days')->default(0); $t->decimal('carrying_amount', 20, 2)->default(0); $t->text('build_flag')->nullable(); $t->integer('sicr')->default(0); $t->integer('ifrs9_stage')->default(0); $t->string('ifrs9stage_pre_qualitative')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->string('calculated_ifrs9_stage')->nullable(); foreach (['arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180', 'arrears_180_to_270', 'arrears_271_to_360'] as $c) { $t->string($c)->nullable(); } });
        $migration = require base_path('database/migrations/2026_10_08_000000_create_ebanker_landing_zone.php');
        $migration->up();
        $this->createSicrSchema();
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
            // a flag on the row with no approved trigger behind it is not read (it is the engine's output now)
            ['contract_id' => 'sicr-flag', 'tenor' => 36, 'overdue_days' => 0, 'sicr' => 1],
        ] as $row) {
            DB::table('loan_books')->insert($row + $base);
        }
        $c = (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $stage = DB::table('loan_books')->pluck('ifrs9stage_post_qualitative', 'contract_id')->all();
        $this->assertSame(['short-100' => '3', 'long-100' => '2', 'long-200' => '3', 'current' => '1', 'mega-bucket' => '3', 'sicr-flag' => '1'], $stage);
        $this->assertSame(0, (int) DB::table('loan_books')->where('contract_id', 'sicr-flag')->value('sicr'));
        $this->assertSame(['rows' => 6, 'stage1' => 2, 'stage2' => 1, 'stage3' => 3, 'by_dpd' => 4, 'by_instalments' => 0, 'by_sicr' => 0, 'by_bucket' => 1], array_intersect_key($c, array_flip(['rows', 'stage1', 'stage2', 'stage3', 'by_dpd', 'by_instalments', 'by_sicr', 'by_bucket'])));
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Loan Book Staged')->count());
    }

    /**
     * A Mega Farm account whose product group carries only its GL code (a book
     * built before the trial balance titles were read: "GL 1050302") is still
     * the programme: 100 days past due is Stage 3 on the directive's 91, not
     * Stage 2 on the 181 of a medium-term facility (spec 3.6, D31).
     */
    public function test_a_mega_farm_account_is_staged_on_its_gl_as_well_as_its_name(): void
    {
        DB::table('loan_books')->insert([
            ['contract_id' => 'mf-by-gl', 'reporting_period' => '2025-12', 'product_group' => 'GL 1050302', 'product_code' => '1050302', 'tenor' => 36, 'overdue_days' => 100],
            ['contract_id' => 'maiic-36', 'reporting_period' => '2025-12', 'product_group' => 'MAIIC Agricultural Loans', 'product_code' => '1050101', 'tenor' => 36, 'overdue_days' => 100],
        ]);
        (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $this->assertSame(['maiic-36' => '2', 'mf-by-gl' => '3'], DB::table('loan_books')->orderBy('contract_id')->pluck('ifrs9stage_post_qualitative', 'contract_id')->all());
    }

    public function test_four_unpaid_instalments_lift_a_current_loan_to_stage_3(): void
    {
        $a = '000104420000005';
        DB::table('loan_books')->insert(['contract_id' => '104420000005', 'external_identity_id' => $a, 'reporting_period' => '2025-12', 'tenor' => 24, 'overdue_days' => 0, 'carrying_amount' => 600000]);
        foreach (['2025-07-31', '2025-08-31', '2025-09-30', '2025-10-31', '2025-11-30', '2025-12-31'] as $i => $due) {
            $this->raw('P2_10', (string) (100 + $i), $a, $due, ['INST_CHART_DET_ID' => (string) (100 + $i), 'INSTALLMENT_DATE' => $due, 'INSTALLMENT_AMT' => '100000.00', 'ACTIVE_FLAG' => 'Y', 'DELETE_FLAG' => 'N', 'TRANSACTION_DATE' => '6/30/2025']);
        }
        $this->raw('P1_01', '0', $a, '2025-06-30', ['TRANSAMT' => '-800000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N']);
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
     * A count the build made on the instalment chart (Udon Business: the
     * report puts the whole balance in the 181-270 bucket from a collapsed
     * chart; the chart dated by its definition and the ledger say paid ahead)
     * is not overruled by the report's buckets.
     */
    public function test_a_count_made_on_the_instalment_chart_is_not_overruled_by_the_report_buckets(): void
    {
        DB::table('loan_books')->insert([
            ['contract_id' => 'udon', 'reporting_period' => '2025-12', 'tenor' => 72, 'overdue_days' => 0, 'arrears_180_to_270' => '70065375', 'build_flag' => \App\Services\Ebanker\LoanBookBuildService::SCHEDULE_COUNT . ': it dates several instalments on one day'],
            ['contract_id' => 'report', 'reporting_period' => '2025-12', 'tenor' => 72, 'overdue_days' => 0, 'arrears_180_to_270' => '70065375', 'build_flag' => null],
        ]);
        (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $this->assertSame(['report' => '3', 'udon' => '1'], DB::table('loan_books')->orderBy('contract_id')->pluck('ifrs9stage_pre_qualitative', 'contract_id')->all());
    }

    /** A fixture loan: 800,000 drawn on 30 June 2025, a chart created then of six instalments of 100,000. */
    private function trigger(string $a, float $balance, string $created = '6/30/2025'): void
    {
        DB::table('loan_books')->insert(['contract_id' => ltrim($a, '0'), 'external_identity_id' => $a, 'reporting_period' => '2025-12', 'tenor' => 24, 'overdue_days' => 0, 'carrying_amount' => $balance]);
        $this->raw('P1_01', $a . '-d', $a, '2025-06-30', ['TRANSAMT' => '-800000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N']);
        foreach (['2025-07-31', '2025-08-31', '2025-09-30', '2025-10-31', '2025-11-30', '2025-12-31'] as $i => $due) {
            $this->raw('P2_10', $a . $i, $a, $due, ['INST_CHART_DET_ID' => $a . $i, 'INSTALLMENT_DATE' => $due, 'INSTALLMENT_AMT' => '100000.00', 'ACTIVE_FLAG' => 'Y', 'DELETE_FLAG' => 'N', 'TRANSACTION_DATE' => $created]);
        }
    }

    /**
     * Verification of 14 October 2026. The trigger read the live chart of
     * 6 October 2026 for every month-end: a chart regenerated after the
     * month-end (Happie Foods' of 5 August 2026) is not the schedule in force
     * at December 2025, and with no version in force the trigger is not
     * evaluated and is counted, never assumed.
     */
    public function test_the_trigger_reads_the_chart_in_force_at_the_month_end_not_a_later_one(): void
    {
        $this->trigger('000104420000062', 600000, '8/5/2026');
        $c = (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $this->assertSame('1', DB::table('loan_books')->value('ifrs9stage_post_qualitative'));
        $this->assertSame(1, $c['instalments_no_chart']);
        $this->assertSame(0, $c['by_instalments']);
    }

    /** A receipt later reversed pays nothing (Chisitu Tea Blenders, 135m refunded as another party's sale proceeds). */
    public function test_a_reversed_receipt_does_not_pay_an_instalment(): void
    {
        $a = '000104450000069';
        $this->trigger($a, 600000);
        $this->raw('P1_01', 'r1', $a, '2025-07-31', ['TRANSAMT' => '300000.00', 'TRANTYPE' => '305', 'DELETE_FLAG' => 'N']);
        (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $this->assertSame('1', DB::table('loan_books')->value('ifrs9stage_post_qualitative'), 'three of six paid: three missed, below the trigger');
        $this->raw('P1_01', 'r2', $a, '2025-11-30', ['TRANSAMT' => '-300000.00', 'TRANTYPE' => '306', 'PARTICULARS' => 'Refund of Sale Proceeds', 'DELETE_FLAG' => 'N']);
        (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $this->assertSame('3', DB::table('loan_books')->value('ifrs9stage_post_qualitative'), 'the refund undoes the receipt: six missed');
    }

    /** A loan that owes nothing has no instalment outstanding (Micholess Creamery, nil balance, was lifted to Stage 3). */
    public function test_a_loan_that_owes_nothing_is_not_lifted_by_the_trigger(): void
    {
        $this->trigger('000104420000003', 0.0);
        $c = (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $this->assertSame('1', DB::table('loan_books')->value('ifrs9stage_post_qualitative'));
        $this->assertSame(0, $c['by_instalments']);
    }

    /**
     * A chart that asks for more than the ledger has ever charged the loan is
     * not the loan's schedule (Lenziemill Milling in 2024: 576m due on the
     * chart against 517m drawn and charged): it is not read, and the loan is
     * named rather than lifted.
     */
    public function test_a_chart_asking_more_than_the_ledger_ever_debited_is_not_evaluated(): void
    {
        $a = '000104420000007';
        $this->trigger($a, 600000);
        DB::table('ebanker_raw_rows')->where('query_id', 'P1_01')->where('account', $a)->update(['payload' => json_encode(['TRANSAMT' => '-400000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N'])]);
        $c = (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $this->assertSame('1', DB::table('loan_books')->value('ifrs9stage_post_qualitative'));
        $this->assertSame(1, $c['instalments_chart_contradicts_ledger']);
        $log = json_decode(DB::table('audit_logs')->where('action', 'Loan Book Staged')->value('new_values'), true);
        $this->assertArrayHasKey('104420000007', $log['instalment_trigger_not_evaluated']);
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
