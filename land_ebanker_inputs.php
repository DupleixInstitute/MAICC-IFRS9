<?php

/**
 * Lands the committed E-Banker inputs into the database DB_DATABASE names, without touching
 * anything already built there: the governance, staging-threshold and query settings (seeders
 * that only add what is missing), every committed pack through its gates in the bootstrap's
 * order (a pack already landed is recognised by its hash), and the account exclusions MAIIC
 * confirmed. No loan book is built; run eir:build-loan-books for the months wanted afterwards.
 *
 * Usage (cmd.exe, project root):
 *   set DB_DATABASE=maiic_ifrs9
 *   php -d memory_limit=2048M land_ebanker_inputs.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Ebanker\AccountExclusionService;
use App\Services\Ebanker\PackLandingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$user = 1;
$inputs = __DIR__ . '/docs/bootstrap';
echo 'Database: ' . DB::connection()->getDatabaseName() . PHP_EOL;

foreach (['GovernanceSettingsSeeder', 'StagingThresholdSeeder', 'EbankerQuerySeeder'] as $seeder) {
    Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
    echo "seeded {$seeder}" . PHP_EOL;
}

$landing = app(PackLandingService::class);
$report = function (string $name, array $r): void {
    $files = count(array_filter($r['files'] ?? [], fn ($f) => is_array($f) && isset($f['new'])));
    echo "land {$name}: {$r['status']} (load {$r['load_id']}), {$files} files" . PHP_EOL;
    if ($r['status'] === 'QUARANTINED') {
        throw new RuntimeException("{$name} did not pass its gates; nothing after it was landed.");
    }
};
$report('docs/bootstrap', $landing->land($inputs, $user, PackLandingService::ROUTE_MANUAL));

$packs = [];
foreach (glob($inputs . '/ebanker-pack-*/manifest.json') ?: [] as $m) {
    $packs[basename(dirname($m))] = dirname($m);
}
foreach (glob($inputs . '/packs/ebanker-pack-*.zip') ?: [] as $zipPath) {
    $name = basename($zipPath, '.zip');
    if (isset($packs[$name])) {
        continue;
    }
    $dir = storage_path('app/bootstrap-packs');
    if (! is_dir($dir . '/' . $name)) {
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("{$name}.zip is not a readable zip.");
        }
        App\Services\Ebanker\FeedZip::extract($zip, $dir, 'bootstrap');
        $zip->close();
    }
    if (is_file($dir . '/' . $name . '/manifest.json')) {
        $packs[$name] = $dir . '/' . $name;
    }
}
ksort($packs);
foreach ($packs as $name => $dir) {
    $report($name, $landing->land($dir, $user, PackLandingService::ROUTE_MANUAL));
}

$exclusions = $inputs . DIRECTORY_SEPARATOR . AccountExclusionService::CLIENT_INPUT;
if (is_file($exclusions)) {
    $x = app(AccountExclusionService::class)->landClientInput($exclusions);
    echo "account exclusions: {$x['accounts']} accounts, {$x['landed']} landed, " . count($x['already']) . ' already held' . PHP_EOL;
}

echo 'done' . PHP_EOL;
