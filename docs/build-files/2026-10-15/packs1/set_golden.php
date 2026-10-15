<?php
// Write docs/bootstrap/golden.json from BaselineService::goldenActuals() on DB_DATABASE.
// Every value is the figure the service recomputes; nothing is typed. Usage: php set_golden.php "<note>"
$base = getenv('BASE') ?: 'C:/xampp/htdocs/MAICC-IFRS9';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection()->getDatabaseName();
$svc = app(App\Services\Eir\BaselineService::class);
$actuals = $svc->goldenActuals();
$values = []; $labels = [];
foreach ($actuals as $k => $g) {
    if ($g['value'] === null) {
        fwrite(STDERR, "no value for {$k}: not written\n");
        continue;
    }
    $values[$k] = $g['amount'] ? round((float) $g['value'], 2) : (int) $g['value'];
    $labels[$k] = $g['what'];
}
$commit = trim((string) shell_exec('git -C ' . escapeshellarg($base) . ' rev-parse --short HEAD'));
$dirty = trim((string) shell_exec('git -C ' . escapeshellarg($base) . ' status --porcelain -- app database docs/bootstrap')) !== '';
$out = [
    'what' => 'The golden numbers a clean install must reproduce (eir:bootstrap --fresh --with-client-inputs --build --run-engines --verify). Each value was recomputed from the database by App\\Services\\Eir\\BaselineService::goldenActuals(); --verify recomputes it and compares, to the cent for amounts and exactly for counts.',
    'set_on' => date('Y-m-d'),
    'commit' => $commit . ($dirty ? ' (with uncommitted changes)' : ''),
    'database' => $db,
    'note' => $argv[1] ?? '',
    'values' => $values,
    'labels' => $labels,
];
file_put_contents($base . '/docs/bootstrap/golden.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo count($values) . " golden values written from {$db}\n";
