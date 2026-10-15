<?php
$svc = app(\App\Services\Pd\PdSegmentationService::class);
$t = microtime(true);
foreach (['2026-01','2026-04','2026-08'] as $p) {
  foreach ([1, 12] as $w) {
    $m = $svc->measure($p, 'book', $w);
    $s = $m['segments']['book']['stages'] ?? [];
    $perf = array_filter($s, fn($k) => (string)$k !== '3', ARRAY_FILTER_USE_KEY);
    $bal = array_sum(array_column($perf, 'balance')); $d = array_sum(array_column($perf, 'default_balance'));
    $n = array_sum(array_column($perf, 'loans')); $dn = array_sum(array_column($perf, 'default_loans'));
    echo "$p w$w {$m['window_start']}->{$m['window_end']} months {$m['window_months']} loans $n defaults $dn rate " . ($bal ? round($d/$bal*100,3) : 'null') . "% stages " . implode(',', array_keys($s)) . "\n";
  }
}
echo "time " . round(microtime(true)-$t,2) . "s\n";
