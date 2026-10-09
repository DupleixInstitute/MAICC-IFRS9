<?php
// Dry run: what the rulebook WOULD classify if every rule were approved (nothing is written).
$rules = \App\Models\EirAccountingRule::where('active', true)->orderBy('priority')->orderBy('id')->get();
$fees = \App\Models\ContractFee::all();
echo $fees->count(), " fees; by type: ", $fees->groupBy('fee_type')->map->count()->toJson(), "\n";
echo "descriptions: ", $fees->pluck('description')->map(fn($d)=>strtolower(trim((string)$d)))->countBy()->sortDesc()->take(8)->toJson(), "\n";
$hits = []; $none = 0;
foreach ($fees as $f) {
    $m = $rules->first(function ($r) use ($f) {
        if ($r->fee_type && strtolower((string)$f->fee_type) !== strtolower($r->fee_type)) return false;
        if ($r->gl_account_ref && (string)$f->gl_account_ref !== $r->gl_account_ref) return false;
        if ($r->cashflow_direction && strtoupper((string)$f->cashflow_direction) !== $r->cashflow_direction) return false;
        if ($r->description_contains && stripos((string)$f->description, $r->description_contains) === false) return false;
        return true;
    });
    if ($m) { $k = "p{$m->priority} {$m->rule_name} => " . ($m->proposed_integral ? 'INTEGRAL' : 'period income'); $hits[$k] = ($hits[$k] ?? 0) + 1; } else { $none++; }
}
arsort($hits); print_r($hits); echo "unmatched: $none\n";
echo "GLs on fees: ", $fees->pluck('gl_account_ref')->countBy()->toJson(), "; directions: ", $fees->pluck('cashflow_direction')->countBy()->toJson(), "\n";
