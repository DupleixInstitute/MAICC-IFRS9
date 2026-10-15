<?php

namespace Tests\Feature\Eir;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\StagingComparisonService;
use App\Services\Eir\StagingPolicy;
use App\Services\Eir\StagingService;
use Database\Seeders\StagingThresholdSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\Feature\Eir\Concerns\CreatesSicrSchema;
use Tests\TestCase;

/**
 * Staging Module Specification v1, section 4 (acceptance 5): the comparison run
 * lists every loan whose stage differs between the booked rules and the
 * compared ones, each with the setting behind it and its ECL effect; its totals
 * reconcile to the two stagings; nothing booked changes; a locked month can be
 * compared.
 *
 * The book, September 2026, staged and booked under single-ladder staging:
 *   short-100   12 months, 100 days, bucket 91 to 180    Stage 2   ECL 50 on 1,000, rate 12%
 *   long-100    36 months, 100 days, bucket 91 to 180    Stage 2   ECL 50
 *   long-200    36 months, 200 days, bucket 181 to 270   Stage 3   ECL 400 (coverage 40%)
 *   part-cured  36 months, 200 days, bucket 31 to 90     Stage 2   ECL 50, rate 12%
 *   current     36 months, current                       Stage 1   ECL 10
 * Under directive-aligned staging short-100 moves to Stage 3 on the threshold
 * profile (91 days short-term), and part-cured on the counting basis (its
 * oldest instalment is 200 days overdue).
 */
