<?php
chdir('C:/xampp/htdocs/MAICC-IFRS9');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
ini_set('memory_limit', '4096M');
use Illuminate\Support\Facades\DB;
echo DB::connection()->getDatabaseName(), "\n";
$zone = app(App\Services\Ebanker\LandingZoneReader::class);
$reader = $zone->ledgerReader();
$svc = new App\Services\Eir\RestructureCandidateService($zone);
$pairs = [];
foreach ($svc->chart() as $account => $rows) {
    $pairs[] = [(string) $account, null];
    foreach (array_unique(array_filter(array_column($rows, 'created'))) as $d) { $pairs[] = [(string) $account, $d]; $pairs[] = [(string) $account, App\Services\Eir\RestructureCandidateService::dayBefore($d)]; }
}
// every ledger account at every month-end too
$accounts = array_keys($zone->ledgerByAccount());
foreach ($accounts as $a) { $pairs[] = [(string) $a, null]; foreach (['2024-07-31', '2024-12-31', '2025-06-30', '2025-12-31', '2026-09-30'] as $d) $pairs[] = [(string) $a, $d]; }
$same = 0; $diff = 0; $rowsCompared = 0;
foreach ($pairs as [$a, $d]) {
    $old = $zone->ledgerByAccount($d, $a)[$a] ?? [];
    $new = $reader($a, $d);
    $rowsCompared += count($old);
    if ($old === $new) $same++; else { $diff++; if ($diff <= 5) echo "DIFF {$a} {$d}: " . count($old) . ' vs ' . count($new) . "\n"; }
}
printf("pairs %d, identical %d, different %d, rows compared %d, accounts %d\n", count($pairs), $same, $diff, $rowsCompared, count(array_unique(array_column($pairs, 0))));
