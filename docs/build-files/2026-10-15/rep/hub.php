<?php
// usage: DB_DATABASE=maiic_ifrs9_reports php hub.php method period [maxlines]
require 'C:/xampp/htdocs/MAICC-IFRS9/vendor/autoload.php';
$app = require 'C:/xampp/htdocs/MAICC-IFRS9/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
ini_set('memory_limit', '2048M');
$method = $argv[1]; $period = $argv[2] ?? '2026-09'; $max = (int) ($argv[3] ?? 60);
$req = Illuminate\Http\Request::create('/x', 'GET', ['period' => $period, 'download' => 'csv']);
$app->instance('request', $req);
$c = $app->make(App\Http\Controllers\Reports\Ifrs9ReportsController::class);
$t = microtime(true);
$resp = $c->{$method}($req);
ob_start();
$resp->sendContent();
$out = ob_get_clean();
$lines = explode("\n", $out);
echo implode("\n", array_slice($lines, 0, $max)) . "\n";
echo '... ' . count($lines) . ' lines, ' . round(microtime(true) - $t, 1) . "s\n";
