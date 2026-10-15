<?php
require __DIR__ . '/boot.php';
use Illuminate\Support\Facades\DB;
$svc = app(App\Services\Ebanker\LoanBookBuildService::class);
$rf = new ReflectionMethod($svc, 'rowsFor'); $rf->setAccessible(true);
$skip = ['created_at','updated_at','build_load_id','build_basis'];
$tot = []; $ex = [];
foreach ($svc->periods('2024-07','2026-08') as $p) {
  $rows = $rf->invoke($svc, $p, 'B');
  $stored = DB::table('loan_books')->where('reporting_period',$p)->get()->keyBy('contract_id');
  foreach ($rows as $r) {
    $s = $stored->get($r['contract_id']); if (!$s) continue;
    foreach ($r as $k => $v) {
      if (in_array($k,$skip)) continue;
      $sv = $s->$k ?? null;
      $same = (is_numeric($v) && is_numeric($sv)) ? abs((float)$v-(float)$sv) < 0.006 : ((string)$v === (string)$sv);
      if (!$same) { $tot[$k] = ($tot[$k] ?? 0) + 1; if (!isset($ex[$k])) $ex[$k] = "$p {$r['contract_id']} stored=" . var_export($sv,true) . " build=" . var_export($v,true); }
    }
  }
}
print_r($tot); print_r($ex);
