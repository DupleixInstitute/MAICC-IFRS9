<?php

namespace App\Console\Commands;

use App\Services\Fli\FliRouteService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The forward-looking route on the console (spec v4 sections 14.6 to 14.8).
 *
 *   php artisan fli:apply 2026-08 --fits                      the applied fits of the period, best first
 *   php artisan fli:apply 2026-08 --propose=372 --user=1
 *   php artisan fli:apply 2026-08 --approve=372 --user=2      (or --bootstrap)
 *   php artisan fli:apply 2026-08 --user=1                    apply the route in force to every loan
 */
class FliApply extends Command
{
    protected $signature = 'fli:apply {period : YYYY-MM} {--fits} {--propose=} {--approve=} {--bootstrap} {--user=} {--top=10}';

    protected $description = 'Propose and approve a fit for the forward-looking route and apply the route to every loan of the period';

    public function handle(FliRouteService $service): int
    {
        $period = (string) $this->argument('period');
        $ym = str_replace('-', '', $period);
        $user = $this->option('user') !== null ? (int) $this->option('user') : null;
        try {
            if ($this->option('fits')) {
                $rows = DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')->where('f.reporting_period', $ym)->where('f.verdict', 'applied')
                    ->orderByDesc('f.r_squared')->limit((int) $this->option('top'))->get(['f.id', 'r.statistic_code', 'r.proxy_code', 'r.lag_months', 'f.slope', 'f.intercept', 'f.correlation_r', 'f.r_squared', 'f.n_obs', 'f.approval_status']);
                $this->table(['Fit', 'Driver', 'Proxy', 'Lag', 'Slope', 'Intercept', 'R', 'R²', 'n', 'Approval'], $rows->map(fn ($r) => [$r->id, $r->statistic_code, $r->proxy_code, $r->lag_months, number_format((float) $r->slope, 6), number_format((float) $r->intercept, 4), number_format((float) $r->correlation_r, 4), number_format((float) $r->r_squared, 4), $r->n_obs, $r->approval_status])->all());
                return self::SUCCESS;
            }
            if ($this->option('propose')) {
                $service->proposeFit((int) $this->option('propose'), $user);
                $this->info('Fit ' . $this->option('propose') . ' proposed for the route; a second person approves it.');
                return self::SUCCESS;
            }
            if ($this->option('approve')) {
                $service->approveFit((int) $this->option('approve'), $user, $this->option('bootstrap') ? \App\Services\Scenario\ScenarioSetService::BOOTSTRAP_LABEL : null);
                $this->info('Fit ' . $this->option('approve') . ' approved' . ($this->option('bootstrap') ? ' under the bootstrap label (not a MAIIC approval)' : '') . '.');
                return self::SUCCESS;
            }
            $r = $service->apply($period, $user);
            $this->info("Route '{$r['route']}', method '{$r['method']}', weighting '{$r['weighting']}'" . ($r['fit'] ? "; fit {$r['fit']} {$r['fit_relationship']}" : '') . ($r['set'] ? "; scenario set {$r['set']}" : ''));
            if ($r['note']) {
                $this->warn($r['note']);
            }
            $this->table(['Scenario', 'Weight', 'Driver', 'Predicted proxy', 'Adjustment'], array_map(fn ($n, $s) => [$n, $s['weight'], $s['driver'], $s['predicted_proxy'], $s['adjustment']], array_keys($r['scenarios']), $r['scenarios']));
            $this->info("{$r['loans']} loans: {$r['adjusted']} adjusted, {$r['held']} held (Stage 3 at 100 percent, or no change).");
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
