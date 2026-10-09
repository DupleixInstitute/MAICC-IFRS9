<?php

namespace App\Services\Fli;

use App\Services\Eir\GovernanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fills the forward-looking engines' input tables from MAIIC's own (spec v4
 * sections 14.3 to 14.6), so that the suite's engines run as they are.
 *
 *   macro_series        from macro_statistics_data with the series code; an
 *                       annual value is held as the year's observation and
 *                       interpolated to the months between, each row saying so
 *   credit_loss_series  the proxies the deriver of 14.4 produces from the staged
 *                       loan books: the NPL ratio by carrying amount, the Stage 3
 *                       share by count, and the 12-month default rate (flows
 *                       into Stage 3 over the exposure not in Stage 3 a year
 *                       earlier), for the book and per product group
 *   governed_parameters the engines' keys from the Governance Centre values
 *                       (fli_r2_cutoff, fli_min_observations, fli_alpha, both
 *                       numbers of fli_normality_limits, fli_expected_sign_test)
 *                       and the suite's defaults for the rest; this service is
 *                       the only writer and puts right any value that drifted
 *   regression_definitions  the expected sign of each driver against each proxy
 *   structural_events   the register's Malawi events, with the E-Banker take-on
 *
 * Refreshed before each run; idempotent.
 */
class FliBridgeService
{
    public const PROXIES = ['NPL_RATIO' => 'Stage 3 carrying amount over total', 'STAGE3_SHARE' => 'Stage 3 accounts over all accounts', 'DEFAULT_RATE_12M' => 'Flows into Stage 3 over the prior year\'s non-Stage-3 exposure'];

    /** Drivers and the sign of their expected relationship with a credit-loss proxy. */
    public const SIGNS = [
        'GDP_GROWTH' => 'negative', 'AGRI_GROWTH' => 'negative', 'PRIVATE_CREDIT' => 'negative', 'RESERVES' => 'negative', 'CURRENT_ACCOUNT' => 'negative',
        'CPI' => 'positive', 'MWK_USD' => 'positive', 'LENDING_RATE' => 'positive', 'REAL_RATE' => 'positive', 'BROAD_MONEY' => 'positive', 'DEBT_GDP' => 'positive', 'POLICY_RATE' => 'positive', 'PLR' => 'positive',
    ];

    public function __construct(private GovernanceService $governance)
    {
    }

    /** @return array{macro_rows:int,macro_series:int,proxy_rows:int,proxies:int,parameters:int,definitions:int,events:int} */
    public function refresh(?string $asOf = null): array
    {
        return ['macro_rows' => $this->macro(), 'macro_series' => DB::table('macro_series')->distinct()->count('statistic_code'),
            'proxy_rows' => $this->proxies(), 'proxies' => DB::table('credit_loss_series')->distinct()->count('proxy_code'),
            'parameters' => $this->parameters($asOf), 'definitions' => $this->definitions(), 'events' => $this->events()];
    }

