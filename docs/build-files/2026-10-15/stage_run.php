<?php
require __DIR__ . '/boot.php';
use Illuminate\Support\Facades\DB;
$mfBefore = DB::table('loan_books')->where('reporting_period','2025-11')->where('product_group','like','%mega%')->selectRaw('ifrs9stage_post_qualitative s, count(*) n')->groupBy('s')->pluck('n','s')->all();
$periods = app(App\Services\Ebanker\LoanBookBuildService::class)->periods('2024-07', '2026-08');
echo "periods: " . implode(',', $periods) . "\n";
$svc = app(App\Services\Eir\StagingService::class);
foreach ($periods as $p) {
  $c = $svc->stage($p, 1);
  echo "$p rows {$c['rows']} S1 {$c['stage1']} S2 {$c['stage2']} S3 {$c['stage3']} dpd {$c['by_dpd']} inst {$c['by_instalments']} bucket {$c['by_bucket']} cure {$c['held_by_cure']}\n";
}
$mfAfter = DB::table('loan_books')->where('reporting_period','2025-11')->where('product_group','like','%mega%')->selectRaw('ifrs9stage_post_qualitative s, count(*) n')->groupBy('s')->pluck('n','s')->all();
echo "MegaFarm 2025-11 stages before " . json_encode($mfBefore) . " after " . json_encode($mfAfter) . "\n";
