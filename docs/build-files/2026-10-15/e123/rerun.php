<?php
// Rerun of the EIR chain on maiic_ifrs9_eircheck after the E1 to E3 fixes.
// Run from the project root:  DB_DATABASE=maiic_ifrs9_eircheck php <this file> [steps]
// steps: inputs,reset,schedules,solve,revenue (default all)

use App\Models\ContractEir;
use App\Services\Ebanker\ContractInputsBuildService;
use App\Services\Ebanker\GlFeeBuildService;
use App\Services\Ebanker\LoanBookBuildService;
use App\Services\Ebanker\TakeonLandingService;
use App\Services\Eir\EirCalculationService;
use App\Services\Eir\FeeRuleMatcher;
use App\Services\Eir\ScheduleWorkflowService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_time_limit(0);
ini_set('memory_limit', '4G');

if (DB::connection()->getDatabaseName() !== 'maiic_ifrs9_eircheck') {
    fwrite(STDERR, "refusing: connected to " . DB::connection()->getDatabaseName() . "\n");
    exit(1);
}
$user = 1;
$steps = isset($argv[1]) ? explode(',', $argv[1]) : ['inputs', 'reset', 'schedules', 'solve', 'revenue'];
$say = function (string $label, $value) { echo str_pad($label, 34) . ' ' . (is_string($value) ? $value : json_encode($value)) . PHP_EOL; };

if (in_array('inputs', $steps, true)) {
    $t = app(TakeonLandingService::class)->build($user);
    $say('take-on population', $t);
    $d = app(ContractInputsBuildService::class)->disbursements();
    $say('tranche register', ['rows' => $d['rows']] + array_intersect_key($d['result'], array_flip(['loaded_rows', 'already_stored', 'contracts', 'total_amount', 'ledger_rows_replaced', 'fee_debits_capitalised'])));
    $g = app(GlFeeBuildService::class)->build($user);
    $say('GL fees', ['legs' => $g['legs'], 'assigned' => $g['assigned'], 'held' => $g['held'], 'loaded' => $g['loaded']['loaded_rows'] ?? null,
        'precedence' => array_diff_key($g['precedence'], ['exceptions' => 1]), 'exceptions' => count($g['precedence']['exceptions']), 'gate' => array_diff_key($g['gate'], ['months' => 1])]);
    file_put_contents(__DIR__ . '/gl_fee_build.json', json_encode($g, JSON_PRETTY_PRINT));
    $s = app(FeeRuleMatcher::class)->sweepPending();
    $say('fee rulebook sweep', $s);
}

if (in_array('reset', $steps, true)) {
    // the clone was bootstrapped before the fixes: reopen every locked EIR (archived to its history with
    // the reason) and send every generated schedule back to be generated again, as a fresh bootstrap would
    $calc = app(EirCalculationService::class);
    $reopened = 0;
    foreach (ContractEir::whereNotNull('locked_at')->pluck('contract_id') as $id) {
        $calc->reopen($id, $user, 'Results review E1 to E3 fixed on 14 October 2026: schedules on the drawdown profile, take-on EIRs, GL fee route; solved again');
        $reopened++;
    }
    DB::table('eir_amortisation')->delete();
    $reset = ContractEir::where('schedule_source', 'GENERATED')->update(['schedule_approval_status' => 'NOT_GENERATED', 'schedule_approved_at' => null, 'schedule_approved_by' => null]);
    DB::table('contract_cashflow_schedule')->where('schedule_version', 1)->where('schedule_source', 'GENERATED')->delete();
    ContractEir::whereNull('locked_at')->update(['calculation_status' => 'PENDING', 'calculation_error' => null, 'eir_period' => null, 'eir_nominal_annual' => null,
        'eir_effective_annual' => null, 'input_snapshot' => null, 'calculated_at' => null, 'calculated_by' => null]);
    $say('reset', ['reopened' => $reopened, 'generated schedules reset' => $reset]);
}

if (in_array('schedules', $steps, true)) {
    $workflow = app(ScheduleWorkflowService::class);
    $gen = $workflow->generateEligible();
    file_put_contents(__DIR__ . '/schedule_exceptions.json', json_encode($gen['exceptions'], JSON_PRETTY_PRINT));
    $approved = 0; $refused = [];
    foreach (ContractEir::query()->whereIn('schedule_approval_status', ['DRAFT', 'PENDING_REVIEW'])->get() as $c) {
        try {
            $workflow->approve($c, $user, LoanBookBuildService::BOOTSTRAP_LABEL);
            $approved++;
        } catch (Throwable $e) {
            $refused[$c->contract_id] = $e->getMessage();
        }
    }
    $say('schedules', ['generated' => $gen['generated'], 'skipped' => $gen['skipped'], 'approved (bootstrap label)' => $approved, 'refused' => count($refused)]);
}

if (in_array('solve', $steps, true)) {
    $calc = app(EirCalculationService::class);
    $solved = 0; $locked = 0; $failed = [];
    foreach (ContractEir::query()->where('schedule_approval_status', 'APPROVED')->whereNull('locked_at')->get() as $c) {
        $r = $calc->calculate($c->contract_id, $user);
        if ($r['status'] !== 'CALCULATED') {
            $failed[$c->contract_id] = $r['error'] ?? $r['status'];
            continue;
        }
        $solved++;
        try {
            $calc->lock($c->contract_id, $user, true);
            $locked++;
        } catch (Throwable $e) {
            $failed[$c->contract_id] = 'lock: ' . $e->getMessage();
        }
    }
    // the readiness issues of every blocked contract, for the report
    $issues = [];
    foreach (ContractEir::where('calculation_status', 'BLOCKED')->pluck('contract_id') as $id) {
        $issues[$id] = array_column(app(\App\Services\Eir\EirReadinessService::class)->assess($id)['issues'], 'message', 'code');
    }
    file_put_contents(__DIR__ . '/solve_blocked.json', json_encode($issues, JSON_PRETTY_PRINT));
    $say('EIR solved and locked', ['solved' => $solved, 'locked (bootstrap label)' => $locked, 'not solved' => count($failed)]);
}

if (in_array('revenue', $steps, true)) {
    $periods = app(LoanBookBuildService::class)->periods('2024-07', '2026-09');
    $errors = [];
    foreach ($periods as $p) {
        $code = Artisan::call('eir:run-revenue', ['period' => $p, '--user' => $user]);
        if ($code !== 0) {
            $errors[] = $p . ': ' . trim(substr(Artisan::output(), 0, 160));
        }
    }
    $say('revenue', count($periods) . ' periods; ' . DB::table('eir_amortisation')->count() . ' rows' . ($errors ? '; failed ' . json_encode($errors) : ''));
}
