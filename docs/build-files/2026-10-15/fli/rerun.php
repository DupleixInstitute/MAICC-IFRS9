<?php
// Re-run the forward-looking chain on maiic_ifrs9_demo for Jan-Aug 2026 after the as-at fix.
chdir('C:/xampp/htdocs/MAICC-IFRS9');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

if (DB::connection()->getDatabaseName() !== 'maiic_ifrs9_demo') {
    fwrite(STDERR, "refusing: connected to " . DB::connection()->getDatabaseName() . "\n");
    exit(1);
}
$months = $argv[1] ?? '2026-01,2026-02,2026-03,2026-04,2026-05,2026-06,2026-07,2026-08';
$route = app(App\Services\Fli\FliRouteService::class);
$sets = app(App\Services\Scenario\ScenarioSetService::class);
$label = 'Demo, not a MAIIC approval';
$book = ['NPL_RATIO', 'STAGE3_SHARE', 'DEFAULT_RATE_12M'];
$out = [];
$eclSql = function (string $period, string $pdCol) {
    $pd12 = "CASE WHEN {$pdCol} > 1 THEN 1 WHEN {$pdCol} < 0 THEN 0 ELSE {$pdCol} END";
    $m = 'CASE WHEN remaining_tenor IS NULL OR remaining_tenor < 1 THEN 12 ELSE remaining_tenor END';
    $st = 'COALESCE(ifrs9stage_post_qualitative, calculated_ifrs9_stage, ifrs9stage_pre_qualitative)';
    $spd = "CASE WHEN {$st} IN ('3',3) THEN 1 WHEN {$st} IN ('2',2) THEN 1 - POWER(1 - ({$pd12}), ({$m}) / 12.0) ELSE 1 - POWER(1 - ({$pd12}), (CASE WHEN ({$m}) < 12 THEN ({$m}) ELSE 12 END) / 12.0) END";

    return (float) DB::table('loan_books')->where('reporting_period', $period)->where('loan_portfolio_id', 1)
        ->selectRaw("sum(IFNULL({$spd},0) * IFNULL(collection_lgd,0) * (IFNULL(carrying_amount,0) + IFNULL(commitments,0) * IFNULL(facility_utilisation_rate,1))) e")->value('e');
};
foreach (explode(',', $months) as $period) {
    $ym = str_replace('-', '', $period);
    $row = ['period' => $period];
    $before = (int) DB::table('fli_fits')->max('id');
    $code = Artisan::call('fli:correlate', ['period' => $period, '--no-refresh' => true, '--top' => 0]);
    $row['correlate_exit'] = $code;
    $row['correlate_out'] = trim(preg_replace('/\s+/', ' ', Artisan::output()));
    $best = DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')
        ->where('f.id', '>', $before)->where('f.reporting_period', $ym)->where('f.verdict', 'applied')->whereIn('r.proxy_code', $book)
        ->orderByDesc('f.r_squared')->orderByDesc('f.n_obs')->orderBy('f.id')
        ->first(['f.id', 'r.statistic_code', 'r.proxy_code', 'r.lag_months', 'f.r_squared', 'f.n_obs', 'f.slope', 'f.intercept', 'f.p_value']);
    $row['applied_book_fits'] = DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')
        ->where('f.id', '>', $before)->where('f.reporting_period', $ym)->where('f.verdict', 'applied')->whereIn('r.proxy_code', $book)->count();
    $row['fits_this_run'] = DB::table('fli_fits')->where('id', '>', $before)->count();
    if ($best === null) {
        $row['fit'] = null;
        $row['note'] = 'no applied fit on a book-level proxy as at ' . $period . ': fail closed, nothing proposed';
        $out[] = $row;
        echo json_encode($row) . "\n";
        continue;
    }
    $row['fit'] = (array) $best;
    $route->proposeFit((int) $best->id, 1, 'Re-run after the as-at fix (no look-ahead, lag applied): best applied book-level fit by R2 then n');
    $route->approveFit((int) $best->id, 2, $label);
    try {
        $r = $route->apply($period, 1);
        $row['route'] = ['fit' => $r['fit'], 'set' => $r['set'], 'base_driver' => $r['base_driver'], 'scenarios' => $r['scenarios'], 'adjusted' => $r['adjusted'], 'held' => $r['held'], 'note' => $r['note']];
    } catch (Throwable $e) {
        $row['route_error'] = $e->getMessage();
        $out[] = $row;
        echo json_encode($row) . "\n";
        continue;
    }
    $code = Artisan::call('ifrs9:recalculate-ecl', ['period' => $period, '--level' => 'portfolio', '--portfolio' => 1, '--pd' => 'pd_post_fli']);
    $row['ecl_exit'] = $code;
    $row['ecl_booked'] = (float) DB::table('loan_books')->where('reporting_period', $period)->where('loan_portfolio_id', 1)->sum('ecl_value');
    $row['ecl_prefli'] = $eclSql($period, 'pd_prefli');
    $row['ecl_postfli_formula'] = $eclSql($period, 'pd_post_fli');
    $setId = (int) $r['set'];
    $sens = $sets->sensitivity($setId);
    $row['sensitivity'] = ['set' => $setId, 'loans' => $sens['loans'], 'weighted_ecl' => $sens['weighted_ecl'], 'per_scenario' => array_map(fn ($p) => $p['ecl'], $sens['per_scenario']), 'basis' => $sens['basis']];
    $out[] = $row;
    echo json_encode($row) . "\n";
}
file_put_contents(__DIR__ . '/rerun_result.json', json_encode($out, JSON_PRETTY_PRINT));
