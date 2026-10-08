<?php

namespace Tests\Feature\FLI;

use App\Services\Eir\GovernanceService;
use App\Services\Fli\FliBridgeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The bridge of spec v4 section 14 fills the suite's engine tables from
 * MAIIC's own: an annual macro value is held at its December and the months
 * between are interpolated and say so; the credit-loss proxies come from the
 * staged loan books for the book and per segment; the governed parameters
 * carry the Governance Centre's values and the suite's defaults for the rest.
 */
class FliBridgeServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        (require base_path('database/migrations/2026_10_09_000000_create_fli_bridge_tables.php'))->up();
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
        $this->assertSame('24', DB::table('governed_parameters')->where('param_key', 'stats.min_obs')->value('param_value'));
        $this->assertSame('2.0', DB::table('governed_parameters')->where('param_key', 'stats.kurtosis_limit')->value('param_value'));
        $this->assertSame('negative', DB::table('regression_definitions')->where('statistic_code', 'GDP_GROWTH')->where('proxy_code', 'NPL_RATIO')->value('expected_sign'));
        $this->assertSame(5, $r['events']);
        $this->assertTrue(DB::table('structural_events')->where('code', 'MAIIC_EBANKER_TAKEON')->exists());
    }
}
