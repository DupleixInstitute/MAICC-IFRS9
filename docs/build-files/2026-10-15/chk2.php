<?php
$svc = app(\App\Services\Eir\ScheduleWorkflowService::class);
$m = new ReflectionMethod($svc, 'drawdownTerms'); $m->setAccessible(true);
foreach (['104430000068','104460000112'] as $c) {
  $k = \App\Models\ContractEir::where('contract_id',$c)->first();
  try { $r = $m->invoke($svc, $k, ($k->moratorium_from ?: $k->interest_start_date ?: $k->origination_date)->copy()); echo $c, ' ', json_encode($r), "\n"; } catch (Throwable $e) { echo $c, ' ERR ', $e->getMessage(), "\n"; }
  echo json_encode(DB::table('audit_logs')->where('action','EIR Schedule Generated')->where('new_values','like',"%$c%")->orderByDesc('id')->limit(1)->get(['created_at','new_values'])), "\n";
}
