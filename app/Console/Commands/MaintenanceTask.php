<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * The Maintenance console's consolidated jobs: each runs its steps in order,
 * prints each step's output, and stops at the first step that fails, naming
 * it. The single steps remain artisan commands of their own.
 *
 *   php artisan maiic:task update                      migrate, then the permission, governance and SICR seeders
 *   php artisan maiic:task month-end 2026-08 --user=1  stage, PD and LGD, forward-looking route and ECL and record, EIR revenue, Mega Farm
 *   php artisan maiic:task refresh-data                World Bank macro series, then the E-Banker feed folder
 *   php artisan maiic:task health-check                system snapshot, migration status, golden numbers, report smoke test
 */
class MaintenanceTask extends Command
{
    protected $signature = 'maiic:task {task : update|month-end|refresh-data|health-check} {period? : YYYY-MM, for month-end} {--user=}';

    protected $description = 'Run one of the Maintenance console jobs: its steps in order, stopping at the first that fails';

    public function handle(): int
    {
        $task = (string) $this->argument('task');
        $period = (string) ($this->argument('period') ?? '');
        $user = (string) ($this->option('user') ?? '');
        if ($task === 'month-end' && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $this->error('The month-end needs a month, YYYY-MM.');

            return self::FAILURE;
        }
        $u = $user !== '' ? ['--user' => $user] : [];
        $steps = match ($task) {
            'update' => [
                ['Database update', 'migrate', ['--force' => true]],
                ['Permissions', 'db:seed', ['--class' => 'MaiicAdminPermissionsSeeder', '--force' => true]],
                ['Governance Centre defaults (additive)', 'db:seed', ['--class' => 'GovernanceSettingsSeeder', '--force' => true]],
                ['Qualitative SICR factors (additive)', 'db:seed', ['--class' => 'SicrQualitativeFactorsSeeder', '--force' => true]],
            ],
            'month-end' => [
                ['Clients for new loan-book customers', 'clients:link-loan-book', $u],
                ['Stage the book', 'eir:stage', ['from' => $period] + $u],
                ['PD and LGD by segment', 'ifrs9:segment-parameters', ['from' => $period] + $u],
                ['Forward-looking route, projection, ECL and the month record', 'fli:month-end', ['period' => $period, '--all' => true] + $u],
                ['Actual cash from the E-Banker ledger', 'eir:load-ledger-cash', $u],
                ['EIR revenue', 'eir:run-revenue', ['period' => $period] + $u],
                ['Mega Farm programme (when the month has Mega Farm loans)', 'megafarm:ecl', ['period' => $period] + $u, true],
            ],
            'refresh-data' => [
                ['World Bank macro series', 'macro:import-worldbank', []],
                ['E-Banker packs in the feed folder', 'eir:poll-feed-folder', []],
            ],
            'health-check' => [
                ['System snapshot', 'about', []],
                ['Database update status', 'migrate:status', []],
                ['Golden numbers and acceptance ties', 'eir:baselines', []],
                ['Every report and its PDF', 'ifrs9:smoke-reports', []],
            ],
            default => null,
        };
        if ($steps === null) {
            $this->error("Unknown job {$task}.");

            return self::FAILURE;
        }
        $n = count($steps);
        $failed = [];
        foreach ($steps as $i => $step) {
            [$label, $command, $args] = $step;
            $optional = $step[3] ?? false;
            $this->line('');
            $this->line(sprintf('<info>Step %d of %d: %s</info>  (php artisan %s)', $i + 1, $n, $label, $command));
            try {
                // the step writes straight to this output, so a step that itself calls
                // another command (the forward-looking month-end calls the ECL) still shows
                $code = Artisan::call($command, $args, $this->output);
            } catch (Throwable $e) {
                $code = 1;
                $this->error($e->getMessage());
            }
            if ($code !== 0 && $optional) {
                $this->warn("Skipped: {$label} has nothing to do this month.");
                continue;
            }
            if ($code !== 0 && $task === 'health-check') {
                // a check that fails is a finding; the other checks still run
                $failed[] = $label;
                continue;
            }
            if ($code !== 0) {
                $this->error("Stopped at step " . ($i + 1) . " ({$label}). Correct what it names and run the job again; the steps before it are done.");

                return self::FAILURE;
            }
        }
        $this->line('');
        if (! empty($failed)) {
            $this->error('Checks that failed: ' . implode(', ', $failed) . '.');

            return self::FAILURE;
        }
        $this->info("All {$n} steps done.");

        return self::SUCCESS;
    }
}
