<?php
// Preview (no writes) of the rows LoanBookBuildService would produce for a period by a method, grouped by GL.
// Usage (from the repo root): php -d memory_limit=-1 <this file> 2026-09 B
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$t = microtime(true);
$z = $app->make(App\Services\Ebanker\LandingZoneReader::class);
$l = $z->ledgerByAccount('2026-09-30');
printf("ledgerByAccount: %d accounts in %.1f s\n", count($l), microtime(true) - $t);
$t = microtime(true);
$rows = $app->make(App\Services\Ebanker\LoanBookBuildService::class)->rowsFor($argv[1], $argv[2]);
$g = [];
foreach ($rows as $r) {
    $k = $r['product_code'];
    $g[$k] ??= ['n' => 0, 'flagged' => 0, 'carrying' => 0.0, 'stored' => 0.0];
    $g[$k]['n']++;
    $g[$k]['flagged'] += $r['build_flag'] !== null ? 1 : 0;
    $g[$k]['carrying'] += (float) $r['carrying_amount'];
    $g[$k]['stored'] += (float) ($r['stored_carrying_amount'] ?? 0);
}
ksort($g);
foreach ($g as $k => $v) printf("%-9s rows %5d flagged %5d carrying %18s stored %18s\n", $k, $v['n'], $v['flagged'], number_format($v['carrying'], 2), number_format($v['stored'], 2));
printf("%.1f s\n", microtime(true) - $t);
