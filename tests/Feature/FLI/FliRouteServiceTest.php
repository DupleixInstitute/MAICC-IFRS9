<?php

namespace Tests\Feature\FLI;

use App\Services\Eir\GovernanceService;
use App\Services\Fli\FliRouteService;
use App\Services\Fli\OverlayService;
use App\Services\Fli\TransmissionMethodCatalogue;
use App\Services\Scenario\ScenarioSetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The forward-looking route (spec v4 sections 14.6 to 14.8): a fit is
 * proposed and approved by a second person; the approved fit runs once
 * per scenario of the approved set and the loan's post-FLI PD is the
 * weighted PD across scenarios, Stage 3 at 100 percent, with the route,
 * method, fit and set recorded on the loan; with no approved fit the PD
 * holds and the loan says so.
 */
class FliRouteServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        foreach (['2026_10_09_000000_create_fli_bridge_tables', '2026_10_09_100000_create_governed_scenario_sets'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        Schema::table('fli_fits', function (Blueprint $t) { $t->string('approval_status', 12)->default('NONE'); $t->integer('proposed_by')->nullable(); $t->timestamp('proposed_at')->nullable(); $t->integer('approved_by')->nullable(); $t->string('approver_label')->nullable(); $t->timestamp('approved_at')->nullable(); $t->string('approval_note')->nullable(); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('product_group')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->decimal('pd_prefli', 16, 8)->nullable(); $t->decimal('pd_value', 16, 8)->nullable(); $t->decimal('12m_pd', 8, 2)->nullable(); $t->decimal('fli_adj', 16, 8)->nullable(); $t->decimal('pd_post_fli', 16, 8)->nullable(); $t->string('fli_route')->nullable(); $t->string('fli_method')->nullable(); $t->integer('fli_fit_id')->nullable(); $t->integer('fli_set_id')->nullable(); $t->text('fli_by_scenario')->nullable(); $t->decimal('lgd_value', 16, 8)->nullable(); $t->decimal('ead', 18, 2)->nullable(); $t->decimal('carrying_amount', 20, 2)->default(0); $t->string('calculated_ifrs9_stage')->nullable(); $t->string('ifrs9stage_pre_qualitative')->nullable(); $t->decimal('ecl_value', 18, 2)->nullable(); $t->decimal('remaining_tenor', 8, 2)->nullable(); $t->decimal('commitments', 18, 2)->nullable(); $t->decimal('facility_utilisation_rate', 5, 2)->nullable(); });
        // the overlay register, with the lineage column it adds to the loan (audit M3)
        (require base_path('database/migrations/2026_10_09_300000_create_fli_overlays.php'))->up();
        // the route is a Governance Centre setting; the table so a test can put "Manual overlay" in force
        Schema::create('governance_settings', function (Blueprint $t) { $t->increments('id'); $t->string('key', 60); $t->string('value', 60); $t->text('options'); $t->string('label'); $t->text('description'); $t->string('effective_from'); $t->integer('set_by')->nullable(); $t->integer('approved_by')->nullable(); $t->string('approved_at')->nullable(); $t->string('reason', 500)->nullable(); $t->string('status', 20)->default('PROPOSED'); $t->timestamps(); });
        DB::table('users')->insert([['id' => 1, 'name' => 'Maker', 'created_at' => now(), 'updated_at' => now()], ['id' => 2, 'name' => 'Checker', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('macro_series')->insert(['statistic_code' => 'PLR', 'observation_period' => '202608', 'value' => 20.0, 'value_type' => 'actual']);
        DB::table('fli_relationships')->insert(['id' => 1, 'statistic_code' => 'PLR', 'proxy_code' => 'STAGE3_SHARE', 'r2_cutoff' => 0.3, 'lag_months' => 0, 'created_at' => now(), 'updated_at' => now()]);
        // proxy = 0.01 x PLR + 0.1: at 20 the proxy is 0.3; a PLR five points higher gives 0.35, an adjustment of one sixth
        DB::table('fli_fits')->insert(['id' => 10, 'fli_relationship_id' => 1, 'reporting_period' => '202608', 'slope' => 0.01, 'intercept' => 0.1, 'correlation_r' => 0.9, 'r_squared' => 0.81, 'n_obs' => 24, 'verdict' => 'applied']);
        DB::table('fli_fits')->insert(['id' => 11, 'fli_relationship_id' => 1, 'reporting_period' => '202608', 'slope' => 0.01, 'intercept' => 0.1, 'correlation_r' => 0.2, 'r_squared' => 0.04, 'n_obs' => 24, 'verdict' => 'declined', 'declined_reason' => 'r2<cutoff']);
        DB::table('loan_books')->insert([
            ['contract_id' => 'A', 'reporting_period' => '2026-08', 'ifrs9stage_post_qualitative' => '1', 'pd_prefli' => 0.30],
            ['contract_id' => 'B', 'reporting_period' => '2026-08', 'ifrs9stage_post_qualitative' => '3', 'pd_prefli' => 0.30],
        ]);
    }

    private function service(): FliRouteService
    {
        $gov = new GovernanceService();

        return new FliRouteService($gov, new TransmissionMethodCatalogue($gov), new ScenarioSetService($gov));
    }

    private function approvedSet(string $period = '2026-08'): int
    {
        $sets = new ScenarioSetService(new GovernanceService());
        $id = $sets->create($period, 'Test set', [
            ['name' => 'Base', 'weight' => 50, 'is_base' => true, 'pd_multiplier' => 1, 'calibration_note' => 'base'],
            ['name' => 'Up', 'weight' => 25, 'pd_multiplier' => 0.9, 'calibration_note' => 'up', 'shocks' => [['statistic_code' => 'PLR', 'kind' => 'abs', 'value' => -5]]],
            ['name' => 'Down', 'weight' => 25, 'pd_multiplier' => 1.2, 'calibration_note' => 'down', 'shocks' => [['statistic_code' => 'PLR', 'kind' => 'abs', 'value' => 5]]],
        ], 1);
        $sets->propose($id, 1);
        $sets->approve($id, 2);

        return $id;
    }

    public function test_a_declined_fit_cannot_be_proposed_and_approval_needs_a_second_person(): void
    {
        try {
            $this->service()->proposeFit(11, 1);
            $this->fail('a declined fit was proposed');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('declined by the guardrail', $e->getMessage());
        }
        $this->service()->proposeFit(10, 1);
        try {
            $this->service()->approveFit(10, 1);
            $this->fail('the proposer approved their own fit');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('different person', $e->getMessage());
        }
        $this->service()->approveFit(10, 2);
        $this->assertSame('APPROVED', DB::table('fli_fits')->where('id', 10)->value('approval_status'));
        $this->assertSame(10, (int) $this->service()->approvedFit('2026-08')->id);
    }

    public function test_the_approved_fit_runs_once_per_scenario_and_the_loan_carries_its_lineage(): void
    {
        $setId = $this->approvedSet();
        $this->service()->proposeFit(10, 1);
        $this->service()->approveFit(10, 2);
        $r = $this->service()->apply('2026-08', 1);
        $this->assertSame(10, $r['fit']);
        $this->assertEqualsWithDelta(0.0, $r['scenarios']['Base']['adjustment'], 1e-6);
        $this->assertEqualsWithDelta(0.35 / 0.30 - 1, $r['scenarios']['Down']['adjustment'], 1e-6);
        $this->assertEqualsWithDelta(0.25 / 0.30 - 1, $r['scenarios']['Up']['adjustment'], 1e-6);
        $a = DB::table('loan_books')->where('contract_id', 'A')->first();
        $expected = 0.5 * 0.30 + 0.25 * 0.30 * (0.25 / 0.30) + 0.25 * 0.30 * (0.35 / 0.30);   // weighted PD across scenarios under the scalar
        $this->assertEqualsWithDelta($expected, (float) $a->pd_post_fli, 1e-6);
        $this->assertEqualsWithDelta($expected / 0.30 - 1, (float) $a->fli_adj, 1e-6);
        $this->assertSame('Regression', $a->fli_route);
        $this->assertSame(TransmissionMethodCatalogue::SCALAR, $a->fli_method);
        $this->assertSame(10, (int) $a->fli_fit_id);
        $this->assertSame($setId, (int) $a->fli_set_id);
        $this->assertCount(3, json_decode($a->fli_by_scenario, true));
        $this->assertEqualsWithDelta(1.0, (float) DB::table('loan_books')->where('contract_id', 'B')->value('pd_post_fli'), 1e-9); // Stage 3 at 100 percent
        $this->assertSame(1, $r['adjusted']); // the scalar is linear: symmetric shocks weight back to the pre-FLI PD, so only the Stage 3 loan moved
    }

    public function test_with_no_approved_fit_the_pd_holds_and_the_loan_says_so(): void
    {
        $this->approvedSet();
        $r = $this->service()->apply('2026-08', 1);
        $this->assertNull($r['fit']);
        $this->assertStringContainsString('manual overlay at zero', $r['note']);
        $this->assertEqualsWithDelta(0.30, (float) DB::table('loan_books')->where('contract_id', 'A')->value('pd_post_fli'), 1e-9);
        $this->assertEqualsWithDelta(0.0, (float) DB::table('loan_books')->where('contract_id', 'A')->value('fli_adj'), 1e-9);
        $this->assertNull(DB::table('loan_books')->where('contract_id', 'A')->value('fli_overlay_ids'));
    }

    /**
     * The manual-overlay route reads the register, not the legacy fli_adj row
     * (system audit of 9 October 2026, finding M3): each loan takes the sum
     * of the approved, unexpired overlays whose scope covers it, and its
     * lineage names them.
     */
    public function test_the_manual_overlay_route_reads_the_register_and_the_loan_names_its_overlays(): void
    {
        DB::table('governance_settings')->insert(['key' => 'fli_adjustment_route', 'value' => 'Manual overlay', 'options' => json_encode(['Regression', 'Manual overlay', 'Regression plus overlay']), 'label' => 'route', 'description' => '', 'effective_from' => '2026-01-01', 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('loan_books')->where('contract_id', 'A')->update(['product_group' => 'AGRI']);
        DB::table('loan_books')->insert(['contract_id' => 'D', 'reporting_period' => '2026-08', 'product_group' => 'SME', 'ifrs9stage_post_qualitative' => '1', 'pd_prefli' => 0.20]);
        $this->approvedSet();
        $this->approvedSet('2026-07');
        $overlays = new OverlayService(new ScenarioSetService(new GovernanceService()));
        $book = $overlays->propose(['reporting_period' => '2026-08', 'scope' => 'book', 'adjustment' => 0.10, 'reason' => 'a drought', 'expiry_period' => '2026-12'], 1);
        $overlays->approve($book, 2);
        $agri = $overlays->propose(['reporting_period' => '2026-08', 'scope' => 'product_group', 'scope_value' => 'AGRI', 'adjustment' => 0.25, 'reason' => 'the harvest', 'expiry_period' => '2026-08'], 1);
        $overlays->approve($agri, 2);
        $lapsed = $overlays->propose(['reporting_period' => '2026-07', 'scope' => 'book', 'adjustment' => 0.50, 'reason' => 'last month', 'expiry_period' => '2026-07'], 1);
        $overlays->approve($lapsed, 2);
        $overlays->propose(['reporting_period' => '2026-08', 'scope' => 'book', 'adjustment' => 0.99, 'reason' => 'not yet approved', 'expiry_period' => '2026-08'], 1);

        $r = $this->service()->apply('2026-08', 1);
        $this->assertSame('Manual overlay', $r['route']);
        $this->assertNull($r['fit']);
        $this->assertSame([$book, $agri], array_column($r['overlays'], 'id'));
        $this->assertStringContainsString('2 overlays in force from the register', $r['note']);
        $this->assertEqualsWithDelta(0.10, $r['scenarios']['Overlay']['adjustment'], 1e-9);   // the book-wide overlay on the scenario row
        $a = DB::table('loan_books')->where('contract_id', 'A')->first();
        $d = DB::table('loan_books')->where('contract_id', 'D')->first();
        $this->assertEqualsWithDelta(0.30 * 1.35, (float) $a->pd_post_fli, 1e-6);   // book + AGRI
        $this->assertEqualsWithDelta(0.20 * 1.10, (float) $d->pd_post_fli, 1e-6);   // book only
        $this->assertSame([$book, $agri], json_decode($a->fli_overlay_ids, true));
        $this->assertSame([$book], json_decode($d->fli_overlay_ids, true));
        $this->assertSame('Manual overlay', $a->fli_route);
        $this->assertEqualsWithDelta(1.0, (float) DB::table('loan_books')->where('contract_id', 'B')->value('pd_post_fli'), 1e-9); // Stage 3 at 100 percent
        $this->assertSame(3, $r['adjusted']);
    }
}
