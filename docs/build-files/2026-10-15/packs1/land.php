<?php
// Land the named pack folders on the database in DB_DATABASE and compare to the manifests.
$base = 'C:/xampp/htdocs/MAICC-IFRS9';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection()->getDatabaseName();
if (in_array($db, ['maiic_ifrs9', 'maiic_ifrs9_demo', 'maiic_ifrs9_results2', 'maiic_ifrs9_reports'], true)) {
    fwrite(STDERR, "refusing to write to {$db}\n");
    exit(2);
}
echo "database {$db}\n";
$svc = app(App\Services\Ebanker\PackLandingService::class);
$dry = in_array('--dry', $argv, true);
foreach (array_slice($argv, 1) as $name) {
    if ($name === '--dry') continue;
    $dir = $base . '/docs/bootstrap/' . $name;
    $t = microtime(true);
    $r = $svc->land($dir, 1, 'BOOTSTRAP', $dry);
    printf("%s: %s load %d in %.1fs\n", $name, $r['status'], $r['load_id'], microtime(true) - $t);
    foreach (($r['gates']['files'] ?? []) as $f => $g) {
        if (($g['failures'] ?? []) !== []) echo "   FAIL {$f}: " . implode(' | ', array_slice($g['failures'], 0, 3)) . "\n";
    }
    foreach (($r['gates']['pack'] ?? []) as $k => $g) {
        if (is_array($g) && isset($g['result'])) echo "   gate {$k}: {$g['result']}" . (isset($g['detail']) ? " ({$g['detail']})" : '') . "\n";
    }
    if ($dry || $r['load_id'] === 0) continue;
    $m = json_decode(file_get_contents($dir . '/manifest.json'), true);
    foreach ($m['files'] as $e) {
        $pf = DB::table('ebanker_pack_files')->where('load_id', $r['load_id'])->where('file', $e['file'])->first();
        $raw = DB::table('ebanker_raw_rows')->where('load_id', $r['load_id'])->where('query_id', $e['query_id'])->count();
        $ok = $pf && (int) $pf->rows_declared === (int) $e['rows'] && ((int) $pf->rows_new + (int) $pf->rows_versioned + (int) $pf->rows_unchanged) === (int) $e['rows'] && $pf->sha256 === $e['sha256'];
        printf("   %-40s %-7s manifest %6d landed new %6d versioned %d unchanged %d  sha %s  %s\n", $e['file'], $e['query_id'], $e['rows'], $pf->rows_new ?? -1, $pf->rows_versioned ?? -1, $pf->rows_unchanged ?? -1, $pf && $pf->sha256 === $e['sha256'] ? 'ok' : 'DIFF', $ok ? 'OK' : 'MISMATCH');
    }
}
