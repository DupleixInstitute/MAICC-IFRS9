<?php

namespace App\Services\Eir;

use App\Services\AuditLoggerService;
use App\Support\MakerChecker;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A governed, per-loan exception from the staging policy's own bucket-ageing basis to the
 * schedule-aware count the build already computes for the RBM directive's `overdue_days`
 * (December 2025 Loan Book Investigation Findings v1, section 10). Proposed and approved by two
 * different people, like every other maker-checker workflow in this system; every step is
 * audit-logged. The override never carries a typed-in day count — it only ever says "for this
 * loan, use the count this row already carries elsewhere", so there is nothing here for a person
 * to get wrong beyond the decision itself.
 *
 * `LoanBookBuildService` reads only `active()` (status APPROVED) when building a period; every
 * other loan is unaffected, whatever is proposed or rejected here.
 */
class LoanStagingBasisOverrideService
{
    public const STATUS_PROPOSED = 'PROPOSED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_WITHDRAWN = 'WITHDRAWN';

    public function propose(string $contractId, string $reason, int $proposedBy): int
    {
        if (trim($reason) === '') {
            throw new RuntimeException('A staging basis override needs a reason.');
        }
        $existing = DB::table('loan_staging_basis_overrides')->where('contract_id', $contractId)
            ->whereIn('status', [self::STATUS_PROPOSED, self::STATUS_APPROVED])->first();
        if ($existing !== null) {
            throw new RuntimeException("Contract {$contractId} already has a {$existing->status} override; withdraw or reject it first.");
        }
        $id = (int) DB::table('loan_staging_basis_overrides')->insertGetId([
            'contract_id' => $contractId, 'reason' => $reason, 'proposed_by' => $proposedBy,
            'status' => self::STATUS_PROPOSED, 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLoggerService::log('Staging Basis Override Proposed', 'loan_staging_basis_overrides', $id, [
            'new_values' => ['contract_id' => $contractId, 'reason' => $reason],
            'meta' => ['proposed_by' => $proposedBy],
        ]);

        return $id;
    }

    /** @return array{status:string} */
    public function approve(int $id, int $approvedBy): array
    {
        $row = DB::table('loan_staging_basis_overrides')->where('id', $id)->first();
        if ($row === null || $row->status !== self::STATUS_PROPOSED) {
            throw new RuntimeException("Override {$id} is not awaiting approval.");
        }
        if ((int) $row->proposed_by === $approvedBy && ! MakerChecker::selfApprovalAllowed('Staging basis override', 'loan_staging_basis_overrides', $id, $approvedBy)) {
            throw new RuntimeException('Maker-checker: the approver must be a different person from the proposer.');
        }
        DB::table('loan_staging_basis_overrides')->where('id', $id)
            ->update(['status' => self::STATUS_APPROVED, 'approved_by' => $approvedBy, 'approved_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('Staging Basis Override Approved', 'loan_staging_basis_overrides', $id, [
            'new_values' => ['contract_id' => $row->contract_id, 'reason' => $row->reason],
            'meta' => ['proposed_by' => (int) $row->proposed_by, 'approved_by' => $approvedBy],
        ]);

        return ['status' => self::STATUS_APPROVED];
    }

    /** @return array{status:string} */
    public function reject(int $id, int $decidedBy, string $reason): array
    {
        $row = DB::table('loan_staging_basis_overrides')->where('id', $id)->first();
        if ($row === null || $row->status !== self::STATUS_PROPOSED) {
            throw new RuntimeException("Override {$id} is not awaiting a decision.");
        }
        DB::table('loan_staging_basis_overrides')->where('id', $id)
            ->update(['status' => self::STATUS_REJECTED, 'approved_by' => $decidedBy, 'approved_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('Staging Basis Override Rejected', 'loan_staging_basis_overrides', $id, [
            'new_values' => ['contract_id' => $row->contract_id, 'rejection_reason' => $reason],
            'meta' => ['proposed_by' => (int) $row->proposed_by, 'decided_by' => $decidedBy],
        ]);

        return ['status' => self::STATUS_REJECTED];
    }

    public function withdraw(int $id, int $withdrawnBy): array
    {
        $row = DB::table('loan_staging_basis_overrides')->where('id', $id)->whereIn('status', [self::STATUS_PROPOSED, self::STATUS_APPROVED])->first();
        if ($row === null) {
            throw new RuntimeException("Override {$id} is not active.");
        }
        DB::table('loan_staging_basis_overrides')->where('id', $id)->update(['status' => self::STATUS_WITHDRAWN, 'updated_at' => now()]);
        AuditLoggerService::log('Staging Basis Override Withdrawn', 'loan_staging_basis_overrides', $id, [
            'new_values' => ['contract_id' => $row->contract_id, 'was' => $row->status],
            'meta' => ['withdrawn_by' => $withdrawnBy],
        ]);

        return ['status' => self::STATUS_WITHDRAWN];
    }

    /**
     * The approved overrides only, keyed by contract id, for the build to apply. Every loan not
     * in this list is staged exactly as the governed policy already says.
     *
     * @return array<string, array{id:int,reason:string,approved_by:?int,approved_at:?string}>
     */
    public function active(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('loan_staging_basis_overrides')) {
            return [];
        }
        $out = [];
        foreach (DB::table('loan_staging_basis_overrides')->where('status', self::STATUS_APPROVED)->get() as $r) {
            $out[$r->contract_id] = ['id' => (int) $r->id, 'reason' => $r->reason, 'approved_by' => $r->approved_by, 'approved_at' => $r->approved_at];
        }

        return $out;
    }
}
