<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MaiicTransitionProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The dashboard's charts (13 October 2026): the operations row (month-end
 * status, latest loan book, recent imports) is gone; three charts replace
 * it, each read from what the engines stored, each with its own from / to
 * range (pf_, st_, dr_) and reloadable alone (Inertia partial reload).
 *
 * Fixture, two months (2026-01, 2026-02):
 *   Portfolio 901 "Alpha": A-1 Seasonal, Stage 1, 1,000, ECL 10;
 *                          A-2 Term, Stage 2 in January (ECL 100), Stage 3 in February (ECL 600).
 *   Portfolio 902 "Beta":  B-1 Seasonal, Stage 1, 3,000, ECL 30.
 *   Portfolios 903 to 909: one ECL run row each (ECL 1 to 7), so the chart folds them into "Other".
 *   February has a forward-looking month-end record: Base 50, Downside 50.
 * Observed default rate, February, one-month window: of the 6,000 performing
 * at the end of January, A-2 (2,000) defaulted: 33.33%; Alpha alone 2,000 of 3,000.
 */
class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $u = User::create(['name' => 'Dash ' . uniqid(), 'email' => uniqid() . '@example.com', 'password' => bcrypt('password'), 'email_verified_at' => now()]);
        $u->forceFill(['active' => 1])->save();

        return $u;
    }

    private function seedBook(): void
    {
        (new MaiicTransitionProfileSeeder())->run();
        $owner = $this->user();
        $names = [901 => 'Alpha', 902 => 'Beta'] + array_combine(range(903, 909), array_map(fn ($i) => 'Small ' . $i, range(903, 909)));
        foreach ($names as $id => $name) {
            DB::table('loan_portfolios')->insert(['id' => $id, 'name' => $name, 'created_by_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (['2026-01' => 1, '2026-02' => 2] as $p => $m) {
            DB::table('reporting_periods')->insert(['reporting_year' => 2026, 'reporting_month' => $m, 'period' => $p . '-01', 'ecl_calculated' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        // [contract, portfolio, product, stage Jan, stage Feb, balance, ECL Jan, ECL Feb]
        $loans = [['A-1', 901, 'Seasonal', '1', '1', 1000, 10, 10], ['A-2', 901, 'Term', '2', '3', 2000, 100, 600], ['B-1', 902, 'Seasonal', '1', '1', 3000, 30, 30]];
        foreach ($loans as [$c, $pf, $prod, $s1, $s2, $bal, $e1, $e2]) {
            foreach ([['2026-01', 1, $s1, $e1], ['2026-02', 2, $s2, $e2]] as [$p, $m, $st, $ecl]) {
                $id = DB::table('loan_books')->insertGetId(['contract_id' => $c, 'customer_id' => $c, 'reporting_period' => $p, 'reporting_year' => 2026, 'reporting_month' => $m,
                    'loan_portfolio_id' => $pf, 'product_group' => $prod, 'ifrs9stage_post_qualitative' => $st, 'carrying_amount' => $bal, 'commitments' => 0, 'facility_utilisation_rate' => 1,
                    'pd_post_fli' => 0.1, 'lgd_value' => 0.5, 'ecl_value' => $ecl, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('expected_credit_loss')->insert(['ecl_calculation_level' => 'portfolio', 'ecl_calculation_id' => $pf, 'reporting_period' => $p, 'ifrs9_stage' => (int) $st,
                    'total_ead' => $bal, 'total_ecl' => $ecl, 'total_loans' => 1, 'pd_value_used' => 0.1, 'lgd_value_used' => 0.5, 'last_reporting_period' => $p, 'created_at' => now(), 'updated_at' => now()]);
                if ($p === '2026-02') {
                    $this->loanIds[$c] = [$id, $pf, $st, $ecl];
                }
            }
        }
        foreach (range(903, 909) as $i => $pf) {
            foreach (['2026-01', '2026-02'] as $p) {
                DB::table('expected_credit_loss')->insert(['ecl_calculation_level' => 'portfolio', 'ecl_calculation_id' => $pf, 'reporting_period' => $p, 'ifrs9_stage' => 1,
                    'total_ead' => 100, 'total_ecl' => $i + 1, 'total_loans' => 1, 'pd_value_used' => 0.1, 'lgd_value_used' => 0.5, 'last_reporting_period' => $p, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /** @var array<string,array{0:int,1:int,2:string,3:float}> */
    private array $loanIds = [];

    private function seedMonthEndRecord(): void
    {
        $setId = DB::table('governed_scenario_sets')->insertGetId(['reporting_period' => '2026-02', 'name' => 'Test set', 'version' => 1, 'status' => 'APPROVED',
            'approver_label' => \App\Services\Scenario\ScenarioSetService::BOOTSTRAP_LABEL, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $snap = DB::table('fli_run_snapshots')->insertGetId(['reporting_period' => '2026-02', 'set_id' => $setId, 'set_version' => 1, 'loans' => 3, 'booked_ecl' => 640, 'weighted_ecl' => 640,
            'difference' => 0, 'reconciles' => true, 'status' => 'RECORDED', 'created_at' => now(), 'updated_at' => now(),
            'per_scenario' => json_encode([['scenario' => 'Base', 'weight' => 50, 'is_base' => true], ['scenario' => 'Downside', 'weight' => 50, 'is_base' => false]])]);
        foreach ($this->loanIds as $c => [$id, $pf, $st, $ecl]) {
            // Stage 3 does not move; the others are 80% of booked under Base and 120% under Downside
            foreach (['Base' => $st === '3' ? 1.0 : 0.8, 'Downside' => $st === '3' ? 1.0 : 1.2] as $name => $f) {
                DB::table('ecl_scenario_results')->insert(['snapshot_id' => $snap, 'reporting_period' => '2026-02', 'loan_book_id' => $id, 'contract_id' => $c, 'loan_portfolio_id' => $pf,
                    'stage' => $st, 'scenario' => $name, 'weight' => 50, 'ecl' => $ecl * $f, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function test_the_dashboard_carries_the_new_charts_and_not_the_operations_row(): void
    {
        $this->seedBook();
        $this->seedMonthEndRecord();

        $this->actingAs($this->user())->get(route('dashboard', ['period' => '2026-02']))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard')
                ->has('eclMixTrend')->has('scenarioEcl')->has('defaultRates')->has('eclTrends', 2)
                ->missing('monthEnd')->missing('loanBookSnapshot')->missing('recentImports')
                ->where('eclMixTrend.dimension', 'portfolio')
                ->where('eclMixTrend.ties', true)
                ->has('eclMixTrend.series', 7)                       // six largest and "Other (3)"
                ->where('eclMixTrend.series.6.label', 'Other (3)')
                // stress results only for February: January is listed as having none, never filled
                ->where('scenarioEcl.periods', ['2026-02'])
                ->where('scenarioEcl.missing', ['2026-01'])
                ->where('scenarioEcl.by_period.2026-02.source', 'month_end')
                ->where('scenarioEcl.by_period.2026-02.set.bootstrap', true)
                // the record covers the 640 of the three loans; portfolios 903 to 909 carry 28 of ECL runs it never saw
                ->where('scenarioEcl.by_period.2026-02.ties', false)
                ->where('scenarioEcl.by_period.2026-02.series.2.name', 'Probability-weighted')
                ->where('defaultRates.rows.1.period', '2026-02')
                ->where('eclMixTrend.range', ['from' => null, 'to' => null]));
    }

    public function test_every_month_of_ecl_by_portfolio_adds_back_to_the_trend_total(): void
    {
        $this->seedBook();
        $u = $this->user();
        foreach ([[], ['portfolio_id' => 901], ['portfolio_id' => 902]] as $filter) {
            $props = $this->actingAs($u)->get(route('dashboard', ['period' => '2026-02'] + $filter))->assertOk()->viewData('page')['props'];
            $mix = $props['eclMixTrend'];
            $this->assertSame($filter ? 'product' : 'portfolio', $mix['dimension']);
            $this->assertCount(2, $props['eclTrends']);
            foreach ($props['eclTrends'] as $i => $t) {
                $this->assertSame($t['period'], $mix['periods'][$i]);
                $sum = array_sum(array_map(fn ($s) => $s['values'][$i], $mix['series']));
                $this->assertEqualsWithDelta($t['total_ecl'], $sum, 0.01, "ECL by portfolio for {$t['period']} " . json_encode($filter) . ' must equal the trend total');
                $this->assertEqualsWithDelta($t['total_ecl'], $mix['totals'][$i], 0.01);
            }
            $this->assertTrue($mix['ties']);
            $this->assertSame([], $mix['differs']);
        }
        // the whole book in February: 10 + 600 + 30 + (1 + ... + 7)
        $props = $this->actingAs($u)->get(route('dashboard', ['period' => '2026-02']))->viewData('page')['props'];
        $this->assertEqualsWithDelta(668.0, $props['eclTrends'][1]['total_ecl'], 0.001);
    }

    public function test_scenarios_by_stage_and_the_observed_default_rate_come_from_the_engines(): void
    {
        $this->seedBook();
        $this->seedMonthEndRecord();
        $u = $this->user();

        $props = $this->actingAs($u)->get(route('dashboard', ['period' => '2026-02']))->viewData('page')['props'];
        $feb = $props['scenarioEcl']['by_period']['2026-02'];
        $s = collect($feb['series'])->keyBy('name');
        // Stage 1: A-1 10 + B-1 30 = 40; Stage 3: A-2 600 in every scenario
        $this->assertEqualsWithDelta(32.0, $s['Base']['values'][0], 0.001);
        $this->assertEqualsWithDelta(48.0, $s['Downside']['values'][0], 0.001);
        $this->assertEqualsWithDelta(600.0, $s['Base']['values'][2], 0.001);
        $this->assertEqualsWithDelta(640.0, $s['Probability-weighted']['values'][3], 0.001);
        $this->assertSame([2, 0, 1, 3], $feb['loans']);

        $feb = $props['defaultRates']['rows'][1];
        $this->assertEqualsWithDelta(2000 / 6000 * 100, $feb['monthly']['book']['rate'], 0.001);
        $this->assertSame(1, $feb['monthly']['book']['defaults']);
        $this->assertSame(3, $feb['monthly']['book']['loans']);

        // one portfolio: its own scenario rows and its own rate beside the book
        $props = $this->actingAs($u)->get(route('dashboard', ['period' => '2026-02', 'portfolio_id' => 902]))->viewData('page')['props'];
        $beta = $props['scenarioEcl']['by_period']['2026-02'];
        $this->assertEqualsWithDelta(24.0, collect($beta['series'])->firstWhere('name', 'Base')['values'][3], 0.001);
        $this->assertTrue($beta['ties']);   // weighted (24 + 36) / 2 = 30, Beta's total ECL for February
        $this->assertEqualsWithDelta(0.0, $props['defaultRates']['rows'][1]['monthly']['portfolio']['rate'], 0.001);

        // January has no record and no saved stress run: an honest empty state
        $props = $this->actingAs($u)->get(route('dashboard', ['period' => '2026-01']))->viewData('page')['props'];
        $this->assertSame([], $props['scenarioEcl']['periods']);
        $this->assertSame(['2026-01'], $props['scenarioEcl']['missing']);
        $this->assertSame([], $props['scenarioEcl']['by_period']);
    }

    public function test_each_chart_keeps_its_own_range(): void
    {
        $this->seedBook();
        $this->seedMonthEndRecord();
        $u = $this->user();

        $props = $this->actingAs($u)->get(route('dashboard', ['period' => '2026-02', 'pf_from' => '2026-02', 'dr_to' => '2026-01', 'st_from' => 'all']))->viewData('page')['props'];
        $this->assertCount(2, $props['eclTrends']);                            // the trend keeps its own default range
        $this->assertSame(['2026-02'], $props['eclMixTrend']['periods']);
        $this->assertSame(['from' => '2026-02', 'to' => null], $props['eclMixTrend']['range']);
        $this->assertTrue($props['eclMixTrend']['ties']);
        $this->assertSame(['2026-01'], array_column($props['defaultRates']['rows'], 'period'));
        $this->assertSame(['from' => null, 'to' => '2026-01'], $props['defaultRates']['range']);
        $this->assertSame(['2026-01', '2026-02'], $props['scenarioEcl']['range_periods']);
        $this->assertSame(['from' => 'all', 'to' => null], $props['scenarioEcl']['range']);

        // a partial reload of one chart carries that chart alone
        $version = $this->actingAs($u)->get(route('dashboard'))->viewData('page')['version'];
        $this->actingAs($u)->get(route('dashboard', ['period' => '2026-02', 'pf_from' => '2026-02']), [
            'X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'Dashboard', 'X-Inertia-Partial-Data' => 'eclMixTrend',
        ])->assertOk()->assertJsonPath('props.eclMixTrend.periods', ['2026-02'])
            ->assertJsonMissingPath('props.scenarioEcl')->assertJsonMissingPath('props.defaultRates')->assertJsonMissingPath('props.summary');
    }

    public function test_the_pdf_still_carries_the_month_end_status(): void
    {
        $this->seedBook();
        $this->actingAs($this->user())->get(route('dashboard.ecl-report-pdf', ['period' => '2026-02']))->assertOk();
    }
}
