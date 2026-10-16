<?php

namespace App\Services\Eir;

use App\Models\ContractFee;
use App\Models\EirFeeClassificationEvent;
use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Classifying a fee line and reviewing the classification (maker-checker), the one path Fee Classification
 * and Finance's workbook answers both take (16 October 2026). A line is classified integral or not with the
 * reason; a second person reviews it. Every step is an event on the line and an audit entry.
 */
class FeeClassificationService
{
    /**
     * MANUAL applies one treatment to every line; RULE applies each line's own matched rule and skips a line
     * with none (never guessed). A reviewed line is left alone. A blank cash-flow direction is filled only
     * when one is given.
     *
     * @param  list<int>  $feeIds
     * @return array{applied:int, refused:list<string>}
     */
    public function classify(array $feeIds, bool $byRule, ?bool $integral, string $reason, int $userId, ?int $ruleId = null, ?string $direction = null, array $auditMeta = []): array
    {
        $applied = 0;
        $refused = [];
        DB::transaction(function () use ($feeIds, $byRule, $integral, $reason, $userId, $ruleId, $direction, &$applied, &$refused) {
            $fees = ContractFee::whereIn('id', $feeIds)->lockForUpdate()->get();
            foreach ($fees as $fee) {
                if ($fee->classification_status === 'REVIEWED') continue;

                if ($byRule && $fee->suggested_rule_id === null) {
                    $refused[] = $fee->contract_id . ' / ' . $fee->fee_type;
                    continue;
                }

                $lineIntegral = $byRule ? (bool) $fee->suggested_integral : (bool) $integral;
                $lineRule = $byRule ? $fee->suggested_rule_id : $ruleId;
                $lineReason = $byRule
                    ? $reason . ' [applied by rule: ' . ($fee->suggestedRule?->name ?? 'unknown') . ']'
                    : $reason;

                $fee->update(($fee->cashflow_direction === null || $fee->cashflow_direction === '') && ! empty($direction)
                    ? ['cashflow_direction' => $direction] : []);
                $fee->update([
                    'integral' => $lineIntegral,
                    'classification_status' => 'CLASSIFIED',
                    'classification_reason' => $lineReason,
                    'classified_by' => $userId,
                    'classified_at' => now(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);
                EirFeeClassificationEvent::create([
                    'contract_fee_id' => $fee->id, 'action' => 'CLASSIFIED',
                    'integral' => $lineIntegral, 'reason' => $lineReason,
                    'accounting_rule_id' => $lineRule,
                    'performed_by' => $userId,
                ]);
                $applied++;
            }
        });

        AuditLoggerService::log('EIR Fees Classified', ContractFee::class, null, ['meta' => $auditMeta + [
            'fee_ids' => $feeIds, 'mode' => $byRule ? 'RULE' : 'MANUAL',
            'integral' => $byRule ? null : $integral,
            'cashflow_direction_set' => $direction,
            'applied' => $applied, 'refused' => count($refused),
        ]]);

        return ['applied' => $applied, 'refused' => $refused];
    }

    /**
     * A second person reviews classified lines. The classifier never reviews their own decision, except
     * through the one governed administrator override when $allowOverride (Staging Module Specification v1,
     * section 8.9), which is recorded; an integral line with no cash-flow direction is refused.
     *
     * @param  list<int>  $feeIds
     * @return array{reviewed:list<int>, maker_checker_override:bool}
     * @throws InvalidArgumentException naming why nothing was approved
     */
    public function review(array $feeIds, int $userId, bool $allowOverride = true, array $auditMeta = []): array
    {
        $fees = ContractFee::whereIn('id', $feeIds)->where('classification_status', 'CLASSIFIED')->get();
        $own = $fees->where('classified_by', $userId);
        // an integral line enters the EIR as money received or paid: approving one with no
        // direction left a reviewed line the EIR could not use and nobody could correct
        $undirected = $fees->filter(fn ($f) => (bool) $f->integral && ! in_array($f->cashflow_direction, ['RECEIVED', 'PAID'], true));
        if ($undirected->isNotEmpty()) {
            throw new InvalidArgumentException($undirected->count() . ' integral line(s) have no cash-flow direction (' . $undirected->take(5)->map(fn ($f) => $f->contract_id . ' / ' . $f->fee_type)->implode(', ')
                . '). Return them to classification so the classifier states whether the fee was received or paid; nothing was approved.');
        }
        // the one maker-checker check (Staging Module Specification v1, section 8.9): recorded when used
        $adminOverride = $allowOverride && $own->isNotEmpty() && \App\Support\MakerChecker::selfApprovalAllowed('Fee classification review', ContractFee::class, $own->pluck('id')->implode(','), $userId);
        if ($own->isNotEmpty() && ! $adminOverride) {
            throw new InvalidArgumentException('A classifier cannot review their own decision.');
        }

        DB::transaction(function () use ($fees, $userId) {
            foreach ($fees as $fee) {
                $fee->update(['classification_status' => 'REVIEWED', 'reviewed_by' => $userId, 'reviewed_at' => now()]);
                EirFeeClassificationEvent::create(['contract_fee_id' => $fee->id, 'action' => 'REVIEWED', 'integral' => $fee->integral, 'reason' => $fee->classification_reason, 'performed_by' => $userId]);
            }
        });
        AuditLoggerService::log('EIR Fee Classifications Reviewed', ContractFee::class, null, [
            'meta' => $auditMeta + [
                'fee_ids' => $fees->pluck('id')->all(),
                'maker_checker_override' => $adminOverride,
            ],
        ]);

        return ['reviewed' => $fees->pluck('id')->map(fn ($id) => (int) $id)->all(), 'maker_checker_override' => $adminOverride];
    }
}
