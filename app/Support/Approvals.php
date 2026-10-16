<?php

namespace App\Support;

use App\Services\AuditLoggerService;
use App\Services\Ebanker\LoanBookBuildService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;

/**
 * The approval workflows outside the Governance Centre (Staging Module
 * Specification v1, section 8.6), on one record: each decides something that
 * changes a figure, and each now has the same events (proposed, documents,
 * approved, rejected, withdrawn) in the one audit log, supporting documents
 * (ApprovalDocuments), a history per item, and the evidence its row of the
 * specification's table requires before it is approved.
 *
 * Each workflow names its table, the column and values that mean "awaiting a
 * decision", the column holding the person who proposed it (the maker, who
 * withdraws; a reviewer rejects), the audit entity types its entries are
 * written under, and the evidence required (null: none). A workflow whose
 * service already rejects or withdraws keeps that logic (decide() calls it);
 * the others are decided here: the status moves to REJECTED or WITHDRAWN (or
 * back to the preparer, where the workflow returns work rather than ending it)
 * and the audit entry records who, when and why.
 */
final class Approvals
{
    /**
     * @var array<string,array{label:string,table:string,status:string,pending:list<string>,maker:?string,entities:list<string>,evidence:?string,reject_to?:string|null}>
     */
    public const WORKFLOWS = [
        'staging-threshold' => ['label' => 'Staging threshold', 'table' => 'staging_thresholds', 'status' => 'status', 'pending' => ['PROPOSED'], 'maker' => 'proposed_by', 'entities' => ['staging_thresholds'],
            'evidence' => 'the rebuttal basis or analysis behind the threshold'],
        'sicr-trigger' => ['label' => 'Qualitative SICR trigger', 'table' => 'finance_sicr_triggers', 'status' => 'status', 'pending' => ['PROPOSED'], 'maker' => 'triggered_by', 'entities' => ['finance_sicr_triggers'],
            'evidence' => 'the credit report or the event behind the trigger'],
        'fee-rule' => ['label' => 'Fee rulebook rule', 'table' => 'eir_accounting_rules', 'status' => 'status', 'pending' => ['PROPOSED'], 'maker' => 'created_by', 'entities' => ['eir_accounting_rules', 'App\Models\EirAccountingRule'],
            'evidence' => 'the policy reference or the offer-letter evidence for the fee type'],
        'scenario-set' => ['label' => 'Scenario set', 'table' => 'governed_scenario_sets', 'status' => 'status', 'pending' => ['PROPOSED'], 'maker' => 'proposed_by', 'entities' => ['governed_scenario_sets'],
            'evidence' => 'the calibration note'],
        'fli-overlay' => ['label' => 'Forward-looking overlay', 'table' => 'fli_overlays', 'status' => 'status', 'pending' => ['PROPOSED'], 'maker' => 'proposed_by', 'entities' => ['fli_overlays'],
            'evidence' => 'the reason and evidence for the adjustment'],
        'fli-fit' => ['label' => 'Forward-looking fit', 'table' => 'fli_fits', 'status' => 'approval_status', 'pending' => ['PROPOSED'], 'maker' => 'proposed_by', 'entities' => ['fli_fits'],
            'evidence' => 'the diagnostics export'],
        'fli-model' => ['label' => 'External forward-looking model', 'table' => 'fli_model_versions', 'status' => 'status', 'pending' => ['PROPOSED'], 'maker' => 'proposed_by', 'entities' => ['fli_model_versions'],
            'evidence' => 'the model documentation'],
        'schedule' => ['label' => 'Contractual schedule', 'table' => 'contract_eir', 'status' => 'schedule_approval_status', 'pending' => ['PENDING_REVIEW'], 'maker' => 'schedule_prepared_by', 'entities' => ['contract_eir', 'contract_eirs', 'App\Models\ContractEir'],
            'evidence' => null, 'reject_to' => 'DRAFT'],
        'eir-reopen' => ['label' => 'EIR reopening', 'table' => 'eir_reopen_requests', 'status' => 'status', 'pending' => ['PROPOSED'], 'maker' => 'requested_by', 'entities' => ['eir_reopen_requests'],
            'evidence' => 'why the locked EIR was wrong'],
        'restructure' => ['label' => 'Restructure', 'table' => 'restructure_events', 'status' => 'status', 'pending' => ['PROPOSED'], 'maker' => 'proposed_by', 'entities' => ['restructure_events'],
            'evidence' => 'the Board approval of the restructure (the directive, section 16(1))'],
        'rate-reset' => ['label' => 'Rate reset', 'table' => 'rate_reset_events', 'status' => 'classification_status', 'pending' => ['PROPOSED'], 'maker' => 'proposed_by', 'entities' => ['rate_reset_events'],
            'evidence' => 'the prime-rate notice or the agreement'],
        'write-off' => ['label' => 'Write-off', 'table' => 'loan_write_offs', 'status' => 'status', 'pending' => ['PROPOSED'], 'maker' => 'proposed_by', 'entities' => ['loan_write_offs'],
            'evidence' => 'the write-off approval under MAIIC\'s credit policy'],
        'takeon-build' => ['label' => 'Take-on build', 'table' => 'takeon_build_requests', 'status' => 'status', 'pending' => ['PROPOSED'], 'maker' => 'proposed_by', 'entities' => ['takeon_build_requests'],
            'evidence' => 'the version of Finance\'s take-on workbook used'],
        'loan-book-build' => ['label' => 'Loan book build', 'table' => 'loan_book_builds', 'status' => 'status', 'pending' => ['PROPOSED'], 'maker' => 'requested_by', 'entities' => ['loan_book_builds'],
            'evidence' => null],
        'period-unlock' => ['label' => 'Period unlock', 'table' => 'period_unlock_requests', 'status' => 'status', 'pending' => ['PROPOSED'], 'maker' => 'requested_by', 'entities' => ['period_unlock_requests'],
            'evidence' => 'the reason and the approval to restate the month'],
        'compliance-signoff' => ['label' => 'Compliance audit sign-off', 'table' => 'compliance_audit_rows', 'status' => 'proposed_status', 'pending' => [], 'maker' => 'signed_by', 'entities' => ['compliance_audit_rows'],
            'evidence' => null, 'reject_to' => null],
        'fli-month' => ['label' => 'Forward-looking month-end sign-off', 'table' => 'fli_run_snapshots', 'status' => 'status', 'pending' => ['RECORDED'], 'maker' => 'created_by', 'entities' => ['fli_run_snapshots'],
            'evidence' => null],
        'macro-override' => ['label' => 'Macro data override', 'table' => 'macro_override_requests', 'status' => 'status', 'pending' => ['PROPOSED'], 'maker' => 'proposed_by', 'entities' => ['macro_override_requests'],
            'evidence' => 'the source of the value'],
    ];

