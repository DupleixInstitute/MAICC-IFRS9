<?php
require __DIR__ . '/boot.php';
$r = app(App\Services\Ebanker\LoanBookBuildService::class)->build('2024-07', '2026-08', null, null, null, App\Services\Ebanker\LoanBookBuildService::BOOTSTRAP_LABEL, false, true);
$rows = 0; $ret = 0; foreach ($r['periods'] as $x) { $rows += $x['rows']; $ret += $x['retired']; }
echo "build {$r['build_id']} {$r['status']}: " . count($r['periods']) . " periods, $rows rows, retired $ret\n";
