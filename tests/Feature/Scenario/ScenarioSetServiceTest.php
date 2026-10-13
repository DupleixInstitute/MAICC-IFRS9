<?php

namespace Tests\Feature\Scenario;

use App\Services\Eir\GovernanceService;
use App\Services\Fli\OverlayService;
use App\Services\Scenario\OverlayPendingException;
use App\Services\Scenario\ScenarioSetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The scenario set as a governed object (spec v4 section 15): the rules
 * refuse a set that breaks them with the reason named; a downside is a
 * transformation of the base; approval is by a second person; a lock is
 * final and a change is a new version; the sensitivity moves the ECL as
 * the weights say. The overlay tie and the editor follow the system audit
 * of 9 October 2026, findings M4 and M5.
 */
class ScenarioSetServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        (require base_path('database/migrations/2026_10_09_100000_create_governed_scenario_sets.php'))->up();
        Schema::create('macro_series', function (Blueprint $t) { $t->increments('id'); $t->string('statistic_code'); $t->string('observation_period'); $t->decimal('value', 18, 6); $t->string('value_type')->default('actual'); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('reporting_period'); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->decimal('pd_prefli', 16, 8)->nullable(); $t->decimal('pd_value', 16, 8)->nullable(); $t->decimal('12m_pd', 8, 2)->nullable(); $t->decimal('lgd_value', 16, 8)->nullable(); $t->decimal('ead', 18, 2)->nullable(); $t->string('calculated_ifrs9_stage')->nullable(); $t->string('ifrs9stage_pre_qualitative')->nullable(); $t->decimal('commitments', 18, 2)->nullable(); $t->decimal('facility_utilisation_rate', 5, 2)->nullable(); $t->decimal('remaining_tenor', 8, 2)->nullable(); $t->text('fli_by_scenario')->nullable(); $t->integer('fli_set_id')->nullable(); $t->decimal('pd_post_fli', 16, 8)->nullable(); $t->decimal('ecl_value', 18, 2)->nullable(); $t->decimal('carrying_amount', 20, 2)->default(0); });
        // the overlay register and the columns it adds to the set and the loan (audit M3, M4)
        (require base_path('database/migrations/2026_10_09_300000_create_fli_overlays.php'))->up();
        DB::table('users')->insert([['id' => 1, 'name' => 'Maker', 'created_at' => now(), 'updated_at' => now()], ['id' => 2, 'name' => 'Checker', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('macro_series')->insert([['statistic_code' => 'MWK_USD', 'observation_period' => '202606', 'value' => 1000], ['statistic_code' => 'GDP_GROWTH', 'observation_period' => '202606', 'value' => 2.0]]);
        DB::table('loan_books')->insert([
            // EAD is carrying + commitments x utilisation, as the ECL engine measures it (audit M9); 24 months remaining so Stage 1 is a full twelve-month PD
            ['reporting_period' => '2026-08', 'ifrs9stage_post_qualitative' => '1', 'pd_prefli' => 0.10, 'lgd_value' => 0.5, 'ead' => 1000, 'carrying_amount' => 1000, 'remaining_tenor' => 24],
            ['reporting_period' => '2026-08', 'ifrs9stage_post_qualitative' => '3', 'pd_prefli' => 0.10, 'lgd_value' => 0.5, 'ead' => 1000, 'carrying_amount' => 1000, 'remaining_tenor' => 24],
        ]);
    }

    private function service(): ScenarioSetService
    {
        return new ScenarioSetService(new GovernanceService());
    }

    public function test_the_first_set_passes_its_rules_and_a_downside_is_a_transformation_of_the_base(): void
    {
        $id = $this->service()->seedFirstSet('2026-08', 1);
        $this->assertSame('PROPOSED', DB::table('governed_scenario_sets')->where('id', $id)->value('status'));
        $v = $this->service()->validate($id);
        $this->assertTrue($v['ok'], implode('; ', $v['problems']));
        $this->assertEquals(100, $v['weights_sum']);
        $paths = $this->service()->paths($id);
        $this->assertEquals(1000, $paths['base']['MWK_USD'][0]);
        $this->assertEquals(1440, $paths['scenarios']['Downside']['path']['MWK_USD'][0]);   // +44 percent
        $this->assertEquals(0.5, $paths['scenarios']['Downside']['path']['GDP_GROWTH'][0]); // 2.0 - 1.5
        $this->assertEquals(1000, $paths['scenarios']['Upside']['path']['MWK_USD'][0]);     // no shock on the rate
    }

    public function test_a_set_that_breaks_a_rule_is_refused_with_the_reason(): void
    {
        $id = $this->service()->create('2026-09', 'Bad set', [
            ['name' => 'Base', 'weight' => 30, 'is_base' => true, 'pd_multiplier' => 1],
            ['name' => 'Downside', 'weight' => 70, 'pd_multiplier' => 1.5, 'shocks' => [['statistic_code' => 'CPI', 'kind' => 'abs', 'value' => 5]]],
        ], 1);
        $v = $this->service()->validate($id);
        $this->assertFalse($v['ok']);
        $this->assertContains('2 scenarios; the rule asks for at least 3', $v['problems']);
        $joined = implode(' | ', $v['problems']);
        $this->assertMatchesRegularExpression('/base weight 30(\.00)? is below the floor of 40/', $joined);
        $this->assertMatchesRegularExpression('/Downside weight 70(\.00)? is above the ceiling of 60/', $joined);
        $this->assertContains('Downside has no calibration note', $v['problems']);
        $this->expectException(RuntimeException::class);
        $this->service()->propose($id, 1);
    }

    public function test_approval_needs_a_second_person_and_a_lock_is_final(): void
    {
        $id = $this->service()->seedFirstSet('2026-08', 1);
        try {
            $this->service()->approve($id, 1);
            $this->fail('the proposer approved their own set');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('different person', $e->getMessage());
        }
        $this->service()->approve($id, 2);
        $set = DB::table('governed_scenario_sets')->where('id', $id)->first();
        $this->assertSame('APPROVED', $set->status);
        $sens = json_decode($set->sensitivity, true);
        $this->assertSame(2, $sens['loans']);
        // Stage 3 at 100 percent: 1000 x 1 x 0.5 = 500 in every scenario; the Stage 1 loan moves with the multiplier
        $this->assertEquals(500 + 1000 * 0.10 * 0.5, $sens['per_scenario']['Base']['ecl']);
        $this->assertEquals(500 + 1000 * 0.135 * 0.5, $sens['per_scenario']['Downside']['ecl']);
        $this->assertGreaterThan($sens['weighted_ecl'], $sens['ten_points_to_downside']);
        $this->assertLessThan($sens['weighted_ecl'], $sens['ten_points_to_upside']);
        $this->service()->lock($id, 2);
        $this->assertSame('LOCKED', DB::table('governed_scenario_sets')->where('id', $id)->value('status'));
        $newId = $this->service()->newVersion($id, 'Dr Thom moved five points to the downside', 1);
        $this->assertSame(2, (int) DB::table('governed_scenario_sets')->where('id', $newId)->value('version'));
        $this->assertSame($id, (int) DB::table('governed_scenario_sets')->where('id', $newId)->value('supersedes_id'));
        $this->assertSame('LOCKED', DB::table('governed_scenario_sets')->where('id', $id)->value('status'));
        $this->assertSame(4, DB::table('governed_scenarios')->where('set_id', $newId)->count());
    }

    /**
     * The overlay tie of spec 15.7 (system audit of 9 October 2026, finding
     * M4): a set cannot lock while an overlay of its period is still proposed,
     * and approval writes the overlays in force on the set.
     */
    public function test_a_lock_is_refused_while_an_overlay_of_the_period_is_proposed(): void
    {
        $id = $this->service()->seedFirstSet('2026-08', 1);
        $this->service()->approve($id, 2);
        $this->assertSame([], json_decode(DB::table('governed_scenario_sets')->where('id', $id)->value('overlays_at_approval'), true)['overlays']);
        $overlays = new OverlayService($this->service());
        $first = $overlays->propose(['reporting_period' => '2026-08', 'scope' => 'book', 'adjustment' => 0.10, 'reason' => 'a drought', 'expiry_period' => '2026-09'], 1);
        $overlays->approve($first, 2);
        $second = $overlays->propose(['reporting_period' => '2026-08', 'scope' => 'book', 'adjustment' => 0.05, 'reason' => 'a devaluation', 'expiry_period' => '2026-08'], 1);
        try {
            $this->service()->lock($id, 2);
            $this->fail('the set locked with an overlay still proposed');
        } catch (OverlayPendingException $e) {
            $this->assertStringContainsString("overlay {$second} for 2026-08 is still proposed", $e->getMessage());
        }
        $this->assertSame('APPROVED', DB::table('governed_scenario_sets')->where('id', $id)->value('status'));
        $this->assertSame([$second], $this->service()->validate($id)['overlays_pending']);
        // rejected, the way is clear; an overlay for another period never held the lock
        $overlays->reject($second, 2, 'not supported');
        $this->service()->lock($id, 2);
        $this->assertSame('LOCKED', DB::table('governed_scenario_sets')->where('id', $id)->value('status'));
        // a new version approved now records the overlay in force
        $newId = $this->service()->newVersion($id, 'weights revisited', 1);
        $this->service()->propose($newId, 1);
        $this->service()->approve($newId, 2);
        $recorded = json_decode(DB::table('governed_scenario_sets')->where('id', $newId)->value('overlays_at_approval'), true)['overlays'];
        $this->assertCount(1, $recorded);
        $this->assertSame($first, $recorded[0]['id']);
        $this->assertEqualsWithDelta(0.10, $recorded[0]['adjustment'], 1e-9);
    }

    /**
     * The editor (system audit of 9 October 2026, finding M5): the proposer
     * changes weights, scenarios and shocks while the set is proposed; a
     * change that would break a rule is refused and nothing is written; an
     * approved set cannot be changed at all.
     */
    public function test_the_proposer_edits_a_proposed_set_within_the_rules_and_an_approved_set_not_at_all(): void
    {
        $id = $this->service()->seedFirstSet('2026-08', 1);
        $byName = fn () => DB::table('governed_scenarios')->where('set_id', $id)->get()->keyBy('name');
        $base = $byName()['Base']; $down = $byName()['Downside']; $severe = $byName()['Severe'];

        // weights that do not sum to 100 are refused and the set is untouched
        try {
            $this->service()->updateProposed($id, ['scenarios' => [['id' => $base->id, 'weight' => 55]]], 1);
            $this->fail('a weight change that breaks the sum was accepted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('weights sum to 105', $e->getMessage());
        }
        $this->assertEquals(50, (float) $byName()['Base']->weight);
        // a second person may not edit the proposer's set
        try {
            $this->service()->updateProposed($id, ['scenarios' => [['id' => $base->id, 'weight' => 50]]], 2);
            $this->fail('someone other than the proposer edited the set');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Only the proposer', $e->getMessage());
        }
        // five points from the base to the downside, a sharper devaluation shock, a new scenario, Severe removed
        $v = $this->service()->updateProposed($id, [
            'name' => 'First set, weights revisited',
            'scenarios' => [
                ['id' => $base->id, 'weight' => 45],
                ['id' => $down->id, 'weight' => 30, 'shocks' => [['statistic_code' => 'MWK_USD', 'kind' => 'pct', 'value' => 60, 'note' => 'a sharper devaluation'], ['statistic_code' => 'GDP_GROWTH', 'kind' => 'abs', 'value' => -2]]],
                ['name' => 'Harvest shock', 'weight' => 10, 'pd_multiplier' => 1.5, 'anchored_to' => '2016', 'calibration_note' => 'the 2016 drought alone', 'shocks' => [['statistic_code' => 'AGRI_GROWTH', 'kind' => 'mult', 'value' => 0.5]]],
            ],
            'remove' => [$severe->id],
        ], 1);
        $this->assertTrue($v['ok'], implode('; ', $v['problems']));
        $this->assertSame('First set, weights revisited', DB::table('governed_scenario_sets')->where('id', $id)->value('name'));
        $this->assertSame('PROPOSED', DB::table('governed_scenario_sets')->where('id', $id)->value('status'));
        $this->assertEquals(45, (float) $byName()['Base']->weight);
        $this->assertEquals(30, (float) $byName()['Downside']->weight);
        $this->assertArrayNotHasKey('Severe', $byName()->all());
        $this->assertSame(['Base', 'Downside', 'Harvest shock', 'Upside'], $byName()->keys()->sort()->values()->all());
        $paths = $this->service()->paths($id);
        $this->assertEquals(1600, $paths['scenarios']['Downside']['path']['MWK_USD'][0]);   // the sharper shock replaced the old one
        $this->assertEquals(0.0, $paths['scenarios']['Downside']['path']['GDP_GROWTH'][0]);  // 2.0 - 2
        $this->assertSame(1, DB::table('scenario_shocks')->where('scenario_id', $byName()['Harvest shock']->id)->count());
        $this->assertSame('Scenario Set Edited', DB::table('audit_logs')->where('entity_type', 'governed_scenario_sets')->orderByDesc('id')->value('action'));
        // the base cannot be removed; a shock needs a known kind
        foreach ([['remove' => [$base->id]], ['scenarios' => [['id' => $down->id, 'shocks' => [['statistic_code' => 'CPI', 'kind' => 'double', 'value' => 1]]]]]] as $bad) {
            try {
                $this->service()->updateProposed($id, $bad, 1);
                $this->fail('accepted ' . json_encode($bad));
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        // approved: read-only
        $this->service()->approve($id, 2);
        try {
            $this->service()->updateProposed($id, ['scenarios' => [['id' => $base->id, 'weight' => 45]]], 1);
            $this->fail('an approved set was changed');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cannot be changed', $e->getMessage());
        }
        try {
            $this->service()->addScenario($id, ['name' => 'Late', 'weight' => 0], 1);
            $this->fail('a scenario was added to an approved set');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cannot be changed', $e->getMessage());
        }
        // a draft (the new version) may be left failing its rules; propose() is what refuses it
        $newId = $this->service()->newVersion($id, 'the CFO asks for a fifth scenario', 1);
        $v = $this->service()->updateProposed($newId, ['scenarios' => [['name' => 'Fifth', 'weight' => 5, 'pd_multiplier' => 1.1, 'calibration_note' => 'note', 'shocks' => [['statistic_code' => 'CPI', 'kind' => 'abs', 'value' => 1]]]]], 1);
        $this->assertFalse($v['ok']);
        $this->assertContains('weights sum to 105, not 100', $v['problems']);
        $this->assertSame(5, DB::table('governed_scenarios')->where('set_id', $newId)->count());
    }
}
