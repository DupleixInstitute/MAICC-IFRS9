<?php
// Rerun of the build, staging, PD, LGD, FLI route, ECL and Mega Farm steps in maiic_ifrs9_pdcheck,
// in the bootstrap's order (Bootstrap::stepBuild 5 and stepEngines 6). Run with DB_DATABASE=maiic_ifrs9_pdcheck.
chdir('C:/xampp/htdocs/MAICC-IFRS9');
require 'C:/xampp/htdocs/MAICC-IFRS9/vendor/autoload.php';
$app = require 'C:/xampp/htdocs/MAICC-IFRS9/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_time_limit(0);
ini_set('memory_limit', '4096M');

use App\Services\Ebanker\LoanBookBuildService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$db = DB::connection()->getDatabaseName();
if ($db !== 'maiic_ifrs9_pdcheck') {
    fwrite(STDERR, "refusing to run on {$db}\n");
    exit(1);
}
$steps = $argv[1] ?? 'build,stage,segment,engines';
$from = $argv[2] ?? '2024-07';
$to = $argv[3] ?? '2026-09';
$user = 1;
$t0 = microtime(true);
$build = app(LoanBookBuildService::class);
$periods = $build->periods($from, $to);

if (str_contains($steps, 'build')) {
    $r = $build->build($from, $to, null, null, null, LoanBookBuildService::BOOTSTRAP_LABEL, false, true);
    echo 'build: ' . json_encode(array_intersect_key($r, array_flip(['status', 'build_id']))) . "\n";
    foreach (DB::table('loan_books')->where('build_flag', 'like', '%instalment chart%')->orderBy('contract_id')->orderBy('reporting_period')->get(['contract_id', 'reporting_period', 'overdue_days', 'build_flag']) as $row) {
        echo "  flag {$row->reporting_period} {$row->contract_id} dpd {$row->overdue_days}: " . substr($row->build_flag, 0, 170) . "\n";
    }
    printf("  %.0fs\n", microtime(true) - $t0);
}
if (str_contains($steps, 'stage')) {
    $staging = app(App\Services\Eir\StagingService::class);
    foreach ($periods as $p) {
        $c = $staging->stage($p, $user);
        echo "stage {$p}: " . json_encode(array_intersect_key($c, array_flip(['stage1', 'stage2', 'stage3', 'by_instalments', 'held_by_cure', 'instalments_no_chart', 'instalments_chart_contradicts_ledger']))) . "\n";
    }
    printf("  %.0fs\n", microtime(true) - $t0);
}
if (str_contains($steps, 'segment')) {
    Artisan::call('ifrs9:segment-portfolios');
    echo 'segment-portfolios: ' . trim(preg_replace('/\s+/', ' ', substr(Artisan::output(), -300))) . "\n";
}
if (str_contains($steps, 'engines')) {
    $mega = app(App\Services\MegaFarm\MegaFarmEclService::class);
    foreach ($periods as $p) {
        try {
            $pd = app(App\Services\Pd\PdSegmentationService::class)->run($p, null, 12, $user, 'bootstrap');
            $lgd = app(App\Services\Lgd\LgdSegmentationService::class)->run($p, null, 12, $user);
            app(App\Services\Fli\FliRouteService::class)->apply($p, $user);
            $code = Artisan::call('ifrs9:segment-ecl', ['from' => $p, '--pd' => 'pd_post_fli', '--discounting' => 'discounted', '--prune-stale' => true]);
            set_time_limit(0);
            if ($code !== 0) {
                throw new RuntimeException(trim(preg_replace('/\s+/', ' ', substr(Artisan::output(), 0, 200))));
            }
            echo "engines {$p}: PD {$pd['window']}, LGD {$lgd['window']}; ECL ok\n";
        } catch (Throwable $e) {
            echo "engines {$p}: " . substr($e->getMessage(), 0, 240) . "\n";
        }
        try {
            if ($mega->loans($p)->exists()) {
                $mf = $mega->run($p, $user, LoanBookBuildService::BOOTSTRAP_LABEL);
                echo "  mega farm {$p}: " . ($mf['declined'] ? 'declined: ' . substr($mf['declined'], 0, 160) : 'provided') . "\n";
            }
        } catch (Throwable $e) {
            echo "  mega farm {$p}: " . substr($e->getMessage(), 0, 160) . "\n";
        }
    }
    printf("  %.0fs\n", microtime(true) - $t0);
}
