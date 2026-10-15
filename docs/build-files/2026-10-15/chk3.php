<?php
$svc = app(\App\Services\Eir\ScheduleWorkflowService::class);
foreach (['104430000068','104460000112'] as $c) {
  DB::beginTransaction();
  try {
    $k = \App\Models\ContractEir::where('contract_id',$c)->first();
    $k->schedule_approval_status = 'DRAFT'; $k->save();
    $r = $svc->generate($k->fresh());
    $rows = DB::table('contract_cashflow_schedule')->where('contract_id',$c)->where('schedule_version',1)->orderBy('due_date')->get(['due_date','principal_due','interest_due']);
    echo $c, ' rows=', $rows->count(), ' first=', json_encode($rows->first()), ' last=', json_encode($rows->last()), ' sumP=', $rows->sum('principal_due'), ' cmp=', $r['comparison']['status'] ?? '', ' basis=', json_encode($r['shape']['basis_sources']), "\n";
    $k2 = \App\Models\ContractEir::where('contract_id',$c)->first(); $k2->schedule_approval_status='APPROVED'; $k2->save();
    try { $in = app(\App\Services\Eir\EirContractInputService::class)->assemble($c); $res = app(\App\Services\Eir\CalculateEirService::class)->calculateDated($in['initial_net_investment'],$in['cash_flows'],$in['payments_per_year'],$in['metadata']['origination_date'],$in['metadata']['day_count_basis']); echo "  EIR ", $res['eir_effective_annual'], " from ", $in['metadata']['origination_date'], " net ", $in['initial_net_investment'], "\n"; } catch (Throwable $e) { echo "  solve ERR ", $e->getMessage(), "\n"; }
  } catch (Throwable $e) { echo $c, ' ERR ', $e->getMessage(), "\n"; }
  DB::rollBack();
}
