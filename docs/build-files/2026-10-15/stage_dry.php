<?php
require __DIR__ . '/boot.php';
use Illuminate\Support\Facades\DB;
$svc = app(App\Services\Eir\StagingService::class);
foreach (['2025-12','2026-01','2026-02','2026-03','2026-04','2026-05','2026-06','2026-07','2026-08'] as $p) {
  $c = $svc->stage($p, 1, true);
  $stored = DB::table('loan_books')->where('reporting_period',$p)->selectRaw("sum(ifrs9stage_post_qualitative='1') s1, sum(ifrs9stage_post_qualitative='2') s2, sum(ifrs9stage_post_qualitative='3') s3, sum(calculated_ifrs9_stage<>ifrs9stage_post_qualitative) diffcalc")->first();
  echo "$p dry ".json_encode([$c['stage1'],$c['stage2'],$c['stage3'],'cure'=>$c['held_by_cure'],'inst'=>$c['by_instalments']])." stored ".json_encode([$stored->s1,$stored->s2,$stored->s3,'calc<>post'=>$stored->diffcalc])."\n";
}
