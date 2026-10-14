<?php
// Dump the take-on postings (31 Jul 2024, types 301/303/305) per account from the landed ledger, plus the loan master names.
use App\Services\Ebanker\LandingZoneReader;
use Illuminate\Support\Facades\DB;

$zone = new LandingZoneReader();
$out = [];
foreach ($zone->ledgerByAccount('2024-08-05') as $account => $posts) {
    foreach ($posts as $p) {
        $pl = $p['payload'];
        $t = (string) ($pl['TRANTYPE'] ?? '');
        $d = substr((string) $p['row_date'], 0, 10);
        if ($d >= '2024-07-25' && $d <= '2024-08-05' && in_array($t, ['301', '303', '305'], true)) {
            $out[$account][$t] = ($out[$account][$t] ?? 0) + (float) str_replace(',', '', (string) ($pl['TRANSAMT'] ?? 0));
            $out[$account]['date'] = $d;
            $out[$account]['narr'] = substr((string) ($pl['NARRATION'] ?? ($pl['PARTICULARS'] ?? '')), 0, 60);
        }
    }
}
$gl = [];
foreach (DB::table('loan_books')->where('reporting_period', '2024-07')->get(['contract_id', 'customer_name', 'product_code', 'carrying_amount', 'principal_balance', 'interest_to_date']) as $r) {
    $gl[ltrim($r->contract_id, '0')] = ['name' => $r->customer_name, 'gl' => $r->product_code, 'carrying' => (float) $r->carrying_amount, 'principal' => (float) $r->principal_balance, 'interest' => (float) $r->interest_to_date];
}
file_put_contents('C:/Users/wadza/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/ee984144-7b28-4df1-9a6e-560f112869e9/scratchpad/takeon.json', json_encode(['postings' => $out, 'book_2024_07' => $gl], JSON_PRETTY_PRINT));
echo 'accounts with take-on postings: ' . count($out) . "; book rows Jul 2024: " . count($gl) . "\n";
