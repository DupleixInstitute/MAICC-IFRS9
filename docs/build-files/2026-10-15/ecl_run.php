<?php
require __DIR__ . '/boot.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
$label = App\Services\Ebanker\LoanBookBuildService::BOOTSTRAP_LABEL;
echo "label: $label\n";
$portfolio = (int) DB::table('loan_portfolios')->orderBy('id')->value('id');
foreach (['2026-01','2026-02','2026-03','2026-04','2026-05','2026-06','2026-07','2026-08'] as $p) {
  try {
    $pd = app(App\Services\Pd\PdEngineService::class)->run($p, $portfolio, 12, 'bootstrap', 1);
    echo "$p PD matrix {$pd['matrix_id']} window {$pd['window']} transitions {$pd['transitioned']} pds " . json_encode($pd['pds']) . " updated {$pd['updated']}\n";
    $missing = DB::table('loan_books')->where('reporting_period',$p)->where('loan_portfolio_id',$portfolio)->whereNull('pd_prefli')->count();
    if ($missing > 0) { throw new RuntimeException("$missing loans without a PD after the PD engine"); }
    $lgd = app(App\Services\Lgd\LgdEngineService::class)->run($p, $portfolio, 12, 1, $label);
    echo "$p LGD {$lgd['lgd_id']} window {$lgd['window']} cohort {$lgd['cohort']} start " . number_format($lgd['start_balance'],0) . " cure {$lgd['cure_rate']} recovery {$lgd['recovery_rate']} LGD {$lgd['lgd']} updated {$lgd['updated']}\n";
    $code = Artisan::call('ifrs9:recalculate-ecl', ['period' => $p, '--level' => 'portfolio', '--portfolio' => $portfolio, '--pd' => 'pd_prefli']);
    echo "$p ECL exit $code: " . trim(preg_replace('/\s+/', ' ', Artisan::output())) . "\n";
  } catch (Throwable $e) {
    echo "$p FAILED: " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
  }
}
