<?php
$z = app(\App\Services\Ebanker\LandingZoneReader::class);
$acc = '000104450000015';
$posts = $z->ledgerByAccount('2026-09-30', $acc)[$acc] ?? [];
$bal = 0; $by = [];
foreach ($posts as $p) { $bal += (float) str_replace(',', '', $p['payload']['TRANSAMT'] ?? 0); $by[substr($p['row_date'], 0, 7)] = round(-$bal, 2); }
foreach (['2025-04','2025-05','2025-06','2025-07','2025-08','2025-12','2026-09'] as $m) { $last = null; foreach ($by as $k => $v) if ($k <= $m) $last = $v; echo "$m ledger ", $last ?? 'n/a', PHP_EOL; }
$old = DB::table('ebanker_raw_rows')->where('query_id','ZF_03')->whereNull('superseded_at')->orderBy('row_date')->get(['row_date','payload']);
foreach ($old as $r) { $p = json_decode($r->payload, true); if ($r->row_date >= '2025-04-01' && $r->row_date <= '2025-09-30') echo 'ZF_03 ', $r->row_date, ' ', $p['PRINCIPAL_BALANCE'] ?? '?', PHP_EOL; }
