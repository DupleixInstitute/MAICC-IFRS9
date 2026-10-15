<?php
ini_set('memory_limit','3G'); set_time_limit(0);
$h = new \App\Services\Ebanker\InstalmentChartHistory(new \App\Services\Ebanker\LandingZoneReader());
$r = new ReflectionClass($h); $m=$r->getMethod('rows'); $m->setAccessible(true); $rows=$m->invoke($h);
$copyOnly = array_keys(array_filter($rows, fn($x)=>isset($x[0]['copy'])));
foreach (['2025-12','2026-08'] as $p) {
 $me = \Carbon\CarbonImmutable::parse($p.'-01')->endOfMonth()->toDateString();
 $lb = DB::table('loan_books')->where('reporting_period',$p)->whereIn('external_identity_id',$copyOnly)->where('carrying_amount','>',0.005)->get(['contract_id','external_identity_id','customer_name','carrying_amount','overdue_days','ifrs9stage_post_qualitative','build_flag']);
 echo "$p: copy-only loans with a balance: ".count($lb)."\n";
 foreach ($lb as $l) {
   $c = $h->at($l->external_identity_id, $me);
   if (!$c) { echo "  {$l->contract_id} no chart in force\n"; continue; }
   $rec = $h->receiptsTo($l->external_identity_id,$me);
   [$dpd] = \App\Services\Ebanker\InstalmentChartHistory::daysPastDue($c['instalments'],$rec,$me);
   $missed = \App\Services\Ebanker\InstalmentChartHistory::missed($c['instalments'],$rec,$me);
   $due = \App\Services\Ebanker\InstalmentChartHistory::dueTo($c['instalments'],$me); $deb=$h->debitsTo($l->external_identity_id,$me);
   echo sprintf("  %s %-28s bal %14s rep_dpd %4d stage %s | chart collapsed %s unread %s chartdpd %4d missed %2d dueVsDebits %s\n", $l->contract_id, substr($l->customer_name,0,28), number_format($l->carrying_amount), $l->overdue_days, $l->ifrs9stage_post_qualitative, $c['collapsed']?'Y':'N', $c['unreadable']?'Y':'N', $dpd, $missed, $due > $deb+0.5 ? 'EXCEEDS' : 'ok');
 }
}
