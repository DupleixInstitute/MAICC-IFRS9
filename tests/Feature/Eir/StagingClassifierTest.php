<?php

namespace Tests\Feature\Eir;

use App\Models\StagingThreshold;
use App\Services\Eir\StagingClassifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The config-driven classifier must reproduce the previously hardcoded
 * ladder exactly — with the seeded DEFAULT rule, with NO rules, and with
 * NO table at all — and future-dated rules must stay inactive. This is
 * the "zero behaviour change until sign-off" guarantee.
 */
class StagingClassifierTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    private function createTable(): void
    {
        Schema::create('staging_thresholds', function (Blueprint $t) {
            $t->increments('id');
            $t->string('facility_class')->default('DEFAULT');
            $t->unsignedSmallInteger('min_tenor_months')->default(0);
            $t->unsignedSmallInteger('stage2_dpd');
            $t->unsignedSmallInteger('stage3_dpd');
            $t->text('rebuttal_basis')->nullable();
            $t->date('effective_from');
            $t->timestamps();
        });
    }

    private function seedDefault(): void
    {
        StagingThreshold::create([
            'facility_class'   => 'DEFAULT',
            'min_tenor_months' => 0,
            'stage2_dpd'       => 31,
            'stage3_dpd'       => 181,
            'effective_from'   => '2020-01-01',
        ]);
    }

    /** The legacy ladder, bucket by bucket. */
    private function legacyExpectations(): array
    {
        return [
            [['271_360_days' => '1,000'], '3'],
            [['181_270_days' => '500'],   '3'],
            [['91_180_days'  => '250'],   '2'],
            [['31_90_days'   => '100'],   '2'],
            [['1_30_days'    => '50'],    '1'],
            [[],                          '1'],
            // The tape's garbage placeholders must read as "no arrears".
            [['31_90_days' => ' -   '],   '1'],
            [['31_90_days' => '-'],       '1'],
            // Dash-variant headers.
            [['181-270_days' => '2,500'], '3'],
            // Highest bucket wins even when lower ones are also filled.
            [['1_30_days' => '10', '271_360_days' => '99'], '3'],
        ];
    }

    public function test_matches_legacy_ladder_with_seeded_default_rule(): void
    {
        $this->createTable();
        $this->seedDefault();

        $classifier = new StagingClassifier('2026-08-31');
        foreach ($this->legacyExpectations() as [$row, $expected]) {
            $this->assertSame($expected, $classifier->classify($row), json_encode($row));
        }
    }

    /** With no rule in force the import stops; it no longer falls back to a 31/181 ladder in code (H11). */
    public function test_with_no_rule_in_force_the_classifier_stops_and_names_it(): void
    {
        $this->createTable();

        $classifier = new StagingClassifier('2026-08-31');
        $this->assertSame('1', $classifier->classify([]), 'a loan with no arrears needs no rule');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No approved staging threshold is in force');
        $classifier->classify(['31_90_days' => '100']);
    }

    /** The rule is read at the period end, not today: a rule effective later does not govern an earlier month. */
    public function test_the_rule_is_read_at_the_period_end_not_today(): void
    {
        $this->createTable();
        $this->seedDefault();
        StagingThreshold::create(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 61, 'stage3_dpd' => 181, 'effective_from' => '2026-06-01']);

        $this->assertSame('2', (new StagingClassifier('2026-05-31'))->classify(['31_90_days' => '100']));
        $this->assertSame('1', (new StagingClassifier('2026-08-31'))->classify(['31_90_days' => '100']), 'the 31-90 bucket reads as 31 days, under the 61 in force from June');
        $this->assertSame('2', (new StagingClassifier('2026-08-31'))->classify(['91_180_days' => '100']));
    }

    /** A proposed row (maker-checker, H11) does not govern until a second person approves it. */
    public function test_a_proposed_rule_does_not_govern(): void
    {
        $this->createTable();
        Schema::table('staging_thresholds', function (Blueprint $t) {
            $t->string('status', 20)->default('APPROVED'); $t->unsignedBigInteger('proposed_by')->nullable(); $t->unsignedBigInteger('approved_by')->nullable(); $t->timestamp('approved_at')->nullable(); $t->string('reason', 500)->nullable();
        });
        $this->seedDefault();
        StagingThreshold::create(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 91, 'effective_from' => '2026-01-01', 'status' => 'PROPOSED', 'proposed_by' => 1]);

        $this->assertSame('2', (new StagingClassifier('2026-08-31'))->classify(['91_180_days' => '100']), 'the proposal (Stage 3 at 91) is not in force');
    }

    public function test_future_dated_rule_is_inactive(): void
    {
        $this->createTable();
        $this->seedDefault();
        StagingThreshold::create([
            'facility_class'   => 'LONG_TERM',
            'min_tenor_months' => 36,
            'stage2_dpd'       => 91,
            'stage3_dpd'       => 181,
            'rebuttal_basis'   => 'RBM directive — pending sign-off',
            'effective_from'   => '2099-01-01',
        ]);

        // Even asking for LONG_TERM with qualifying tenor: the future-dated
        // rule must not govern; DEFAULT (31 DPD) still applies.
        $classifier = new StagingClassifier('2026-08-31');
        $this->assertSame('2', $classifier->classify(['31_90_days' => '100'], 'LONG_TERM', 60));
    }

    public function test_activated_long_term_rule_rebuts_thirty_dpd(): void
    {
        $this->createTable();
        $this->seedDefault();
        StagingThreshold::create([
            'facility_class'   => 'LONG_TERM',
            'min_tenor_months' => 36,
            'stage2_dpd'       => 91,
            'stage3_dpd'       => 181,
            'rebuttal_basis'   => 'RBM directive — signed',
            'effective_from'   => '2026-01-01',
        ]);

        // 31-90 bucket: DEFAULT stages it 2; the active LONG_TERM rule
        // (90-day backstop) keeps a long-tenor facility in stage 1.
        $long = new StagingClassifier('2026-08-31');
        $this->assertSame('1', $long->classify(['31_90_days' => '100'], 'LONG_TERM', 60));
        $this->assertSame('2', $long->classify(['91_180_days' => '100'], 'LONG_TERM', 60));

        // A short-tenor facility never picks up the LONG_TERM rule.
        $short = new StagingClassifier('2026-08-31');
        $this->assertSame('2', $short->classify(['31_90_days' => '100'], 'LONG_TERM', 12));
    }
}
