<?php

namespace App\Services\Eir;

use App\Models\EirAccountingRule;

class FeeRuleMatcher
{
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
