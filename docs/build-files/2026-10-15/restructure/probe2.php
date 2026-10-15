<?php
ini_set('memory_limit','2G'); set_time_limit(0);
$t=microtime(true);
$r = app(\App\Services\Eir\RateResetService::class)->detect(null);
echo json_encode($r), "\n", round(microtime(true)-$t,1), "s\n";
foreach (DB::table('rate_reset_events')->selectRaw('event_type, COUNT(*) n, COUNT(DISTINCT contract_id) c, MIN(reporting_period) a, MAX(reporting_period) b')->groupBy('event_type')->get() as $x) echo json_encode($x),"\n";
foreach (DB::table('rate_reset_events')->where('event_type','SPREAD_CHANGE')->orderBy('contract_id')->orderBy('reporting_period')->limit(40)->get() as $x) echo "$x->contract_id $x->reporting_period $x->reset_date rate $x->old_rate>$x->new_rate plr $x->old_reference_rate>$x->new_reference_rate spread $x->old_spread>$x->new_spread\n";
echo DB::table('restructure_events')->where('source','RATE_CHANGE')->count(), " fixed-rate candidates\n";
foreach (DB::table('restructure_events')->where('source','RATE_CHANGE')->orderBy('contract_id')->limit(30)->get() as $x) echo "$x->contract_id $x->customer_name $x->event_date $x->old_rate>$x->new_rate ".($x->incomplete_reason?'INC':'')."\n";
