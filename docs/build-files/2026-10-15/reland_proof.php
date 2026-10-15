<?php
// Re-land every committed pack into the database named by DB_DATABASE, each from a copy whose manifest
// carries one extra field (so its hash differs and the files go through the door again), and count
// what each landing adds. Proof for the keyless-query fix of 14 October 2026.
$base = 'C:/xampp/htdocs/MAICC-IFRS9';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
ini_set('memory_limit', '-1');

use App\Services\Ebanker\PackLandingService;
use Illuminate\Support\Facades\DB;

$tmp = $argv[1];
$packs = ['manifest.json (packs 1 and 2, data dictionary)' => $base . '/docs/bootstrap'];
foreach (glob($base . '/docs/bootstrap/ebanker-pack-*/manifest.json') as $m) {
    $packs[basename(dirname($m))] = dirname($m);
}
foreach (glob($base . '/storage/app/bootstrap-packs/ebanker-pack-*/manifest.json') as $m) {
    $packs[basename(dirname($m)) . ' (zip)'] = dirname($m);
}
echo 'database ' . DB::connection()->getDatabaseName() . PHP_EOL;
$totalNew = 0;
foreach ($packs as $name => $dir) {
    $manifest = json_decode(file_get_contents($dir . '/manifest.json'), true);
    $copy = $tmp . '/' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $name);
    @mkdir($copy, 0775, true);
    foreach ($manifest['files'] as $f) {
        $src = $dir . '/' . $f['file'];
        if (! is_file($src)) {
            continue;
        }
        $dst = $copy . '/' . $f['file'];
        @mkdir(dirname($dst), 0775, true);
        copy($src, $dst);
    }
    $manifest['relanded_for_proof'] = '14 October 2026: keyless queries keyed by content';
    file_put_contents($copy . '/manifest.json', json_encode($manifest));
    $before = DB::table('ebanker_raw_rows')->count();
    $currentBefore = DB::table('ebanker_raw_rows')->whereNull('superseded_at')->count();
    $r = app(PackLandingService::class)->land($copy, 1, PackLandingService::ROUTE_MANUAL);
    $after = DB::table('ebanker_raw_rows')->count();
    $currentAfter = DB::table('ebanker_raw_rows')->whereNull('superseded_at')->count();
    $files = array_filter($r['files'] ?? [], fn ($x) => is_array($x) && isset($x['new']));
    $new = array_sum(array_column($files, 'new'));
    $versioned = array_sum(array_column($files, 'versioned'));
    $unchanged = array_sum(array_column($files, 'unchanged'));
    $withdrawn = array_sum(array_map(fn ($x) => $x['withdrawn'] ?? 0, $files));
    $totalNew += $after - $before;
    printf("%-55s %-12s files %3d  new %d  versioned %d  unchanged %7d  withdrawn %d  raw rows +%d  current %+d%s\n", $name, $r['status'], count($files), $new, $versioned, $unchanged, $withdrawn, $after - $before, $currentAfter - $currentBefore,
        $r['status'] === 'QUARANTINED' ? ' ' . json_encode(array_filter(array_map(fn ($x) => $x['failures'] ?? [], $r['gates']['files'] ?? []))) : '');
}
echo "raw rows added in all: {$totalNew}" . PHP_EOL;
