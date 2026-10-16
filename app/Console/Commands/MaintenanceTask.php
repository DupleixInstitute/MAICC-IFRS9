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
 *   php artisan maiic:task month-end 2026-08 --user=1  scheme settings, restructure candidates and rate moves (detection only), stage, PD and LGD, forward-looking route and ECL and record, ledger cash, GL fees, EIR revenue, Mega Farm
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
        if ($task === 'month-end' && $user === '') {
            // the revenue run and the approvals record a person; refuse at the start, not at step 9
            $this->error('The month-end needs --user=<id>, the person running it.');

            return self::FAILURE;
        }
        if ($task === 'month-end' && ! \Illuminate\Support\Facades\DB::table('loan_books')->where('reporting_period', $period)->exists()) {
            // refuse at the start, naming the month and what it lacks, instead of running four steps
            // and staging an empty book before the PD step stops (final check of 14 October 2026)
            $this->error(\App\Http\Controllers\MaintenanceController::noLoanBookMessage($period, 'The month-end'));

            return self::FAILURE;
        }
        $u = $user !== '' ? ['--user' => $user] : [];
        $steps = self::steps($task, $period, $u);
        if ($steps === null) {
            $this->error("Unknown job {$task}.");

            return self::FAILURE;
        }
        if ($task !== 'month-end' || ! \App\Services\RunRecordService::available()) {
            return $this->runSteps($task, $steps);
        }
        // the month-end is one run of the month (Staging Module Specification v1, section 8.8): each engine records
        // its step in it, and at the end it freezes what the month ran on and stamps its number on the results
        $runs = app(\App\Services\RunRecordService::class);
        $runId = $runs->start($period, \App\Services\RunRecordService::MONTH_END, (int) $user, 'Month-end job');
        \App\Support\RunContext::open($period, $runId);
        $this->line("Run record {$runId} for {$period}.");
        try {
            $code = $this->runSteps($task, $steps);
        } catch (Throwable $e) {
            \App\Support\RunContext::close($period);
            $runs->finish($runId, false, $e->getMessage());
            throw $e;
        }
        \App\Support\RunContext::close($period);
        $runs->finish($runId, $code === self::SUCCESS, $code === self::SUCCESS ? null : 'A step of the month-end job failed.');
        $this->line("Run record {$runId} " . ($code === self::SUCCESS ? 'completed' : 'failed') . '; its snapshot is fingerprinted and its number is on the month results.');

        return $code;
    }

    /** The steps in order; stops at the first that fails (a health check runs them all). */
    private function runSteps(string $task, array $steps): int
    {
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

    /**
     * The steps of a job, in order: [label, command, arguments, optional?]. Public so the order
     * can be tested without running a step.
     *
     * @return list<array>|null
     */
    public static function steps(string $task, string $period, array $u): ?array
    {
        return match ($task) {
            'update' => [
                ['Database update', 'migrate', ['--force' => true]],
                // a stale cached permission list made the seeders below fail (UI audit, 14 Oct 2026)
                ['Permission cache cleared', 'permission:cache-reset', []],
                ['Permissions', 'db:seed', ['--class' => 'MaiicAdminPermissionsSeeder', '--force' => true]],
                ['Support ticket permissions', 'db:seed', ['--class' => 'TicketsPermissionsSeeder', '--force' => true]],
                ['Governance Centre defaults (additive)', 'db:seed', ['--class' => 'GovernanceSettingsSeeder', '--force' => true]],
                ['Qualitative SICR factors (additive)', 'db:seed', ['--class' => 'SicrQualitativeFactorsSeeder', '--force' => true]],
            ],
            'month-end' => [
                ['Clients for new loan-book customers', 'clients:link-loan-book', $u],
                // the scheme master of the pack (SM_01, DD_10 where it lacks a field) so a schedule reads its scheme's interest basis; the over-sanction flag (spec 7.3)
                ['Scheme settings and the over-sanction flag', 'eir:load-schemes', []],
                ['Programme portfolios (the Mega Farm schemes kept apart from the MAIIC book)', 'ifrs9:segment-portfolios', []],
                // detection only, from the landed pack's reschedule history and the loan book's rates, before staging:
                // each candidate and each rate move waits for one person's classification and another's approval on
                // EIR Data, Restructures & Resets; nothing is confirmed or approved here (spec v4 3.9, P5; review item R10)
                ['Restructure candidates and rate moves (detection only, nothing is confirmed)', 'eir:restructure-candidates', []],
                ['Stage the book', 'eir:stage', ['from' => $period] + $u],
                ['PD and LGD by segment', 'ifrs9:segment-parameters', ['from' => $period] + $u],
                ['Forward-looking route, projection, ECL and the month record', 'fli:month-end', ['period' => $period, '--all' => true] + $u],
                ['Actual cash from the E-Banker ledger', 'eir:load-ledger-cash', $u],
                // the month's GL_04 fee postings tied to their loans (PENDING for Fee Classification), the precedence, and the fee GL gate against the trial balance (spec 7.8.3 to 7.8.5)
                ['GL fee postings tied to their loans, and the fee GL gate', 'eir:build-gl-fees', $u],
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
                ['Every report and its PDF', 'ifrs9:smoke-reports', array_filter(['period' => \Illuminate\Support\Facades\Schema::hasTable('loan_books') ? \Illuminate\Support\Facades\DB::table('loan_books')->max('reporting_period') : null])],
            ],
            default => null,
        };
    }
}
