<?php
use App\Services\Reports\EclBuildUpService;
$loans = app(EclBuildUpService::class)->loans('2026-09');
$bad = [];
foreach ($loans as $l) { $x = EclBuildUpService::lineage($l); if ($x['ecl']!==null && !$x['ties']) { $bad[$l->loan_portfolio_id][] = [$l->contract_id, $x['ecl'], $x['ecl_recalc'], $l->ecl_discount_status]; } }
foreach ($bad as $p=>$b) { echo "p$p: ".count($b)." e.g. ".json_encode(array_slice($b,0,2))."\n"; }
$up = app(EclBuildUpService::class)->buildUp($loans,'2026-09'); echo json_encode(array_diff_key($up,['lineage'=>1,'overlay_lines'=>1])),"\n";
