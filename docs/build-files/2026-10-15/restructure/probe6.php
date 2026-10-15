<?php
$svc = app(\App\Services\Eir\EirRevenueService::class);
foreach (\App\Models\ContractEir::where('rate_type','FLOATING')->whereNotNull('locked_at')->whereNotNull('eir_effective_annual')->get() as $c) {
  $l = DB::table('loan_books')->where('contract_id',$c->contract_id)->where('reporting_period','2026-09')->orderByDesc('id')->first();
  if (!$l || (float)$l->carrying_amount <= 0) continue;
  $cur = $svc->currentFloatingEir($c, $l, '2026-09');
  echo sprintf("%s stage %s bal %s locked %.4f current %s rate %s\n", $c->contract_id, $l->ifrs9stage_post_qualitative, number_format($l->carrying_amount), $c->eir_effective_annual, $cur===null?'NULL':sprintf('%.4f',$cur), $l->interest_rate);
}
