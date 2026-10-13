<?php

namespace App\Console\Commands;

use App\Services\Fli\FliCloseService;
use App\Services\Fli\FliMonthEnd;
use App\Services\Fli\FliProjectionService;
use App\Services\Fli\FliRouteService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * The forward-looking month-end on the console (FLI module specification v1,
 * section 5). Each step can be run on its own, or --all runs every automated
 * step that is not done, in order, and stops at the first that cannot run
 * (with the reason). The approvals (the model version, the scenario set, the
 * overlays and the sign-off) stay with people; this command never approves.
 *
 *   php artisan fli:month-end 2026-08                  the steps and where each stands
 *   php artisan fli:month-end 2026-08 --project --user=1
 *   php artisan fli:month-end 2026-08 --close --user=1
 *   php artisan fli:month-end 2026-08 --all --user=1   apply, project, ECL and record
 */
class FliMonthEndCommand extends Command
{
    protected $signature = 'fli:month-end {period : YYYY-MM} {--all : Run every automated step that is not done} {--project : Project the scenarios year by year} {--close : Record the month (ECL per scenario and the snapshot)} {--user=}';

    protected $description = 'Show the forward-looking month-end steps for a month, run the projection or the record, or run every remaining automated step';

    public function handle(FliMonthEnd $monthEnd, FliRouteService $route, FliProjectionService $projection, FliCloseService $close): int
    {
        $period = (string) $this->argument('period');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $this->error('Period must be YYYY-MM.');

            return self::FAILURE;
        }
        $user = $this->option('user') !== null ? (int) $this->option('user') : null;
        try {
            if ($this->option('project')) {
                $this->project($projection, $period, $user);

                return self::SUCCESS;
            }
            if ($this->option('close')) {
                $this->record($close, $period, $user);

                return self::SUCCESS;
            }
            if ($this->option('all')) {
                return $this->all($monthEnd, $route, $projection, $close, $period, $user);
            }
            $this->show($monthEnd->steps($period));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function all(FliMonthEnd $monthEnd, FliRouteService $route, FliProjectionService $projection, FliCloseService $close, string $period, ?int $user): int
    {
        $steps = collect($monthEnd->steps($period))->keyBy('key');
        foreach (['book', 'macro', 'model', 'set', 'overlays'] as $k) {
            $s = $steps[$k];
            if (in_array($s['state'], ['blocked', 'todo'], true) && $k !== 'macro') {
                $this->error("{$s['title']}: {$s['status']}");
                $this->line('That step needs a person (an import or an approval). Do it on its screen, then run this again.');

                return self::FAILURE;
            }
            $this->line("<info>ok</info>  {$s['title']}: {$s['status']}");
        }
        if ($steps['apply']['state'] !== 'done') {
            $r = $route->apply($period, $user);
            $this->line("<info>run</info> Forward-looking route: {$r['adjusted']} loans adjusted, {$r['held']} held" . ($r['note'] ? " ({$r['note']})" : ''));
        } else {
            $this->line('<info>ok</info>  Forward-looking route already applied under the version and set in force');
        }
        $this->project($projection, $period, $user);
        $this->line('<info>run</info> ECL on the post-FLI PD ...');
        $code = Artisan::call('ifrs9:segment-ecl', ['from' => $period, '--pd' => 'pd_post_fli']);
        $this->output->write(Artisan::output());
        if ($code !== 0) {
            $this->error('The ECL run stopped; the month is not recorded. Correct what it names and run this again.');

            return self::FAILURE;
        }
        $this->record($close, $period, $user);
        $this->line('Next: a second person signs the month off on Forward-Looking Information, Overview.');

        return self::SUCCESS;
    }

    private function project(FliProjectionService $projection, string $period, ?int $user): void
    {
        $p = $projection->run($period, $user);
        $this->line("<info>run</info> Projection: " . count($p['segments']) . ' portfolios, ' . count($p['adjustments']) . " scenarios, {$p['horizon']['label']}");
        $rows = [];
        foreach ($p['segments'] as $s) {
            foreach ($s['summary'] as $name => $v) {
                $rows[] = [$s['portfolio'], $name, number_format($s['pd_pre'] * 100, 2) . '%', number_format($v['pd_year1'] * 100, 2) . '%', number_format($v['lifetime_pd'] * 100, 2) . '%', $s['years']];
            }
        }
        $this->table(['Portfolio', 'Scenario', 'PD before FLI', 'PD year 1', 'Lifetime PD', 'Years'], $rows);
    }

    private function record(FliCloseService $close, string $period, ?int $user): void
    {
        $s = $close->record($period, $user);
        $this->line("<info>run</info> Recorded {$period}: booked " . number_format((float) $s['booked_ecl'], 2) . ', weighted over the scenarios ' . number_format((float) $s['weighted_ecl'], 2)
            . ', difference ' . number_format((float) $s['difference'], 2) . ($s['reconciles'] ? ' (ties)' : ' (DOES NOT TIE)'));
        $this->table(['Scenario', 'Weight', 'ECL', 'Contribution'], array_map(fn ($r) => [$r['scenario'], $r['weight'], number_format($r['ecl'], 2), number_format($r['contribution'], 2)], $s['per_scenario']));
        if ($s['needs_commentary']) {
            $this->warn('The ECL moved by ' . round((float) $s['movement_pct'] * 100, 1) . ' percent against the previous month-end: a commentary is needed before sign-off.');
        }
    }

    private function show(array $steps): void
    {
        $this->table(['#', 'Step', 'State', 'Where it stands'], array_map(fn ($s, $i) => [$i + 1, $s['title'], strtoupper($s['state']), mb_substr($s['status'], 0, 110)], $steps, array_keys($steps)));
    }
}
