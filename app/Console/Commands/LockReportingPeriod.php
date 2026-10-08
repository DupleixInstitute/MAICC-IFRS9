<?php

namespace App\Console\Commands;

use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceService;
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
 */
class LockReportingPeriod extends Command
{
    protected $signature = 'eir:lock-period {period : YYYY-MM} {--user=} {--reason=} {--unlock}';

    protected $description = 'Lock (or unlock) a reporting period so that no build restates it';

    public function handle(GovernanceService $governance): int
    {
        $period = (string) $this->argument('period');
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('Period must be YYYY-MM.');
            return self::FAILURE;
        }
        $user = $this->option('user') !== null ? (int) $this->option('user') : null;
        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('--reason is required; it is written to the audit log.');
            return self::FAILURE;
        }
        if ($this->option('unlock')) {
            $n = DB::table('reporting_period_locks')->where('reporting_period', $period)->delete();
            AuditLoggerService::log('Reporting Period Unlocked', 'reporting_period_locks', null, ['new_values' => ['period' => $period, 'reason' => $reason], 'meta' => ['user' => $user]]);
            $this->info($n ? "Period {$period} unlocked." : "Period {$period} was not locked.");
            return self::SUCCESS;
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
        ]);
        AuditLoggerService::log('Reporting Period Locked', 'reporting_period_locks', null, ['new_values' => ['period' => $period, 'reason' => $reason, 'settings' => count($snapshot)], 'meta' => ['user' => $user]]);
        $this->info("Period {$period} locked with " . count($snapshot) . ' governance values snapshotted.');

        return self::SUCCESS;
    }
}
