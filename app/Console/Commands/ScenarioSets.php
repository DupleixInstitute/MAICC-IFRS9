<?php

namespace App\Console\Commands;

use App\Services\Scenario\ScenarioSetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Scenario sets on the console (spec v4 section 15).
 *
 *   php artisan scenario:sets 2026-08 --seed                 the first set of 15.8, proposed
 *   php artisan scenario:sets 2026-08 --approve=3 --user=2   a second person approves
 *   php artisan scenario:sets 2026-08 --approve=3 --bootstrap
 *   php artisan scenario:sets 2026-08 --lock=3 --user=2
 *   php artisan scenario:sets 2026-08                        list, validate, show the paths and the sensitivity
 */
class ScenarioSets extends Command
{
    protected $signature = 'scenario:sets {period : YYYY-MM} {--seed} {--approve=} {--lock=} {--bootstrap} {--user=}';

    protected $description = 'List, seed, validate, approve and lock the governed scenario sets of a period, with their back-test and sensitivity';

    public function handle(ScenarioSetService $service): int
    {
        $period = (string) $this->argument('period');
        $user = $this->option('user') !== null ? (int) $this->option('user') : null;
        try {
            if ($this->option('seed')) {
                $id = $service->seedFirstSet($period, $user);
                $this->info("Set {$id} proposed for {$period}.");
            }
            if ($this->option('approve')) {
                $service->approve((int) $this->option('approve'), $user, $this->option('bootstrap') ? ScenarioSetService::BOOTSTRAP_LABEL : null);
                $this->info('Set ' . $this->option('approve') . ' approved' . ($this->option('bootstrap') ? ' under the bootstrap label (not a MAIIC approval)' : '') . '.');
            }
            if ($this->option('lock')) {
                $service->lock((int) $this->option('lock'), $user);
                $this->info('Set ' . $this->option('lock') . ' locked.');
            }
            $sets = DB::table('governed_scenario_sets')->where('reporting_period', $period)->orderBy('version')->get();
            if ($sets->isEmpty()) {
                $this->warn("No scenario set for {$period}; --seed proposes the first set of spec 15.8.");
                return self::SUCCESS;
            }
            $rules = $service->rules($period);
            $this->line('Rules: at least ' . $rules['min_count'] . ' scenarios; base at least ' . $rules['base_floor'] . '; any weight at most ' . $rules['single_ceiling'] . '; calibration note ' . ($rules['note_required'] ? 'required' : 'optional') . '; weighting: ' . $rules['weighting']);
            foreach ($sets as $set) {
                $v = $service->validate((int) $set->id);
                $this->info("Set {$set->id} v{$set->version} '{$set->name}': {$set->status}; weights sum {$v['weights_sum']}; " . ($v['ok'] ? 'passes its rules' : 'PROBLEMS: ' . implode('; ', $v['problems'])));
                $rows = [];
                foreach (DB::table('governed_scenarios')->where('set_id', $set->id)->orderBy('order_position')->get() as $s) {
                    $shocks = DB::table('scenario_shocks')->where('scenario_id', $s->id)->get()->map(fn ($x) => "{$x->statistic_code} {$x->kind} {$x->value}")->implode(', ');
                    $rows[] = [$s->name, $s->weight, $s->is_base ? 'base' : '', $s->pd_multiplier, mb_substr((string) $s->anchored_to, 0, 60), $shocks];
                }
                $this->table(['Scenario', 'Weight', '', 'PD mult', 'Anchored to', 'Shocks on the base'], $rows);
                $paths = $service->paths((int) $set->id);
                $rows = [];
                foreach ($paths['base'] as $code => $o) {
                    $row = [$code, $o[0] !== null ? number_format($o[0], 2) : '-'];
                    foreach ($paths['scenarios'] as $name => $sc) {
                        $row[] = isset($sc['path'][$code][0]) && $sc['path'][$code][0] !== null ? number_format($sc['path'][$code][0], 2) : '-';
                    }
                    $rows[] = $row;
                }
                $this->table(array_merge(['Series, first forecast year', 'Base'], array_keys($paths['scenarios'])), $rows);
                if (in_array($set->status, ['APPROVED', 'LOCKED'], true)) {
                    $sens = json_decode($set->sensitivity ?? '', true) ?: $service->sensitivity((int) $set->id);
                    $this->line('Sensitivity (' . $sens['loans'] . ' loans): ' . collect($sens['per_scenario'])->map(fn ($p, $n) => "{$n} " . number_format($p['ecl'], 0))->implode('; ') . ' | weighted ' . number_format($sens['weighted_ecl'], 0) . ' | +10 to downside ' . number_format((float) $sens['ten_points_to_downside'], 0) . ' | +10 to upside ' . number_format((float) $sens['ten_points_to_upside'], 0));
                    $bt = json_decode($set->backtest ?? '', true) ?: $service->backtest((int) $set->id);
                    $this->line('Back-test: ' . $bt['note']);
                }
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
