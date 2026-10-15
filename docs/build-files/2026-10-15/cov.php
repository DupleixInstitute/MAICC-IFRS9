<?php
// Read-only: computes the EIR coverage profile for 2026-09 on the DB in DB_DATABASE.
$base = 'C:/xampp/htdocs/MAICC-IFRS9';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'DB: ' . Illuminate\Support\Facades\DB::connection()->getDatabaseName() . PHP_EOL;
$p = app(App\Services\Eir\EirCoverageService::class)->profile('2026-09');
echo json_encode([
    'summary' => $p['summary'],
    'states' => $p['states'],
    'issues' => array_map(fn ($i) => [$i['code'], $i['contracts'], $i['exposure'], $i['exposure_percent']], $p['issues']),
    'out_of_scope' => $p['out_of_scope'],
    'portfolios' => array_map(fn ($x) => [$x['portfolio'], $x['contracts'], $x['covered']], $p['portfolios']),
], JSON_PRETTY_PRINT) . PHP_EOL;
