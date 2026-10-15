<?php
// Reruns the bootstrap periodChain steps from the route on, for the periods given, on the DB in DB_DATABASE.
chdir('C:/xampp/htdocs/MAICC-IFRS9');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
$db = DB::connection()->getDatabaseName();
if ($db !== 'maiic_ifrs9_fliall') { fwrite(STDERR, "refusing: connected to {$db}\n"); exit(1); }
$user = 1;
foreach (array_slice($argv, 1) as $p) {
    $t = microtime(true);
    $r = app(App\Services\Fli\FliRouteService::class)->apply($p, $user);
    echo "{$p} route: set {$r['set']}, scenarios " . implode(',', array_keys($r['scenarios'])) . ", loans {$r['loans']}, note: {$r['note']}\n";
    try { app(App\Services\Fli\FliProjectionService::class)->run($p, $user); echo "{$p} projection: ok\n"; } catch (Throwable $e) { echo "{$p} projection: not run: " . substr($e->getMessage(), 0, 160) . "\n"; }
    $code = Artisan::call('ifrs9:segment-ecl', App\Console\Commands\Bootstrap::eclArguments($p));
    echo "{$p} ecl: exit {$code}\n";
    if ($code !== 0) { echo Artisan::output(); exit(1); }
    $s = app(App\Services\Fli\FliCloseService::class)->record($p, $user);
    printf("%s record %d: booked %.2f weighted %.2f reconciles %s | discounted booked %.2f weighted %.2f diff %.2f reconciles %s | loans %d discounted %d unresolved %d without_scenario_pd %d\n",
        $p, $s['id'], $s['booked_ecl'], $s['weighted_ecl'], var_export((bool) $s['reconciles'], true), $s['booked_ecl_discounted'], $s['weighted_ecl_discounted'], $s['discounted_difference'], var_export((bool) $s['discounted_reconciles'], true),
        $s['loans'], $s['discounted_loans'], $s['discount_unresolved_loans'], $s['inputs']['without_scenario_pd']['loans']);
    $mega = app(App\Services\MegaFarm\MegaFarmEclService::class);
    if ($mega->loans($p)->exists()) { $mega->run($p, $user, App\Services\Ebanker\LoanBookBuildService::BOOTSTRAP_LABEL); echo "{$p} mega-farm: run\n"; }
    printf("%s took %.1fs\n", $p, microtime(true) - $t);
}
