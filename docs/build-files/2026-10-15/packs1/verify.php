<?php
// Run BaselineService::checks() on DB_DATABASE and print the table (read-only).
$base = getenv('BASE') ?: 'C:/xampp/htdocs/MAICC-IFRS9';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'database ' . Illuminate\Support\Facades\DB::connection()->getDatabaseName() . "\n";
$t = microtime(true);
$svc = app(App\Services\Eir\BaselineService::class);
$only = $argv[1] ?? null;
$rows = $only === 'inventory' ? $svc->packInventory(base_path('docs/bootstrap')) : ($only === 'takeon' ? array_map(fn ($k, $g) => [$g["what"], $k, var_export($g["value"], true), true], array_keys($a = $svc->goldenActuals()), $a) : array_map(fn ($c) => [$c['what'], $c['expected'], $c['actual'], $c['ok']], $svc->checks()));
foreach ($rows as [$what, $exp, $act, $ok]) {
    echo ($ok ? 'PASS' : 'FAIL') . ' | ' . $what . ' | ' . $exp . ' | ' . $act . "\n";
}
printf("%.1fs\n", microtime(true) - $t);
