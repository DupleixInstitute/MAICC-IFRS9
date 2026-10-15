<?php
$r = DB::table('ebanker_raw_rows')->where('query_id','MF_03')->whereNull('superseded_at')->first();
echo 'MF_03 sample account=', $r->account ?? 'null', ' payload=', substr($r->payload, 0, 400), PHP_EOL;
$r5 = DB::table('ebanker_raw_rows')->where('query_id','MF_05')->whereNull('superseded_at')->first();
echo 'MF_05 sample account=', $r5->account ?? 'null', ' payload=', substr($r5->payload, 0, 300), PHP_EOL;
$z = app(\App\Services\Ebanker\LandingZoneReader::class);
$t = microtime(true); $l = $z->ledgerByAccount('2025-01-31'); echo 'accounts in ledger ', count($l), ' in ', round(microtime(true)-$t,1), 's', PHP_EOL;
echo 'demo-book rows 2025-01: ', DB::table('loan_books')->where('reporting_period','2025-01')->count(), PHP_EOL;
