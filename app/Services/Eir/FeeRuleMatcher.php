<?php

namespace App\Services\Eir;

use App\Models\ContractFee;
use App\Models\EirAccountingRule;

class FeeRuleMatcher
{
    /**
     * Re-applies the approved rulebook to fee lines already on file.
     *
     * Rules are matched at import, which means a line loaded before its rule
     * existed — or before that rule was approved — carries no suggestion and
     * never will. Re-importing the source file to fix that is not an option:
     * `FeeImportService` only skips a duplicate when the row carries both a
     * source system and an external transaction id, so a file without them
     * would load a second copy of every line.
     *
     * Only PENDING lines are touched. A CLASSIFIED or REVIEWED line has a
     * decision recorded against it, and moving the suggestion underneath that
     * decision would leave an approved treatment sitting next to a rule that
     * no longer agrees with it — the audit trail would read as though someone
     * had overruled a rule they never saw.
     *
     * @return array{examined:int,matched:int,unmatched:int,changed:int,left_alone:int}
     */
    public function sweepPending(): array
    {
        $examined = 0;
        $matched = 0;
        $changed = 0;

        ContractFee::where('classification_status', 'PENDING')
            ->orderBy('id')
            ->chunkById(500, function ($fees) use (&$examined, &$matched, &$changed) {
                foreach ($fees as $fee) {
                    $examined++;
                    $rule = $this->match([
                        'fee_type' => $fee->fee_type,
                        'description' => $fee->description,
                        'cashflow_direction' => $fee->cashflow_direction,
                        'gl_account_ref' => $fee->gl_account_ref,
                    ]);

                    if ($rule) $matched++;

                    // Compare the treatment as well as the rule identity: an
                    // approved rule whose proposed_integral is later reversed
                    // keeps the same id, so testing the id alone would leave
                    // every already-tagged line carrying the old verdict.
                    $sameRule = (int) $fee->suggested_rule_id === (int) $rule?->id;
                    $sameVerdict = (int) $fee->suggested_integral === (int) $rule?->proposed_integral;
                    if ($sameRule && $sameVerdict) continue;

                    $fee->update([
                        'suggested_rule_id' => $rule?->id,
                        'suggested_integral' => $rule?->proposed_integral,
                    ]);
                    $changed++;
                }
            });

        return [
            'examined' => $examined,
            'matched' => $matched,
            'unmatched' => $examined - $matched,
            'changed' => $changed,
            'left_alone' => ContractFee::whereIn('classification_status', ['CLASSIFIED', 'REVIEWED'])->count(),
        ];
    }

    public function match(array $fee): ?EirAccountingRule
    {
        return EirAccountingRule::query()
            ->where('active', true)
            ->whereNotNull('approved_at')
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->first(function (EirAccountingRule $rule) use ($fee) {
                if ($rule->fee_type && strtolower((string) ($fee['fee_type'] ?? '')) !== strtolower($rule->fee_type)) return false;
                if ($rule->gl_account_ref && (string) ($fee['gl_account_ref'] ?? '') !== $rule->gl_account_ref) return false;
                if ($rule->cashflow_direction && strtoupper((string) ($fee['cashflow_direction'] ?? '')) !== $rule->cashflow_direction) return false;
                if ($rule->description_contains && ! str_contains(strtolower((string) ($fee['description'] ?? '')), strtolower($rule->description_contains))) return false;
                return true;
            });
    }
}
