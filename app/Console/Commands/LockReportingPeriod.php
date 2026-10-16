<?php

namespace App\Console\Commands;

use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\PeriodUnlockService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lock a reporting period (spec v4 sections 6.6 and 6.11): a locked period is
 * never restated by a build, and it keeps a snapshot of the governance values
 * in force so that a view as at a date inside it reproduces the locked figures.
 *
 *   php artisan eir:lock-period 2025-12 --user=1 --reason="Audited year-end"
 *   php artisan eir:lock-period 2025-12 --user=1 --unlock --reason="Restatement approved by Dr Thom, minute 12"
 *   php artisan eir:lock-period 2025-12 --user=2 --approve-unlock
 *
 * Locking is one person's act. Unlocking is maker-checker (documentation audit
 * of 14 October 2026; PeriodUnlockService): --unlock requests it with the
 * reason, and a different user approves it with --approve-unlock, which removes
 * the lock. The screen (Imports & Feeds, Periods Built) does the same.
 */
class LockReportingPeriod extends Command
{
    protected $signature = 'eir:lock-period {period : YYYY-MM} {--user=} {--reason=} {--unlock : Request the unlock; a different user approves it} {--approve-unlock : Approve the unlock another user requested} {--without-run-record : Lock a month computed before run records existed; the lock then names no run, and the reason must say so}';

    protected $description = 'Lock (or unlock) a reporting period so that no build restates it';

    public function handle(GovernanceService $governance, PeriodUnlockService $unlocks): int
    {
        $period = (string) $this->argument('period');
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('Period must be YYYY-MM.');
            return self::FAILURE;
        }
        $user = $this->option('user') !== null ? (int) $this->option('user') : null;
        if ($this->option('approve-unlock')) {
            if ($user === null) {
                $this->error('--user is required: the approver is recorded, and must not be the person who asked.');
                return self::FAILURE;
            }
            $request = DB::table(PeriodUnlockService::TABLE)->where('reporting_period', $period)->where('status', 'PROPOSED')->orderByDesc('id')->first();
            if (! $request) {
                $this->error("No unlock of {$period} is awaiting approval.");
                return self::FAILURE;
            }
            try {
                $unlocks->approve((int) $request->id, $user);
            } catch (\LogicException $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
            $this->info("Period {$period} unlocked.");
            return self::SUCCESS;
        }
        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('--reason is required; it is written to the audit log.');
            return self::FAILURE;
        }
        if ($this->option('unlock')) {
            if ($user === null) {
                $this->error('--user is required: the person asking is recorded, and a different person approves.');
                return self::FAILURE;
            }
            try {
                $unlocks->request($period, $user, $reason);
            } catch (\LogicException $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
            $this->info("Unlock of period {$period} requested; a different person with EIR governance approves it before the lock is removed.");
            return self::SUCCESS;
        }
        // the lock names the run whose figures the month reports (Staging Module Specification v1, section 8.8, item 3)
        $runs = app(\App\Services\RunRecordService::class);
        try {
            // a database without run records (not yet migrated) locks as it did before them
            $run = \App\Services\RunRecordService::available() ? $runs->runOfRecord($period) : null;
        } catch (\LogicException $e) {
            if (! $this->option('without-run-record')) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
            $run = null;
        }
        $asOf = CarbonImmutable::parse($period . '-01')->endOfMonth();
        $snapshot = [];
        foreach (GovernanceService::keys() as $key) {
            try {
                $snapshot[$key] = $governance->get($key, $asOf);
            } catch (\Throwable) {
                $snapshot[$key] = null;
            }
        }
        DB::table('reporting_period_locks')->updateOrInsert(['reporting_period' => $period], [
            'locked_by' => $user, 'locked_at' => now(), 'reason' => $reason, 'settings_snapshot' => json_encode($snapshot), 'created_at' => now(), 'updated_at' => now(),
        ] + (\Illuminate\Support\Facades\Schema::hasColumn('reporting_period_locks', 'run_record_id') ? ['run_record_id' => $run?->id] : []));
        if ($run !== null) {
            $runs->markOfRecord((int) $run->id, $user);
        }
        AuditLoggerService::log('Reporting Period Locked', 'reporting_period_locks', null, ['new_values' => ['period' => $period, 'reason' => $reason, 'settings' => count($snapshot),
            'run_of_record' => $run?->id, 'fingerprint' => $run?->fingerprint, 'without_run_record' => $run === null], 'meta' => ['user' => $user]]);
        $this->info("Period {$period} locked with " . count($snapshot) . ' governance values snapshotted; '
            . ($run !== null ? "run of record {$run->id} (fingerprint " . substr((string) $run->fingerprint, 0, 12) . ').' : 'no run of record (computed before run records; the reason says so).'));

        return self::SUCCESS;
    }
}
