<?php

namespace Tests\Feature\Eir;

use App\Models\StagingThreshold;
use Database\Seeders\StagingThresholdSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The seeded staging thresholds follow the RBM Financial Services (Credit Risk
 * Management for Development Finance Institutions) Directive, 2018: Stage 3 at
 * the directive's non-performing line (91 days short-term, 181 days medium and
 * long-term), Stage 2 at 31 days everywhere, the Mega Farm class short-term
 * whatever its tenor, and the long-term Stage 2 rebuttal inactive until signed.
 */
class StagingThresholdSeederTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('staging_thresholds', function (Blueprint $t) {
            $t->increments('id');
            $t->string('facility_class')->default('DEFAULT');
            $t->unsignedInteger('min_tenor_months')->default(0);
            $t->unsignedInteger('stage2_dpd');
            $t->unsignedInteger('stage3_dpd');
            $t->text('rebuttal_basis')->nullable();
            $t->date('effective_from');
            $t->timestamps();
        });
        (new StagingThresholdSeeder())->run();
    }

    public function test_the_seeder_writes_four_rows_once_and_is_idempotent(): void
    {
        $this->assertSame(4, StagingThreshold::count());
        (new StagingThresholdSeeder())->run();
        $this->assertSame(4, StagingThreshold::count());
    }

    public function test_short_term_facilities_are_stage_3_at_91_days_as_the_directive_classifies_them(): void
    {
        $rule = StagingThreshold::forFacility(null, 12);
        $this->assertSame(31, (int) $rule->stage2_dpd);
        $this->assertSame(91, (int) $rule->stage3_dpd);
        $this->assertStringContainsString('s.10(c)(i)', $rule->rebuttal_basis);
    }

    public function test_medium_and_long_term_facilities_are_stage_3_at_181_days_with_the_rebuttal_cited(): void
    {
        foreach ([13, 36, 120] as $tenor) {
            $rule = StagingThreshold::forFacility(null, $tenor);
            $this->assertSame(31, (int) $rule->stage2_dpd, "tenor {$tenor}");
            $this->assertSame(181, (int) $rule->stage3_dpd, "tenor {$tenor}");
            $this->assertStringContainsString('B5.5.37', $rule->rebuttal_basis);
            $this->assertStringContainsString('Directive, 2018', $rule->rebuttal_basis);
        }
    }

    public function test_mega_farm_facilities_are_short_term_whatever_their_tenor(): void
    {
        $rule = StagingThreshold::forFacility('MEGA_FARM', 60);
        $this->assertSame('MEGA_FARM', $rule->facility_class);
        $this->assertSame(91, (int) $rule->stage3_dpd);
    }

    public function test_the_long_term_stage_2_rebuttal_is_inactive_until_signed(): void
    {
        $rule = StagingThreshold::forFacility('LONG_TERM', 60);
        // The future-dated LONG_TERM row must not govern; the DEFAULT medium/long row does.
        $this->assertSame('DEFAULT', $rule->facility_class);
        $this->assertSame(31, (int) $rule->stage2_dpd);
        $proposal = StagingThreshold::where('facility_class', 'LONG_TERM')->first();
        $this->assertSame('2099-01-01', $proposal->effective_from->toDateString());
        $this->assertStringContainsString('PENDING CFO SIGN-OFF', $proposal->rebuttal_basis);
    }
}
