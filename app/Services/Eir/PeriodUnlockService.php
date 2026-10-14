<?php

namespace App\Services\Eir;

use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Unlocking a reporting period under maker-checker (documentation audit of
 * 14 October 2026). A locked period is never restated (spec v4 6.6, D21);
 * unlocking it reopens every engine to restatement, so it is not one person's
 * act: one person with eir.govern requests it with a reason, a different person
 * approves it, and only then is the lock removed. Locking stays one person's
 * act. A request is rejected by another person with a reason, or withdrawn by
 * the person who made it. One open request per period.
 */
class PeriodUnlockService
{
    public const TABLE = 'period_unlock_requests';

    public function request(string $period, int $userId, string $reason): int
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new LogicException('A specific reason of at least 10 characters is required to unlock a period.');
        }

        return DB::transaction(function () use ($period, $userId, $reason) {
            if (! DB::table('reporting_period_locks')->where('reporting_period', $period)->exists()) {
                throw new LogicException("Period {$period} is not locked.");
            }
            if (DB::table(self::TABLE)->where('reporting_period', $period)->where('status', 'PROPOSED')->exists()) {
                throw new LogicException("An unlock of {$period} is already awaiting approval.");
            }
            $id = (int) DB::table(self::TABLE)->insertGetId([
                'reporting_period' => $period, 'status' => 'PROPOSED', 'reason' => mb_substr($reason, 0, 500),
                'requested_by' => $userId, 'requested_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            AuditLoggerService::log('Reporting Period Unlock Requested', 'reporting_period_locks', null, [
                'reporting_period' => $period, 'new_values' => ['period' => $period, 'request_id' => $id, 'reason' => $reason], 'meta' => ['user' => $userId],
            ]);

            return $id;
        });
    }

    /** A different person approves, and the lock is removed. */
    public function approve(int $requestId, int $approverId): string
    {
        return DB::transaction(function () use ($requestId, $approverId) {
            $r = DB::table(self::TABLE)->where('id', $requestId)->lockForUpdate()->first();
            if (! $r || $r->status !== 'PROPOSED') {
                throw new LogicException('Only an unlock awaiting approval can be approved.');
            }
            if ((int) $r->requested_by === $approverId) {
                throw new LogicException('The person who requested the unlock cannot approve it; a second person with EIR governance approves it.');
            }
            DB::table('reporting_period_locks')->where('reporting_period', $r->reporting_period)->delete();
            DB::table(self::TABLE)->where('id', $requestId)->update(['status' => 'APPROVED', 'decided_by' => $approverId, 'decided_at' => now(), 'updated_at' => now()]);
            AuditLoggerService::log('Reporting Period Unlocked', 'reporting_period_locks', null, [
                'reporting_period' => $r->reporting_period,
                'new_values' => ['period' => $r->reporting_period, 'reason' => $r->reason],
                'meta' => ['request_id' => $requestId, 'requested_by' => (int) $r->requested_by, 'approved_by' => $approverId],
            ]);

            return (string) $r->reporting_period;
        });
    }

    /** Reject (another person, with a reason) or withdraw (the person who asked). */
    public function close(int $requestId, int $userId, string $action, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new LogicException('Give the reason, at least 10 characters.');
        }
        DB::transaction(function () use ($requestId, $userId, $action, $reason) {
            $r = DB::table(self::TABLE)->where('id', $requestId)->lockForUpdate()->first();
            if (! $r || $r->status !== 'PROPOSED') {
                throw new LogicException('Only an unlock awaiting approval can be rejected or withdrawn.');
            }
            $mine = (int) $r->requested_by === $userId;
            $status = match (true) {
                $action === 'withdraw' && $mine => 'WITHDRAWN',
                $action === 'reject' && ! $mine => 'REJECTED',
                default => null,
            };
            if ($status === null) {
                throw new LogicException($action === 'reject'
                    ? 'The person who requested the unlock cannot reject it; withdraw it instead.'
                    : 'Only the person who requested the unlock can withdraw it.');
            }
            DB::table(self::TABLE)->where('id', $requestId)->update(['status' => $status, 'decided_by' => $userId, 'decided_at' => now(),
                'decision_reason' => mb_substr($reason, 0, 500), 'updated_at' => now()]);
            AuditLoggerService::log('Reporting Period Unlock ' . ucfirst(strtolower($status)), 'period_unlock_requests', $requestId, [
                'reporting_period' => $r->reporting_period, 'new_values' => ['status' => $status], 'meta' => ['reason' => $reason],
            ]);
        });
    }

    /** The requests awaiting approval, by period, with the names. */
    public function pending(): array
    {
        $users = DB::table('users')->pluck('name', 'id');

        return DB::table(self::TABLE)->where('status', 'PROPOSED')->orderBy('reporting_period')->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'period' => $r->reporting_period, 'reason' => $r->reason, 'requested_by' => (int) $r->requested_by,
                'requested_by_name' => $users[$r->requested_by] ?? null, 'requested_at' => (string) $r->requested_at])
            ->all();
    }
}
