<?php

namespace Tests\Feature\Macro;

use App\Services\Eir\GovernanceService;
use App\Services\Macro\MacroImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * Which macro source wins where two carry the same series and period is the
 * governed order of macro_source_precedence (spec v4 section 13.5; system
 * audit of 9 October 2026, finding M12: the order was written in code and
 * the setting read by nothing). The order is read from the option text.
 */
class MacroImportPrecedenceTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('macro_statistics', function (Blueprint $t) { $t->increments('id'); $t->string('statistic_code'); $t->string('statistic_name'); $t->string('frequency'); $t->json('external_codes')->nullable(); });
        Schema::create('macro_statistics_data', function (Blueprint $t) { $t->increments('id'); $t->integer('macro_stat_definition_id'); $t->integer('scenario_profile_id')->nullable(); $t->integer('scenario_id')->nullable(); $t->date('period'); $t->decimal('value', 15, 4); $t->boolean('is_forecast')->default(0); $t->decimal('actual_value', 14, 4)->nullable(); $t->string('source')->nullable(); $t->integer('source_import_batch_id')->nullable(); $t->integer('created_by'); $t->timestamps(); });
        Schema::create('macro_source_import_batches', function (Blueprint $t) { $t->increments('id'); $t->string('source'); $t->string('address')->nullable(); $t->string('country', 3)->default('MWI'); $t->string('file_sha256')->nullable(); $t->timestamp('fetched_at')->nullable(); $t->integer('committed_by')->nullable(); $t->integer('rows')->default(0); $t->json('series')->nullable(); $t->text('note')->nullable(); $t->timestamps(); });
        Schema::create('scenario_profiles', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('profile_code'); $t->string('description')->nullable(); $t->integer('created_by'); $t->timestamps(); });
        Schema::create('scenarios', function (Blueprint $t) { $t->increments('id'); $t->integer('profile_id'); $t->string('name'); $t->string('description')->nullable(); $t->decimal('probability', 5, 2); $t->boolean('is_base_case'); $t->boolean('is_active'); $t->timestamps(); });
        DB::table('macro_statistics')->insert([
            ['id' => 1, 'statistic_code' => 'GDP_GROWTH', 'statistic_name' => 'Real GDP growth', 'frequency' => 'yearly', 'external_codes' => json_encode(['world_bank' => 'NY.GDP.MKTP.KD.ZG', 'imf_weo' => 'NGDP_RPCH'])],
            ['id' => 2, 'statistic_code' => 'POLICY_RATE', 'statistic_name' => 'Policy rate', 'frequency' => 'monthly', 'external_codes' => json_encode(['rbm' => 'policy_rate'])],
        ]);
    }

    private function setOption(string $value): void
    {
        DB::table('governance_settings')->where('key', 'macro_source_precedence')->update(['value' => $value]);
    }

    private function commit(string $series, string $source, float $value, string $type = 'actual', string $period = '2024-12-31'): array
    {
        $preview = ['series_code' => $series, 'indicator_code' => null, 'country' => 'MWI', 'source' => $source, 'address' => null, 'fetched_at' => now()->toDateTimeString(),
            'rows' => [['period' => $period, 'year' => 2024, 'value' => $value, 'value_type' => $type]]];

        return (new MacroImportService(new GovernanceService()))->commit($preview, 1, $source);
    }

    private function held(int $seriesId = 1): array
    {
        $r = DB::table('macro_statistics_data as d')->join('macro_source_import_batches as b', 'b.id', '=', 'd.source_import_batch_id')->where('d.macro_stat_definition_id', $seriesId)->first(['d.value', 'b.source']);

        return [(float) $r->value, $r->source];
    }

    public function test_the_order_is_read_from_the_option_text(): void
    {
        $this->assertSame([['source' => 'world_bank', 'kind' => 'actual'], ['source' => 'imf_weo', 'kind' => 'forecast'], ['source' => 'rbm_file', 'kind' => 'rate']],
            MacroImportService::precedenceFromOption('World Bank actuals, IMF forecasts, RBM rates'));
        $this->assertSame([['source' => 'imf_weo', 'kind' => 'all']], MacroImportService::precedenceFromOption('IMF for everything it carries'));
        $this->assertSame([['source' => 'manual', 'kind' => 'all'], ['source' => 'world_bank', 'kind' => 'all'], ['source' => 'imf_weo', 'kind' => 'all'], ['source' => 'rbm_file', 'kind' => 'all']],
            MacroImportService::precedenceFromOption('Manual entry first, then the sources'));
        // every option in the catalogue parses to at least one source
        foreach (GovernanceService::catalogue()['macro_source_precedence']['options'] as $option) {
            $this->assertNotEmpty(MacroImportService::precedenceFromOption($option), $option);
        }
    }

    public function test_the_seeded_order_trusts_the_world_bank_for_actuals_and_the_imf_for_forecasts(): void
    {
        $r = $this->commit('GDP_GROWTH', 'world_bank', 2.1);
        $this->assertSame('World Bank actuals, IMF forecasts, RBM rates', $r['precedence']);
        $r = $this->commit('GDP_GROWTH', 'imf_weo', 2.4);             // the IMF's actual does not displace the World Bank's
        $this->assertSame(1, $r['kept_precedence']);
        $this->assertSame([2.1, 'world_bank'], $this->held());
        $this->commit('GDP_GROWTH', 'world_bank', 2.2);                  // the World Bank's own refresh does
        $this->assertSame([2.2, 'world_bank'], $this->held());

        // a forecast year: the IMF wins, and a later World Bank forecast does not displace it
        $this->commit('GDP_GROWTH', 'world_bank', 3.0, 'forecast', '2026-12-31');
        $this->commit('GDP_GROWTH', 'imf_weo', 3.5, 'forecast', '2026-12-31');
        $r = $this->commit('GDP_GROWTH', 'world_bank', 3.1, 'forecast', '2026-12-31');
        $this->assertSame(1, $r['kept_precedence']);
        $this->assertEquals(3.5, (float) DB::table('macro_statistics_data')->where('period', '2026-12-31')->value('value'));

        // an actual held is never replaced by a forecast, whichever source brings it
        $r = $this->commit('GDP_GROWTH', 'imf_weo', 9.9, 'forecast');
        $this->assertSame(1, $r['kept_actual']);
        $this->assertSame([2.2, 'world_bank'], $this->held());

        // a rate series: the Reserve Bank file wins over the IMF
        $this->commit('POLICY_RATE', 'imf_weo', 24.0);
        $this->commit('POLICY_RATE', 'rbm_file', 26.0);
        $r = $this->commit('POLICY_RATE', 'imf_weo', 25.0);
        $this->assertSame(1, $r['kept_precedence']);
        $this->assertSame([26.0, 'rbm_file'], $this->held(2));

        // a manual entry overrides with its reason; under this option the World Bank's next refresh replaces it
        $this->commit('GDP_GROWTH', 'manual', 2.5);
        $this->assertSame([2.5, 'manual'], $this->held());
        $this->commit('GDP_GROWTH', 'world_bank', 2.3);
        $this->assertSame([2.3, 'world_bank'], $this->held());
    }

    public function test_the_imf_option_puts_the_imf_first_for_every_kind(): void
    {
        $this->setOption('IMF for everything it carries');
        $this->commit('GDP_GROWTH', 'imf_weo', 2.4);
        $r = $this->commit('GDP_GROWTH', 'world_bank', 2.1);
        $this->assertSame(1, $r['kept_precedence']);
        $this->assertSame([2.4, 'imf_weo'], $this->held());
        $this->commit('POLICY_RATE', 'rbm_file', 26.0);
        $this->commit('POLICY_RATE', 'imf_weo', 24.0);
        $this->assertSame([24.0, 'imf_weo'], $this->held(2));
    }

    public function test_the_manual_first_option_keeps_a_hand_entry_over_every_source(): void
    {
        $this->setOption('Manual entry first, then the sources');
        $this->commit('GDP_GROWTH', 'world_bank', 2.1);
        $this->commit('GDP_GROWTH', 'manual', 2.5);
        $r = $this->commit('GDP_GROWTH', 'world_bank', 2.2);
        $this->assertSame(1, $r['kept_precedence']);
        $this->assertSame([2.5, 'manual'], $this->held());
        $this->assertSame('Manual entry first, then the sources', json_decode(DB::table('audit_logs')->orderByDesc('id')->value('meta'), true)['precedence']);
    }

    public function test_a_commit_without_an_approved_order_is_refused(): void
    {
        DB::table('governance_settings')->where('key', 'macro_source_precedence')->delete();
        $this->expectException(\App\Exceptions\GovernanceSettingMissingException::class);
        $this->commit('GDP_GROWTH', 'world_bank', 2.1);
    }
}
