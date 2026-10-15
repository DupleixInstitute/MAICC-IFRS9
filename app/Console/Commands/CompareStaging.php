<?php

namespace App\Console\Commands;

use App\Services\Eir\StagingComparisonService;
use App\Services\Eir\StagingPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The staging comparison run (Staging Module Specification v1, section 4):
 * a month's stages and ECL under another staging policy, or the policy in
 * force with settings changed, beside the stages as booked, with the setting
 * behind each difference. Nothing booked is changed; a locked month can be
 * compared.
 *
 *   php artisan staging:compare 2025-12 "Directive-aligned staging" --user=1
 *   php artisan staging:compare 2026-09 "Single-ladder staging" --set="stage_cure_months=3 months"
 */
class CompareStaging extends Command
{
    protected $signature = 'staging:compare {period : YYYY-MM} {preset : Single-ladder staging, Directive-aligned staging, or Custom (the policy in force with --set)}
        {--set=* : a setting changed on top of the preset, as key=value} {--user=} {--note=}';

    protected $description = 'Compare a month\'s booked stages and ECL with those under another staging policy, and name the setting behind each difference; nothing booked is changed';

    public function handle(StagingComparisonService $comparison): int
    {
        $period = (string) $this->argument('period');
        $preset = (string) $this->argument('preset');
        try {
            if (! DB::table('loan_books')->where('reporting_period', $period)->exists()) {
                $this->error(\App\Http\Controllers\MaintenanceController::noLoanBookMessage($period, 'The staging comparison'));

                return self::FAILURE;
            }
            $settings = [];
            foreach ((array) $this->option('set') as $pair) {
                [$key, $value] = array_pad(explode('=', (string) $pair, 2), 2, null);
                if ($value === null || ! isset(StagingPolicy::SETTINGS[trim($key)])) {
                    $this->error("'{$pair}' is not a staging setting as key=value. The settings: " . implode(', ', array_keys(StagingPolicy::SETTINGS)) . '.');

                    return self::FAILURE;
                }
                $settings[trim($key)] = trim($value);
            }
            $rules = $preset === StagingPolicy::PRESET_CUSTOM
                ? $comparison->rulesInForce($period)->with($settings)
                : $comparison->rules($preset, $settings);
            $id = $comparison->run($period, $rules, $this->option('user') !== null ? (int) $this->option('user') : null, $this->option('note'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $run = DB::table('staging_comparison_runs')->find($id);
        $totals = json_decode($run->totals, true);
        $this->info("Comparison run {$id}, {$period}: booked under '{$run->booked_preset}', compared with '{$run->compared_preset}'. {$run->loans} loans, {$run->loans_moved} change stage. Nothing booked was changed.");
        $this->table(['Movement', 'Loans', 'Carrying amount'], collect($totals['moves'])->map(fn ($m, $k) => ['Stage ' . str_replace('>', ' to Stage ', $k), $m['loans'], number_format($m['carrying_amount'], 2)])->values()->all());
        $this->table(['Reason', 'ECL effect'], collect($totals['ecl_by_reason'])->map(fn ($v, $k) => [$k, number_format($v, 2)])->values()->all());
        $this->line('ECL booked ' . number_format((float) $run->booked_ecl, 2) . ', compared ' . number_format((float) $run->compared_ecl, 2) . ', effect ' . number_format((float) $run->compared_ecl - (float) $run->booked_ecl, 2)
            . '; Stage 3 interest effect for the month ' . number_format((float) $totals['stage3_interest_effect'], 2)
            . ($totals['ecl_not_known'] > 0 ? "; {$totals['ecl_not_known']} loan(s) move to a stage no loan of the book is in, so their ECL there is not known" : '') . '.');

        return self::SUCCESS;
    }
}