    private function macro(): int
    {
        $series = DB::table('macro_statistics')->get(['id', 'statistic_code', 'statistic_name', 'frequency']);
        $n = 0;
        foreach ($series as $s) {
            $rows = DB::table('macro_statistics_data')->where('macro_stat_definition_id', $s->id)->orderBy('period')->get(['period', 'value', 'is_forecast', 'source']);
            if ($rows->isEmpty()) {
                continue;
            }
            $points = [];
            foreach ($rows as $r) {
                $points[substr(str_replace('-', '', $r->period), 0, 6)] = ['value' => (float) $r->value, 'type' => (int) $r->is_forecast ? 'forecast' : 'actual', 'source' => $r->source];
            }
            $out = [];
            if ($s->frequency === 'yearly') {
                // the year's value at its December; the months between two Decembers interpolated, and said so
                $keys = array_keys($points);
                for ($i = 0; $i < count($keys); $i++) {
                    $k = $keys[$i];
                    $out[$k] = $points[$k] + ['note' => $points[$k]['source']];
                    if ($i + 1 < count($keys)) {
                        $a = $points[$k]['value']; $b = $points[$keys[$i + 1]]['value'];
                        $from = CarbonImmutable::createFromFormat('Ym', $k); $to = CarbonImmutable::createFromFormat('Ym', $keys[$i + 1]);
                        $months = $from->diffInMonths($to);
                        for ($m = 1; $m < $months; $m++) {
                            $p = $from->addMonths($m)->format('Ym');
                            $out[$p] = ['value' => $a + ($b - $a) * $m / $months, 'type' => $points[$keys[$i + 1]]['type'], 'source' => 'interpolated from the annual observations', 'note' => 'interpolated'];
                        }
                    }
                }
            } else {
                foreach ($points as $k => $p) {
                    $out[$k] = $p + ['note' => $p['source']];
                }
            }
            $batch = [];
            foreach ($out as $period => $p) {
                $batch[] = ['statistic_code' => $s->statistic_code, 'statistic_name' => $s->statistic_name, 'observation_period' => $period, 'value' => round($p['value'], 6),
                    'source' => substr((string) $p['source'], 0, 64), 'value_type' => $p['type'], 'vintage' => 'bridge ' . now()->toDateString(), 'imported_at' => now(), 'created_at' => now(), 'updated_at' => now()];
            }
            foreach (array_chunk($batch, 500) as $chunk) {
                DB::table('macro_series')->upsert($chunk, ['statistic_code', 'observation_period'], ['value', 'source', 'value_type', 'vintage', 'imported_at', 'updated_at']);
            }
            $n += count($batch);
        }
        // the PLR from the landing zone, monthly, as the series the EIR engine reads
        $plr = DB::table('reference_rate_series')->where('index_code', 'PLR')->orderBy('effective_date')->get(['effective_date', 'rate']);
        if ($plr->isNotEmpty()) {
            $batch = [];
            $cursor = CarbonImmutable::parse($plr->first()->effective_date)->startOfMonth();
            $end = CarbonImmutable::parse(DB::table('loan_books')->max('reporting_period') . '-01')->startOfMonth();
            while ($cursor <= $end) {
                $rate = $plr->where('effective_date', '<=', $cursor->endOfMonth()->toDateString())->last()?->rate;
                if ($rate !== null) {
                    $batch[] = ['statistic_code' => 'PLR', 'statistic_name' => 'Prime lending rate', 'observation_period' => $cursor->format('Ym'), 'value' => round((float) $rate, 6), 'source' => 'landing zone P2_06', 'value_type' => 'actual', 'vintage' => 'bridge ' . now()->toDateString(), 'imported_at' => now(), 'created_at' => now(), 'updated_at' => now()];
                }
                $cursor = $cursor->addMonth();
            }
            DB::table('macro_series')->upsert($batch, ['statistic_code', 'observation_period'], ['value', 'source', 'updated_at']);
            $n += count($batch);
        }

        return $n;
    }

    private function proxies(): int
    {
        $periods = DB::table('loan_books')->whereNotNull('ifrs9stage_post_qualitative')->whereIn('product_code', array_keys(\App\Services\Ebanker\LandingZoneReader::LOAN_GLS))
            ->selectRaw("reporting_period, product_group, count(*) n, sum(carrying_amount) ca, sum(case when ifrs9stage_post_qualitative = '3' then carrying_amount else 0 end) ca3, sum(case when ifrs9stage_post_qualitative = '3' then 1 else 0 end) n3")
            ->groupBy('reporting_period', 'product_group')->orderBy('reporting_period')->get();
        $byPeriod = [];
        foreach ($periods as $r) {
            $byPeriod[$r->reporting_period]['_book'] = $this->add($byPeriod[$r->reporting_period]['_book'] ?? null, $r);
            $byPeriod[$r->reporting_period][$r->product_group] = $this->add(null, $r);
        }
        // flows into Stage 3: accounts in Stage 3 this month that were not in Stage 3 twelve months earlier
        $stageBy = [];
        foreach (DB::table('loan_books')->whereNotNull('ifrs9stage_post_qualitative')->whereIn('product_code', array_keys(\App\Services\Ebanker\LandingZoneReader::LOAN_GLS))->get(['contract_id', 'reporting_period', 'product_group', 'ifrs9stage_post_qualitative', 'carrying_amount']) as $r) {
            $stageBy[$r->reporting_period][$r->contract_id] = [$r->ifrs9stage_post_qualitative, (float) $r->carrying_amount, $r->product_group];
        }
        $batch = [];
        foreach ($byPeriod as $period => $segments) {
            $ym = str_replace('-', '', $period);
            $prior = CarbonImmutable::parse($period . '-01')->subYear()->format('Y-m');
            foreach ($segments as $seg => $v) {
                $code = $seg === '_book' ? '' : '_' . strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', trim((string) $seg)));
                $code = substr($code, 0, 18);
                $batch[] = $this->row('NPL_RATIO' . $code, self::PROXIES['NPL_RATIO'] . ($seg === '_book' ? '' : ", {$seg}"), $ym, $v['ca'] > 0 ? $v['ca3'] / $v['ca'] : 0.0);
                $batch[] = $this->row('STAGE3_SHARE' . $code, self::PROXIES['STAGE3_SHARE'] . ($seg === '_book' ? '' : ", {$seg}"), $ym, $v['n'] > 0 ? $v['n3'] / $v['n'] : 0.0);
                if (isset($stageBy[$prior])) {
                    $exposure = 0.0; $defaulted = 0.0;
                    foreach ($stageBy[$prior] as $cid => [$stage, $ca, $group]) {
                        if ($stage === '3' || ($seg !== '_book' && $group !== $seg)) {
                            continue;
                        }
                        $exposure += $ca;
                        if (($stageBy[$period][$cid][0] ?? null) === '3') {
                            $defaulted += $ca;
                        }
                    }
                    if ($exposure > 0) {
                        $batch[] = $this->row('DEFAULT_RATE_12M' . $code, self::PROXIES['DEFAULT_RATE_12M'] . ($seg === '_book' ? '' : ", {$seg}"), $ym, $defaulted / $exposure);
                    }
                }
            }
        }
        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table('credit_loss_series')->upsert($chunk, ['proxy_code', 'observation_period'], ['value', 'proxy_name', 'updated_at']);
        }

