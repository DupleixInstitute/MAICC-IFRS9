<?php

namespace App\Services\Eir;

use App\Services\AuditLoggerService;
use App\Support\MakerChecker;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A governed, per-loan exception from the build's own rebuilt carrying (gross) amount to an
 * explicit audited figure, reconciling an already-closed, already-audited period to the gross
 * loan amount the external audit approved (MAIIC Finance Overlay Importers Specification v1,
 * section 4.3, and the staging basis override of the same date — LoanStagingBasisOverrideService
 * — built to the identical pattern). The reason names the audit's own approval; nothing here
 * invents a number that was not actually reported.
 *
 * `LoanBookBuildService` reads only `active()` (status APPROVED) when building a period; every
 * other loan is unaffected, whatever is proposed or rejected here. The loan's own system figure
 * is never lost: it is kept and named in `build_flag` beside the override.
 */
class LoanGrossAmountOverrideService
{
    public const STATUS_PROPOSED = 'PROPOSED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_WITHDRAWN = 'WITHDRAWN';

    /** An override reconciles one reporting period (YYYY-MM); only that month's build applies it. */
    public function propose(string $contractId, string $reportingPeriod, string $reason, int $proposedBy, float $overrideAmount): int
    {
        if (trim($reason) === '') {
            throw new RuntimeException('A gross amount override needs a reason.');
        }
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $reportingPeriod) !== 1) {
            throw new RuntimeException("A gross amount override names its reporting period as YYYY-MM; '{$reportingPeriod}' is not one.");
        }
        $existing = DB::table('loan_gross_amount_overrides')->where('contract_id', $contractId)->where('reporting_period', $reportingPeriod)
            ->whereIn('status', [self::STATUS_PROPOSED, self::STATUS_APPROVED])->first();
        if ($existing !== null) {
            throw new RuntimeException("Contract {$contractId} already has a {$existing->status} gross amount override for {$reportingPeriod}; withdraw or reject it first.");
        }
        $id = (int) DB::table('loan_gross_amount_overrides')->insertGetId([
            'contract_id' => $contractId, 'reporting_period' => $reportingPeriod, 'reason' => $reason, 'proposed_by' => $proposedBy,
            'override_amount' => $overrideAmount,
            'status' => self::STATUS_PROPOSED, 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLoggerService::log('Gross Amount Override Proposed', 'loan_gross_amount_overrides', $id, [
            'new_values' => ['contract_id' => $contractId, 'reporting_period' => $reportingPeriod, 'reason' => $reason, 'override_amount' => $overrideAmount],
            'meta' => ['proposed_by' => $proposedBy],
        ]);

        return $id;
    }

    /** @return array{status:string} */
    public function approve(int $id, int $approvedBy): array
    {
        $row = DB::table('loan_gross_amount_overrides')->where('id', $id)->first();
        if ($row === null || $row->status !== self::STATUS_PROPOSED) {
            throw new RuntimeException("Gross amount override {$id} is not awaiting approval.");
        }
        if ((int) $row->proposed_by === $approvedBy && ! MakerChecker::selfApprovalAllowed('Gross amount override', 'loan_gross_amount_overrides', $id, $approvedBy)) {
            throw new RuntimeException('Maker-checker: the approver must be a different person from the proposer.');
        }
        DB::table('loan_gross_amount_overrides')->where('id', $id)
            ->update(['status' => self::STATUS_APPROVED, 'approved_by' => $approvedBy, 'approved_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('Gross Amount Override Approved', 'loan_gross_amount_overrides', $id, [
            'new_values' => ['contract_id' => $row->contract_id, 'reason' => $row->reason],
            'meta' => ['proposed_by' => (int) $row->proposed_by, 'approved_by' => $approvedBy],
        ]);

        return ['status' => self::STATUS_APPROVED];
    }

    /** @return array{status:string} */
    public function reject(int $id, int $decidedBy, string $reason): array
    {
        $row = DB::table('loan_gross_amount_overrides')->where('id', $id)->first();
        if ($row === null || $row->status !== self::STATUS_PROPOSED) {
            throw new RuntimeException("Gross amount override {$id} is not awaiting a decision.");
        }
        DB::table('loan_gross_amount_overrides')->where('id', $id)
            ->update(['status' => self::STATUS_REJECTED, 'approved_by' => $decidedBy, 'approved_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('Gross Amount Override Rejected', 'loan_gross_amount_overrides', $id, [
            'new_values' => ['contract_id' => $row->contract_id, 'rejection_reason' => $reason],
            'meta' => ['proposed_by' => (int) $row->proposed_by, 'decided_by' => $decidedBy],
        ]);

        return ['status' => self::STATUS_REJECTED];
    }

    public function withdraw(int $id, int $withdrawnBy): array
    {
        $row = DB::table('loan_gross_amount_overrides')->where('id', $id)->whereIn('status', [self::STATUS_PROPOSED, self::STATUS_APPROVED])->first();
        if ($row === null) {
            throw new RuntimeException("Gross amount override {$id} is not active.");
        }
        DB::table('loan_gross_amount_overrides')->where('id', $id)->update(['status' => self::STATUS_WITHDRAWN, 'updated_at' => now()]);
        AuditLoggerService::log('Gross Amount Override Withdrawn', 'loan_gross_amount_overrides', $id, [
            'new_values' => ['contract_id' => $row->contract_id, 'was' => $row->status],
            'meta' => ['withdrawn_by' => $withdrawnBy],
        ]);

        return ['status' => self::STATUS_WITHDRAWN];
    }

    /**
     * The approved overrides for one reporting period, keyed by contract id, for that month's
     * build to apply. Every loan not in this list keeps the gross amount the rebuild computed.
     *
     * @return array<string, array{id:int,reason:string,approved_by:?int,approved_at:?string,override_amount:float}>
     */
    public function active(string $reportingPeriod): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('loan_gross_amount_overrides')) {
            return [];
        }
        $out = [];
        foreach (DB::table('loan_gross_amount_overrides')->where('status', self::STATUS_APPROVED)->where('reporting_period', $reportingPeriod)->get() as $r) {
            $out[$r->contract_id] = [
                'id' => (int) $r->id, 'reason' => $r->reason, 'approved_by' => $r->approved_by, 'approved_at' => $r->approved_at,
                'override_amount' => (float) $r->override_amount,
            ];
        }

        return $out;
    }
}
