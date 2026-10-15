<?php
// ILLUSTRATION ONLY on maiic_ifrs9_eircheck: for every contract with an approved schedule that is
// not locked, solve the EIR (a) with no fee and (b) with the arrangement and legal fee lines from the
// source of record treated as integral, each inside a transaction that is rolled back, so nothing is
// kept and no fee is reviewed. Writes illustrate.csv beside this file.
use Illuminate\Support\Facades\DB;

require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_time_limit(0);
if (DB::connection()->getDatabaseName() !== 'maiic_ifrs9_eircheck') {
    fwrite(STDERR, "refusing\n");
    exit(1);
}
$calc = app(App\Services\Eir\EirCalculationService::class);
$solve = function (string $id, bool $withFees) use ($calc): array {
    DB::beginTransaction();
    try {
        $q = DB::table('contract_fees')->where('contract_id', $id)->whereNull('superseded_by_source');
        if ($withFees) {
            (clone $q)->whereIn('fee_type', ['arrangement', 'legal'])->update(['classification_status' => 'REVIEWED', 'integral' => 1, 'cashflow_direction' => 'RECEIVED']);
            (clone $q)->whereNotIn('fee_type', ['arrangement', 'legal'])->update(['classification_status' => 'REJECTED', 'integral' => 0]);
        } else {
            (clone $q)->update(['classification_status' => 'REJECTED', 'integral' => 0]);
        }
        $fees = (float) DB::table('contract_fees')->where('contract_id', $id)->whereNull('superseded_by_source')->where('integral', 1)->sum('amount');
        $r = $calc->calculate($id, 1);
        $c = DB::table('contract_eir')->where('contract_id', $id)->first();
        $snap = json_decode((string) $c->input_snapshot, true) ?: [];
        $out = ['status' => $r['status'], 'nominal' => $c->eir_nominal_annual, 'effective' => $c->eir_effective_annual, 'fees' => $fees,
            'error' => $r['error'] ?? null, 'solve_from' => $snap['metadata']['origination_date'] ?? null, 'later' => $snap['metadata']['later_drawdowns'] ?? null,
            'net' => $snap['initial_net_investment'] ?? null];
    } finally {
        DB::rollBack();
    }

    return $out;
};
$rows = [];
$ids = DB::table('contract_eir')->where('schedule_approval_status', 'APPROVED')->orderBy('contract_id')->pluck('contract_id');
foreach ($ids as $id) {
    $c = DB::table('contract_eir')->where('contract_id', $id)->first();
    $dates = DB::table('contract_disbursements')->where('contract_id', $id)->distinct()->count('disbursement_date');
    $locked = $c->locked_at !== null;
    $a = $locked ? ['status' => 'LOCKED', 'nominal' => $c->eir_nominal_annual, 'fees' => 0.0, 'error' => null] : $solve($id, false);
    $b = $locked ? ['status' => '-', 'nominal' => null, 'fees' => 0.0, 'error' => null] : $solve($id, true);
    $rows[] = [$id, $c->schedule_source, $c->contractual_rate, $dates, $c->calculation_status, $a['nominal'], $a['status'], $b['nominal'], $b['status'], $b['fees'],
        $a['solve_from'] ?? null, $a['later'] ?? null, mb_substr((string) ($b['error'] ?? $a['error'] ?? ''), 0, 200)];
}
$f = fopen(__DIR__ . '/illustrate.csv', 'w');
fputcsv($f, ['contract_id', 'schedule_source', 'contractual_rate', 'drawdown_dates', 'status_now', 'eir_nofee', 'nofee_status', 'eir_with_fees', 'fees_status', 'fees_integral', 'solve_from', 'later_drawdowns', 'error']);
foreach ($rows as $r) {
    fputcsv($f, $r);
}
fclose($f);
echo count($rows) . " contracts\n";
