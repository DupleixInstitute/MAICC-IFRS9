<?php

namespace Tests\Feature\FLI;

use App\Services\Eir\GovernanceService;
use App\Services\Fli\FliRouteService;
use App\Services\Fli\OverlayService;
use App\Services\Fli\RegressionEngine;
use App\Services\Fli\TransmissionMethodCatalogue;
use App\Services\Scenario\ScenarioSetService;
use App\Support\Fli\GovernedValues;
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
    use \Tests\Concerns\SeedsGovernedDefaults;

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
        // the model registry (FLI module D-F2): an approved fit is published as the version in force
        (require base_path('database/migrations/2026_10_13_100000_fli_module_registry_and_snapshots.php'))->up();
        (require base_path('database/migrations/2026_10_13_120000_fli_model_versions_external.php'))->up();
        // the route is a Governance Centre setting; the table so a test can put "Manual overlay" in force
        Schema::create('governance_settings', function (Blueprint $t) { $t->increments('id'); $t->string('key', 60); $t->string('value', 60); $t->text('options'); $t->string('label'); $t->text('description'); $t->string('effective_from'); $t->integer('set_by')->nullable(); $t->integer('approved_by')->nullable(); $t->string('approved_at')->nullable(); $t->string('reason', 500)->nullable(); $t->string('status', 20)->default('PROPOSED'); $t->timestamps(); });
        DB::table('users')->insert([['id' => 1, 'name' => 'Maker', 'created_at' => now(), 'updated_at' => now()], ['id' => 2, 'name' => 'Checker', 'created_at' => now(), 'updated_at' => now()]]);
        // the route and the scenario rules read their settings with no value in code: the seeded defaults (D21)
        $this->seedGovernedDefaults(['scenario_minimum_count', 'scenario_weight_bounds', 'scenario_calibration_note', 'overlay_requires_approved_set', 'scenario_weighting_method', 'fli_adjustment_route']);
        DB::table('macro_series')->insert(['statistic_code' => 'PLR', 'observation_period' => '202608', 'value' => 20.0, 'value_type' => 'actual']);
        DB::table('fli_relationships')->insert(['id' => 1, 'statistic_code' => 'PLR', 'proxy_code' => 'STAGE3_SHARE', 'r2_cutoff' => 0.3, 'lag_months' => 0, 'created_at' => now(), 'updated_at' => now()]);
        // proxy = 0.01 x PLR + 0.1: at 20 the proxy is 0.3; a PLR five points higher gives 0.35, an adjustment of one sixth
        DB::table('fli_fits')->insert(['id' => 10, 'fli_relationship_id' => 1, 'reporting_period' => '202608', 'slope' => 0.01, 'intercept' => 0.1, 'correlation_r' => 0.9, 'r_squared' => 0.81, 'n_obs' => 24, 'verdict' => 'applied']);
        DB::table('fli_fits')->insert(['id' => 11, 'fli_relationship_id' => 1, 'reporting_period' => '202608', 'slope' => 0.01, 'intercept' => 0.1, 'correlation_r' => 0.2, 'r_squared' => 0.04, 'n_obs' => 24, 'verdict' => 'declined', 'declined_reason' => 'r2<cutoff']);
        DB::table('loan_books')->insert([
            ['contract_id' => 'A', 'reporting_period' => '2026-08', 'ifrs9stage_post_qualitative' => '1', 'pd_prefli' => 0.30],
            ['contract_id' => 'B', 'reporting_period' => '2026-08', 'ifrs9stage_post_qualitative' => '3', 'pd_prefli' => 0.30],
        ]);
        // what a fit is checked against before it is proposed or approved (UI audit item 3): the governed
        // guardrail settings its inputs hash is taken under, the credit-loss measure it was estimated on, the
        // governed forecast horizon the projection reads, and each fit stamped with the hash of the data held
        GovernedValues::ensureDefaults('sqlite');
        foreach (['202512' => 0.28, '202601' => 0.29, '202607' => 0.30] as $p => $v) {
            DB::table('credit_loss_series')->insert(['proxy_code' => 'STAGE3_SHARE', 'proxy_name' => 'Stage 3 share', 'observation_period' => $p, 'value' => $v]);
        }
        DB::table('governance_settings')->insert(['key' => 'fli_forecast_horizon', 'value' => '3 years, then 2 years back to the long-run PD', 'options' => json_encode(['3 years, then 2 years back to the long-run PD']), 'label' => 'horizon', 'description' => '', 'effective_from' => '1900-01-01', 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
        $this->stampFit(10);
        $this->stampFit(11);
    }

    /** The inputs hash a sweep would record for the fit on the data now held, as at its period. */
    private function stampFit(int $id): void
    {
        $f = DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')->where('f.id', $id)->first(['f.reporting_period', 'r.statistic_code', 'r.proxy_code', 'r.lag_months']);
        $hash = (new RegressionEngine('sqlite', new GovernedValues($f->reporting_period, 'sqlite')))->currentInputsHash($f->reporting_period, $f->statistic_code, $f->proxy_code, (int) $f->lag_months);
        $this->assertNotNull($hash, "fit {$id}: the fixture holds its driver and its measure");
        DB::table('fli_fits')->where('id', $id)->update(['inputs_hash' => $hash, 'computed_at' => now()]);
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
        $this->approvedSet();
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

    /**
     * A fit for January 2026 on PLR, lag in months, estimated on the data held; with the approved set of
     * January it is proposed and approved by two people, or (propose false) published as approved, as a
     * version published before its data went missing would be. One Stage 1 loan in January. Returns the set.
     */
    private function januaryFit(int $lag, bool $propose = true): int
    {
        DB::table('fli_relationships')->insert(['id' => 2, 'statistic_code' => 'PLR', 'proxy_code' => 'STAGE3_SHARE', 'r2_cutoff' => 0.3, 'lag_months' => $lag, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('fli_fits')->insert(['id' => 20, 'fli_relationship_id' => 2, 'reporting_period' => '202601', 'slope' => 0.01, 'intercept' => 0.1, 'correlation_r' => 0.9, 'r_squared' => 0.81, 'n_obs' => 24, 'verdict' => 'applied']);
        $this->stampFit(20);
        $setId = $this->approvedSet('2026-01');
        if ($propose) {
            $this->service()->proposeFit(20, 1);
            $this->service()->approveFit(20, 2);
        } else {
            DB::table('fli_fits')->where('id', 20)->update(['approval_status' => 'APPROVED', 'proposed_by' => 1, 'approved_by' => 2, 'approved_at' => now()]);
            (new \App\Services\Fli\FliModelRegistry())->publishFromFit(20, 2);
        }
        DB::table('loan_books')->insert(['contract_id' => 'E', 'reporting_period' => '2026-01', 'ifrs9stage_post_qualitative' => '1', 'pd_prefli' => 0.30, 'remaining_tenor' => 24]);

        return $setId;
    }

    /**
     * The base value is the driver as at the period, under the fitted lag
     * (IFRS 9 B5.5.49 to B5.5.51): for January 2026 with a lag of nine
     * months the base window reads the April 2025 PLR, the twelve-month
     * window reads April 2026, inside the first forecast year, whose base
     * path is the PLR known in January (25.3), never August's 20. A later
     * observation leaves January's adjustments as they were.
     */
    public function test_the_base_value_is_the_driver_as_at_the_period_under_the_fitted_lag(): void
    {
        DB::table('macro_series')->insert([
            ['statistic_code' => 'PLR', 'observation_period' => '202504', 'value' => 26.0, 'value_type' => 'actual'],
            ['statistic_code' => 'PLR', 'observation_period' => '202601', 'value' => 25.3, 'value_type' => 'actual'],
        ]);
        $setId = $this->januaryFit(9);
        $r = $this->service()->apply('2026-01', 1);

        $this->assertSame('202504', (string) $r['base_driver']['base_period']);
        $this->assertEqualsWithDelta(26.0, $r['base_driver']['base_value'], 1e-9);
        $this->assertSame('202604', (string) $r['base_driver']['window_period']);
        $this->assertStringContainsString('forecast year 0', $r['base_driver']['window_driver_source']);
        $this->assertEqualsWithDelta(25.3, $r['scenarios']['Base']['driver'], 1e-9);
        $this->assertEqualsWithDelta(30.3, $r['scenarios']['Down']['driver'], 1e-9);
        $this->assertEqualsWithDelta(20.3, $r['scenarios']['Up']['driver'], 1e-9);
        $predBase = 0.01 * 26.0 + 0.1;
        $this->assertEqualsWithDelta((0.01 * 25.3 + 0.1) / $predBase - 1, $r['scenarios']['Base']['adjustment'], 1e-6);
        $this->assertEqualsWithDelta((0.01 * 30.3 + 0.1) / $predBase - 1, $r['scenarios']['Down']['adjustment'], 1e-6);
        $this->assertSame($setId, (int) DB::table('loan_books')->where('contract_id', 'E')->value('fli_set_id'));

        // February to July arrive (and August is already held): January does not move
        foreach (['202602', '202603', '202604', '202605', '202606', '202607'] as $p) {
            DB::table('macro_series')->insert(['statistic_code' => 'PLR', 'observation_period' => $p, 'value' => 10.0, 'value_type' => 'actual']);
        }
        $again = $this->service()->apply('2026-01', 1);
        $this->assertSame($r['base_driver'], $again['base_driver']);
        $this->assertSame(array_map(fn ($s) => $s['adjustment'], $r['scenarios']), array_map(fn ($s) => $s['adjustment'], $again['scenarios']));
    }

    /** With a lag of twelve months the window's driver is the period's own actual: every scenario takes it. */
    public function test_a_lag_of_twelve_months_puts_the_window_on_data_known_at_the_period(): void
    {
        DB::table('macro_series')->insert([
            ['statistic_code' => 'PLR', 'observation_period' => '202501', 'value' => 24.0, 'value_type' => 'actual'],
            ['statistic_code' => 'PLR', 'observation_period' => '202601', 'value' => 25.3, 'value_type' => 'actual'],
        ]);
        $this->januaryFit(12);
        $r = $this->service()->apply('2026-01', 1);
        $expected = (0.01 * 25.3 + 0.1) / (0.01 * 24.0 + 0.1) - 1;
        foreach (['Base', 'Up', 'Down'] as $name) {
            $this->assertEqualsWithDelta(25.3, $r['scenarios'][$name]['driver'], 1e-9);
            $this->assertEqualsWithDelta($expected, $r['scenarios'][$name]['adjustment'], 1e-6);
        }
        $this->assertStringContainsString('actual 202601', $r['base_driver']['window_driver_source']);
    }

    /**
     * No actual at P - lag known at the period: the route refuses, and no loan is touched. Such a fit can
     * no longer be proposed or approved (see the fit-applicability tests below); the route still fails
     * closed for a version published before, or one whose data was withdrawn after it was published.
     */
    public function test_the_route_fails_closed_when_the_lagged_base_is_not_known_at_the_period(): void
    {
        DB::table('macro_series')->insert(['statistic_code' => 'PLR', 'observation_period' => '202601', 'value' => 25.3, 'value_type' => 'actual']);
        $this->januaryFit(9, false);
        try {
            $this->service()->apply('2026-01', 1);
            $this->fail('the route ran without the lagged base');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cannot run for 2026-01', $e->getMessage());
            $this->assertStringContainsString('202504', $e->getMessage());
        }
        $this->assertNull(DB::table('loan_books')->where('contract_id', 'E')->value('pd_post_fli'));
    }

    /**
     * The booked post-FLI PD reproduces the probability-weighted ECL (spec
     * 15.5): on a Stage 2 loan over three years, whose lifetime PD is concave
     * in the twelve-month PD, the ECL measured on the post-FLI PD equals the
     * weighted ECL across scenarios, and the sensitivity's weighted figure
     * equals the allowance booked on it.
     */
    public function test_the_ecl_on_the_post_fli_pd_is_the_weighted_ecl_across_scenarios(): void
    {
        DB::table('loan_books')->insert(['contract_id' => 'C', 'reporting_period' => '2026-08', 'ifrs9stage_post_qualitative' => '2', 'pd_prefli' => 0.30, 'remaining_tenor' => 36]);
        DB::table('loan_books')->where('reporting_period', '2026-08')->update(['lgd_value' => 0.5, 'carrying_amount' => 1000]);
        $setId = $this->approvedSet();
        $this->service()->proposeFit(10, 1);
        $this->service()->approveFit(10, 2);
        $this->service()->apply('2026-08', 1);

        $c = DB::table('loan_books')->where('contract_id', 'C')->first();
        $life = fn (float $pd12) => 1 - pow(1 - $pd12, 36 / 12);
        $weighted = 0.0;
        foreach (json_decode($c->fli_by_scenario, true) as $s) {
            $weighted += $s['weight'] / 100 * $life((float) $s['pd']);
        }
        $this->assertEqualsWithDelta($weighted, $life((float) $c->pd_post_fli), 1e-8);
        // the averaged twelve-month PD would have overstated it
        $this->assertLessThan($life(0.30), $weighted);

        // book the ECL as the engine measures it, then the sensitivity reconciles to it
        foreach (DB::table('loan_books')->where('reporting_period', '2026-08')->get() as $l) {
            $stagePd = ScenarioSetService::stagePd((object) ['stage' => $l->ifrs9stage_post_qualitative, 'remaining_tenor' => $l->remaining_tenor], (float) $l->pd_post_fli);
            DB::table('loan_books')->where('id', $l->id)->update(['ecl_value' => round($stagePd * 0.5 * 1000, 2)]);
        }
        $sens = (new ScenarioSetService(new GovernanceService()))->sensitivity($setId);
        $this->assertEqualsWithDelta((float) DB::table('loan_books')->where('reporting_period', '2026-08')->sum('ecl_value'), $sens['weighted_ecl'], 0.02);
    }

    /** FLI module D-F1, D-F2: approving a fit publishes a version that governs its month and every later one until replaced. */
    public function test_an_approved_fit_is_a_version_in_force_from_its_month_until_a_later_one(): void
    {
        $this->approvedSet();
        $this->service()->proposeFit(10, 1);
        $this->service()->approveFit(10, 2);
        $registry = new \App\Services\Fli\FliModelRegistry();
        $this->assertNull($registry->inForce('2026-07'), 'a version never governs a month before its own');
        $this->assertSame(10, (int) $registry->inForce('2026-08')->fli_fit_id);
        $this->assertSame(10, (int) $registry->inForce('2027-03')->fli_fit_id, 'the month-end applies the version without a new fit');
        $v = $this->service()->approvedFit('2026-11');
        $this->assertSame('2026-08', $v->effective_from);
        $this->assertNotNull($v->model_version_id);
    }

    /** External Calculations: a model calibrated outside needs a second person, and retiring it sends its months back to the version before. */
    public function test_an_external_model_needs_a_second_person_and_retiring_it_falls_back(): void
    {
        Schema::create('macro_statistics', function (Blueprint $t) { $t->increments('id'); $t->string('statistic_code'); $t->string('statistic_name')->nullable(); });
        DB::table('macro_statistics')->insert(['statistic_code' => 'PLR', 'statistic_name' => 'Prime lending rate']);
        DB::table('credit_loss_series')->insert(['proxy_code' => 'STAGE3_SHARE', 'proxy_name' => 'Stage 3 share', 'observation_period' => '202608', 'value' => 0.3]);
        $this->stampFit(10); // the fit as a sweep records it on the August measure just added
        $this->approvedSet();
        $this->service()->proposeFit(10, 1);
        $this->service()->approveFit(10, 2);
        $registry = new \App\Services\Fli\FliModelRegistry();
        $id = $registry->proposeExternal(['statistic_code' => 'PLR', 'proxy_code' => 'STAGE3_SHARE', 'lag_months' => 3, 'slope' => 0.02, 'intercept' => 0.05,
            'calibrated_period' => '2026-08', 'effective_from' => '2026-10', 'evidence' => 'validation workbook'], 1);
        $this->assertSame(10, (int) $registry->inForce('2026-10')->fli_fit_id, 'a proposal governs nothing');
        try {
            $registry->approveExternal($id, 1);
            $this->fail('the proposer approved their own model');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('different person', $e->getMessage());
        }
        $registry->approveExternal($id, 2);
        $this->assertSame($id, (int) $registry->inForce('2026-10')->id);
        $this->assertSame(10, (int) $registry->inForce('2026-09')->fli_fit_id, 'the month before it keeps the earlier version');
        $registry->retire($id, 2, 'test');
        $this->assertSame(10, (int) $registry->inForce('2026-10')->fli_fit_id);
        try {
            $registry->proposeExternal(['statistic_code' => 'PLR', 'proxy_code' => 'STAGE3_SHARE', 'lag_months' => 0, 'slope' => 1, 'intercept' => 0,
                'calibrated_period' => '2026-08', 'effective_from' => '2026-07', 'evidence' => 'x'], 1);
            $this->fail('a model governed a month before its data');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('B5.5.49', $e->getMessage());
        }
    }

    /** FLI module D-F9: an overlay on the model's own driver is refused unless the proposer says why it is not counted twice. */
    public function test_an_overlay_on_the_models_driver_needs_a_double_count_review(): void
    {
        $sets = new ScenarioSetService(new GovernanceService());
        $this->approvedSet();
        $this->service()->proposeFit(10, 1);
        $this->service()->approveFit(10, 2);
        $overlays = new \App\Services\Fli\OverlayService($sets);
        $bare = $overlays->propose(['reporting_period' => '2026-08', 'scope' => 'book', 'adjustment' => 0.05, 'reason' => 'rates', 'expiry_period' => '2026-08', 'risk_driver' => 'PLR'], 1);
        try {
            $overlays->approve($bare, 2);
            $this->fail('an overlay on the driver of the model in force was approved without a review');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('count that risk twice', $e->getMessage());
        }
        $reviewed = $overlays->propose(['reporting_period' => '2026-08', 'scope' => 'book', 'adjustment' => 0.05, 'reason' => 'rates', 'expiry_period' => '2026-08', 'risk_driver' => 'PLR',
            'double_count_review' => 'the model reads the PLR with a nine-month lag; the overlay covers the rate rise announced after the month-end'], 1);
        $overlays->approve($reviewed, 2);
        $this->assertSame('APPROVED', DB::table('fli_overlays')->where('id', $reviewed)->value('status'));
    }

    /** Audit C9 residual: a null approver (fli:apply --approve with no --user) skipped the different-person check. */
    public function test_a_fit_is_not_approved_without_a_named_approver_or_a_named_proposer(): void
    {
        $this->approvedSet(); // a fit is proposed only with the scenario paths its horizon needs (UI audit item 3)
        $this->service()->proposeFit(10, 1);
        try {
            $this->service()->approveFit(10, null);
            $this->fail('a fit was approved with no approver named');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('different person', $e->getMessage());
        }
        DB::table('fli_fits')->where('id', 10)->update(['proposed_by' => null]);
        try {
            $this->service()->approveFit(10, 2);
            $this->fail('a fit with no named proposer was approved');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('both must be named', $e->getMessage());
        }
        $this->assertSame('PROPOSED', DB::table('fli_fits')->where('id', 10)->value('approval_status'));
    }

    /** The route reads its settings with no value in code: a missing approved value stops it, naming the key. */
    public function test_the_route_stops_when_its_governed_setting_has_no_approved_value(): void
    {
        DB::table('governance_settings')->where('key', 'fli_adjustment_route')->delete();
        $this->expectException(\App\Exceptions\GovernanceSettingMissingException::class);
        $this->service()->apply('2026-08', 1);
    }
}