class StagingComparisonTest extends TestCase
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
        DB::table('users')->insert(['id' => 1, 'name' => 'Finance analyst']);
        Schema::create('staging_thresholds', function (Blueprint $t) {
            $t->increments('id'); $t->string('facility_class'); $t->integer('min_tenor_months'); $t->integer('stage2_dpd'); $t->integer('stage3_dpd'); $t->text('rebuttal_basis')->nullable();
            $t->date('effective_from'); $t->string('status')->default('APPROVED'); $t->integer('proposed_by')->nullable(); $t->integer('approved_by')->nullable(); $t->timestamp('approved_at')->nullable(); $t->string('reason')->nullable(); $t->timestamps();
        });
        (new StagingThresholdSeeder())->run();
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id'); $t->string('contract_id'); $t->string('customer_name')->nullable(); $t->string('external_identity_id')->nullable(); $t->string('customer_id')->nullable(); $t->string('reporting_period');
            $t->string('product_group')->nullable(); $t->string('product_code')->nullable(); $t->integer('tenor')->default(0); $t->integer('overdue_days')->default(0);
            $t->decimal('carrying_amount', 20, 2)->default(0); $t->decimal('ead', 20, 2)->nullable(); $t->decimal('ecl_value', 20, 2)->nullable(); $t->double('interest_rate')->nullable();
            $t->text('build_flag')->nullable(); $t->text('build_basis')->nullable(); $t->integer('sicr')->default(0); $t->integer('ifrs9_stage')->default(0);
            $t->string('ifrs9stage_pre_qualitative')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->string('calculated_ifrs9_stage')->nullable();
            $t->integer('staging_dpd')->nullable(); $t->string('staging_dpd_basis')->nullable();
            foreach (['arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180', 'arrears_180_to_270', 'arrears_271_to_360'] as $c) { $t->string($c)->nullable(); }
        });
        Schema::create('reporting_period_locks', function (Blueprint $t) { $t->increments('id'); $t->string('reporting_period')->unique(); $t->integer('locked_by')->nullable(); $t->timestamp('locked_at')->nullable(); $t->string('reason')->nullable(); $t->text('settings_snapshot')->nullable(); $t->timestamps(); });
        (require base_path('database/migrations/2026_10_08_000000_create_ebanker_landing_zone.php'))->up();
        (require base_path('database/migrations/2026_10_15_140000_create_staging_comparison_tables.php'))->up();
        $this->createSicrSchema();

        $base = ['reporting_period' => '2026-09', 'carrying_amount' => 1000, 'ead' => 1000, 'ecl_value' => 50, 'interest_rate' => 12];
        foreach ([
            ['contract_id' => 'short-100', 'tenor' => 12, 'overdue_days' => 100, 'arrears_91_to_180' => '500'],
            ['contract_id' => 'long-100', 'tenor' => 36, 'overdue_days' => 100, 'arrears_91_to_180' => '500'],
            ['contract_id' => 'long-200', 'tenor' => 36, 'overdue_days' => 200, 'arrears_180_to_270' => '500', 'ecl_value' => 400],
            ['contract_id' => 'part-cured', 'tenor' => 36, 'overdue_days' => 200, 'arrears_30_to_90' => '500'],
            ['contract_id' => 'current', 'tenor' => 36, 'overdue_days' => 0, 'ecl_value' => 10],
        ] as $row) {
            DB::table('loan_books')->insert($row + $base + ['customer_name' => 'Borrower ' . $row['contract_id']]);
        }
        (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2026-09', 1);
    }

    private function service(): StagingComparisonService
    {
        return new StagingComparisonService(new GovernanceService(), new StagingService(new GovernanceService(), new LandingZoneReader()));
    }

    private function booked(): array
    {
        return DB::table('loan_books')->orderBy('contract_id')->get(['contract_id', 'ifrs9stage_post_qualitative', 'ecl_value'])->map(fn ($r) => (array) $r)->all();
    }

    public function test_every_loan_that_changes_stage_is_listed_with_its_reason_and_ecl_effect(): void
    {
        $before = $this->booked();
        $id = $this->service()->run('2026-09', StagingPolicy::named(StagingPolicy::PRESET_DIRECTIVE), 1, 'What adopting the directive would change');

        $this->assertSame($before, $this->booked(), 'nothing booked changes');
        $run = DB::table('staging_comparison_runs')->find($id);
        $this->assertSame([StagingPolicy::PRESET_SINGLE, StagingPolicy::PRESET_DIRECTIVE, 5, 2], [$run->booked_preset, $run->compared_preset, (int) $run->loans, (int) $run->loans_moved]);

        $rows = DB::table('staging_comparison_rows')->where('run_id', $id)->get()->keyBy('contract_id');
        $this->assertSame([2, 3, 'threshold profile'], [(int) $rows['short-100']->booked_stage, (int) $rows['short-100']->compared_stage, $rows['short-100']->reason]);
        $this->assertSame([2, 3, 'counting basis'], [(int) $rows['part-cured']->booked_stage, (int) $rows['part-cured']->compared_stage, $rows['part-cured']->reason]);
        $this->assertSame([31, 200], [(int) $rows['part-cured']->booked_dpd, (int) $rows['part-cured']->compared_dpd]);
        foreach (['long-100', 'long-200', 'current'] as $c) {
            $this->assertSame((int) $rows[$c]->booked_stage, (int) $rows[$c]->compared_stage, $c);
            $this->assertNull($rows[$c]->reason, $c);
            $this->assertEquals(0, $rows[$c]->ecl_effect, $c);
        }
        // the ECL at Stage 3 is the book's Stage 3 coverage (400 over 1,000) on the loan's EAD
        $this->assertEquals([400, 350], [$rows['short-100']->compared_ecl, $rows['short-100']->ecl_effect]);
        // a month's interest at 12% on the Stage 3 allowance is lost on the net basis
        $this->assertEquals(-4, $rows['short-100']->stage3_interest_effect);
        $this->assertSame('Substandard', $rows['short-100']->rbm_class);

        // the totals reconcile to the rows and to the two stagings
        $totals = json_decode($run->totals, true);
        $this->assertSame(['2>3' => ['loans' => 2, 'carrying_amount' => 2000]], $totals['moves']);
        $this->assertEquals(['threshold profile' => 350, 'counting basis' => 350], $totals['ecl_by_reason']);
        $this->assertEquals(-8, $totals['stage3_interest_effect']);
        $this->assertEquals((float) DB::table('loan_books')->sum('ecl_value'), (float) $run->booked_ecl);
        $this->assertEquals((float) DB::table('staging_comparison_rows')->where('run_id', $id)->sum('compared_ecl'), (float) $run->compared_ecl);
        $directive = (new StagingService(new GovernanceService(), new LandingZoneReader()))->evaluate('2026-09', StagingPolicy::named(StagingPolicy::PRESET_DIRECTIVE));
        $this->assertSame(array_map(fn ($l) => $l['stage'], $directive['loans']), DB::table('staging_comparison_rows')->where('run_id', $id)->pluck('compared_stage', 'contract_id')->map(fn ($s) => (int) $s)->all());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Staging Comparison Run')->count());
    }

    public function test_a_locked_month_can_be_compared_and_a_single_setting_tested(): void
    {
        DB::table('reporting_period_locks')->insert(['reporting_period' => '2026-09', 'locked_by' => 1, 'locked_at' => now(), 'reason' => 'Reported']);
        $id = $this->service()->run('2026-09', $this->service()->rulesInForce('2026-09')->with(['staging_threshold_profile' => StagingPolicy::PROFILE_IFRS9]), 1);

        $run = DB::table('staging_comparison_runs')->find($id);
        $this->assertSame(StagingPolicy::PRESET_CUSTOM, $run->compared_preset);
        // 91 days for every facility: the three loans at 91 days or more on the bucket move to Stage 3
        $moved = DB::table('staging_comparison_rows')->where('run_id', $id)->whereColumn('booked_stage', '!=', 'compared_stage')->orderBy('contract_id')->pluck('reason', 'contract_id')->all();
        $this->assertSame(['long-100' => 'threshold profile', 'short-100' => 'threshold profile'], $moved);
    }

    public function test_the_command_runs_a_comparison_and_refuses_an_unknown_setting(): void
    {
        $this->assertSame(0, Artisan::call('staging:compare', ['period' => '2026-09', 'preset' => StagingPolicy::PRESET_DIRECTIVE, '--user' => 1]));
        $this->assertStringContainsString('2 change stage', Artisan::output());
        $this->assertSame(1, Artisan::call('staging:compare', ['period' => '2026-09', 'preset' => StagingPolicy::PRESET_CUSTOM, '--set' => ['no_such_setting=1']]));
        $this->assertSame(1, Artisan::call('staging:compare', ['period' => '2026-09', 'preset' => StagingPolicy::PRESET_CUSTOM, '--set' => ['stage_cure_months=7 months']]));
        $this->assertStringContainsString('not an option', Artisan::output());
    }
}
