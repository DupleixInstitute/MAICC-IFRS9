<?php
// Coverage of the EIR chain on the freshly bootstrapped DB: why schedules were skipped and EIRs not solved.
$svc = app(\App\Services\Eir\ScheduleWorkflowService::class);
$reasons = [];
\App\Models\ContractEir::orderBy('contract_id')->each(function ($c) use ($svc, &$reasons) {
    if ($c->schedule_approval_status === 'APPROVED') { $reasons['already approved']++; return; }
    try { $svc->generate($c); $reasons['generated now']++; }
    catch (\Throwable $e) { $k = preg_replace('/\d[\d,\.]*/', 'N', $e->getMessage()); $reasons[substr($k, 0, 110)] = ($reasons[substr($k, 0, 110)] ?? 0) + 1; }
});
arsort($reasons); print_r($reasons);
echo "--- contract_eirs by schedule status / eir status\n";
print_r(DB::table('contract_eirs')->selectRaw('schedule_approval_status, count(*) n')->groupBy('schedule_approval_status')->get()->toArray());
foreach (['eir_status', 'eir_approval_status', 'status'] as $col) {
    if (Schema::hasColumn('contract_eirs', $col)) { print_r(DB::table('contract_eirs')->selectRaw("$col s, count(*) n")->groupBy($col)->get()->toArray()); }
}
echo "--- eir_calculations\n";
if (Schema::hasTable('eir_calculations')) {
    print_r(DB::table('eir_calculations')->selectRaw('status, count(*) n')->groupBy('status')->get()->toArray());
    print_r(DB::table('eir_calculations')->whereNotIn('status', ['LOCKED', 'APPROVED'])->select('contract_id', 'status', 'error_message')->limit(8)->get()->map(fn ($r) => substr(json_encode($r), 0, 220))->toArray());
}
echo "--- loan book Aug 2026: contracts ", DB::table('loan_books')->where('reporting_period', '2026-08')->count(), "; with locked EIR: ",
    DB::table('loan_books')->where('reporting_period', '2026-08')->whereIn('contract_id', DB::table('contract_eirs')->where('schedule_approval_status', 'APPROVED')->pluck('contract_id'))->count(), "\n";
