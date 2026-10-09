<?php

namespace Tests\Feature\FLI;

use App\Services\Fli\CorrelationFinder;
use App\Services\Fli\RegressionEngine;
use App\Support\Fli\AsAtSeries;
use App\Support\Fli\GovernedValues;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The forward-looking engines read only what was knowable at the end of the
 * reporting period (IFRS 9 5.5.17(c), B5.5.49 to B5.5.51). The fixture holds
 * a driver and a proxy that move together up to June 2025 and the opposite
 * way after it: a fit or a sweep for June 2025 must not change when the later
 * months arrive, while the same fit for December 2025 does change, which
 * proves the later months would have moved it had they been read.
 */
class AsAtReportingDateTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        foreach (['2026_10_09_000000_create_fli_bridge_tables', '2026_10_09_200000_fli_fit_approval_and_loan_lineage', '2026_10_09_600000_fli_fit_sign_warning'] as $m) {
            if ($m === '2026_10_09_200000_fli_fit_approval_and_loan_lineage') {
                \Illuminate\Support\Facades\Schema::create('loan_books', function ($t) { $t->increments('id'); $t->decimal('pd_post_fli', 12, 8)->nullable(); });
            }
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        GovernedValues::ensureDefaults('sqlite', '190001');
        DB::table('governed_parameters')->where('param_key', 'stats.min_obs')->update(['param_value' => '12']);
        DB::table('governed_parameters')->where('param_key', 'fli.r2_cutoff.default')->update(['param_value' => '0.3']);
        DB::table('governed_parameters')->where('param_key', 'fli.lag_grid')->update(['param_value' => '0']);
        DB::table('regression_definitions')->insert(['statistic_code' => 'PLR', 'proxy_code' => 'NPL_RATIO', 'expected_sign' => 'positive', 'r2_cutoff_pct' => 30, 'created_at' => now(), 'updated_at' => now()]);
        // January 2023 to June 2025: the proxy rises with the rate
        $this->months('2023-01', 30, fn ($i) => ($i % 7) + 0.1 * ($i % 3), fn ($x, $i) => 0.02 + 0.004 * $x + 0.0003 * ($i % 2));
    }

    private function months(string $from, int $n, callable $x, callable $y): void
    {
        $start = CarbonImmutable::parse($from . '-01');
        for ($i = 0; $i < $n; $i++) {
            $p = $start->addMonths($i)->format('Ym');
            $xv = $x($i);
            DB::table('macro_series')->insert(['statistic_code' => 'PLR', 'observation_period' => $p, 'value' => $xv, 'value_type' => 'actual', 'source' => 'test', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('credit_loss_series')->insert(['proxy_code' => 'NPL_RATIO', 'observation_period' => $p, 'value' => $y($xv, $i), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** July to December 2025: the proxy falls as the rate rises, steeply */
    private function laterMonths(): void
    {
        $this->months('2025-07', 6, fn ($i) => 20 + 2 * $i, fn ($x) => 0.5 - 0.02 * $x);
    }

    private function fit(string $period): array
    {
        $r = (new RegressionEngine('sqlite', new GovernedValues($period, 'sqlite')))->fitCandidates($period);

        return collect($r['fits'])->first(fn ($f) => $f['statistic_code'] === 'PLR' && $f['proxy_code'] === 'NPL_RATIO');
    }

    public function test_a_fit_for_a_period_never_sees_an_observation_after_its_end(): void
    {
        $before = $this->fit('202506');
        $hashBefore = DB::table('fli_fits')->orderByDesc('id')->value('inputs_hash');
        $this->assertSame(30, $before['n_obs']);
        $this->assertSame('applied', $before['verdict']);

        $this->laterMonths();
        $after = $this->fit('202506');
        $this->assertSame($before['n_obs'], $after['n_obs']);
        $this->assertEqualsWithDelta($before['slope'], $after['slope'], 1e-12);
        $this->assertEqualsWithDelta($before['r_squared'], $after['r_squared'], 1e-12);
        $this->assertSame($hashBefore, DB::table('fli_fits')->orderByDesc('id')->value('inputs_hash'));

        // the control: as at December the later months are read, and they change the fit
        $december = $this->fit('202512');
        $this->assertSame(36, $december['n_obs']);
        $this->assertGreaterThan(1e-4, abs($december['slope'] - $before['slope']));
    }

    public function test_the_correlation_sweep_for_a_period_never_sees_an_observation_after_its_end(): void
    {
        $finder = fn (string $p) => collect((new CorrelationFinder('sqlite', new GovernedValues($p, 'sqlite')))->run($p)['ranked'])
            ->first(fn ($s) => $s['statistic_code'] === 'PLR' && $s['proxy_code'] === 'NPL_RATIO');
        $before = $finder('202506');
        $this->laterMonths();
        $after = $finder('202506');
        $this->assertSame('202506', $after['overlap_end']);
        $this->assertSame($before['n_obs'], $after['n_obs']);
        $this->assertEqualsWithDelta($before['r_squared'], $after['r_squared'], 1e-12);
        $this->assertSame($before['verdict'], $after['verdict']);
        $this->assertSame('202512', $finder('202512')['overlap_end']);
    }

    public function test_when_a_macro_row_becomes_knowable(): void
    {
        DB::table('macro_series')->insert([
            // two annual observations and a month interpolated between them
            ['statistic_code' => 'GDP_GROWTH', 'observation_period' => '202412', 'value' => 2.0, 'value_type' => 'actual', 'source' => 'World Bank', 'lag_months' => 0, 'vintage' => null],
            ['statistic_code' => 'GDP_GROWTH', 'observation_period' => '202506', 'value' => 2.5, 'value_type' => 'actual', 'source' => AsAtSeries::INTERPOLATED_SOURCE, 'lag_months' => 0, 'vintage' => null],
            ['statistic_code' => 'GDP_GROWTH', 'observation_period' => '202512', 'value' => 3.0, 'value_type' => 'actual', 'source' => 'World Bank', 'lag_months' => 0, 'vintage' => null],
            // an actual published three months after its period
            ['statistic_code' => 'CPI', 'observation_period' => '202504', 'value' => 30.0, 'value_type' => 'actual', 'source' => 'NSO', 'lag_months' => 3, 'vintage' => null],
            // a forecast published in April 2025, and one with no dated vintage
            ['statistic_code' => 'CPI', 'observation_period' => '202612', 'value' => 18.0, 'value_type' => 'forecast', 'source' => 'IMF WEO', 'lag_months' => 0, 'vintage' => 'IMF WEO April 2025 (2025-04-22)'],
            ['statistic_code' => 'CPI', 'observation_period' => '202712', 'value' => 12.0, 'value_type' => 'forecast', 'source' => 'IMF WEO', 'lag_months' => 0, 'vintage' => 'undated'],
        ]);

        $june = AsAtSeries::macro('sqlite', '2025-06', ['actual', 'forecast'], ['GDP_GROWTH', 'CPI']);
        // June 2025 is interpolated towards December 2025, which is not yet known: not knowable in June
        $this->assertSame([202412 => 2.0], $june['GDP_GROWTH']);
        // the April CPI is published in July; the April forecast is known; the undated forecast never is
        $this->assertSame([202612 => 18.0], $june['CPI']);
        $this->assertArrayNotHasKey('CPI', AsAtSeries::macro('sqlite', '2025-03', ['forecast'], ['CPI']));

        $december = AsAtSeries::macro('sqlite', '202512', ['actual', 'forecast'], ['GDP_GROWTH', 'CPI']);
        $this->assertSame([202412 => 2.0, 202506 => 2.5, 202512 => 3.0], $december['GDP_GROWTH']);
        $this->assertSame([202504 => 30.0, 202612 => 18.0], $december['CPI']);

        $this->expectException(\InvalidArgumentException::class);
        AsAtSeries::ym('2025');
    }
}
