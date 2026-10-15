<?php
// ILLUSTRATION ONLY, on a throwaway copy of the results database (maiic_ifrs9_eirsandbox):
// the arrangement and legal fees E-Banker holds (LOS charges, PENDING review) are treated
// as integral and reviewed under a sandbox label, every contract whose only blocker was
// the fee review is solved by the system's own EIR service, and the EIRs are printed.
// Nothing here is a MAIIC review; it runs only against the sandbox database.
require 'C:/xampp/htdocs/MAICC-IFRS9/vendor/autoload.php';
$app = require 'C:/xampp/htdocs/MAICC-IFRS9/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

if (DB::connection()->getDatabaseName() !== 'maiic_ifrs9_eirsandbox') {
    fwrite(STDERR, "refused: not the sandbox database\n");
    exit(1);
}
DB::table('contract_fees')->whereIn('fee_type', ['arrangement', 'legal'])->update(['classification_status' => 'REVIEWED', 'integral' => 1, 'cashflow_direction' => DB::raw("coalesce(cashflow_direction,'RECEIVED')"), 'classification_reason' => 'SANDBOX ILLUSTRATION (not a review)']);
DB::table('contract_fees')->where('fee_type', 'other')->update(['classification_status' => 'REJECTED', 'integral' => 0, 'classification_reason' => 'SANDBOX ILLUSTRATION: other fees left out']);
$calc = app(App\Services\Eir\EirCalculationService::class);
$out = fopen('php://stdout', 'w');
fputcsv($out, ['contract_id', 'status', 'contractual_rate', 'eir_nominal', 'eir_effective', 'fees_integral', 'drawn', 'error']);
foreach (DB::table('contract_eir')->where('schedule_approval_status', 'APPROVED')->whereNull('locked_at')->pluck('contract_id') as $id) {
    try {
        $r = $calc->calculate($id, 1);
    } catch (Throwable $e) {
        $r = ['error' => $e->getMessage()];
    }
    $c = DB::table('contract_eir')->where('contract_id', $id)->first();
    $fees = (float) DB::table('contract_fees')->where('contract_id', $id)->where('integral', 1)->sum('amount');
    fputcsv($out, [$id, $c->calculation_status, $c->contractual_rate, $c->eir_nominal_annual, $c->eir_effective_annual, $fees, $c->drawn_amount, substr((string) ($c->calculation_error ?? ($r['error'] ?? '')), 0, 160)]);
}
