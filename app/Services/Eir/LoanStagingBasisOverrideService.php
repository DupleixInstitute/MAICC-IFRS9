<?php

namespace App\Services\Eir;

use App\Services\AuditLoggerService;
use App\Support\MakerChecker;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A governed, per-loan exception from the staging policy's own bucket-ageing basis. Two sources,
 * both proposed and approved by two different people like every other maker-checker workflow in
 * this system, every step audit-logged:
 *
 *  - `directive_count` (no `$overrideDays` given): use the schedule-aware count the build
 *    already computes for the RBM directive's `overdue_days`, evidenced loan by loan (December
 *    2025 Loan Book Investigation Findings v1, section 10 — JAT and BenCal).
 *  - `audited_workbook` (`$overrideDays` given): an explicit day count reconciling an
 *    already-closed, already-audited period to the figures the external audit approved (MAIIC
 *    Finance Overlay Importers Specification v1, section 4.3), pending MAIIC's facility-level
 *    reasons. The reason still names the audit's own approval; nothing here invents a number
 *    that was not actually reported.
 *
 * `LoanBookBuildService` reads only `active()` (status APPROVED) when building a period; every
 * other loan is unaffected, whatever is proposed or rejected here. The loan's own system figure
 * is never lost: it is kept on the row and named in `build_flag` beside whichever source applied.
 */
class LoanStagingBasisOverrideService
{
    public const STATUS_PROPOSED = 'PROPOSED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_WITHDRAWN = 'WITHDRAWN';
    public const SOURCE_DIRECTIVE_COUNT = 'directive_count';
    public const SOURCE_AUDITED_WORKBOOK = 'audited_workbook';

    /** An override reconciles one reporting period (YYYY-MM); only that month's build applies it. */
    public function propose(string $contractId, string $reportingPeriod, string $reason, int $proposedBy, ?int $overrideDays = null): int
    {
        if (trim($reason) === '') {
            throw new RuntimeException('A staging basis override needs a reason.');
        }
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $reportingPeriod) !== 1) {
            throw new RuntimeException("A staging basis override names its reporting period as YYYY-MM; '{$reportingPeriod}' is not one.");
        }
        $existing = DB::table('loan_staging_basis_overrides')->where('contract_id', $contractId)->where('reporting_period', $reportingPeriod)
            ->whereIn('status', [self::STATUS_PROPOSED, self::STATUS_APPROVED])->first();
        if ($existing !== null) {
            throw new RuntimeException("Contract {$contractId} already has a {$existing->status} override for {$reportingPeriod}; withdraw or reject it first.");
        }
        $id = (int) DB::table('loan_staging_basis_overrides')->insertGetId([
            'contract_id' => $contractId, 'reporting_period' => $reportingPeriod, 'reason' => $reason, 'proposed_by' => $proposedBy,
            'override_days' => $overrideDays, 'source' => $overrideDays !== null ? self::SOURCE_AUDITED_WORKBOOK : self::SOURCE_DIRECTIVE_COUNT,
            'status' => self::STATUS_PROPOSED, 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLoggerService::log('Staging Basis Override Proposed', 'loan_staging_basis_overrides', $id, [
            'new_values' => ['contract_id' => $contractId, 'reporting_period' => $reportingPeriod, 'reason' => $reason, 'override_days' => $overrideDays],
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
     * The approved overrides for one reporting period, keyed by contract id, for that month's
     * build to apply. Every loan not in this list is staged exactly as the governed policy says.
     *
     * @return array<string, array{id:int,reason:string,approved_by:?int,approved_at:?string,override_days:?int,source:string}>
     */
    public function active(string $reportingPeriod): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('loan_staging_basis_overrides')) {
            return [];
        }
        $out = [];
        foreach (DB::table('loan_staging_basis_overrides')->where('status', self::STATUS_APPROVED)->where('reporting_period', $reportingPeriod)->get() as $r) {
            $out[$r->contract_id] = [
                'id' => (int) $r->id, 'reason' => $r->reason, 'approved_by' => $r->approved_by, 'approved_at' => $r->approved_at,
                'override_days' => isset($r->override_days) ? (int) $r->override_days : null,
                'source' => $r->source ?? self::SOURCE_DIRECTIVE_COUNT,
            ];
        }

        return $out;
    }
}
