<?php

namespace Tests\Feature\FLI;

use App\Services\Fli\FliCloseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The month record on a discounted month-end (UI end-to-end audit of 14
 * October 2026, item 1). Step 5 of the Maintenance month-end stopped with
 * "the ECL of 2026-09 is not on the PD after FLI ... the time-phased engine
 * ... cannot be split": the Mega Farm programme's loans, measured by their own
 * engine on their own PD and LGD, were in the record and could not tie to the
 * post-FLI stage PD, and the message blamed the discounting. The record now
 * keeps the build-up on the engine measure for MAIIC's book, the discounted
 * allowance beside it weighted from the engine's own per-scenario figures,
 * the reconciliation between the two, the loans not discounted and the
 * programme as its own line; a second person signs it off.
 */
class FliCloseDiscountedRecordTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            foreach (['contract_id', 'customer_name', 'reporting_period', 'product_group', 'product_code', 'industry_type', 'industry_code', 'ifrs9stage_post_qualitative', 'calculated_ifrs9_stage', 'ifrs9stage_pre_qualitative',
                'fli_route', 'fli_method', 'fli_overlay_ids', 'ecl_discount_status', 'ecl_calculation_run_id', 'ecl_calculated_at', 'build_load_id', 'pd_segment_run_id'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['loan_portfolio_id', 'overdue_days', 'tenor', 'fli_fit_id', 'fli_set_id', 'fli_model_version_id'] as $c) {
                $t->integer($c)->nullable();
            }
            foreach (['remaining_tenor', 'carrying_amount', 'commitments', 'facility_utilisation_rate', 'pd_prefli', 'fli_adj', 'pd_post_fli', 'lgd_value', 'collection_lgd', 'customer_lgd', 'ecl_value', 'ecl_value_discounted', 'ecl_discounting_effect'] as $c) {
                $t->double($c)->nullable();
            }
            $t->text('fli_by_scenario')->nullable();
            $t->text('ecl_by_scenario')->nullable();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type')->nullable(); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable();
            $t->string('reporting_period')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps();
        });
        Schema::create('governed_scenario_sets', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->integer('version'); $t->string('status'); $t->string('reporting_period'); });
        Schema::create('governed_scenarios', function (Blueprint $t) { $t->increments('id'); $t->integer('set_id'); $t->string('name'); $t->double('weight'); $t->integer('order_position'); $t->boolean('is_base')->default(false); });
        Schema::create('fli_model_versions', function (Blueprint $t) { $t->increments('id'); });
        Schema::create('loan_portfolios', function (Blueprint $t) { $t->increments('id'); $t->string('name'); });
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); });
        Schema::create('macro_series', function (Blueprint $t) { $t->increments('id'); $t->string('statistic_code'); $t->string('observation_period'); $t->double('value'); $t->string('value_type'); $t->string('vintage')->nullable(); });
        Schema::create('ecl_projection_runs', function (Blueprint $t) { $t->increments('id'); $t->string('run_id'); $t->text('exceptions')->nullable(); });
        Schema::create('ecl_scenario_results', function (Blueprint $t) {
            $t->increments('id'); $t->integer('snapshot_id'); $t->string('reporting_period'); $t->integer('loan_book_id'); $t->string('contract_id')->nullable(); $t->integer('loan_portfolio_id')->nullable();
            $t->string('stage')->nullable(); $t->string('scenario'); $t->double('weight')->nullable(); $t->double('pd12')->nullable(); $t->double('stage_pd')->nullable(); $t->double('ecl')->nullable(); $t->double('ecl_discounted')->nullable(); $t->timestamps();
        });
        Schema::create('fli_run_snapshots', function (Blueprint $t) {
            $t->increments('id');
            foreach (['reporting_period', 'route', 'method', 'weighting', 'macro_vintage', 'status', 'approver_label', 'signed_off_at'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['model_version_id', 'fit_id', 'set_id', 'set_version', 'loans', 'created_by', 'signed_off_by', 'programme_loans', 'discounted_loans', 'discount_unresolved_loans'] as $c) {
                $t->integer($c)->nullable();
            }
            foreach (['pre_fli_ecl', 'model_effect', 'overlay_effect', 'booked_ecl', 'weighted_ecl', 'difference', 'prior_booked_ecl', 'movement_pct', 'programme_ecl', 'unresolved_ecl', 'projection_effect',
                'discounting_effect', 'booked_ecl_discounted', 'weighted_ecl_discounted', 'discounted_difference'] as $c) {
                $t->double($c)->nullable();
            }
            foreach (['reconciles', 'needs_commentary', 'discounted_reconciles'] as $c) {
                $t->boolean($c)->nullable();
            }
            foreach (['overlay_ids', 'per_scenario', 'by_stage', 'by_portfolio', 'inputs', 'commentary', 'discount_detail'] as $c) {
                $t->text($c)->nullable();
            }
            $t->timestamps();
        });

        DB::table('users')->insert([['id' => 2, 'name' => 'Maker'], ['id' => 3, 'name' => 'Checker']]);
        DB::table('loan_portfolios')->insert([['id' => 1, 'name' => 'MAIIC'], ['id' => 8, 'name' => 'Mega Farm']]);
        DB::table('governed_scenario_sets')->insert(['id' => 1, 'name' => 'Set', 'version' => 1, 'status' => 'APPROVED', 'reporting_period' => '2026-09']);
        DB::table('governed_scenarios')->insert([['set_id' => 1, 'name' => 'Base', 'weight' => 50, 'order_position' => 1, 'is_base' => true], ['set_id' => 1, 'name' => 'Severe', 'weight' => 50, 'order_position' => 2, 'is_base' => false]]);
        DB::table('ecl_projection_runs')->insert(['run_id' => 'R1', 'exceptions' => json_encode(['C' => 'A locked original EIR is unavailable.'])]);

        $fli = json_encode(['Base' => ['weight' => 50, 'pd' => 0.10], 'Severe' => ['weight' => 50, 'pd' => 0.16]]);
        $base = ['reporting_period' => '2026-09', 'loan_portfolio_id' => 1, 'product_code' => '1040101', 'remaining_tenor' => 24, 'commitments' => 0, 'facility_utilisation_rate' => 1,
            'fli_set_id' => 1, 'fli_route' => 'Regression', 'ecl_calculated_at' => '2026-10-14 10:00:00', 'ecl_calculation_run_id' => 'R1'];
        // Stage 1: EAD 1,000, post-FLI PD 0.13 (the weighted scenario PD), LGD 0.5: engine ECL 65;
        // time-phased 48 / 45 and 80 / 75 by scenario: undiscounted 64, discounted 60
        DB::table('loan_books')->insert($base + ['contract_id' => 'A', 'ifrs9stage_post_qualitative' => '1', 'carrying_amount' => 1000, 'pd_prefli' => 0.10, 'pd_post_fli' => 0.13, 'lgd_value' => 0.5,
            'ecl_value' => 65, 'fli_by_scenario' => $fli, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED', 'ecl_value_discounted' => 60, 'ecl_discounting_effect' => 4,
            'ecl_by_scenario' => json_encode(['Base' => ['weight' => .5, 'undiscounted' => 48, 'discounted' => 45], 'Severe' => ['weight' => .5, 'undiscounted' => 80, 'discounted' => 75]])]);
        // Stage 3: EAD 2,000 x LGD 0.4 at the reporting date in every scenario
        DB::table('loan_books')->insert($base + ['contract_id' => 'B', 'ifrs9stage_post_qualitative' => '3', 'carrying_amount' => 2000, 'pd_prefli' => 1, 'pd_post_fli' => 1, 'lgd_value' => 0.4,
            'ecl_value' => 800, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED', 'ecl_value_discounted' => 800, 'ecl_discounting_effect' => 0,
            'ecl_by_scenario' => json_encode(['Base' => ['weight' => .5, 'undiscounted' => 800, 'discounted' => 800], 'Severe' => ['weight' => .5, 'undiscounted' => 800, 'discounted' => 800]])]);
        // Stage 1 with no locked EIR: the engine measure only
        DB::table('loan_books')->insert($base + ['contract_id' => 'C', 'ifrs9stage_post_qualitative' => '1', 'carrying_amount' => 500, 'pd_prefli' => 0.10, 'pd_post_fli' => 0.13, 'lgd_value' => 0.5,
            'ecl_value' => 32.5, 'fli_by_scenario' => $fli, 'ecl_discount_status' => 'TIME_PHASED_UNRESOLVED']);
        // the Mega Farm programme, measured by its own engine on its own PD (does not tie to the stage PD)
        DB::table('loan_books')->insert(['reporting_period' => '2026-09', 'loan_portfolio_id' => 8, 'product_code' => '1050301', 'contract_id' => 'P', 'ifrs9stage_post_qualitative' => '1',
            'carrying_amount' => 5000, 'pd_prefli' => 0.5, 'pd_post_fli' => 0.5, 'lgd_value' => 0.2, 'remaining_tenor' => 6, 'ecl_value' => 999, 'fli_set_id' => 1, 'ecl_discount_status' => 'NOT_CALCULATED']);
    }

    public function test_a_discounted_month_with_a_mega_farm_programme_is_recorded_and_reconciled(): void
    {
        $s = app(FliCloseService::class)->record('2026-09', 2);

        $this->assertSame(3, (int) $s['loans']);
        $this->assertEqualsWithDelta(897.5, (float) $s['booked_ecl'], 0.005);
        $this->assertTrue((bool) $s['reconciles'], 'weighted ' . $s['weighted_ecl']);
        $this->assertEqualsWithDelta((float) $s['booked_ecl'], (float) $s['pre_fli_ecl'] + (float) $s['model_effect'] + (float) $s['overlay_effect'], 0.011);
        $this->assertSame(1, (int) $s['programme_loans']);
        $this->assertEqualsWithDelta(999, (float) $s['programme_ecl'], 0.005);

        $this->assertSame(2, (int) $s['discounted_loans']);
        $this->assertSame(1, (int) $s['discount_unresolved_loans']);
        $this->assertEqualsWithDelta(860, (float) $s['booked_ecl_discounted'], 0.005);
        $this->assertEqualsWithDelta(860, (float) $s['weighted_ecl_discounted'], 0.005);
        $this->assertTrue((bool) $s['discounted_reconciles']);
        // engine 897.5 - not discounted 32.5 + projection -1 + discounting -4 = 860
        $this->assertEqualsWithDelta(32.5, (float) $s['unresolved_ecl'], 0.005);
        $this->assertEqualsWithDelta(-1, (float) $s['projection_effect'], 0.005);
        $this->assertEqualsWithDelta(-4, (float) $s['discounting_effect'], 0.005);
        $this->assertEqualsWithDelta((float) $s['booked_ecl_discounted'],
            (float) $s['booked_ecl'] - (float) $s['unresolved_ecl'] + (float) $s['projection_effect'] + (float) $s['discounting_effect'], 0.011);
        $this->assertSame('C', $s['discount_detail']['unresolved'][0]['contract_id']);
        $this->assertSame('A locked original EIR is unavailable.', $s['discount_detail']['unresolved'][0]['reason']);
        $severe = collect($s['per_scenario'])->firstWhere('scenario', 'Severe');
        $this->assertEqualsWithDelta(875, $severe['ecl_discounted'], 0.005);
        $this->assertSame(0, DB::table('ecl_scenario_results')->where('contract_id', 'P')->count());
        $this->assertEqualsWithDelta(75, (float) DB::table('ecl_scenario_results')->where('contract_id', 'A')->where('scenario', 'Severe')->value('ecl_discounted'), 0.005);
    }

    public function test_a_second_person_signs_off_saying_why_loans_are_not_discounted(): void
    {
        $close = app(FliCloseService::class);
        $s = $close->record('2026-09', 2);
        try {
            $close->signOff((int) $s['id'], 2, 'commentary');
            $this->fail('the recorder signed off');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Maker-checker', $e->getMessage());
        }
        try {
            $close->signOff((int) $s['id'], 3);
            $this->fail('signed off with loans undiscounted and no commentary');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('no discounted allowance', $e->getMessage());
        }
        $close->signOff((int) $s['id'], 3, 'C waits for its EIR to be locked; its engine ECL is booked meanwhile.');
        $this->assertSame('SIGNED_OFF', DB::table('fli_run_snapshots')->where('id', $s['id'])->value('status'));
    }

    public function test_a_discounted_allowance_that_does_not_weight_back_cannot_be_signed_off(): void
    {
        // the per-scenario figures no longer give the discounted allowance booked
        DB::table('loan_books')->where('contract_id', 'A')->update(['ecl_value_discounted' => 70]);
        $close = app(FliCloseService::class);
        $s = $close->record('2026-09', 2);
        $this->assertFalse((bool) $s['discounted_reconciles']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('discounted allowance');
        $close->signOff((int) $s['id'], 3, 'commentary');
    }

    /**
     * No approved fit: the route holds the PD in each scenario of the set
     * (maiic_ifrs9_fliall, December 2025 and September 2026). Every scenario
     * carries the same PD, the time-phased engine measures each, and the
     * record weights the discounted figures back to the allowance booked to
     * the cent; a second person signs it off.
     */
    public function test_a_month_whose_pd_holds_in_every_scenario_weights_back_and_is_signed_off(): void
    {
        $held = json_encode(['Base' => ['weight' => 50, 'pd' => 0.13], 'Severe' => ['weight' => 50, 'pd' => 0.13]]);
        DB::table('loan_books')->whereIn('contract_id', ['A', 'C'])->update(['pd_prefli' => 0.13, 'fli_by_scenario' => $held]);
        DB::table('loan_books')->where('contract_id', 'A')->update(['ecl_by_scenario' => json_encode(['Base' => ['weight' => .5, 'undiscounted' => 64, 'discounted' => 60], 'Severe' => ['weight' => .5, 'undiscounted' => 64, 'discounted' => 60]])]);
        $close = app(FliCloseService::class);
        $s = $close->record('2026-09', 2);

        $this->assertTrue((bool) $s['reconciles']);
        $this->assertSame(0, (int) $s['inputs']['without_scenario_pd']['loans']);
        $this->assertEqualsWithDelta(0.0, (float) $s['model_effect'], 0.005);
        $this->assertSame(round((float) $s['booked_ecl_discounted'], 2), round((float) $s['weighted_ecl_discounted'], 2));
        $this->assertEqualsWithDelta(860, (float) $s['weighted_ecl_discounted'], 0.005);
        $this->assertTrue((bool) $s['discounted_reconciles']);
        foreach ($s['per_scenario'] as $row) {
            $this->assertEqualsWithDelta(860, $row['ecl_discounted'], 0.005, $row['scenario']);
        }
        $close->signOff((int) $s['id'], 3, 'C waits for its EIR to be locked.');
        $this->assertSame('SIGNED_OFF', DB::table('fli_run_snapshots')->where('id', $s['id'])->value('status'));
    }

    /**
     * A performing loan measured on one "Overlay" scenario, as the route wrote
     * it before 14 October 2026: the set's scenarios were never measured on it.
     * Its booked ECL is not put in their place: the record names it, neither
     * figure reconciles, and sign-off is refused with the reason.
     */
    public function test_a_loan_without_the_sets_scenarios_is_named_and_the_month_is_refused(): void
    {
        DB::table('loan_books')->where('contract_id', 'A')->update(['pd_prefli' => 0.13,
            'fli_by_scenario' => json_encode(['Overlay' => ['weight' => 100, 'pd' => 0.13]]),
            'ecl_by_scenario' => json_encode(['Overlay' => ['weight' => 1, 'undiscounted' => 64, 'discounted' => 60]])]);
        $close = app(FliCloseService::class);
        $s = $close->record('2026-09', 2);

        $this->assertFalse((bool) $s['reconciles']);
        $this->assertSame(1, (int) $s['inputs']['without_scenario_pd']['loans']);
        $this->assertSame(['A (Base)', 'A (Severe)'], $s['inputs']['without_scenario_pd']['first']);
        $this->assertFalse((bool) $s['discounted_reconciles']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('carry no PD for a scenario of the set');
        $close->signOff((int) $s['id'], 3, 'commentary');
    }
}
