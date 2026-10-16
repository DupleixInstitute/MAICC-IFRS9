<?php

namespace Tests\Feature\FLI;

use App\Services\Eir\GovernanceService;
use App\Services\Fli\FliRouteService;
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
 * A fit the data cannot support, or a stale one, is neither proposed nor
 * approved (UI audit item 3; FLI module D-F1, D-F2, D-F7 and section 7).
 * Approving a fit publishes the model version in force from its month, so
 * the inputs the route and the projection read for that month must be
 * there, as knowable at its end: the driver's actual lag months before it,
 * the actuals or the scenario path values of each forecast year of the
 * governed horizon; and the fit must still be the estimate a sweep would
 * make on the data now held, not one set aside or replaced.
 */
class FliFitApplicabilityTest extends TestCase
{
    use \Tests\Concerns\SeedsGovernedDefaults;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        // these tests are of other behaviour; the evidence a workflow needs before approval is tested in ApprovalWorkflowsTest
        config(['approvals.require_evidence' => false]);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        foreach (['2026_10_09_000000_create_fli_bridge_tables', '2026_10_09_100000_create_governed_scenario_sets'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        Schema::table('fli_fits', function (Blueprint $t) { $t->string('approval_status', 12)->default('NONE'); $t->integer('proposed_by')->nullable(); $t->timestamp('proposed_at')->nullable(); $t->integer('approved_by')->nullable(); $t->string('approver_label')->nullable(); $t->timestamp('approved_at')->nullable(); $t->string('approval_note')->nullable(); });
        // the loans a scenario set's approval measures its sensitivity on (none are needed here)
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('product_group')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->decimal('pd_prefli', 16, 8)->nullable(); $t->decimal('pd_value', 16, 8)->nullable(); $t->decimal('12m_pd', 8, 2)->nullable(); $t->decimal('fli_adj', 16, 8)->nullable(); $t->decimal('pd_post_fli', 16, 8)->nullable(); $t->string('fli_route')->nullable(); $t->string('fli_method')->nullable(); $t->integer('fli_fit_id')->nullable(); $t->integer('fli_set_id')->nullable(); $t->text('fli_by_scenario')->nullable(); $t->decimal('lgd_value', 16, 8)->nullable(); $t->decimal('ead', 18, 2)->nullable(); $t->decimal('carrying_amount', 20, 2)->default(0); $t->string('calculated_ifrs9_stage')->nullable(); $t->string('ifrs9stage_pre_qualitative')->nullable(); $t->decimal('ecl_value', 18, 2)->nullable(); $t->decimal('remaining_tenor', 8, 2)->nullable(); $t->decimal('commitments', 18, 2)->nullable(); $t->decimal('facility_utilisation_rate', 5, 2)->nullable(); });
        foreach (['2026_10_13_100000_fli_module_registry_and_snapshots', '2026_10_13_120000_fli_model_versions_external'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        Schema::create('governance_settings', function (Blueprint $t) { $t->increments('id'); $t->string('key', 60); $t->string('value', 60); $t->text('options'); $t->string('label'); $t->text('description'); $t->string('effective_from'); $t->integer('set_by')->nullable(); $t->integer('approved_by')->nullable(); $t->string('approved_at')->nullable(); $t->string('reason', 500)->nullable(); $t->string('status', 20)->default('PROPOSED'); $t->timestamps(); });
        // the engines read their governed settings with no value in code: the seeded defaults (D21)
        $this->seedGovernedDefaults(['scenario_minimum_count', 'scenario_weight_bounds', 'scenario_calibration_note', 'overlay_requires_approved_set', 'scenario_weighting_method', 'fli_adjustment_route']);
        DB::table('users')->insert([['id' => 1, 'name' => 'Maker', 'created_at' => now(), 'updated_at' => now()], ['id' => 2, 'name' => 'Checker', 'created_at' => now(), 'updated_at' => now()]]);
        foreach (['fli_adjustment_route' => 'Regression', 'scenario_weighting_method' => 'Weight the ECL across scenarios', 'fli_forecast_horizon' => '3 years, then 2 years back to the long-run PD'] as $key => $value) {
            $this->setting($key, $value);
        }
        GovernedValues::ensureDefaults('sqlite');

        // the driver, the credit-loss measure and one fit on them: PLR in the same month, August 2026
        DB::table('macro_series')->insert(['statistic_code' => 'PLR', 'observation_period' => '202608', 'value' => 20.0, 'value_type' => 'actual']);
        foreach (['202512' => 0.28, '202601' => 0.29, '202607' => 0.30, '202608' => 0.31] as $p => $v) {
            DB::table('credit_loss_series')->insert(['proxy_code' => 'STAGE3_SHARE', 'proxy_name' => 'Stage 3 share', 'observation_period' => $p, 'value' => $v]);
        }
        $this->fit(10, 1, 'PLR', 0, '202608');
    }

    private function setting(string $key, string $value): void
    {
        DB::table('governance_settings')->insert(['key' => $key, 'value' => $value, 'options' => json_encode(GovernanceService::catalogue()[$key]['options']), 'label' => $key, 'description' => '', 'effective_from' => '1900-01-01', 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** An applied fit, estimated on the data held: its inputs hash is the one a sweep records now. */
    private function fit(int $id, int $relationshipId, string $code, int $lag, string $period): void
    {
        if (! DB::table('fli_relationships')->where('id', $relationshipId)->exists()) {
            DB::table('fli_relationships')->insert(['id' => $relationshipId, 'statistic_code' => $code, 'proxy_code' => 'STAGE3_SHARE', 'r2_cutoff' => 0.3, 'lag_months' => $lag, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('fli_fits')->insert(['id' => $id, 'fli_relationship_id' => $relationshipId, 'reporting_period' => $period, 'slope' => 0.01, 'intercept' => 0.1, 'correlation_r' => 0.9, 'r_squared' => 0.81, 'n_obs' => 24, 'verdict' => 'applied']);
        $this->stamp($id);
    }

    private function stamp(int $id): void
    {
        $f = DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')->where('f.id', $id)->first(['f.reporting_period', 'r.statistic_code', 'r.proxy_code', 'r.lag_months']);
        $hash = (new RegressionEngine('sqlite', new GovernedValues($f->reporting_period, 'sqlite')))->currentInputsHash($f->reporting_period, $f->statistic_code, $f->proxy_code, (int) $f->lag_months);
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

    private function refused(callable $fn, array $needles): string
    {
        try {
            $fn();
        } catch (RuntimeException $e) {
            foreach ($needles as $n) {
                $this->assertStringContainsString($n, $e->getMessage());
            }

            return $e->getMessage();
        }
        $this->fail('the fit was not refused: ' . implode(', ', $needles));
    }

    public function test_a_fit_with_its_inputs_in_place_is_applicable_and_the_check_writes_nothing(): void
    {
        $this->approvedSet();
        $before = [DB::table('fli_fits')->count(), DB::table('fli_model_versions')->count(), DB::table('audit_logs')->count(), DB::table('fli_fits')->where('id', 10)->value('approval_status')];
        $check = $this->service()->fitApplicability(10);
        $this->assertTrue($check['applicable'], implode(' ', $check['reasons']));
        $this->assertSame('2026-08', $check['period']);
        $this->assertSame($before, [DB::table('fli_fits')->count(), DB::table('fli_model_versions')->count(), DB::table('audit_logs')->count(), DB::table('fli_fits')->where('id', 10)->value('approval_status')]);
        $this->service()->proposeFit(10, 1);
        $this->service()->approveFit(10, 2);
        $this->assertSame(10, (int) $this->service()->approvedFit('2026-08')->id);
    }

    /**
     * The base window reads the driver's actual lag months before the period,
     * as known at its end (AsAtSeries): with no April 2025 PLR for a January
     * 2026 fit on a nine-month lag the fit is refused at proposal, and one
     * published only in March 2026 does not count for January. A fit proposed
     * while the actual was held is refused at approval once it is withdrawn,
     * and no model version is published.
     */
    public function test_a_fit_without_the_driver_actual_at_its_lag_is_refused_at_proposal_and_at_approval(): void
    {
        DB::table('macro_series')->insert(['statistic_code' => 'PLR', 'observation_period' => '202601', 'value' => 25.3, 'value_type' => 'actual']);
        $this->fit(20, 2, 'PLR', 9, '202601');
        $this->approvedSet('2026-01');
        $this->refused(fn () => $this->service()->proposeFit(20, 1), ['cannot be proposed for 2026-01', 'PLR actual for 2025-04', 'the last PLR actual known at the end of 2026-01 is for 2026-01']);
        $this->assertSame('NONE', DB::table('fli_fits')->where('id', 20)->value('approval_status'));

        // released only in March 2026: not knowable at the end of January
        DB::table('macro_series')->insert(['statistic_code' => 'PLR', 'observation_period' => '202504', 'value' => 26.0, 'value_type' => 'actual', 'vintage' => 'published 2026-03-02']);
        $this->stamp(20);
        $this->refused(fn () => $this->service()->proposeFit(20, 1), ['PLR actual for 2025-04']);

        // known in time: proposed; then withdrawn before the approval
        DB::table('macro_series')->where('observation_period', '202504')->update(['vintage' => null]);
        $this->stamp(20);
        $this->service()->proposeFit(20, 1);
        DB::table('macro_series')->where('observation_period', '202504')->delete();
        $this->refused(fn () => $this->service()->approveFit(20, 2), ['cannot be approved for 2026-01', 'PLR actual for 2025-04']);
        $this->assertSame('PROPOSED', DB::table('fli_fits')->where('id', 20)->value('approval_status'));
        $this->assertSame(0, DB::table('fli_model_versions')->count());
    }

    /**
     * With a lag of twelve months the first forecast window is the period's
     * own month (the case of fit 163 on maiic_ifrs9_uat): its actual must be
     * known at the end of the period.
     */
    public function test_a_twelve_month_lag_needs_the_actual_of_the_period_itself(): void
    {
        DB::table('macro_series')->insert(['statistic_code' => 'CPI', 'observation_period' => '202508', 'value' => 30.0, 'value_type' => 'actual']);
        $this->fit(30, 3, 'CPI', 12, '202608');
        $this->approvedSet();
        $this->refused(fn () => $this->service()->proposeFit(30, 1), ['forecast window reads the CPI actual for 2026-08 (forecast year 0)', 'the last CPI actual known at the end of 2026-08 is for 2025-08']);
    }

    /**
     * The years after the period come from the scenario paths of the set
     * approved for the period, over the governed horizon, as the route and
     * the projection read them: no set, a set whose paths do not carry the
     * driver, or no horizon in force, and the fit is refused.
     */
    public function test_a_fit_without_the_forecasts_its_horizon_needs_is_refused(): void
    {
        DB::table('macro_series')->insert(['statistic_code' => 'BROAD_MONEY', 'observation_period' => '202608', 'value' => 45.0, 'value_type' => 'actual']);
        $this->fit(40, 4, 'BROAD_MONEY', 0, '202608');
        $this->refused(fn () => $this->service()->proposeFit(40, 1), ['No scenario set is approved for 2026-08', 'forecast years 0, 1, 2']);

        $this->approvedSet();
        $this->refused(fn () => $this->service()->proposeFit(40, 1), ['carries no BROAD_MONEY path', 'forecast years 0 (2027-08), 1 (2028-08), 2 (2029-08)']);
        $this->assertTrue($this->service()->fitApplicability(10)['applicable'], 'the PLR fit, whose driver the set carries, stays applicable');

        DB::table('governance_settings')->where('key', 'fli_forecast_horizon')->delete();
        $this->refused(fn () => $this->service()->proposeFit(10, 1), ['No forecast horizon is in force for 2026-08']);
    }

    /**
     * A stale fit is refused: one whose inputs changed after it was estimated
     * (the stored inputs hash no longer matches what a sweep reads now), with
     * the later fit named when the sweep has recorded it; one set aside when
     * another fit of its period was approved, whose model version was
     * superseded; and one with no record of its inputs.
     */
    public function test_a_stale_or_superseded_fit_is_refused(): void
    {
        $this->approvedSet();

        // the August PLR revised after fit 10 was estimated
        DB::table('macro_series')->where('statistic_code', 'PLR')->where('observation_period', '202608')->update(['value' => 21.0]);
        $this->refused(fn () => $this->service()->proposeFit(10, 1), ['It is stale', 'PLR and STAGE3_SHARE data known at the end of 2026-08', 're-run the regression for 2026-08']);
        $this->fit(12, 1, 'PLR', 0, '202608');   // the sweep's new estimate on the revised data
        $this->refused(fn () => $this->service()->proposeFit(10, 1), ['fit 12, estimated on']);

        // fit 12 approved, then fit 13 approved for the same month: 12 is set aside and its version superseded
        $this->service()->proposeFit(12, 1);
        $this->service()->approveFit(12, 2);
        $this->fit(13, 1, 'PLR', 0, '202608');
        $this->service()->proposeFit(13, 1);
        $this->service()->approveFit(13, 2);
        $this->assertSame('SUPERSEDED', DB::table('fli_model_versions')->where('fli_fit_id', 12)->value('status'));
        $this->refused(fn () => $this->service()->proposeFit(12, 1), ['It was set aside (superseded by fit 13)', 'model version 1 (in force from 2026-08) is superseded']);
        $this->refused(fn () => $this->service()->proposeFit(13, 1), ['already approved']);

        // no record of the inputs it was estimated on
        $this->fit(14, 1, 'PLR', 0, '202608');
        DB::table('fli_fits')->where('id', 14)->update(['inputs_hash' => null]);
        $this->refused(fn () => $this->service()->proposeFit(14, 1), ['no record of the inputs it was estimated on']);
    }
}
