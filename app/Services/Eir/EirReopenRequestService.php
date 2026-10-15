<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Reopening a locked original EIR under maker-checker (documentation audit of
 * 14 October 2026). Until then the controller refused everyone unless the
 * administrator override was in force, so under the default nobody could
 * reopen a locked EIR, and the message said "only an administrator".
 *
 * One person with eir.govern requests the reopening with a reason; a different
 * person with eir.govern approves it, and only then does
 * EirCalculationService::reopen run, which archives the EIR and its
 * roll-forward and refuses while a month the EIR reached is locked. A request
 * is rejected by another person with a reason, or withdrawn by the person who
 * made it. One open request per contract.
 */
class EirReopenRequestService
{
    public const TABLE = 'eir_reopen_requests';

    public function __construct(private EirCalculationService $calculations) {}

    public function request(string $contractId, int $userId, string $reason): int
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new LogicException('A specific reopening reason of at least 10 characters is required.');
        }

        return DB::transaction(function () use ($contractId, $userId, $reason) {
            $contract = ContractEir::where('contract_id', $contractId)->lockForUpdate()->first();
            if (! $contract || $contract->locked_at === null || $contract->calculation_status !== 'LOCKED') {
                throw new LogicException('Only a locked original EIR can be reopened.');
            }
            if (DB::table(self::TABLE)->where('contract_id', $contractId)->where('status', 'PROPOSED')->exists()) {
                throw new LogicException("A reopening of {$contractId} is already awaiting approval.");
            }
            $id = (int) DB::table(self::TABLE)->insertGetId([
                'contract_id' => $contractId, 'status' => 'PROPOSED', 'reason' => mb_substr($reason, 0, 500),
                'requested_by' => $userId, 'requested_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            AuditLoggerService::log('Original EIR Reopening Requested', ContractEir::class, $contract->id, [
                'new_values' => ['contract_id' => $contractId, 'request_id' => $id], 'meta' => ['reason' => $reason, 'requested_by' => $userId],
            ]);

            return $id;
        });
    }

    /** A different person approves; the reopening then runs, or the request stays open with the reason it could not. */
    public function approve(int $requestId, int $approverId): ContractEir
    {
        return DB::transaction(function () use ($requestId, $approverId) {
            $r = DB::table(self::TABLE)->where('id', $requestId)->lockForUpdate()->first();
            if (! $r || $r->status !== 'PROPOSED') {
                throw new LogicException('Only a reopening awaiting approval can be approved.');
            }
            if ((int) $r->requested_by === $approverId) {
                throw new LogicException('The person who requested a reopening cannot approve it; a second person with EIR governance approves it.');
            }
            $before = ContractEir::where('contract_id', $r->contract_id)->first();
            $reopened = $this->calculations->reopen($r->contract_id, $approverId, $r->reason);
            DB::table(self::TABLE)->where('id', $requestId)->update(['status' => 'APPROVED', 'decided_by' => $approverId, 'decided_at' => now(), 'updated_at' => now()]);
            // the loan's open review flags close: the EIR is solved again with its fees (EirLockReviewService)
            app(EirLockReviewService::class)->closeOnReopen($r->contract_id, $approverId);
            AuditLoggerService::log('Locked Original EIR Reopened', ContractEir::class, $reopened->id, [
                'old_values' => ['contract_id' => $r->contract_id, 'eir_effective_annual' => $before?->eir_effective_annual, 'locked_at' => $before?->locked_at, 'locked_by' => $before?->locked_by],
                'new_values' => ['calculation_status' => $reopened->calculation_status, 'locked_at' => null],
                'meta' => ['reason' => $r->reason, 'request_id' => $requestId, 'requested_by' => (int) $r->requested_by, 'approved_by' => $approverId, 'downstream_results_invalidated' => true],
            ]);

            return $reopened;
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
                throw new LogicException('Only a reopening awaiting approval can be rejected or withdrawn.');
            }
            $mine = (int) $r->requested_by === $userId;
            $status = match (true) {
                $action === 'withdraw' && $mine => 'WITHDRAWN',
                $action === 'reject' && ! $mine => 'REJECTED',
                default => null,
            };
            if ($status === null) {
                throw new LogicException($action === 'reject'
                    ? 'The person who requested a reopening cannot reject it; withdraw it instead.'
                    : 'Only the person who requested a reopening can withdraw it.');
            }
            DB::table(self::TABLE)->where('id', $requestId)->update(['status' => $status, 'decided_by' => $userId, 'decided_at' => now(),
                'decision_reason' => mb_substr($reason, 0, 500), 'updated_at' => now()]);
            AuditLoggerService::log('Original EIR Reopening ' . ucfirst(strtolower($status)), 'eir_reopen_requests', $requestId, [
                'new_values' => ['contract_id' => $r->contract_id, 'status' => $status], 'meta' => ['reason' => $reason],
            ]);
        });
    }

    /** The requests awaiting approval, newest first, with the names. */
    public function pending(): array
    {
        $users = DB::table('users')->pluck('name', 'id');

        return DB::table(self::TABLE)->where('status', 'PROPOSED')->orderByDesc('id')->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'contract_id' => $r->contract_id, 'reason' => $r->reason, 'requested_by' => (int) $r->requested_by,
                'requested_by_name' => $users[$r->requested_by] ?? null, 'requested_at' => (string) $r->requested_at])
            ->all();
    }
}
