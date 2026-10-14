<?php

namespace Tests\Feature\FLI;

use App\Console\Commands\Bootstrap;
use App\Services\Eir\GovernanceService;
use App\Services\Fli\FliMonthEnd;
use App\Services\Fli\FliRouteService;
use App\Services\Fli\FliWithoutModel;
use App\Services\Fli\MonthEndCurrency;
use Database\Seeders\GovernanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Screens versus bootstrap (14 October 2026; FLI module D-F1 note). On a
 * fresh install the Maintenance month-end stopped at "No model version is in
 * force" and the Overview hid its buttons, while the bootstrap applied the
 * route with no fit and booked the ECL. One governed setting now decides for
 * both (fli_without_model): Proceed (the seeded technical default) runs every
 * step with the PD held and says so; Refuse stops the month-end, the route and
 * the bootstrap with the reason; no approved value stops them too.
 */
class FliWithoutModelTest extends TestCase
{
    use RefreshDatabase;

    private const P = '2026-09';

    protected function setUp(): void
    {
        parent::setUp();
        (new GovernanceSettingsSeeder())->run();
        DB::table('users')->insertOrIgnore([['id' => 901, 'name' => 'Maker', 'email' => 'maker@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]]);
        $set = DB::table('governed_scenario_sets')->insertGetId(['reporting_period' => self::P, 'name' => 'Set', 'version' => 1, 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('governed_scenarios')->insert([
            ['set_id' => $set, 'name' => 'Base', 'weight' => 50, 'is_base' => true, 'order_position' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['set_id' => $set, 'name' => 'Downside', 'weight' => 50, 'is_base' => false, 'order_position' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
        foreach ([['A', '1', 0.10], ['B', '2', 0.20]] as [$c, $stage, $pd]) {
            DB::table('loan_books')->insert(['contract_id' => $c, 'customer_id' => 'C' . $c, 'reporting_year' => 2026, 'reporting_month' => 9, 'reporting_period' => self::P, 'ifrs9stage_post_qualitative' => $stage, 'calculated_ifrs9_stage' => $stage,
                'pd_prefli' => $pd, 'remaining_tenor' => 24, 'carrying_amount' => 1000, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function setValue(string $value): void
    {
        DB::table('governance_settings')->where('key', FliWithoutModel::KEY)->update(['value' => $value]);
        app()->forgetInstance(GovernanceService::class);
    }

    private function step(string $key): array
    {
        return collect(app(FliMonthEnd::class)->steps(self::P))->firstWhere('key', $key);
    }

    public function test_the_setting_is_seeded_as_proceed_a_technical_default_not_a_maiic_approval(): void
    {
        $row = DB::table('governance_settings')->where('key', FliWithoutModel::KEY)->first();
        $this->assertSame(FliWithoutModel::PROCEED, $row->value);
        $this->assertSame('1900-01-01', substr((string) $row->effective_from, 0, 10));
        $this->assertStringContainsString('not a MAIIC approval', $row->reason);
        $this->assertSame([FliWithoutModel::PROCEED, FliWithoutModel::REFUSE], GovernanceService::catalogue()[FliWithoutModel::KEY]['options']);
    }

    public function test_under_proceed_every_step_runs_and_the_screens_show_the_buttons(): void
    {
        $d = app(FliWithoutModel::class)->decide(self::P);
        $this->assertFalse($d['in_force']);
        $this->assertTrue($d['proceed']);

        $model = $this->step('model');
        $this->assertSame('warn', $model['state'], 'a warning, not a stop');
        $this->assertTrue($model['without_model']);
        $this->assertStringContainsString('the PD holds with no forward-looking adjustment', $model['status']);
        $apply = $this->step('apply');
        $this->assertSame('todo', $apply['state'], 'the route button shows (it was blocked)');
        $this->assertSame('optional', $this->step('project')['state']);
        $this->assertStringContainsString(strtolower(FliWithoutModel::PROJECTION_SKIPPED), strtolower($this->step('project')['status']));
        $this->assertNull($this->step('project')['command'], 'nothing to project');

        $r = app(FliRouteService::class)->apply(self::P, 901);
        $this->assertNull($r['model_version']);
        $this->assertSame(0, $r['adjusted']);
        $this->assertSame(2, $r['held']);
        foreach (DB::table('loan_books')->where('reporting_period', self::P)->get() as $l) {
            $this->assertEqualsWithDelta((float) $l->pd_prefli, (float) $l->pd_post_fli, 1e-12, 'the PD holds');
            $this->assertSame(['Base', 'Downside'], array_keys(json_decode($l->fli_by_scenario, true)), 'each scenario of the set at the held PD');
        }
        $this->assertSame('done', $this->step('apply')['state']);
        $this->assertSame('todo', $this->step('ecl')['state'], 'next: the ECL');
        $this->assertNotNull(FliWithoutModel::disclosure(self::P));
        $this->assertStringContainsString(FliWithoutModel::NOTE, (string) FliWithoutModel::disclosure(self::P));
    }

    public function test_the_projection_is_skipped_by_the_command_and_says_why(): void
    {
        $this->artisan('fli:month-end', ['period' => self::P, '--project' => true, '--user' => 901])
            ->expectsOutputToContain(FliWithoutModel::PROJECTION_SKIPPED)
            ->assertExitCode(0);
    }

    public function test_under_refuse_the_month_end_and_the_route_stop_with_the_reason(): void
    {
        $this->setValue(FliWithoutModel::REFUSE);
        $model = $this->step('model');
        $this->assertSame('blocked', $model['state']);
        $this->assertStringContainsString('refuses the month-end until one is', $model['status']);
        $this->assertSame('blocked', $this->step('apply')['state']);

        $this->artisan('fli:month-end', ['period' => self::P, '--all' => true, '--user' => 901])
            ->expectsOutputToContain('refuses the month-end until one is')
            ->assertExitCode(1);

        try {
            app(FliRouteService::class)->apply(self::P, 901);
            $this->fail('the route ran under Refuse');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('refuses the month-end until one is', $e->getMessage());
        }
        $this->assertSame(0, DB::table('loan_books')->whereNotNull('pd_post_fli')->count(), 'nothing written');
    }

    public function test_no_approved_value_stops_the_month_end_naming_the_setting(): void
    {
        DB::table('governance_settings')->where('key', FliWithoutModel::KEY)->delete();
        app()->forgetInstance(GovernanceService::class);
        $d = app(FliWithoutModel::class)->decide(self::P);
        $this->assertFalse($d['proceed']);
        $this->assertStringContainsString('fli_without_model', $d['message']);
        $this->expectException(RuntimeException::class);
        app(FliRouteService::class)->apply(self::P, 901);
    }

    public function test_the_bootstrap_reads_the_same_setting(): void
    {
        $src = file_get_contents(app_path('Console/Commands/Bootstrap.php'));
        $engines = substr($src, strpos($src, 'private function stepEngines('));
        $this->assertStringContainsString('FliWithoutModel::class', $engines);
        $this->assertStringContainsString('$withoutModel->decide($p)', $engines);
        $this->assertStringContainsString("if (! \$decision['proceed']) {\n                        throw new \\RuntimeException", str_replace("\r\n", "\n", $engines), 'Refuse stops the bootstrap, outside the per-step catch');
        $this->assertStringContainsString('FliWithoutModel::PROJECTION_SKIPPED', $engines);
        // the bootstrap's route step is the month-end's
        $this->assertContains('fli-route', array_column(Bootstrap::periodChain(), 0));
    }

    public function test_the_overview_shows_the_banner_and_the_mid_run_state(): void
    {
        $u = \App\Models\User::create(['name' => 'Viewer ' . uniqid(), 'email' => uniqid() . '@example.com', 'password' => bcrypt('password'), 'email_verified_at' => now()]);
        $u->forceFill(['active' => 1])->save();
        $this->actingAs($u)->get(route('fli.overview', ['period' => self::P]))->assertOk()
            ->assertInertia(fn ($page) => $page->component('FLI/Overview')
                ->where('withoutModel', fn ($v) => is_string($v) && str_contains($v, 'the PD holds with no forward-looking adjustment'))
                ->where('currency.mid_run', false));
    }

    public function test_a_step_that_reran_after_the_ecl_marks_the_month_mid_run(): void
    {
        DB::table('loan_books')->where('reporting_period', self::P)->update(['ecl_calculated_at' => '2026-10-14 08:00:00', 'ecl_value' => 10]);
        $this->assertFalse(MonthEndCurrency::state(self::P)['mid_run']);
        DB::table('audit_logs')->insert(['action' => 'PD Segment Run', 'entity_type' => 'segment_parameter_runs', 'reporting_period' => self::P, 'created_at' => '2026-10-14 09:00:00', 'updated_at' => '2026-10-14 09:00:00']);

        $s = MonthEndCurrency::state(self::P);
        $this->assertTrue($s['mid_run']);
        $this->assertStringContainsString('the PD run (2026-10-14 09:00:00)', (string) $s['ecl_stale']);
        $this->assertStringContainsString('is mid-run', (string) $s['message']);
        $this->assertNotSame('done', $this->step('ecl')['state']);
    }
}
