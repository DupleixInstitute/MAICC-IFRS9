<?php

namespace Tests\Feature\FLI;

use App\Services\Fli\CorrelationFinder;
use App\Services\Fli\Guardrail;
use App\Services\Fli\RegressionEngine;
use App\Support\Fli\GovernedValues;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The governed expected-sign test (spec v4 section 14.4; system audit of
 * 9 October 2026, finding M12). A fit whose realised sign is the wrong way
 * round is declined under the gating option, as before; under the advisory
 * option it is recorded as a warning on the fit and the verdict is decided
 * by the other tests, so the finder and the regression engine both keep it
 * applicable with the sign shown.
 */
class ExpectedSignTestModeTest extends TestCase
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
        DB::table('governed_parameters')->where('param_key', 'fli.lag_grid')->update(['param_value' => '0']);   // one lag, so the pair under test is the one scored
        // the economy says defaults fall as growth rises; the data says the opposite, strongly and significantly
        DB::table('regression_definitions')->insert(['statistic_code' => 'GDP_GROWTH', 'proxy_code' => 'NPL_RATIO', 'expected_sign' => 'negative', 'r2_cutoff_pct' => 30, 'created_at' => now(), 'updated_at' => now()]);
        $period = \Carbon\CarbonImmutable::parse('2022-01-01');
        for ($i = 0; $i < 36; $i++) {
            $x = ($i % 7) + 0.1 * ($i % 3);
            $y = 0.02 + 0.004 * $x + 0.0003 * ($i % 2);
            DB::table('macro_series')->insert(['statistic_code' => 'GDP_GROWTH', 'observation_period' => $period->addMonths($i)->format('Ym'), 'value' => $x, 'value_type' => 'actual', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('credit_loss_series')->insert(['proxy_code' => 'NPL_RATIO', 'observation_period' => $period->addMonths($i)->format('Ym'), 'value' => $y, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function mode(string $mode): GovernedValues
    {
        DB::table('governed_parameters')->where('param_key', 'fli.sign_test.mode')->update(['param_value' => $mode]);

        return new GovernedValues('202412', 'sqlite');
    }

    public function test_the_guardrail_declines_a_wrong_sign_when_gating_and_warns_when_advisory(): void
    {
        $fit = ['n' => 30, 'r2' => 0.9, 'p_value' => 0.001, 'slope' => 2.5];
        $gating = (new Guardrail())->evaluate($fit, 'negative', 0.3, 12, 0.05, Guardrail::SIGN_TEST_GATING);
        $this->assertSame('declined', $gating['verdict']);
        $this->assertSame(Guardrail::REASON_WRONG_SIGN, $gating['declined_reason']);
        $this->assertNull($gating['sign_warning']);

        $advisory = (new Guardrail())->evaluate($fit, 'negative', 0.3, 12, 0.05, Guardrail::SIGN_TEST_ADVISORY);
        $this->assertSame('applied', $advisory['verdict']);
        $this->assertNull($advisory['declined_reason']);
        $this->assertFalse($advisory['sign_ok']);
        $this->assertStringContainsString('wrong sign: realised positive against expected negative; the sign test is advisory', $advisory['sign_warning']);

        // the other three tests still decide under the advisory option
        $weak = (new Guardrail())->evaluate(['n' => 30, 'r2' => 0.1, 'p_value' => 0.4, 'slope' => 2.5], 'negative', 0.3, 12, 0.05, Guardrail::SIGN_TEST_ADVISORY);
        $this->assertSame('declined', $weak['verdict']);
        $this->assertSame(Guardrail::REASON_R2_BELOW_CUTOFF, $weak['declined_reason']);
        $this->assertNotNull($weak['sign_warning']);
    }

    public function test_the_regression_engine_records_the_warning_on_the_fit_under_the_advisory_option(): void
    {
        $gating = (new RegressionEngine('sqlite', $this->mode('gating')))->fitCandidates('202412');
        $fit = collect($gating['fits'])->firstWhere('proxy_code', 'NPL_RATIO');
        $this->assertSame('declined', $fit['verdict']);
        $this->assertSame('wrong_sign', $fit['declined_reason']);
        $this->assertNull($fit['sign_warning']);
        $this->assertSame('declined', DB::table('fli_fits')->orderByDesc('id')->value('verdict'));

        $advisory = (new RegressionEngine('sqlite', $this->mode('advisory')))->fitCandidates('202412');
        $fit = collect($advisory['fits'])->firstWhere('proxy_code', 'NPL_RATIO');
        $this->assertSame('applied', $fit['verdict']);
        $this->assertNull($fit['declined_reason']);
        $this->assertStringContainsString('the sign test is advisory', $fit['sign_warning']);
        $row = DB::table('fli_fits')->orderByDesc('id')->first();
        $this->assertSame('applied', $row->verdict);
        $this->assertSame(0, (int) $row->sign_ok);
        $this->assertStringContainsString('wrong sign: realised positive against expected negative', $row->sign_warning);
    }

    public function test_the_finder_keeps_a_wrong_signed_pair_applicable_under_the_advisory_option(): void
    {
        $gating = (new CorrelationFinder('sqlite', $this->mode('gating')))->run('202412');
        $pair = collect($gating['ranked'])->first(fn ($s) => $s['statistic_code'] === 'GDP_GROWTH' && $s['proxy_code'] === 'NPL_RATIO');
        $this->assertSame('rejected', $pair['verdict']);
        $this->assertStringContainsString('WRONG sign positive vs negative', $pair['reason']);
        $this->assertStringNotContainsString('advisory', $pair['reason']);

        $advisory = (new CorrelationFinder('sqlite', $this->mode('advisory')))->run('202412');
        $pair = collect($advisory['ranked'])->first(fn ($s) => $s['statistic_code'] === 'GDP_GROWTH' && $s['proxy_code'] === 'NPL_RATIO');
        $this->assertSame('recommended', $pair['verdict']);
        $this->assertFalse($pair['sign_ok']);
        $this->assertStringContainsString('WRONG sign positive vs negative (warning: sign test advisory)', $pair['reason']);
        $saved = DB::table('fli_suggestions')->where('run_id', $advisory['run_id'])->where('statistic_code', 'GDP_GROWTH')->first();
        $this->assertSame('recommended', $saved->verdict);
        $this->assertSame(0, (int) $saved->sign_ok);
    }
}
