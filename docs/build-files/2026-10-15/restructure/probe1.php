<?php
ini_set('memory_limit','2G'); set_time_limit(0);
$svc = app(\App\Services\Eir\RestructureCandidateService::class);
$t=microtime(true);
$r = $svc->detect(null);
echo json_encode($r, JSON_PRETTY_PRINT), "\n", round(microtime(true)-$t,1), "s\n";
foreach (DB::table('restructure_events')->orderBy('contract_id')->orderBy('event_date')->get() as $e) {
  echo implode(' | ', [$e->contract_id, substr($e->customer_name??'',0,25), $e->event_date, $e->source, $e->suggested_class, $e->old_maturity.'>'.$e->new_maturity, $e->old_instalments.'>'.$e->new_instalments, $e->capitalised_amount, $e->incomplete_reason ? 'INCOMPLETE' : '', substr($e->suggestion_reason,0,90)]), "\n";
}
