<?php

namespace App\Http\Controllers;

use App\Services\Eir\GovernanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Governance Centre, Audit Trail, Audit Trace (spec v4 section 12.5): the
 * suite's per-record trace. Open any contract and see, oldest to newest,
 * every change to its schedule, every reset and modification, every
 * engine run that touched it, and the governance values each of its months
 * was run under. The workbook states the rule; the trace shows the rule
 * applied to one loan.
 */
class AuditTraceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request, GovernanceService $governance)
    {
        $contract = ltrim(trim((string) $request->query('contract', '')), '0');
        $events = [];
        $months = [];
        $facts = null;
        if ($contract !== '') {
            $eir = DB::table('contract_eir')->where('contract_id', $contract)->first();
            $facts = $eir ? ['contract_id' => $eir->contract_id, 'customer_name' => $eir->customer_name, 'product_type' => $eir->product_type, 'gl' => $eir->gl_account_code, 'origination_date' => $eir->origination_date, 'eir' => $eir->eir_effective_annual, 'calculation_status' => $eir->calculation_status, 'locked_at' => $eir->locked_at, 'schedule_status' => $eir->schedule_approval_status] : ['contract_id' => $contract];
            $add = function (string $at, string $what, string $detail, string $source, ?string $who = null) use (&$events) {
                $events[] = ['at' => $at, 'what' => $what, 'detail' => $detail, 'source' => $source, 'who' => $who];
            };
            // the audit log: anything that names the contract
            foreach (DB::table('audit_logs as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')->where(fn ($q) => $q->where('a.new_values', 'like', "%{$contract}%")->orWhere('a.meta', 'like', "%{$contract}%")->orWhere('a.old_values', 'like', "%{$contract}%"))
                ->orderBy('a.id')->limit(500)->get(['a.action', 'a.created_at', 'a.new_values', 'a.old_values', 'u.name']) as $l) {
                $add((string) $l->created_at, $l->action, mb_substr((string) ($l->new_values ?: $l->old_values), 0, 300), 'audit log', $l->name);
            }
            // schedule versions, resets, roll-forward, fees, builds of the loan's rows
            foreach (DB::table('contract_cashflow_schedule')->where('contract_id', $contract)->selectRaw('schedule_version, min(created_at) at, count(*) n, min(due_date) first_due, max(due_date) last_due, schedule_source')->groupBy('schedule_version', 'schedule_source')->get() as $v) {
                $add((string) $v->at, "Schedule version {$v->schedule_version}", "{$v->n} lines, {$v->first_due} to {$v->last_due}, source {$v->schedule_source}", 'contract_cashflow_schedule');
            }
            foreach (DB::table('rate_reset_events')->where('contract_id', $contract)->orderBy('reset_date')->get() as $r) {
                $add((string) $r->created_at, 'Rate reset', "{$r->reset_date}: reference {$r->old_reference_rate} to {$r->new_reference_rate}, schedule v{$r->new_schedule_version}", 'rate_reset_events');
            }
            foreach (DB::table('contract_fees')->where('contract_id', $contract)->orderBy('id')->get() as $f) {
                $add((string) $f->created_at, "Fee {$f->fee_type} " . number_format((float) $f->amount, 2), "{$f->classification_status}" . ($f->integral !== null ? ($f->integral ? ', integral' : ', not integral') : '') . ($f->classification_reason ? ': ' . mb_substr($f->classification_reason, 0, 120) : ''), 'contract_fees');
            }
            if (DB::getSchemaBuilder()->hasTable('eir_calculation_history')) {
                foreach (DB::table('eir_calculation_history')->where('contract_id', $contract)->orderBy('id')->get() as $h) {
                    $add((string) $h->created_at, 'Earlier EIR solve', 'effective annual ' . $h->eir_effective_annual, 'eir_calculation_history');
                }
            }
            foreach (DB::table('eir_amortisation')->where('contract_id', $contract)->orderBy('reporting_period')->get() as $a) {
                $add((string) $a->created_at, "Roll-forward {$a->reporting_period}", 'opening ' . number_format((float) $a->opening_gross, 2) . ', EIR interest ' . number_format((float) $a->interest_accrued, 2) . " ({$a->interest_basis}), cash " . number_format((float) $a->cash_received, 2) . " ({$a->cash_source}), closing " . number_format((float) $a->closing_gross, 2), 'eir_amortisation');
            }
            // the loan's months and the governance values each ran under
            $keys = ['loan_book_build_method', 'dpd_basis', 'stage3_missed_instalments', 'stage3_interest_basis', 'fli_adjustment_route', 'fli_transmission_method', 'scenario_weighting_method', 'plr_mid_period'];
            foreach (DB::table('loan_books')->where('contract_id', $contract)->orderBy('reporting_period')->get(['reporting_period', 'build_method', 'build_load_id', 'ifrs9stage_post_qualitative', 'overdue_days', 'carrying_amount', 'pd_prefli', 'pd_post_fli', 'lgd_value', 'ecl_value', 'fli_route', 'fli_method', 'fli_fit_id', 'fli_set_id']) as $m) {
                $lock = DB::table('reporting_period_locks')->where('reporting_period', $m->reporting_period)->first();
                $snapshot = $lock && $lock->settings_snapshot ? json_decode($lock->settings_snapshot, true) : null;
                $values = [];
                foreach ($keys as $k) {
                    try {
                        $values[$k] = $snapshot[$k] ?? $governance->get($k, CarbonImmutable::parse($m->reporting_period . '-01')->endOfMonth());
                    } catch (Throwable) {
                        $values[$k] = null;
                    }
                }
                $months[] = ['period' => $m->reporting_period, 'build' => $m->build_method . ($m->build_load_id ? " (load {$m->build_load_id})" : ''), 'stage' => $m->ifrs9stage_post_qualitative, 'dpd' => $m->overdue_days, 'carrying' => (float) $m->carrying_amount,
                    'pd_pre' => $m->pd_prefli, 'pd_post' => $m->pd_post_fli, 'lgd' => $m->lgd_value, 'ecl' => $m->ecl_value, 'fli' => trim(($m->fli_route ?? '') . ' ' . ($m->fli_method ?? '')) . ($m->fli_fit_id ? " fit {$m->fli_fit_id}" : '') . ($m->fli_set_id ? " set {$m->fli_set_id}" : ''),
                    'locked' => $lock !== null, 'governance' => $values, 'governance_source' => $snapshot ? 'locked snapshot' : 'values in force on the month-end'];
            }
            usort($events, fn ($a, $b) => strcmp($a['at'], $b['at']));
        }

        return Inertia::render('Audit/Trace', ['contract' => $contract, 'facts' => $facts, 'events' => $events, 'months' => $months,
            'suggestions' => DB::table('contract_eir')->orderBy('contract_id')->limit(300)->get(['contract_id', 'customer_name'])->map(fn ($c) => ['id' => $c->contract_id, 'name' => $c->customer_name])]);
    }
}
