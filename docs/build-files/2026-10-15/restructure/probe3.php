<?php
ini_set('memory_limit','2G');
$h = new \App\Services\Ebanker\InstalmentChartHistory(new \App\Services\Ebanker\LandingZoneReader());
$a='000104460000059';
foreach (['2024-06-30','2024-08-31','2024-10-31','2024-12-31','2025-02-28','2025-06-30','2025-12-31','2026-06-30','2026-09-30'] as $d) {
  $c = $h->at($a,$d);
  if (!$c) { echo "$d no chart\n"; continue; }
  $rec = $h->receiptsTo($a,$d);
  [$dpd,$old] = \App\Services\Ebanker\InstalmentChartHistory::daysPastDue($c['instalments'],$rec,$d);
  $missed = \App\Services\Ebanker\InstalmentChartHistory::missed($c['instalments'],$rec,$d);
  echo "$d rows {$c['rows']} collapsed ".json_encode($c['collapsed'])." unreadable ".json_encode($c['unreadable'])." due ".round(\App\Services\Ebanker\InstalmentChartHistory::dueTo($c['instalments'],$d))." receipts ".round($rec)." dpd $dpd ($old) missed $missed src {$c['source']}\n";
}
// how many copy-only accounts now have charts
$r = new ReflectionClass($h); $m=$r->getMethod('rows'); $m->setAccessible(true); $rows=$m->invoke($h);
$copyOnly = array_filter($rows, fn($x)=>isset($x[0]['copy']));
echo count($copyOnly)." copy-only accounts; open-ended: ".count(array_filter($copyOnly, fn($x)=>count(array_filter($x, fn($r)=>$r['replaced']===null))>0))."\n";
