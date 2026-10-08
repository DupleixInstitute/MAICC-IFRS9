<?php

namespace App\Console\Commands;

use App\Services\Fli\CorrelationFinder;
use App\Services\Fli\Diagnostics;
use App\Services\Fli\FliBridgeService;
use App\Services\Fli\FliSeriesProfiler;
use App\Services\Fli\RegressionEngine;
use App\Services\Fli\StructuralEventsRegister;
use App\Support\Fli\GovernedValues;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The forward-looking chain of spec v4 sections 14.3 to 14.6, end to end:
 * the bridge fills the engines' inputs from MAIIC's tables; the profiler
 * says what each series is; the correlation finder sweeps every driver
 * against every credit-loss proxy over the lag grid under the guardrail;
 * the regression engine fits the candidates and applies or declines each
 * with its reason. Nothing is applied to a PD here: an applied fit is a
 * proposal for the FLI route, under maker-checker.
 *
 *   php artisan fli:correlate 2026-08
 *   php artisan fli:correlate 2026-08 --no-refresh --top=20
 */
class FliCorrelate extends Command
{
    protected $signature = 'fli:correlate {period : YYYY-MM} {--no-refresh : Use the bridge tables as they are} {--top=15} {--user=1}';

    protected $description = 'Refresh the FLI bridge, profile the series, sweep the correlations under the guardrail and fit the candidates';

    public function handle(FliBridgeService $bridge): int
    {
        $period = (string) $this->argument('period');
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('Period must be YYYY-MM.');
            return self::FAILURE;
        }
        $ym = str_replace('-', '', $period);
        $conn = (string) config('database.default');
        try {
            if (! $this->option('no-refresh')) {
                $r = $bridge->refresh($period);
                $this->info("Bridge: {$r['macro_rows']} macro rows over {$r['macro_series']} series; {$r['proxy_rows']} proxy rows over {$r['proxies']} proxies; {$r['parameters']} parameters; {$r['definitions']} sign definitions; {$r['events']} structural events.");
            }
            $gov = new GovernedValues($ym, $conn);
            $profiled = (new FliSeriesProfiler($conn, $gov))->profileAll();
            $this->info("Profiled {$profiled} series: " . DB::table('fli_series_distributions')->selectRaw("kind, count(*) n")->groupBy('kind')->get()->map(fn ($r) => "{$r->n} {$r->kind}")->implode(', '));
            $events = (new StructuralEventsRegister($conn))->all();
            $this->info(count($events) . ' structural events in the register: ' . implode(', ', array_map(fn ($e) => $e['code'], $events)));

            $finder = new CorrelationFinder($conn, $gov, new Diagnostics($gov, null, new StructuralEventsRegister($conn)));
            $sweep = $finder->run($ym);
            $this->info($sweep['note']);
            $this->line('By verdict: ' . json_encode($sweep['by_verdict'] ?? []));
            $rows = [];
            foreach (array_slice($sweep['ranked'] ?? [], 0, (int) $this->option('top')) as $s) {
                $rows[] = [$s['statistic_code'], $s['proxy_code'], $s['lag_months'], number_format((float) ($s['r_squared'] ?? 0), 4), $s['sign_ok'] ? 'yes' : 'no', $s['verdict'] ?? '', mb_substr((string) ($s['reason'] ?? ''), 0, 90)];
            }
            if ($rows !== []) {
                $this->table(['Driver', 'Proxy', 'Lag', 'R²', 'Sign ok', 'Verdict', 'Reason'], $rows);
            }

            $fit = (new RegressionEngine($conn, $gov))->fitCandidates($ym);
            $this->info("Regression: {$fit['fitted']} fitted, {$fit['applied']} applied, {$fit['declined']} declined" . ($fit['declined_by_reason'] !== [] ? '; declined by reason ' . json_encode($fit['declined_by_reason']) : '') . ($fit['note'] ?? '' ? ' | ' . ($fit['note'] ?? '') : ''));
            $rows = [];
            foreach (array_slice($fit['fits'] ?? [], 0, (int) $this->option('top')) as $f) {
                $rows[] = [$f['statistic_code'] ?? '', $f['proxy_code'] ?? '', $f['lag_months'] ?? '', isset($f['slope']) ? number_format((float) $f['slope'], 6) : '', isset($f['r_squared']) ? number_format((float) $f['r_squared'], 4) : '', $f['n_obs'] ?? '', $f['verdict'] ?? '', mb_substr((string) ($f['declined_reason'] ?? ''), 0, 60)];
            }
            if ($rows !== []) {
                $this->table(['Driver', 'Proxy', 'Lag', 'Slope', 'R²', 'n', 'Verdict', 'Declined because'], $rows);
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
