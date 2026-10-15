<?php

namespace App\Services\Eir;

use App\Models\EirAccountingRule;
use App\Services\AuditLoggerService;
use App\Support\MakerChecker;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The fee rulebook under maker-checker (Staging Module Specification v1,
 * section 8.6; phase 3; acceptance 14). A rule decides whether a fee type is
 * integral to the EIR, so it changes figures; until 15 October 2026 the person
 * who wrote a rule could approve it with no reason, and switching a rule on or
 * off took effect at once.
 *
 *   write       creating or editing a rule proposes it (PROPOSED); an edit
 *               clears its approval, so the matcher stops using it until a
 *               second person approves the new wording
 *   approve     by a person other than the one who last wrote it, with a
 *               reason; an approval given before this control (approval to
 *               confirm, decision S9 option (a)) is confirmed the same way
 *   reject      by the approver, with a reason; withdraw by its writer
 *   switch      switching a rule on or off is proposed with a reason and in
 *               force only when a second person approves it
 *
 * The second-person rule is the one maker-checker check (MakerChecker): under
 * UAT mode an administrator may decide their own proposal, and the use is
 * recorded on the self-approvals report. Every step is audit-logged.
 */
class AccountingRuleService
{
    public const WORKFLOW = 'Fee rulebook';

    public function create(array $data, int $userId): EirAccountingRule
    {
        $rule = EirAccountingRule::create($data + ['created_by' => $userId, 'status' => EirAccountingRule::STATUS_PROPOSED, 'approved_by' => null, 'approved_at' => null]);
        AuditLoggerService::log('EIR Accounting Rule Proposed', EirAccountingRule::class, $rule->id, ['new_values' => $rule->toArray(), 'meta' => ['user' => $userId]]);

        return $rule;
    }

    public function update(EirAccountingRule $rule, array $data, int $userId): EirAccountingRule
    {
        $old = $rule->toArray();
        $rule->update($data + ['updated_by' => $userId, 'status' => EirAccountingRule::STATUS_PROPOSED, 'approved_by' => null, 'approved_at' => null,
            'approval_reason' => null, 'approval_to_confirm' => false, 'proposed_active' => null, 'switch_proposed_by' => null, 'switch_proposed_at' => null, 'switch_reason' => null]);
        AuditLoggerService::log('EIR Accounting Rule Updated', EirAccountingRule::class, $rule->id, ['old_values' => $old, 'new_values' => $rule->fresh()->toArray(), 'meta' => ['user' => $userId, 'note' => 'approval cleared; the rule waits for a second person']]);

        return $rule->fresh();
    }

    /** Approve a proposed rule, or confirm an approval given before this control. */
    public function approve(EirAccountingRule $rule, int $userId, string $reason): EirAccountingRule
    {
        $reason = $this->reason($reason, 'An approval');

        return DB::transaction(function () use ($rule, $userId, $reason) {
            $rule = EirAccountingRule::query()->lockForUpdate()->findOrFail($rule->id);
            $confirming = $rule->status === EirAccountingRule::STATUS_APPROVED && $rule->approval_to_confirm;
            if ($rule->status !== EirAccountingRule::STATUS_PROPOSED && ! $confirming) {
                throw new LogicException("Rule \"{$rule->name}\" is " . strtolower($rule->status) . '; only a proposed rule, or an approval waiting to be confirmed, can be approved.');
            }
            $this->assertSecondPerson($rule->makerId(), $userId, (int) $rule->id, 'The person who wrote or last changed this rule cannot approve it; a second person approves.');
            $rule->update(['status' => EirAccountingRule::STATUS_APPROVED, 'approved_by' => $userId, 'approved_at' => now(), 'approval_reason' => $reason, 'approval_to_confirm' => false]);
            AuditLoggerService::log($confirming ? 'EIR Accounting Rule Approval Confirmed' : 'EIR Accounting Rule Approved', EirAccountingRule::class, $rule->id, [
                'new_values' => ['status' => EirAccountingRule::STATUS_APPROVED, 'reason' => $reason], 'meta' => ['user' => $userId, 'maker' => $rule->makerId()]]);

            return $rule->fresh();
        });
    }

    public function reject(EirAccountingRule $rule, int $userId, string $reason): EirAccountingRule
    {
        $reason = $this->reason($reason, 'A rejection');
        if ($rule->status !== EirAccountingRule::STATUS_PROPOSED) {
            throw new LogicException("Rule \"{$rule->name}\" is " . strtolower($rule->status) . '; only a proposed rule can be rejected.');
        }
        $rule->update(['status' => EirAccountingRule::STATUS_REJECTED, 'rejected_by' => $userId, 'rejected_at' => now(), 'rejection_reason' => $reason]);
        AuditLoggerService::log('EIR Accounting Rule Rejected', EirAccountingRule::class, $rule->id, ['new_values' => ['reason' => $reason], 'meta' => ['user' => $userId]]);

        return $rule->fresh();
    }

