<?php
// ILLUSTRATION ONLY on maiic_ifrs9_eircheck, inside one transaction that is rolled back: the arrangement
// and legal fee lines from the source of record (GL_04, the charges table where GL_04 holds none, the
// take-on workbook) are treated as reviewed and integral, every approved schedule is solved and locked,
// and the revenue is run for every period. Nothing is kept; no fee is reviewed in any database.
use App\Models\ContractEir;
use App\Services\Eir\EirCalculationService;
use App\Services\Eir\EirRevenueService;
use Illuminate\Support\Facades\DB;

require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_time_limit(0);
if (DB::connection()->getDatabaseName() !== 'maiic_ifrs9_eircheck') {
    fwrite(STDERR, "refusing\n");
    exit(1);
}
$out = [];
DB::beginTransaction();
try {
    $live = DB::table('contract_fees')->whereNull('superseded_by_source');
    (clone $live)->whereIn('fee_type', ['arrangement', 'legal'])->update(['classification_status' => 'REVIEWED', 'integral' => 1, 'cashflow_direction' => 'RECEIVED']);
    (clone $live)->whereNotIn('fee_type', ['arrangement', 'legal'])->update(['classification_status' => 'REJECTED', 'integral' => 0]);
    $calc = app(EirCalculationService::class);
    $blocked = [];
    foreach (ContractEir::where('schedule_approval_status', 'APPROVED')->whereNull('locked_at')->pluck('contract_id') as $id) {
        $r = $calc->calculate($id, 1);
        if ($r['status'] === 'CALCULATED') {
            $calc->lock($id, 1, true);
        } else {
            $blocked[$id] = array_keys(array_column(app(App\Services\Eir\EirReadinessService::class)->assess($id)['issues'], 'message', 'code'));
        }
    }
    $out['locked'] = ContractEir::whereNotNull('locked_at')->count();
    $out['locked_takeon'] = ContractEir::whereNotNull('locked_at')->where('schedule_source', 'TAKEON_WORKBOOK')->count();
    $out['blocked'] = $blocked;
    DB::table('eir_amortisation')->delete();
    $svc = new EirRevenueService();
    $periods = app(App\Services\Ebanker\LoanBookBuildService::class)->periods('2024-07', '2026-09');
    foreach (ContractEir::whereNotNull('locked_at')->pluck('contract_id') as $id) {
        foreach ($periods as $p) {
            $svc->run($id, $p, false, 1);
        }
    }
    $ac = function (string $p) {
        $rows = DB::select("select a.contract_id, a.closing_gross g, lb.carrying_amount l from eir_amortisation a left join loan_books lb on lb.contract_id=a.contract_id
            and replace(substr(lb.reporting_period,1,7),'-','')=? where a.reporting_period=?", [str_replace('-', '', $p), $p]);
        $g = array_sum(array_map(fn ($r) => (float) $r->g, $rows)); $l = array_sum(array_map(fn ($r) => (float) $r->l, $rows));
        usort($rows, fn ($a, $b) => abs($b->g - $b->l) <=> abs($a->g - $a->l));

        return ['loans' => count($rows), 'amortised_cost' => round($g / 1e6, 1), 'ledger' => round($l / 1e6, 1), 'difference' => round(($g - $l) / 1e6, 1),
            'largest' => array_map(fn ($r) => [$r->contract_id, round($r->g / 1e6, 1), round($r->l / 1e6, 1)], array_slice($rows, 0, 6))];
    };
    $rev = function (string $from, string $to) {
        $rows = DB::select('select contract_id, sum(interest_accrued) i, sum(unwind_amount) u from eir_amortisation where reporting_period between ? and ? group by contract_id', [$from, $to]);
        $ids = array_map(fn ($r) => $r->contract_id, $rows);
        $posted = $ids === [] ? 0 : (float) DB::table('gl_interest_postings')->whereIn('contract_id', $ids)->whereRaw('substr(reporting_period,1,7) between ? and ?', [$from, $to])->sum('interest_income_posted');
        $i = array_sum(array_map(fn ($r) => (float) $r->i, $rows)); $u = array_sum(array_map(fn ($r) => (float) $r->u, $rows));

        return ['loans' => count($rows), 'eir_interest' => round($i / 1e6, 1), 'unwind' => round($u / 1e6, 1), 'posted' => round($posted / 1e6, 1), 'shift' => round(($i + $u - $posted) / 1e6, 1)];
    };
    $out['ac_2025_12'] = $ac('2025-12');
    $out['ac_2026_09'] = $ac('2026-09');
    $out['rev_2025'] = $rev('2025-01', '2025-12');
    $out['rev_2026'] = $rev('2026-01', '2026-09');
    $out['takeon_eirs'] = DB::table('contract_eir')->where('schedule_source', 'TAKEON_WORKBOOK')->whereNotNull('locked_at')
        ->selectRaw('contract_id, round(eir_nominal_annual*100,2) e')->pluck('e', 'contract_id')->all();
} finally {
    DB::rollBack();
}
file_put_contents(__DIR__ . '/illustrate2.json', json_encode($out, JSON_PRETTY_PRINT));
echo json_encode(array_diff_key($out, ['blocked' => 1, 'takeon_eirs' => 1]), JSON_PRETTY_PRINT), "\n";
echo 'blocked: ' . count($out['blocked']) . ' ' . json_encode(array_count_values(array_map(fn ($v) => implode('+', $v), $out['blocked']))) . "\n";
echo 'locked after rollback: ' . ContractEir::whereNotNull('locked_at')->count() . "\n";