    /** @return array{label:string,table:string,status:string,pending:list<string>,maker:?string,entities:list<string>,evidence:?string} */
    public static function workflow(string $workflow): array
    {
        return self::WORKFLOWS[$workflow] ?? throw new InvalidArgumentException("'{$workflow}' is not an approval workflow.");
    }

    public static function record(string $workflow, int $id): object
    {
        $w = self::workflow($workflow);

        return DB::table($w['table'])->where('id', $id)->first() ?? throw new InvalidArgumentException("No {$w['label']} {$id}.");
    }

    /** Whether the item waits for a decision (the compliance sign-off waits while a status is proposed). */
    public static function pending(string $workflow, object $row): bool
    {
        $w = self::workflow($workflow);
        $value = $row->{$w['status']} ?? null;

        return $w['pending'] === [] ? $value !== null : in_array($value, $w['pending'], true);
    }

    public static function evidenceRequired(string $workflow): ?string
    {
        return self::workflow($workflow)['evidence'];
    }

    /** The live documents on an item, attached to it or linked to it from another decision (section 8.7); removed ones excluded. */
    public static function documentCount(string $workflow, int $id): int
    {
        return SupportingDocuments::count(self::workflow($workflow)['table'], $id);
    }

    /**
     * Refuse an approval without the evidence the workflow requires (section
     * 8.6; acceptance 13). The bootstrap's automated approvals are not MAIIC
     * approvals and carry no evidence.
     */
    public static function assertEvidence(string $workflow, int $id, ?string $label = null): void
    {
        $evidence = self::evidenceRequired($workflow);
        if ($evidence === null || $label === LoanBookBuildService::BOOTSTRAP_LABEL || ! config('approvals.require_evidence', true)) {
            return;
        }
        if (self::documentCount($workflow, $id) === 0) {
            $w = self::workflow($workflow);
            throw new LogicException("{$w['label']} {$id} needs a supporting document before it is approved: {$evidence} (Staging Module Specification v1, section 8.6).");
        }
    }

