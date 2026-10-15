<?php
require __DIR__ . '/boot.php';
use Illuminate\Support\Facades\DB;
$sets = app(\App\Services\Scenario\ScenarioSetService::class);
foreach (['2026-01','2026-02','2026-03','2026-04','2026-05','2026-06','2026-07','2026-08'] as $p) {
    $id = $sets->seedFirstSet($p, 1);
    if (DB::table('governed_scenario_sets')->where('id', $id)->value('status') === 'PROPOSED') {
        $sets->approve($id, null, \App\Services\Scenario\ScenarioSetService::BOOTSTRAP_LABEL);
    }
    $s = DB::table('governed_scenario_sets')->where('id', $id)->first();
    echo "$p set {$s->id} v{$s->version} {$s->status} | " . DB::table('governed_scenarios')->where('set_id', $id)->orderBy('order_position')->get()->map(fn($x) => "{$x->name} {$x->weight}")->implode(', ') . PHP_EOL;
}
