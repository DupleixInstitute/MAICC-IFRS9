<?php
// Read-only: the chart in force, instalments missed and days past due for accounts at a date. usage: php chart.php <db> <date> <account>...
$db = $argv[1];
if ($db === 'maiic_ifrs9') exit(1);
putenv("DB_DATABASE={$db}"); $_ENV['DB_DATABASE'] = $db; $_SERVER['DB_DATABASE'] = $db;
chdir('C:/xampp/htdocs/MAICC-IFRS9');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
ini_set('memory_limit', '4096M');
use Illuminate\Support\Facades\DB;
if (DB::connection()->getDatabaseName() !== $db) exit(1);
DB::statement('SET SESSION TRANSACTION READ ONLY');
$h = app(App\Services\Ebanker\InstalmentChartHistory::class);
$date = $argv[2];
foreach (array_slice($argv, 3) as $a) {
    $c = $h->at($a, $date);
    if ($c === null) { echo "{$a}: no chart in force\n"; continue; }
    $rec = $h->receiptsTo($a, $date);
    $due = App\Services\Ebanker\InstalmentChartHistory::dueTo($c['instalments'], $date);
    $missed = App\Services\Ebanker\InstalmentChartHistory::missed($c['instalments'], $rec, $date);
    $dpd = App\Services\Ebanker\InstalmentChartHistory::daysPastDue($c['instalments'], $rec, $date);
    printf("%s: source %s, unreadable %s, instalments %d, due to date %.2f, receipts %.2f, missed %d, dpd %s\n", $a, $c['source'] ?? '?', var_export($c['unreadable'] ?? null, true), count($c['instalments']), $due, $rec, $missed, json_encode($dpd));
}
