<?php
// Read-only: E-Banker's overdue run (OD_01) against the days past due staging reads, on the DB given.
// usage: php odcmp.php <db>
$db = $argv[1] ?? '';
if ($db === '' || $db === 'maiic_ifrs9') { fwrite(STDERR, "refusing\n"); exit(1); }
putenv("DB_DATABASE={$db}"); $_ENV['DB_DATABASE'] = $db; $_SERVER['DB_DATABASE'] = $db;
chdir('C:/xampp/htdocs/MAICC-IFRS9');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
ini_set('memory_limit', '4096M');
use Illuminate\Support\Facades\DB;
if (DB::connection()->getDatabaseName() !== $db) { fwrite(STDERR, "connected to the wrong DB\n"); exit(1); }
DB::statement('SET SESSION TRANSACTION READ ONLY');
$r = app(App\Services\Eir\BaselineService::class)->overdueRunComparison(15);
echo json_encode($r, JSON_PRETTY_PRINT), "\n";
