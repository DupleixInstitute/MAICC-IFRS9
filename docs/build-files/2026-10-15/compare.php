<?php
// Compare HEAD's method B (OldBuild) with the working tree's, per account-month, on the clone named by DB_DATABASE.
require 'C:/xampp/htdocs/MAICC-IFRS9/vendor/autoload.php';
$app = require 'C:/xampp/htdocs/MAICC-IFRS9/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require __DIR__ . '/OldBuild.php';

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Eir\GovernanceService;
use Illuminate\Support\Facades\DB;

if (DB::connection()->getDatabaseName() !== 'maiic_ifrs9_mfgap') {
    fwrite(STDERR, "refusing: not the clone\n");
    exit(1);
}
$from = $argv[1] ?? '2024-07';
$to = $argv[2] ?? '2026-09';
$new = new App\Services\Ebanker\LoanBookBuildService(new LandingZoneReader(), new GovernanceService());
$old = new OldBuild\LoanBookBuildService(new LandingZoneReader(), new GovernanceService());
$last = $new->monthEnd($to);
foreach ([$new, $old] as $svc) {
    (function () use ($last) { $this->ledgerTo = $last; $this->ledgerCache = null; $this->masterCache = []; })->call($svc);
}
$mfGls = App\Services\MegaFarm\MegaFarmEclService::GLS;
$tot = [];
$resid = fopen(__DIR__ . '/mf_residual.csv', 'w');
fputcsv($resid, ['period', 'account', 'stored', 'ledger_with_gov', 'difference', 'old_ledger', 'gov_interest_to_date', 'flag']);
$split = fopen(__DIR__ . '/mf_split.csv', 'w');
fputcsv($split, ['period', 'account', 'interest_own_gl', 'interest_government_portion', 'interest_total', 'month_own', 'month_gov', 'month_total', 'gov_gls', 'contra_gls']);
foreach ($new->periods($from, $to) as $period) {
    $t0 = microtime(true);
    $n = collect($new->rowsFor($period, 'B'))->keyBy('contract_id');
    $o = collect($old->rowsFor($period, 'B'))->keyBy('contract_id');
    $s = ['period' => $period, 'rows_new' => $n->count(), 'rows_old' => $o->count(), 'mf' => 0, 'mf_run' => 0, 'mf_tie' => 0, 'mf_resid' => 0, 'mf_norun' => 0,
        'mf_carry_changed' => 0, 'mf_carry_delta' => 0.0, 'mf_other_col_changed' => 0, 'other_changed' => 0, 'only_one_side' => 0, 'old_tie' => 0, 'gov' => 0.0, 'own' => 0.0];
    foreach ($n as $cid => $r) {
        $q = $o->get($cid);
        if ($q === null) { $s['only_one_side']++; continue; }
        $isMf = in_array((string) $r['product_code'], $mfGls, true);
        $cols = array_diff(array_keys($r), ['created_at', 'updated_at', 'build_basis', 'build_flag']);
        $changed = array_values(array_filter($cols, fn ($c) => (string) $r[$c] !== (string) $q[$c]));
        if (! $isMf) {
            if ($changed !== [] || $r['build_basis'] !== $q['build_basis'] || $r['build_flag'] !== $q['build_flag']) { $s['other_changed']++; }
            continue;
        }
        $s['mf']++;
        $b = json_decode($r['build_basis'], true);
        $ob = json_decode($q['build_basis'], true);
        $m = $b['mega_farm'] ?? [];
        $s['gov'] += $m['interest_government_portion'] ?? 0; $s['own'] += $m['interest_own_gl'] ?? 0;
        fputcsv($split, [$period, $r['external_identity_id'], $m['interest_own_gl'] ?? '', $m['interest_government_portion'] ?? '', $m['interest_total'] ?? '', $m['month']['interest_own_gl'] ?? '', $m['month']['interest_government_portion'] ?? '', $m['month']['interest_total'] ?? '', implode('|', $m['government_portion_gls'] ?? []), implode('|', $m['contra_gls'] ?? [])]);
        if ($r['stored_carrying_amount'] === null) {
            $s['mf_norun']++;
        } else {
            $s['mf_run']++;
            if (abs((float) $ob['ledger_carrying'] - (float) $r['stored_carrying_amount']) <= 0.005) { $s['old_tie']++; }
            if (abs((float) $b['ledger_carrying'] - (float) $r['stored_carrying_amount']) <= 0.005) {
                $s['mf_tie']++;
            } else {
                $s['mf_resid']++;
                fputcsv($resid, [$period, $r['external_identity_id'], $r['stored_carrying_amount'], $b['ledger_carrying'], round($b['ledger_carrying'] - $r['stored_carrying_amount'], 2), $ob['ledger_carrying'], $m['interest_government_portion'] ?? '', $r['build_flag']]);
            }
        }
        if (abs((float) $r['carrying_amount'] - (float) $q['carrying_amount']) > 0.005) { $s['mf_carry_changed']++; $s['mf_carry_delta'] += (float) $r['carrying_amount'] - (float) $q['carrying_amount']; }
        if (array_diff($changed, ['carrying_amount']) !== []) { $s['mf_other_col_changed']++; $s['other_cols'][implode(',', array_diff($changed, ['carrying_amount']))] = ($s['other_cols'][implode(',', array_diff($changed, ['carrying_amount']))] ?? 0) + 1; }
    }
    $s['only_one_side'] += $o->diffKeys($n)->count();
    $s['secs'] = round(microtime(true) - $t0, 1);
    $s['gov'] = round($s['gov'], 2); $s['own'] = round($s['own'], 2); $s['mf_carry_delta'] = round($s['mf_carry_delta'], 2);
    echo json_encode($s), PHP_EOL;
    foreach ($s as $k => $v) { if (is_int($v) || is_float($v)) { $tot[$k] = ($tot[$k] ?? 0) + $v; } }
}
echo 'TOTAL ', json_encode($tot), PHP_EOL;