    /**
     * Reject (a reviewer) or withdraw (the maker) an item awaiting a decision,
     * with a reason. A workflow whose service decides it already is called;
     * any other is decided here.
     */
    public static function decide(string $workflow, int $id, string $action, int $userId, string $reason): void
    {
        $reason = trim($reason);
        if (! in_array($action, ['reject', 'withdraw'], true)) {
            throw new InvalidArgumentException("'{$action}' is not reject or withdraw.");
        }
        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException(($action === 'reject' ? 'A rejection' : 'A withdrawal') . ' needs a specific reason of at least 10 characters.');
        }
        $own = self::serviceDecision($workflow, $id, $action, $userId, $reason);
        if ($own) {
            return;
        }
        $w = self::workflow($workflow);
        $row = self::record($workflow, $id);
        if (! self::pending($workflow, $row)) {
            throw new LogicException("{$w['label']} {$id} is not waiting for a decision.");
        }
        $maker = $w['maker'] !== null && isset($row->{$w['maker']}) ? (int) $row->{$w['maker']} : null;
        if ($action === 'withdraw' && $maker !== null && $maker !== $userId) {
            throw new LogicException("Only the person who proposed {$w['label']} {$id} can withdraw it; a second person rejects it instead.");
        }
        if ($action === 'reject' && $maker === $userId && ! MakerChecker::selfApprovalAllowed($w['label'] . ' (reject)', $w['table'], $id, $userId)) {
            throw new LogicException("The person who proposed {$w['label']} {$id} withdraws it rather than rejecting it.");
        }
        $to = $action === 'withdraw' ? 'WITHDRAWN' : (array_key_exists('reject_to', $w) ? $w['reject_to'] : 'REJECTED');
        $update = [$w['status'] => $to];
        if (Schema::hasColumn($w['table'], 'updated_at')) {
            $update['updated_at'] = now();
        }
        DB::table($w['table'])->where('id', $id)->update($update);
        AuditLoggerService::log($w['label'] . ($action === 'reject' ? ' Rejected' : ' Withdrawn'), $w['table'], $id, [
            'old_values' => [$w['status'] => $row->{$w['status']} ?? null],
            'new_values' => [$w['status'] => $to, 'reason' => $reason, 'workflow' => $workflow],
            'meta' => ['user' => $userId],
        ]);
    }

    /** The workflows whose own service already rejects or withdraws. */
    private static function serviceDecision(string $workflow, int $id, string $action, int $userId, string $reason): bool
    {
        switch ($workflow) {
            case 'eir-reopen':
                app(\App\Services\Eir\EirReopenRequestService::class)->close($id, $userId, $action, $reason);

                return true;
            case 'period-unlock':
                app(\App\Services\Eir\PeriodUnlockService::class)->close($id, $userId, $action, $reason);

                return true;
            case 'restructure':
                app(\App\Services\Eir\RestructureRegisterService::class)->close($id, $userId, $action, $reason);

                return true;
            case 'rate-reset':
                app(\App\Services\Eir\RateResetService::class)->close($id, $userId, $action, $reason);

                return true;
            case 'write-off':
                app(\App\Services\Eir\WriteOffRegisterService::class)->close($id, $userId, $action, $reason);

                return true;
            case 'fee-rule':
                $rule = \App\Models\EirAccountingRule::findOrFail($id);
                $action === 'reject' ? app(\App\Services\Eir\AccountingRuleService::class)->reject($rule, $userId, $reason) : app(\App\Services\Eir\AccountingRuleService::class)->withdraw($rule, $userId, $reason);

                return true;
            case 'staging-threshold':
                if ($action === 'reject') {
                    app(\App\Services\Eir\StagingThresholdService::class)->reject($id, $userId, $reason);

                    return true;
                }

                return false;
            case 'sicr-trigger':
                if ($action === 'reject') {
                    app(\App\Services\Eir\QualitativeSicrService::class)->reject($id, $userId, $reason);

                    return true;
                }

                return false;
            case 'fli-overlay':
                if ($action === 'reject') {
                    app(\App\Services\Fli\OverlayService::class)->reject($id, $userId, $reason);

                    return true;
                }

                return false;
            case 'fli-model':
                if ($action === 'reject') {
                    app(\App\Services\Fli\FliModelRegistry::class)->rejectExternal($id, $userId, $reason);

                    return true;
                }

                return false;
            default:
                return false;
        }
    }
}
