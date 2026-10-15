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

    /**
     * The locked months whose figures a change effective from a date would alter:
     * every locked month whose month-end is on or after the date (a setting, rule
     * or trigger effective from a date governs every month-end from it), earliest
     * first. A date may be given as YYYY-MM-DD or as a month, YYYY-MM.
     *
     * @return list<string>
     */
    public static function lockedFrom(string $date): array
    {
        if (! Schema::hasTable('reporting_period_locks')) {
            return [];
        }

        return DB::table('reporting_period_locks')->where('reporting_period', '>=', substr(self::normalise($date), 0, 7))
            ->orderBy('reporting_period')->pluck('reporting_period')->map(fn ($p) => (string) $p)->all();
    }

    /**
     * No back-dating into a locked month (Staging Module Specification v1,
     * section 8.8, item 4; decision S10): a change to a governed setting or
     * rule cannot take effect inside a locked month, or the values looked up
     * for that month would no longer be the ones it ran on. A deliberate
     * restatement goes through the reopen-and-restate approval: once a second
     * person approves the unlock the month is no longer locked, and the change
     * can be made.
     *
     * A change with an end date ($until) governs only the month-ends from its
     * effective date to its end, so only those are protected.
     *
     * @throws BackDatedChangeException
     */
    public static function assertNotBackDated(string $effectiveFrom, string $what, ?string $until = null): void
    {
        // a change that also ends (a SICR trigger with an end date) affects only the month-ends up to its end
        $locked = array_values(array_filter(self::lockedFrom($effectiveFrom),
            fn ($p) => $until === null || \Carbon\CarbonImmutable::parse($p . '-01')->endOfMonth()->toDateString() <= substr($until, 0, 10)));
        if ($locked !== []) {
            throw new BackDatedChangeException($what, $effectiveFrom, $locked);
        }
    }

    private static function normalise(string $period): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $period)) {
            return substr($period, 0, 7);
        }
        $digits = preg_replace('/\D/', '', $period);

        return strlen($digits) >= 6 ? substr($digits, 0, 4) . '-' . substr($digits, 4, 2) : $period;
    }
}

final class LockedPeriodException extends RuntimeException
{
    public function __construct(public readonly string $period, string $what)
    {
        parent::__construct("Reporting period {$period} is locked: {$what} cannot be written. A locked period is never restated; if the restatement is deliberate, request the unlock with a reason on Imports & Feeds, Periods Built (or eir:lock-period --unlock), and a second person with EIR governance approves it.");
    }
}
