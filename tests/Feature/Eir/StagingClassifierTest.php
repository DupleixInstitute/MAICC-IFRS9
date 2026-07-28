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

        $classifier = new StagingClassifier();
        foreach ($this->legacyExpectations() as [$row, $expected]) {
            $this->assertSame($expected, $classifier->classify($row), json_encode($row));
        }
    }

    public function test_matches_legacy_ladder_with_empty_config_table(): void
    {
        $this->createTable();

        $classifier = new StagingClassifier();
        foreach ($this->legacyExpectations() as [$row, $expected]) {
            $this->assertSame($expected, $classifier->classify($row), json_encode($row));
        }
    }

    public function test_matches_legacy_ladder_with_no_table_at_all(): void
    {
        $classifier = new StagingClassifier();
        foreach ($this->legacyExpectations() as [$row, $expected]) {
            $this->assertSame($expected, $classifier->classify($row), json_encode($row));
        }
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
        $classifier = new StagingClassifier();
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
        $long = new StagingClassifier();
        $this->assertSame('1', $long->classify(['31_90_days' => '100'], 'LONG_TERM', 60));
        $this->assertSame('2', $long->classify(['91_180_days' => '100'], 'LONG_TERM', 60));

        // A short-tenor facility never picks up the LONG_TERM rule.
        $short = new StagingClassifier();
        $this->assertSame('2', $short->classify(['31_90_days' => '100'], 'LONG_TERM', 12));
    }
}
