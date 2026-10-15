<?php
chdir('C:/xampp/htdocs/MAICC-IFRS9');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$t = microtime(true);
$z = new App\Services\Ebanker\LandingZoneReader();
$h = new App\Services\Ebanker\InstalmentChartHistory($z);
$accts = Illuminate\Support\Facades\DB::table('loan_books')->where('reporting_period', '2025-12')->pluck('external_identity_id');
foreach ($accts as $a) { $h->at((string) $a, '2025-12-31'); }
printf("charts for %d accounts: %.1fs\n", count($accts), microtime(true) - $t);
$t = microtime(true);
$z->ledgerByAccount('2025-12-31', null, true);
printf("one month ledger read: %.1fs\n", microtime(true) - $t);
