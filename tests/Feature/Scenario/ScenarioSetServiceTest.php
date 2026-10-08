<?php

namespace Tests\Feature\Scenario;

use App\Services\Eir\GovernanceService;
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
 * the weights say.
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
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('reporting_period'); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->decimal('pd_prefli', 16, 8)->nullable(); $t->decimal('pd_value', 16, 8)->nullable(); $t->decimal('12m_pd', 8, 2)->nullable(); $t->decimal('lgd_value', 16, 8)->nullable(); $t->decimal('ead', 18, 2)->nullable(); $t->decimal('carrying_amount', 20, 2)->default(0); });
        DB::table('users')->insert([['id' => 1, 'name' => 'Maker', 'created_at' => now(), 'updated_at' => now()], ['id' => 2, 'name' => 'Checker', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('macro_series')->insert([['statistic_code' => 'MWK_USD', 'observation_period' => '202612', 'value' => 1000], ['statistic_code' => 'GDP_GROWTH', 'observation_period' => '202612', 'value' => 2.0]]);
        DB::table('loan_books')->insert([
            ['reporting_period' => '2026-08', 'ifrs9stage_post_qualitative' => '1', 'pd_prefli' => 0.10, 'lgd_value' => 0.5, 'ead' => 1000],
            ['reporting_period' => '2026-08', 'ifrs9stage_post_qualitative' => '3', 'pd_prefli' => 0.10, 'lgd_value' => 0.5, 'ead' => 1000],
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
}
