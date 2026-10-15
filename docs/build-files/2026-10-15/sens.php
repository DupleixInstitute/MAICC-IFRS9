<?php
require __DIR__ . '/boot.php';
use Illuminate\Support\Facades\DB;
$svc = app(\App\Services\Scenario\ScenarioSetService::class);
foreach (DB::table('governed_scenario_sets')->where('status', 'APPROVED')->orderBy('reporting_period')->get() as $s) {
    $r = $svc->sensitivity((int) $s->id);
    echo "{$s->reporting_period} set {$s->id}: loans {$r['loans']}; weighted ECL " . number_format($r['weighted_ecl'], 0) . '; ' . collect($r['per_scenario'])->map(fn ($p, $n) => "{$n} " . number_format($p['ecl'], 0))->implode(', ') . "\n";
}
