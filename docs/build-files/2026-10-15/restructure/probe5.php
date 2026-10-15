<?php
ini_set('memory_limit','3G'); set_time_limit(0);
DB::beginTransaction();
try {
  $id = 213; $u = [1, 999];
  $reg = app(\App\Services\Eir\RestructureRegisterService::class);
  $reg->propose($id, ['classification'=>'MODIFICATION','reason'=>'ILLUSTRATION ONLY on the clone, rolled back','fair_value'=>557385103.90,'fair_value_basis'=>'illustration: the carrying amount'], $u[0]);
  $reg->approve($id, $u[1], 'illustration');
  $m = DB::table('contract_modifications')->where('restructure_event_id',$id)->first();
  echo "modification row: v{$m->old_schedule_version}->v{$m->new_schedule_version} period {$m->reporting_period} status {$m->status}\n";
  $svc = new \App\Services\Eir\EirRevenueService();
  foreach (['2026-03','2026-04','2026-05'] as $p) {
    $r = $svc->run('104430000085', $p, true, $u[0], 'illustration of the modification hook');
    echo $p.' '.json_encode($r)."\n";
  }
  $m = DB::table('contract_modifications')->where('restructure_event_id',$id)->first();
  echo json_encode(collect((array)$m)->only(['status','treatment','carrying_amount_before','pv_new_flows_at_original_eir','eir_used','change_pct','threshold_pct','gain_loss','carrying_amount_after','revised_eir','blocked_reason','rate_before','rate_after']), JSON_PRETTY_PRINT)."\n";
  foreach (DB::table('eir_amortisation')->where('contract_id','104430000085')->whereBetween('reporting_period',['2026-03','2026-05'])->get() as $a) echo "$a->reporting_period open $a->opening_gross int $a->interest_accrued cash $a->cash_received adj $a->modification_adjustment kind $a->adjustment_kind close $a->closing_gross eir $a->eir_annual\n";
} finally { DB::rollBack(); echo "rolled back\n"; }
