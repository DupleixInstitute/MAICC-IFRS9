<?php
require __DIR__ . '/boot.php';
$r = app(App\Services\Ebanker\LoanBookBuildService::class)->build('2024-07', '2026-08', null, null, null, App\Services\Ebanker\LoanBookBuildService::BOOTSTRAP_LABEL, true, true);
$cols = [];
foreach ($r['periods'] as $p => $x) {
  foreach ($x['differences'] as $d) { foreach (($d['fields'] ?? $d['changes'] ?? []) as $k => $v) { $cols[is_int($k) ? (is_array($v) ? ($v['field'] ?? json_encode($v)) : $v) : $k] = ($cols[is_int($k) ? (is_array($v) ? ($v['field'] ?? json_encode($v)) : $v) : $k] ?? 0) + 1; } }
  echo "$p {$x['method']} rows {$x['rows']} flagged {$x['flagged']} new {$x['new']} changed {$x['changed']} unchanged {$x['unchanged']} not_in_build {$x['not_in_build']} stale " . count($x['stale']) . "\n";
}
echo "fields changed: " . json_encode($cols) . "\n";
$first = null; foreach ($r['periods'] as $p => $x) { if ($x['differences']) { echo "sample $p: " . substr(json_encode($x['differences'][0]), 0, 600) . "\n"; break; } }