    public function withdraw(EirAccountingRule $rule, int $userId, string $reason): EirAccountingRule
    {
        $reason = $this->reason($reason, 'A withdrawal');
        if ($rule->status !== EirAccountingRule::STATUS_PROPOSED) {
            throw new LogicException("Rule \"{$rule->name}\" is " . strtolower($rule->status) . '; only a proposed rule can be withdrawn.');
        }
        if ($rule->makerId() !== null && $rule->makerId() !== $userId) {
            throw new LogicException('Only the person who wrote or last changed the rule can withdraw it; a second person rejects it instead.');
        }
        $rule->update(['status' => EirAccountingRule::STATUS_WITHDRAWN, 'withdrawn_by' => $userId, 'withdrawn_at' => now(), 'withdrawal_reason' => $reason]);
        AuditLoggerService::log('EIR Accounting Rule Withdrawn', EirAccountingRule::class, $rule->id, ['new_values' => ['reason' => $reason], 'meta' => ['user' => $userId]]);

        return $rule->fresh();
    }

    /** Propose switching a rule on or off; nothing changes until a second person approves. */
    public function proposeSwitch(EirAccountingRule $rule, bool $active, int $userId, string $reason): EirAccountingRule
    {
        $reason = $this->reason($reason, 'Switching a rule on or off');
        if ($rule->proposed_active !== null) {
            throw new LogicException("A switch of rule \"{$rule->name}\" is already waiting for approval.");
        }
        if ((bool) $rule->active === $active) {
            throw new LogicException("Rule \"{$rule->name}\" is already " . ($active ? 'on' : 'off') . '.');
        }
        $rule->update(['proposed_active' => $active, 'switch_proposed_by' => $userId, 'switch_proposed_at' => now(), 'switch_reason' => $reason]);
        AuditLoggerService::log('EIR Accounting Rule Switch Proposed', EirAccountingRule::class, $rule->id, ['new_values' => ['active' => $active, 'reason' => $reason], 'meta' => ['user' => $userId]]);

        return $rule->fresh();
    }

    public function approveSwitch(EirAccountingRule $rule, int $userId, string $reason): EirAccountingRule
    {
        $reason = $this->reason($reason, 'An approval');

        return DB::transaction(function () use ($rule, $userId, $reason) {
            $rule = EirAccountingRule::query()->lockForUpdate()->findOrFail($rule->id);
            if ($rule->proposed_active === null) {
                throw new LogicException("No switch of rule \"{$rule->name}\" is waiting for approval.");
            }
            $this->assertSecondPerson($rule->switch_proposed_by === null ? null : (int) $rule->switch_proposed_by, $userId, (int) $rule->id, 'The person who proposed switching this rule cannot approve it; a second person approves.');
            $active = (bool) $rule->proposed_active;
            $rule->update(['active' => $active, 'proposed_active' => null, 'switch_proposed_by' => null, 'switch_proposed_at' => null, 'switch_reason' => null]);
            AuditLoggerService::log('EIR Accounting Rule Status Changed', EirAccountingRule::class, $rule->id, ['new_values' => ['active' => $active, 'reason' => $reason], 'meta' => ['user' => $userId]]);

            return $rule->fresh();
        });
    }

    public function rejectSwitch(EirAccountingRule $rule, int $userId, string $reason): EirAccountingRule
    {
        $reason = $this->reason($reason, 'A rejection');
        if ($rule->proposed_active === null) {
            throw new LogicException("No switch of rule \"{$rule->name}\" is waiting for approval.");
        }
        AuditLoggerService::log('EIR Accounting Rule Switch Rejected', EirAccountingRule::class, $rule->id, ['new_values' => ['active' => (bool) $rule->proposed_active, 'reason' => $reason], 'meta' => ['user' => $userId]]);
        $rule->update(['proposed_active' => null, 'switch_proposed_by' => null, 'switch_proposed_at' => null, 'switch_reason' => null]);

        return $rule->fresh();
    }

    /** The one maker-checker check: a named second person, or UAT mode (recorded). */
    private function assertSecondPerson(?int $makerId, int $userId, int $ruleId, string $message): void
    {
        if ($makerId === $userId && ! MakerChecker::selfApprovalAllowed(self::WORKFLOW, EirAccountingRule::class, $ruleId, $userId)) {
            throw new LogicException($message);
        }
    }

    private function reason(string $reason, string $what): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException("{$what} needs a specific reason of at least 10 characters.");
        }

        return mb_substr($reason, 0, 2000);
    }
}
