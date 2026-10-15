<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\StagingPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The migration of 15 October 2026 adds the single-ladder and IFRS 9 profile
 * threshold rows to a database seeded before them (MAIIC's server), only where
 * missing, and leaves every existing row, approved changes included, untouched.
 */
class StagingPolicyThresholdMigrationTest extends TestCase
{
    protected $seed = false;

    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('staging_thresholds', function (Blueprint $t) {
            $t->increments('id'); $t->string('facility_class'); $t->integer('min_tenor_months'); $t->integer('stage2_dpd'); $t->integer('stage3_dpd'); $t->text('rebuttal_basis')->nullable();
            $t->date('effective_from'); $t->string('status', 20)->default('APPROVED'); $t->integer('proposed_by')->nullable(); $t->integer('approved_by')->nullable(); $t->timestamp('approved_at')->nullable(); $t->string('reason', 500)->nullable(); $t->timestamps();
        });
        // a server seeded before 15 October 2026, with a threshold MAIIC has since changed through maker-checker
        DB::table('staging_thresholds')->insert([
            ['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 91, 'effective_from' => '2018-07-13', 'status' => 'APPROVED', 'proposed_by' => null, 'approved_by' => null, 'reason' => null],
            ['facility_class' => 'DEFAULT', 'min_tenor_months' => 13, 'stage2_dpd' => 31, 'stage3_dpd' => 181, 'effective_from' => '2018-07-13', 'status' => 'APPROVED', 'proposed_by' => null, 'approved_by' => null, 'reason' => null],
            ['facility_class' => 'DEFAULT', 'min_tenor_months' => 13, 'stage2_dpd' => 31, 'stage3_dpd' => 150, 'effective_from' => '2026-07-01', 'status' => 'APPROVED', 'proposed_by' => 1, 'approved_by' => 2, 'reason' => 'Credit Committee change'],
        ]);
        $this->migration = require base_path('database/migrations/2026_10_15_110000_staging_policy_threshold_rows.php');
    }

    private function snapshot(): array
    {
        return DB::table('staging_thresholds')->where('facility_class', 'DEFAULT')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    public function test_the_profile_rows_are_added_once_as_seeded_defaults_and_no_existing_row_moves(): void
    {
        $before = $this->snapshot();
        $this->migration->up();
        $this->migration->up();

        $this->assertSame($before, $this->snapshot(), 'existing rows, the approved change included, are untouched');
        $single = DB::table('staging_thresholds')->where('facility_class', StagingPolicy::CLASS_SINGLE)->get();
        $this->assertCount(1, $single);
        $this->assertSame([31, 181, 'APPROVED', '1900-01-01'], [(int) $single[0]->stage2_dpd, (int) $single[0]->stage3_dpd, $single[0]->status, $single[0]->effective_from]);
        $this->assertNull($single[0]->approved_by, 'a seeded default, never a MAIIC approval');
        $this->assertStringContainsString('not a MAIIC approval', $single[0]->reason);
        $this->assertSame(1, DB::table('staging_thresholds')->where('facility_class', StagingPolicy::CLASS_IFRS9)->count());

        // staging under the seeded default now finds its row
        $this->assertSame(181, \App\Models\StagingThreshold::forFacility(StagingPolicy::CLASS_SINGLE, 6, '2026-09-30', true)->stage3_dpd);
    }

    public function test_a_profile_row_already_present_is_left_as_it_is(): void
    {
        DB::table('staging_thresholds')->insert(['facility_class' => StagingPolicy::CLASS_SINGLE, 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 200, 'effective_from' => '2026-01-01', 'status' => 'APPROVED', 'approved_by' => 2, 'reason' => 'Approved by MAIIC']);
        $this->migration->up();

        $this->assertSame([200], DB::table('staging_thresholds')->where('facility_class', StagingPolicy::CLASS_SINGLE)->pluck('stage3_dpd')->map(fn ($v) => (int) $v)->all());
    }

    public function test_rolling_back_removes_only_the_rows_it_added(): void
    {
        $this->migration->up();
        $this->migration->down();

        $this->assertSame(0, DB::table('staging_thresholds')->whereIn('facility_class', [StagingPolicy::CLASS_SINGLE, StagingPolicy::CLASS_IFRS9])->count());
        $this->assertSame(3, DB::table('staging_thresholds')->count());
    }
}
