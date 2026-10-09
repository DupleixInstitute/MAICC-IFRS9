<?php

namespace Tests\Feature\FLI;

use App\Services\Eir\GovernanceService;
use App\Services\Fli\FliBridgeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The bridge of spec v4 section 14 fills the suite's engine tables from
 * MAIIC's own: an annual macro value is held at its December and the months
 * between are interpolated and say so; the credit-loss proxies come from the
 * staged loan books for the book and per segment; the governed parameters
 * carry the Governance Centre's values (both normality limits, the sign-test
 * mode) and the suite's defaults for the rest, and the bridge is their only
 * writer: a value that drifted is put back with a warning (system audit of
 * 9 October 2026, finding M12).
 */
class FliBridgeServiceTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        (require base_path('database/migrations/2026_10_09_000000_create_fli_bridge_tables.php'))->up();
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
        Schema::create('macro_statistics', function (Blueprint $t) { $t->increments('id'); $t->string('statistic_code'); $t->string('statistic_name'); $t->string('frequency'); });
        Schema::create('macro_statistics_data', function (Blueprint $t) { $t->increments('id'); $t->integer('macro_stat_definition_id'); $t->date('period'); $t->decimal('value', 15, 4); $t->boolean('is_forecast')->default(0); $t->string('source')->nullable(); });
        Schema::create('reference_rate_series', function (Blueprint $t) { $t->increments('id'); $t->string('index_code'); $t->date('effective_date'); $t->decimal('rate', 10, 4); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('product_code')->nullable(); $t->string('product_group')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->decimal('carrying_amount', 20, 2)->default(0); });
        DB::table('macro_statistics')->insert(['id' => 1, 'statistic_code' => 'GDP_GROWTH', 'statistic_name' => 'Real GDP growth', 'frequency' => 'yearly']);
        DB::table('macro_statistics_data')->insert([['macro_stat_definition_id' => 1, 'period' => '2024-12-31', 'value' => 2.0, 'source' => 'World Bank'], ['macro_stat_definition_id' => 1, 'period' => '2025-12-31', 'value' => 3.2, 'source' => 'World Bank']]);
        foreach (['2024-12' => ['1', '1', '3'], '2025-12' => ['1', '3', '3']] as $p => $stages) {
            foreach ($stages as $i => $stage) {
                DB::table('loan_books')->insert(['contract_id' => 'c' . $i, 'reporting_period' => $p, 'product_code' => '1050101', 'product_group' => 'MAIIC Agricultural Loans', 'ifrs9stage_post_qualitative' => $stage, 'carrying_amount' => 100]);
            }
        }
    }

    private function param(string $key): ?string
    {
        return DB::table('governed_parameters')->where('param_key', $key)->where('status', 'approved')->value('param_value');
    }

    public function test_the_bridge_fills_the_engine_tables_from_maiic_tables(): void
    {
        $r = (new FliBridgeService(new GovernanceService()))->refresh('2025-12');
        $this->assertSame(13, $r['macro_rows']);                                  // Dec 2024, Dec 2025, eleven months between
        $this->assertEquals(2.6, (float) DB::table('macro_series')->where('observation_period', '202506')->value('value'));
        $this->assertSame('interpolated from the annual observations', DB::table('macro_series')->where('observation_period', '202506')->value('source'));
        $this->assertSame('World Bank', DB::table('macro_series')->where('observation_period', '202512')->value('source'));
        $npl = DB::table('credit_loss_series')->where('proxy_code', 'NPL_RATIO')->orderBy('observation_period')->pluck('value', 'observation_period')->all();
        $this->assertEqualsWithDelta(1 / 3, (float) $npl['202412'], 1e-6);
        $this->assertEqualsWithDelta(2 / 3, (float) $npl['202512'], 1e-6);
        // the flow into Stage 3 over the year: of the two non-Stage-3 accounts a year earlier, one defaulted
        $this->assertEqualsWithDelta(0.5, (float) DB::table('credit_loss_series')->where('proxy_code', 'DEFAULT_RATE_12M')->where('observation_period', '202512')->value('value'), 1e-6);
        $this->assertTrue(DB::table('credit_loss_series')->where('proxy_code', 'like', 'NPL_RATIO_MAIIC%')->exists());
        // the Governance Centre's seeded values, not the suite's: 12 observations, 30 percent, 5 percent, skewness 1.0 and kurtosis 3.0, the sign test required
        $this->assertSame('12', $this->param('stats.min_obs'));
        $this->assertSame('0.3', $this->param('fli.r2_cutoff.default'));
        $this->assertSame('0.05', $this->param('stats.alpha'));
        $this->assertSame('1.0', $this->param('stats.skew_limit'));
        $this->assertSame('3.0', $this->param('stats.kurtosis_limit'));
        $this->assertSame('gating', $this->param('fli.sign_test.mode'));
        $this->assertSame('negative', DB::table('regression_definitions')->where('statistic_code', 'GDP_GROWTH')->where('proxy_code', 'NPL_RATIO')->value('expected_sign'));
        $this->assertEquals(30, DB::table('regression_definitions')->where('statistic_code', 'GDP_GROWTH')->where('proxy_code', 'NPL_RATIO')->value('r2_cutoff_pct'));
        $this->assertSame(5, $r['events']);
        $this->assertTrue(DB::table('structural_events')->where('code', 'MAIIC_EBANKER_TAKEON')->exists());
    }

    public function test_a_drifted_or_bypassing_value_is_put_back_with_a_warning(): void
    {
        // a direct write that bypassed the Governance Centre, and a later-dated row the engines would have read instead
        DB::table('governed_parameters')->insert([
            ['param_key' => 'stats.min_obs', 'param_value' => '8', 'effective_from' => '190001', 'status' => 'approved', 'changed_at' => now()],
            ['param_key' => 'fli.r2_cutoff.default', 'param_value' => '0.1', 'effective_from' => '202501', 'status' => 'approved', 'changed_at' => now()],
        ]);
        Log::shouldReceive('warning')->once()->withArgs(fn ($m) => str_contains($m, 'governed_parameters.stats.min_obs had drifted') && str_contains($m, 'held 8') && str_contains($m, 'says 12'));
        Log::shouldReceive('warning')->once()->withArgs(fn ($m) => str_contains($m, 'governed_parameters.fli.r2_cutoff.default had a row effective 202501') && str_contains($m, 'retired'));

        (new FliBridgeService(new GovernanceService()))->refresh('2025-12');

        $this->assertSame('12', $this->param('stats.min_obs'));
        $this->assertSame('0.3', $this->param('fli.r2_cutoff.default'));
        $this->assertSame('retired', DB::table('governed_parameters')->where('param_key', 'fli.r2_cutoff.default')->where('effective_from', '202501')->value('status'));
        $this->assertSame(1, DB::table('governed_parameters')->where('param_key', 'fli.r2_cutoff.default')->where('status', 'approved')->count());
    }

    public function test_the_governed_choices_reach_the_engines_keys(): void
    {
        DB::table('governance_settings')->where('key', 'fli_expected_sign_test')->update(['value' => 'Advisory: shown, not enforced']);
        DB::table('governance_settings')->where('key', 'fli_normality_limits')->update(['value' => 'Skewness 2.0; excess kurtosis 6.0']);
        DB::table('governance_settings')->where('key', 'fli_min_observations')->update(['value' => '24']);
        (new FliBridgeService(new GovernanceService()))->refresh('2025-12');
        $this->assertSame('advisory', $this->param('fli.sign_test.mode'));
        $this->assertSame('2.0', $this->param('stats.skew_limit'));
        $this->assertSame('6.0', $this->param('stats.kurtosis_limit'));
        $this->assertSame('24', $this->param('stats.min_obs'));
    }

    public function test_a_setting_without_an_approved_value_stops_the_bridge(): void
    {
        DB::table('governance_settings')->where('key', 'fli_alpha')->delete();
        $this->expectException(\App\Exceptions\GovernanceSettingMissingException::class);
        (new FliBridgeService(new GovernanceService()))->refresh('2025-12');
    }
}
