<?php

namespace App\Services\Eir;

use App\Services\AuditLoggerService;
use App\Services\Ebanker\LoanBookBuildService;
use App\Support\MakerChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * A governed, per-loan stage for one reporting period, whatever the day count gives (Client Book
 * Reconciliation Specification v1, section 6a): for a client who does not use E-Banker's ageing for
 * that month, the stage its own book or an audited figure gives. Proposed and approved by two
 * different people with a supporting document, like the day-count and gross amount overrides, every
 * step audit-logged.
 *
 * StagingService reads only active() (status APPROVED) when it stages the month: the override is the
 * last word on the loan's stage that month, after the day count, the triggers and the cure rule, and
 * the stage the policy itself gave is kept beside it ('policy_stage'). It needs no rebuild of the
 * loan book, only the month staged again (and its PD, LGD and ECL run). Every other loan, and every
 * other month, is unaffected.
 */
class LoanStageOverrideService
{
    public const STATUS_PROPOSED = 'PROPOSED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_WITHDRAWN = 'WITHDRAWN';

    public function propose(string $contractId, string $reportingPeriod, int $stage, string $reason, int $proposedBy, ?int $clientBookId = null): int
    {
        if (! in_array($stage, [1, 2, 3], true)) {
            throw new RuntimeException("A stage override names stage 1, 2 or 3; {$stage} is not one.");
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new RuntimeException('A stage override needs a reason of at least 10 characters.');
        }
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $reportingPeriod) !== 1) {
            throw new RuntimeException("A stage override names its reporting period as YYYY-MM; '{$reportingPeriod}' is not one.");
        }
        \App\Support\ReportingPeriodLock::assertOpen($reportingPeriod, 'a stage override');
        $existing = DB::table('loan_stage_overrides')->where('contract_id', $contractId)->where('reporting_period', $reportingPeriod)
            ->whereIn('status', [self::STATUS_PROPOSED, self::STATUS_APPROVED])->first();
        if ($existing !== null) {
            throw new RuntimeException("Contract {$contractId} already has a {$existing->status} stage override for {$reportingPeriod}; withdraw or reject it first.");
        }
        $id = (int) DB::table('loan_stage_overrides')->insertGetId([
            'contract_id' => $contractId, 'reporting_period' => $reportingPeriod, 'override_stage' => $stage, 'reason' => mb_substr(trim($reason), 0, 2000),
            'proposed_by' => $proposedBy, 'client_book_id' => $clientBookId, 'status' => self::STATUS_PROPOSED, 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLoggerService::log('Stage Override Proposed', 'loan_stage_overrides', $id, [
            'new_values' => ['contract_id' => $contractId, 'reporting_period' => $reportingPeriod, 'stage' => $stage, 'reason' => $reason, 'client_book_id' => $clientBookId],
            'meta' => ['proposed_by' => $proposedBy],
        ]);

        return $id;
    }

    /** A second person approves, on the evidence attached; the bootstrap's automated approval of a committed schedule carries its label. */
    public function approve(int $id, int $approvedBy, ?string $label = null): array
    {
        $row = DB::table('loan_stage_overrides')->where('id', $id)->first();
        if ($row === null || $row->status !== self::STATUS_PROPOSED) {
            throw new RuntimeException("Stage override {$id} is not awaiting approval.");
        }
        $bootstrap = $label === LoanBookBuildService::BOOTSTRAP_LABEL;
        if (! $bootstrap && (int) $row->proposed_by === $approvedBy && ! MakerChecker::selfApprovalAllowed('Stage override', 'loan_stage_overrides', $id, $approvedBy)) {
            throw new RuntimeException('Maker-checker: the approver must be a different person from the proposer.');
        }
        \App\Support\Approvals::assertEvidence('stage-override', $id, $label);
        DB::table('loan_stage_overrides')->where('id', $id)
            ->update(['status' => self::STATUS_APPROVED, 'approved_by' => $approvedBy, 'approver_label' => $bootstrap ? $label : null, 'approved_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('Stage Override Approved', 'loan_stage_overrides', $id, [
            'new_values' => ['contract_id' => $row->contract_id, 'reporting_period' => $row->reporting_period, 'stage' => (int) $row->override_stage],
            'meta' => ['proposed_by' => (int) $row->proposed_by, 'approved_by' => $approvedBy, 'label' => $bootstrap ? $label : null],
        ]);

        return ['status' => self::STATUS_APPROVED];
    }

    public function reject(int $id, int $decidedBy, string $reason): array
    {
        $row = DB::table('loan_stage_overrides')->where('id', $id)->first();
        if ($row === null || $row->status !== self::STATUS_PROPOSED) {
            throw new RuntimeException("Stage override {$id} is not awaiting a decision.");
        }
        if ((int) $row->proposed_by === $decidedBy && ! MakerChecker::selfApprovalAllowed('Stage override (reject)', 'loan_stage_overrides', $id, $decidedBy)) {
            throw new RuntimeException('The person who proposed an override withdraws it; a second person rejects it.');
        }
        DB::table('loan_stage_overrides')->where('id', $id)->update(['status' => self::STATUS_REJECTED, 'approved_by' => $decidedBy, 'approved_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('Stage Override Rejected', 'loan_stage_overrides', $id, ['new_values' => ['contract_id' => $row->contract_id, 'rejection_reason' => $reason], 'meta' => ['decided_by' => $decidedBy]]);

        return ['status' => self::STATUS_REJECTED];
    }

    /** The proposer withdraws a proposal; an approved override is withdrawn with a reason and stops applying at the month's next staging. */
    public function withdraw(int $id, int $withdrawnBy, ?string $reason = null): array
    {
        $row = DB::table('loan_stage_overrides')->where('id', $id)->whereIn('status', [self::STATUS_PROPOSED, self::STATUS_APPROVED])->first();
        if ($row === null) {
            throw new RuntimeException("Stage override {$id} is not active.");
        }
        if ($row->status === self::STATUS_PROPOSED && (int) $row->proposed_by !== $withdrawnBy) {
            throw new RuntimeException('Only the person who proposed an override withdraws it; a second person rejects it instead.');
        }
        if ($row->status === self::STATUS_APPROVED && mb_strlen(trim((string) $reason)) < 10) {
            throw new RuntimeException('Withdrawing an approved override needs the reason (at least 10 characters): the next staging of the month stops applying it.');
        }
        DB::table('loan_stage_overrides')->where('id', $id)->update(['status' => self::STATUS_WITHDRAWN, 'updated_at' => now()]);
        AuditLoggerService::log('Stage Override Withdrawn', 'loan_stage_overrides', $id, ['new_values' => ['contract_id' => $row->contract_id, 'was' => $row->status, 'reason' => $reason], 'meta' => ['withdrawn_by' => $withdrawnBy]]);

        return ['status' => self::STATUS_WITHDRAWN];
    }

    /** @return array<string, array{id:int, stage:int}> the approved stage overrides of a month, by contract */
    public function active(string $reportingPeriod): array
    {
        if (! Schema::hasTable('loan_stage_overrides')) {
            return [];
        }

        return DB::table('loan_stage_overrides')->where('reporting_period', $reportingPeriod)->where('status', self::STATUS_APPROVED)->get(['id', 'contract_id', 'override_stage'])
            ->mapWithKeys(fn ($r) => [(string) $r->contract_id => ['id' => (int) $r->id, 'stage' => (int) $r->override_stage]])->all();
    }
}
