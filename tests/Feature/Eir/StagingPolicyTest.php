<?php

namespace Tests\Feature\Eir;

use App\Exceptions\GovernanceSettingMissingException;
use App\Services\Ebanker\LandingZoneReader;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\StagingPolicy;
use App\Services\Eir\StagingService;
use Carbon\CarbonImmutable;
use Database\Seeders\StagingThresholdSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\Feature\Eir\Concerns\CreatesSicrSchema;
use Tests\TestCase;

/**
 * Staging Module Specification v1, section 3 (acceptance 1 to 3 and 7): every
 * staging rule a governed setting; single-ladder staging, MAIIC's staging
 * policy as at 31 December 2025, the seeded default for every period;
 * directive-aligned staging in force only once proposed and approved, never by
 * date; a missing setting a named error.
 */
class StagingPolicyTest extends TestCase
{
    use CreatesGovernanceSchema, CreatesSicrSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        $this->createGovernanceSchema(); $this->seedGovernanceDefaults();
        if (! Schema::hasTable('users')) { Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); }); }
        DB::table('users')->insert([['id' => 1, 'name' => 'Finance analyst'], ['id' => 2, 'name' => 'Chief Finance Officer']]);
        Schema::create('staging_thresholds', function (Blueprint $t) {
            $t->increments('id'); $t->string('facility_class'); $t->integer('min_tenor_months'); $t->integer('stage2_dpd'); $t->integer('stage3_dpd'); $t->text('rebuttal_basis')->nullable();
            $t->date('effective_from'); $t->string('status')->default('APPROVED'); $t->integer('proposed_by')->nullable(); $t->integer('approved_by')->nullable(); $t->timestamp('approved_at')->nullable(); $t->string('reason')->nullable(); $t->timestamps();
        });
        (new StagingThresholdSeeder())->run();
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('external_identity_id')->nullable(); $t->string('customer_id')->nullable(); $t->string('reporting_period'); $t->string('product_group')->nullable(); $t->string('product_code')->nullable(); $t->integer('tenor')->default(0); $t->integer('overdue_days')->default(0); $t->decimal('carrying_amount', 20, 2)->default(0); $t->text('build_flag')->nullable(); $t->integer('sicr')->default(0); $t->integer('ifrs9_stage')->default(0); $t->string('ifrs9stage_pre_qualitative')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->string('calculated_ifrs9_stage')->nullable(); foreach (['arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180', 'arrears_180_to_270', 'arrears_271_to_360'] as $c) { $t->string($c)->nullable(); } });
        $migration = require base_path('database/migrations/2026_10_08_000000_create_ebanker_landing_zone.php');
        $migration->up();
        $this->createSicrSchema();
    }

    /**
     * A small book: a short-term loan 100 days late, a medium-term loan 100 and
     * one 200 days late, a Mega Farm loan of 36 months 100 days late, and a
     * partly cured medium-term loan whose oldest instalment is 200 days overdue
     * while its overdue amounts sit in the 31 to 90 bucket.
     */
    private function book(string $period): void
    {
        $base = ['reporting_period' => $period, 'carrying_amount' => 1000];
        foreach ([
            ['contract_id' => 'short-100', 'tenor' => 12, 'overdue_days' => 100, 'arrears_91_to_180' => '500'],
            ['contract_id' => 'long-100', 'tenor' => 36, 'overdue_days' => 100, 'arrears_91_to_180' => '500'],
            ['contract_id' => 'long-200', 'tenor' => 36, 'overdue_days' => 200, 'arrears_180_to_270' => '500'],
            ['contract_id' => 'mega-36', 'tenor' => 36, 'overdue_days' => 100, 'arrears_91_to_180' => '500', 'product_group' => 'Mega Farm Seed Loans'],
            ['contract_id' => 'part-cured', 'tenor' => 36, 'overdue_days' => 200, 'arrears_30_to_90' => '500'],
        ] as $row) {
            DB::table('loan_books')->insert($row + $base);
        }
    }

    private function stage(string $period): array
    {
        (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage($period, 1);

        return DB::table('loan_books')->where('reporting_period', $period)->orderBy('contract_id')->pluck('ifrs9stage_post_qualitative', 'contract_id')->all();
    }

    /** Acceptance 1: every staging setting holds the 31 December 2025 option from inception; nothing directive-aligned is in force. */
    public function test_the_seeded_default_is_single_ladder_staging_from_inception(): void
    {
        $catalogue = GovernanceService::catalogue();
        $this->assertSame(StagingPolicy::PRESET_SINGLE, $catalogue[StagingPolicy::PRESET]['default']);
        foreach (StagingPolicy::PRESETS[StagingPolicy::PRESET_SINGLE] as $key => $value) {
            $this->assertContains($value, $catalogue[$key]['options'], "{$key}: the preset's value is one of its options");
            // dpd_basis keeps its default: it governs the build's day count, which the RBM return reads, until
            // section 2 of the build gives staging its own count; staging takes the basis from the policy meanwhile
            if ($key !== 'dpd_basis') {
                $this->assertSame($value, $catalogue[$key]['default'], "{$key} is seeded at the 31 December 2025 option");
            }
        }
        foreach (StagingPolicy::PRESETS[StagingPolicy::PRESET_DIRECTIVE] as $key => $value) {
            $this->assertContains($value, $catalogue[$key]['options'], "{$key}: the directive-aligned value is one of its options");
        }
        $this->assertStringContainsString('31 December 2025', $catalogue[StagingPolicy::PRESET]['seed_reason']);

        $policy = StagingPolicy::at(new GovernanceService(), CarbonImmutable::parse('1990-01-31'));
        $this->assertSame(StagingPolicy::PRESET_SINGLE, $policy->preset);
        $this->assertSame(StagingPolicy::PRESETS[StagingPolicy::PRESET_SINGLE], $policy->values);
        $this->assertSame(0, DB::table('governance_settings')->where('value', StagingPolicy::PRESET_DIRECTIVE)->count());
    }

    /** Acceptance 2: no date switches the policy; every month stages on the seeded default. */
    public function test_no_date_switches_on_directive_aligned_staging(): void
    {
        foreach (['2025-12-31', '2026-01-31', '2026-09-30', '2027-06-30', '2035-12-31'] as $date) {
            $this->assertSame(StagingPolicy::PRESET_SINGLE, StagingPolicy::at(new GovernanceService(), CarbonImmutable::parse($date))->preset, $date);
        }
        $this->book('2026-01');
        // one ladder, 31 and 181, on the bucket ageing: the short-term and Mega Farm loans at 100 days are Stage 2,
        // and the partly cured loan reads its 31 to 90 bucket
        $this->assertSame(['long-100' => '2', 'long-200' => '3', 'mega-36' => '2', 'part-cured' => '2', 'short-100' => '2'], $this->stage('2026-01'));
    }

    /** Acceptance 3: approving directive-aligned staging from a month stages that month and later ones on its rules; earlier months keep theirs. */
    public function test_adopting_directive_aligned_staging_applies_from_its_month(): void
    {
        $governance = new GovernanceService();
        $proposal = $governance->propose(StagingPolicy::PRESET, StagingPolicy::PRESET_DIRECTIVE, '2026-04-01', 'Board decision of 20 March 2026 adopting directive-aligned staging', 1);
        $this->assertSame(StagingPolicy::PRESET_SINGLE, StagingPolicy::at(new GovernanceService(), CarbonImmutable::parse('2026-04-30'))->preset, 'a proposal governs nothing');
        $governance->approve($proposal->id, 2);

        $this->book('2026-03');
        $this->book('2026-04');
        $this->assertSame(['long-100' => '2', 'long-200' => '3', 'mega-36' => '2', 'part-cured' => '2', 'short-100' => '2'], $this->stage('2026-03'));
        // by term on the oldest overdue instalment: short-term Stage 3 from 91, the partly cured loan on its 200 days,
        // the Mega Farm loan by its 36-month tenor
        $this->assertSame(['long-100' => '2', 'long-200' => '3', 'mega-36' => '2', 'part-cured' => '3', 'short-100' => '3'], $this->stage('2026-04'));
        $this->assertSame(['long-100' => '2', 'long-200' => '3', 'mega-36' => '2', 'part-cured' => '2', 'short-100' => '2'], DB::table('loan_books')->where('reporting_period', '2026-03')->orderBy('contract_id')->pluck('ifrs9stage_post_qualitative', 'contract_id')->all());

        $logged = json_decode(DB::table('audit_logs')->where('action', 'Loan Book Staged')->where('reporting_period', '2026-04')->value('new_values'), true);
        $this->assertSame(StagingPolicy::PRESET_DIRECTIVE, $logged['staging_policy']['preset']);
        $this->assertSame('preset: ' . StagingPolicy::PRESET_DIRECTIVE, $logged['staging_policy']['source']['staging_threshold_profile']);
    }

    /** Each threshold profile and the Mega Farm and Stage 2 settings under Custom. */
    public function test_the_custom_settings_each_govern_their_rule(): void
    {
        $this->book('2026-05');
        $this->stageDirectiveAligned(['staging_threshold_profile' => StagingPolicy::PROFILE_IFRS9]);
        $this->assertSame(['long-100' => '3', 'long-200' => '3', 'mega-36' => '3', 'part-cured' => '3', 'short-100' => '3'], $this->stage('2026-05'));

        $this->stageDirectiveAligned(['staging_mega_farm_term' => StagingPolicy::MF_SHORT, 'dpd_basis' => StagingPolicy::BASIS_BUCKET]);
        $this->assertSame(['long-100' => '2', 'long-200' => '3', 'mega-36' => '3', 'part-cured' => '2', 'short-100' => '3'], $this->stage('2026-05'));

        // 91 days for Stage 2 over 12 months: the partly cured loan's 31 to 90 bucket leaves it in Stage 1
        $this->stageDirectiveAligned(['stage2_threshold_medium_long' => StagingPolicy::STAGE2_91, 'dpd_basis' => StagingPolicy::BASIS_BUCKET]);
        $this->assertSame('1', $this->stage('2026-05')['part-cured']);
    }

    /** A change to one setting under a named policy turns it to Custom, and every other rule keeps the policy's value. */
    public function test_a_single_setting_change_turns_the_policy_to_custom_and_keeps_the_rest(): void
    {
        $governance = new GovernanceService();
        $governance->approve($governance->propose(StagingPolicy::PRESET, StagingPolicy::PRESET_DIRECTIVE, '2026-01-01', 'Board decision adopting directive-aligned staging', 1)->id, 2);
        $governance->approve($governance->propose('stage_cure_months', '6 months', '2026-06-01', 'Credit Committee lengthens the probation to six months', 1)->id, 2);

        $before = StagingPolicy::at(new GovernanceService(), CarbonImmutable::parse('2026-05-31'));
        $this->assertSame(StagingPolicy::PRESET_DIRECTIVE, $before->preset);
        $after = StagingPolicy::at(new GovernanceService(), CarbonImmutable::parse('2026-06-30'));
        $this->assertSame(StagingPolicy::PRESET_CUSTOM, $after->preset);
        $this->assertSame(array_replace(StagingPolicy::PRESETS[StagingPolicy::PRESET_DIRECTIVE], ['stage_cure_months' => '6 months']), $after->values);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Staging Policy Turned Custom')->count());
    }

    /** Acceptance 7: a staging setting missing at the month-end stops staging and names it. */
    public function test_a_missing_staging_setting_stops_staging_with_a_named_error(): void
    {
        $this->book('2026-02');
        $this->stageDirectiveAligned(['stage_cure_test' => StagingPolicy::CURE_MONTHS]);
        DB::table('governance_settings')->where('key', 'staging_threshold_profile')->delete();
        try {
            $this->stage('2026-02');
            $this->fail('staging ran without its threshold profile');
        } catch (GovernanceSettingMissingException $e) {
            $this->assertStringContainsString('staging_threshold_profile', $e->getMessage());
        }
        DB::table('governance_settings')->where('key', StagingPolicy::PRESET)->delete();
        $this->expectException(GovernanceSettingMissingException::class);
        $this->expectExceptionMessage(StagingPolicy::PRESET);
        $this->stage('2026-02');
    }

    /** A profile whose threshold row is missing stops: it never falls back to the by-term rows. */
    public function test_a_profile_without_its_threshold_row_stops(): void
    {
        $this->book('2026-02');
        DB::table('staging_thresholds')->where('facility_class', StagingPolicy::CLASS_SINGLE)->delete();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(StagingPolicy::CLASS_SINGLE);
        $this->stage('2026-02');
    }

    /** The cure tests: arrears cleared holds a loan whose arrears remain, whatever the months. */
    public function test_the_cure_test_of_arrears_cleared_holds_a_loan_with_arrears(): void
    {
        foreach (['2026-01' => 200, '2026-02' => 0] as $period => $days) {
            DB::table('loan_books')->insert(['contract_id' => 'L1', 'reporting_period' => $period, 'tenor' => 36, 'overdue_days' => $days, 'carrying_amount' => 1000, 'arrears_1_to_30' => $period === '2026-02' ? '50' : null, 'arrears_180_to_270' => $period === '2026-01' ? '500' : null]);
        }
        $this->stageDirectiveAligned(['stage_cure_months' => '0 months (no probation)', 'stage_cure_test' => StagingPolicy::CURE_CLEARED]);
        $this->stage('2026-01');
        // February: no days counted but an amount still in the 1 to 30 bucket, so not cleared
        $this->assertSame('3', $this->stage('2026-02')['L1']);
        $this->stageDirectiveAligned(['stage_cure_months' => '0 months (no probation)', 'stage_cure_test' => StagingPolicy::CURE_MONTHS]);
        $this->assertSame('1', $this->stage('2026-02')['L1']);
    }
}
