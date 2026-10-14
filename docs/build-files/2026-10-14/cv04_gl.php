<?php
// Is the pack 7 old ledger (CV_04a*, CUMVOUCH_BK240824) landed? If so, the GL balances at 31 Dec 2023 by GLCODE, to tie CV_14.
use Illuminate\Support\Facades\DB;
$ids = DB::table('ebanker_raw_rows')->where('query_id', 'like', 'CV_04%')->select('query_id', DB::raw('count(*) n'))->groupBy('query_id')->get();
echo "landed CV_04 query ids: " . json_encode($ids) . "\n";
if ($ids->isEmpty()) { echo "not landed\n"; return; }
$sum = [];
foreach (DB::table('ebanker_raw_rows')->where('query_id', 'like', 'CV_04a%')->orderBy('id')->cursor() as $r) {
    $p = json_decode($r->payload, true);
    $d = substr((string) ($r->row_date ?? ''), 0, 10);
    if ($d === '' || $d > '2023-12-31') { continue; }
    $gl = (string) ($p['GLCODE'] ?? '?');
    $sum[$gl] = ($sum[$gl] ?? 0) + (float) str_replace(',', '', (string) ($p['TRANSAMT'] ?? 0));
}
ksort($sum);
foreach ($sum as $gl => $v) { if (preg_match('/^(105|132|4206|4215|4216)/', $gl)) { echo "GL {$gl}: " . number_format($v, 2) . "\n"; } }
echo "GL count: " . count($sum) . "\n";
