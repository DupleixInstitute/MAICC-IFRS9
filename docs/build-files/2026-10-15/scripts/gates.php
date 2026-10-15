<?php
// Dry-run a pack through PackLandingService and print the pack-level gates and every file failure.
// Usage (from the repo root): php -d memory_limit=-1 <this file> <pack folder>
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$t = microtime(true);
$r = $app->make(App\Services\Ebanker\PackLandingService::class)->land($argv[1], 1, 'ROUTE_1_MANUAL', true);
echo "status {$r['status']}\n";
foreach ($r['gates']['pack'] as $name => $g) {
    if (! is_array($g) || ! isset($g['result'])) continue;
    echo "PACK {$name}: {$g['level']} {$g['result']} " . ($g['detail'] ?? '') . "\n";
    foreach (array_slice($g['failures'] ?? [], 0, 3) as $f) echo "   - {$f}\n";
}
foreach ($r['gates']['files'] as $file => $f) {
    $iso = $f['gates']['dates_iso']['result'] ?? '-';
    echo "FILE {$file}: rows " . ($f['rows'] ?? '-') . ", numeric " . ($f['gates']['numeric']['result'] ?? '-') . " (" . count($f['gates']['numeric']['columns'] ?? []) . " cols), iso {$iso}" . (($f['failures'] ?? []) ? ' FAIL: ' . implode(' | ', array_slice($f['failures'], 0, 3)) : '') . "\n";
}
printf("peak memory %.0f MB, %.1f s\n", memory_get_peak_usage(true) / 1048576, microtime(true) - $t);