        return count($batch);
    }

    private function add(?array $acc, object $r): array
    {
        $acc ??= ['n' => 0, 'ca' => 0.0, 'ca3' => 0.0, 'n3' => 0];

        return ['n' => $acc['n'] + (int) $r->n, 'ca' => $acc['ca'] + (float) $r->ca, 'ca3' => $acc['ca3'] + (float) $r->ca3, 'n3' => $acc['n3'] + (int) $r->n3];
    }

    private function row(string $code, string $name, string $ym, float $value): array
    {
        return ['proxy_code' => substr($code, 0, 32), 'proxy_name' => substr($name, 0, 128), 'observation_period' => $ym, 'value' => round($value, 6), 'created_at' => now(), 'updated_at' => now()];
    }

    /**
     * The engines' governed_parameters from the Governance Centre. This is
     * the only writer of that table (system audit of 9 October 2026, finding
     * M12: the two stores had different seed defaults and a direct write to
     * the second would have bypassed the first). On every run each bridged
     * key is set to the Centre's value in force: a value found to differ is
     * overwritten and a warning logged naming the drift, and any row for a
     * bridged key dated after the bridge row, which the engines would have
     * read in its place, is retired. A Centre setting with no approved value
     * stops the bridge, as it stops every engine; nothing is defaulted here.
     */
    public function parameters(?string $asOf = null): int
    {
        $date = $asOf ? CarbonImmutable::parse($asOf . '-01')->endOfMonth() : CarbonImmutable::today();
        $num = fn (string $v) => preg_match('/(\d+(?:\.\d+)?)/', $v, $m) ? $m[1] : $v;
        $r2 = (float) $num($this->governance->get('fli_r2_cutoff', $date));
        $alpha = (float) $num($this->governance->get('fli_alpha', $date));
        // both numbers of the normality limits: skewness, then excess kurtosis
        preg_match_all('/(\d+(?:\.\d+)?)/', $this->governance->get('fli_normality_limits', $date), $limits);
        $skew = $limits[1][0] ?? null;
        $kurtosis = $limits[1][1] ?? null;
        if ($skew === null || $kurtosis === null) {
            throw new \RuntimeException('fli_normality_limits must name two numbers (skewness and excess kurtosis).');
        }
        $signTest = str_starts_with(strtolower($this->governance->get('fli_expected_sign_test', $date)), 'advisory') ? Guardrail::SIGN_TEST_ADVISORY : Guardrail::SIGN_TEST_GATING;
        $bridged = [
            'fli.r2_cutoff.default' => (string) ($r2 > 1 ? $r2 / 100 : $r2),
            'stats.min_obs' => $num($this->governance->get('fli_min_observations', $date)),
            'stats.alpha' => (string) ($alpha > 1 ? $alpha / 100 : $alpha),
            'fli.pvalue.max' => (string) ($alpha > 1 ? $alpha / 100 : $alpha),
            'stats.skew_limit' => $skew,
            'stats.kurtosis_limit' => $kurtosis,
            'fli.sign_test.mode' => $signTest,
        ];
        $suite = [
            'fli.lag_grid' => '0,3,6,9,12', 'fli.max_history_years' => '5', 'fli.vif.max' => '5.0',
            'stats.shapiro_max_n' => '50', 'stats.default_method' => 'pearson', 'fli.methodology.default' => 'regression', 'fli.adjustment.method' => 'fli_adj_byPDs', 'fli.transmission.style' => 'multiplicative',
            'ecl.scenario_method' => 'probability_weighted_outcomes', 'pd.derivation.method' => 'balance_sum_cumulative',
        ];
        foreach ($bridged + $suite as $key => $value) {
            $governed = array_key_exists($key, $bridged);
            $existing = DB::table('governed_parameters')->where('param_key', $key)->where('effective_from', '190001')->first();
            if ($existing !== null && (string) $existing->param_value !== (string) $value) {
                Log::warning(sprintf('governed_parameters.%s had drifted from %s: held %s, the Governance Centre says %s; overwritten by the FLI bridge (audit finding M12)',
                    $key, $governed ? 'the Governance Centre' : 'the suite default', $existing->param_value, $value));
            }
            DB::table('governed_parameters')->updateOrInsert(['param_key' => $key, 'effective_from' => '190001'],
                ['param_value' => (string) $value, 'status' => 'approved', 'note' => $governed ? 'bridged from the Governance Centre' : 'the suite default, written by the FLI bridge', 'changed_at' => now()]);
            // a later-dated row would win over the bridge row in the engines' resolver: it was not written here, so it is retired
            $later = DB::table('governed_parameters')->where('param_key', $key)->where('effective_from', '!=', '190001')->where('status', 'approved')->get();
            foreach ($later as $row) {
                Log::warning(sprintf('governed_parameters.%s had a row effective %s with value %s that the Governance Centre never approved; retired by the FLI bridge (audit finding M12)', $key, $row->effective_from, $row->param_value));
                DB::table('governed_parameters')->where('id', $row->id)->update(['status' => 'retired', 'note' => 'retired by the FLI bridge: not a Governance Centre value', 'changed_at' => now()]);
            }
        }
        // every other key the engines read takes the suite's seed default, insert-if-absent
        \App\Support\Fli\GovernedValues::ensureDefaults(config('database.default'), '190001');

        return (int) DB::table('governed_parameters')->distinct()->count('param_key');
    }

    private function definitions(): int
    {
        $n = 0;
        $r2 = (float) (DB::table('governed_parameters')->where('param_key', 'fli.r2_cutoff.default')->value('param_value') ?? 0.6);
        foreach (DB::table('credit_loss_series')->distinct()->pluck('proxy_code') as $proxy) {
            foreach (self::SIGNS as $code => $sign) {
                DB::table('regression_definitions')->updateOrInsert(['statistic_code' => $code, 'proxy_code' => $proxy], ['expected_sign' => $sign, 'r2_cutoff_pct' => $r2 * 100, 'comment' => 'spec v4 section 14.5: the sign a credit-loss proxy is expected to move with this driver', 'updated_at' => now(), 'created_at' => now()]);
                $n++;
            }
        }

        return $n;
    }

    private function events(): int
    {
        $n = 0;
        foreach (StructuralEventsRegister::seedSet() as $e) {
            if (DB::table('structural_events')->where('code', $e['code'])->exists()) {
                $n++;
                continue;
            }
            DB::table('structural_events')->insert(['code' => $e['code'], 'name' => $e['name'], 'event_type' => $e['event_type'], 'event_date' => $e['event_date'], 'end_date' => $e['end_date'], 'country' => $e['country'],
                'affected_variables' => json_encode($e['affected_variables']), 'direction' => $e['direction'], 'detection' => $e['detection'], 'status' => $e['status'], 'created_at' => now(), 'updated_at' => now()]);
            $n++;
        }

        return $n;
    }
}
