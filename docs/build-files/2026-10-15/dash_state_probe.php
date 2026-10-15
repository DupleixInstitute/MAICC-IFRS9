<?php
DB::listen(function ($q) { if (preg_match('/^\s*(insert|update|delete|replace|alter|drop|create|truncate)/i', $q->sql)) { echo "WRITE QUERY: {$q->sql}\n"; } });
$c = app(\App\Http\Controllers\DashboardController::class);
$m = new ReflectionMethod($c, 'dashboardState');
$m->setAccessible(true);
foreach ([[], ['portfolio_id' => 4], ['period' => '2026-07'], ['trend_from' => 'all']] as $q) {
    $t = microtime(true);
    $s = $m->invoke($c, Request::create('/dashboard', 'GET', $q));
    echo json_encode($q) . ' time ' . round(microtime(true) - $t, 2) . "s\n";
    $mix = $s['eclMixTrend'];
    echo '  mix ' . $mix['dimension'] . ' periods ' . count($mix['periods']) . ' series ' . implode(' | ', array_column($mix['series'], 'label')) . ' ties ' . json_encode($mix['ties']) . ' differs ' . json_encode($mix['differs']) . "\n";
    $sc = $s['scenarioEcl'];
    echo '  scen ' . json_encode($sc['source']) . ' note ' . json_encode($sc['note']) . ' ties ' . json_encode($sc['ties']) . ' set ' . json_encode($sc['set']) . "\n";
    foreach ($sc['series'] as $x) { echo '    ' . $x['name'] . ' ' . json_encode($x['values']) . "\n"; }
    echo '    loans ' . json_encode($sc['loans']) . "\n";
    foreach ($s['defaultRates']['rows'] as $r) { echo '  dr ' . $r['period'] . ' m ' . json_encode($r['monthly']['book']['rate'] ?? null) . ' a ' . json_encode($r['annual']['book']['rate'] ?? null) . ' pm ' . json_encode($r['monthly']['portfolio']['rate'] ?? null) . ' pa ' . json_encode($r['annual']['portfolio']['rate'] ?? null) . ' months ' . json_encode($r['annual']['book']['months'] ?? null) . "\n"; }
    echo '  notes ' . json_encode($s['defaultRates']['notes']) . "\n";
    echo '  keys ' . implode(',', array_keys($s)) . "\n";
}
