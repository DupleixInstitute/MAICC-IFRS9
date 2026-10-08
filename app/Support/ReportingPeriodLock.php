<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * The one lock check every engine calls before it writes a period.
 *
 * A locked reporting period is never restated (spec 6.6, D21). Until the
 * system audit of 9 October 2026 (finding H4) only the loan-book build read
 * reporting_period_locks; the revenue roll-forward, the ECL, staging, the PD,
 * the LGD and the forward-looking route wrote freely into a locked month.
 */
final class ReportingPeriodLock
{
    public static function isLocked(string $period): bool
    {
        $period = self::normalise($period);
        if (! Schema::hasTable('reporting_period_locks')) {
            return false;
        }

        return DB::table('reporting_period_locks')->where('reporting_period', $period)->exists();
    }

    /** @throws LockedPeriodException */
    public static function assertOpen(string $period, string $what): void
    {
        if (self::isLocked($period)) {
            throw new LockedPeriodException(self::normalise($period), $what);
        }
    }

    private static function normalise(string $period): string
    {
        $digits = preg_replace('/\D/', '', $period);

        return strlen($digits) >= 6 ? substr($digits, 0, 4) . '-' . substr($digits, 4, 2) : $period;
    }
}

final class LockedPeriodException extends RuntimeException
{
    public function __construct(public readonly string $period, string $what)
    {
        parent::__construct("Reporting period {$period} is locked: {$what} cannot be written. A locked period is never restated; unlock it with a reason (eir:lock-period --unlock) if the restatement is deliberate.");
    }
}
