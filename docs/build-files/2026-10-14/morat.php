<?php
$r = app(\App\Services\Ebanker\ContractInputsBuildService::class)->contractMaster();
echo 'contract master: ', json_encode(array_intersect_key($r['result'], array_flip(['created','updated','unchanged']))), PHP_EOL;
echo 'moratorium types now: ', json_encode(DB::table('contract_eir')->selectRaw('moratorium_type t, count(*) n')->groupBy('moratorium_type')->get()->map(fn($x)=>[$x->t ?? 'null' => $x->n])->collapse()), PHP_EOL;
$svc = app(\App\Services\Eir\ScheduleWorkflowService::class); $reasons = [];
\App\Models\ContractEir::orderBy('contract_id')->each(function ($c) use ($svc, &$reasons) {
    if ($c->schedule_approval_status === 'APPROVED') { $reasons['already approved'] = ($reasons['already approved'] ?? 0) + 1; return; }
    try { $svc->generate($c); $reasons['generated now'] = ($reasons['generated now'] ?? 0) + 1; }
    catch (\Throwable $e) { $k = substr(preg_replace('/\d[\d,\.]*/', 'N', $e->getMessage()), 0, 90); $reasons[$k] = ($reasons[$k] ?? 0) + 1; }
});
arsort($reasons); print_r($reasons);
