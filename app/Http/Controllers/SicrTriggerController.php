<?php

namespace App\Http\Controllers;

use App\Services\Eir\GovernanceService;
use App\Services\Eir\QualitativeSicrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Staging & SICR Rules, SICR Trigger Alerts: the qualitative SICR register.
 *
 * A credit officer raises a trigger on a loan (or on every loan of a
 * customer) from the SICR item list, with the reason, the evidence and the
 * dates it applies; a second person approves or rejects it; when the
 * condition passes it is closed with a date and a reason. Nothing here
 * writes to the loan book: the staging engine reads the approved triggers in
 * force at each month-end the next time the month is staged (eir:stage),
 * and the PD and ECL runs then read the stage it wrote.
 *
 * Replaces the screen of the cloned platform, whose form sent columns the
 * table never had (they were dropped without a word), whose "update loan
 * book now" set a flag on one row of the latest month only, and whose
 * "remove alert" wrote a column that does not exist.
 */
class SicrTriggerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:settings');
    }

    public function index(Request $request)
    {
        $today = now()->toDateString();
        $show = (string) $request->query('show', 'current');
        $search = trim((string) $request->query('search', ''));
        $latest = (string) (DB::table('loan_books')->whereNotNull('ifrs9stage_post_qualitative')->max('reporting_period') ?? DB::table('loan_books')->max('reporting_period') ?? '');

        $query = DB::table('finance_sicr_triggers as t')
            ->leftJoin('finance_sicr_items as i', 'i.id', '=', 't.item_id')
            ->leftJoin('finance_sicr_groups as g', 'g.id', '=', 't.group_id')
            ->leftJoin('users as r', 'r.id', '=', 't.triggered_by')
            ->leftJoin('users as a', 'a.id', '=', 't.approved_by')
            ->leftJoin('users as x', 'x.id', '=', 't.rejected_by')
            ->leftJoin('users as c', 'c.id', '=', 't.closed_by')
            ->select(['t.*', 'i.name as item_name', 'g.name as group_name', 'r.name as raised_by', 'a.name as approved_by_name', 'x.name as rejected_by_name', 'c.name as closed_by_name']);
        match ($show) {
            'proposed' => $query->where('t.status', 'PROPOSED'),
            'closed' => $query->where(fn ($q) => $q->whereIn('t.status', ['CLOSED', 'REJECTED'])->orWhere(fn ($q) => $q->where('t.status', 'APPROVED')->where('t.effective_to', '<', $today))),
            'all' => null,
            default => $query->where(fn ($q) => $q->where('t.status', 'PROPOSED')->orWhere(fn ($q) => $q->where('t.status', 'APPROVED')->where(fn ($q) => $q->whereNull('t.effective_to')->orWhere('t.effective_to', '>=', $today)))),
        };
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('t.account_number', 'like', "%{$search}%")->orWhere('t.customer_name', 'like', "%{$search}%")->orWhere('t.customer_id', $search));
        }
        $triggers = $query->orderByRaw("CASE t.status WHEN 'PROPOSED' THEN 0 WHEN 'APPROVED' THEN 1 ELSE 2 END")->orderByDesc('t.id')->paginate(15)->withQueryString();

        // the stage each loan was given at the latest staged month, before and after the qualitative triggers
        $page = collect($triggers->items());
        $contracts = $page->where('scope', 'contract')->pluck('account_number')->all();
        $customers = $page->where('scope', 'customer')->pluck('customer_id')->filter()->all();
        $rows = $latest === '' ? collect() : DB::table('loan_books')->where('reporting_period', $latest)
            ->where(fn ($q) => $q->whereIn('contract_id', $contracts ?: ['-'])->orWhereIn('customer_id', $customers ?: ['-']))
            ->get(['contract_id', 'customer_id', 'ifrs9stage_pre_qualitative', 'ifrs9stage_post_qualitative', 'ecl_value', 'carrying_amount']);
        $triggers->setCollection($page->map(function ($t) use ($rows, $today) {
            $loans = $t->scope === 'customer' ? $rows->where('customer_id', $t->customer_id) : $rows->where('contract_id', $t->account_number);
            $t->loans = $loans->map(fn ($l) => ['contract_id' => $l->contract_id, 'pre' => $l->ifrs9stage_pre_qualitative, 'post' => $l->ifrs9stage_post_qualitative, 'ecl' => $l->ecl_value === null ? null : (float) $l->ecl_value])->values();
            $t->display_status = $this->displayStatus($t, $today);

            return $t;
        }));

        $inForceToday = (new QualitativeSicrService())->inForce($today);

        return Inertia::render('StageingRules/Triggers', [
            'triggers' => $triggers,
            'filters' => ['show' => $show, 'search' => $search],
            'latestPeriod' => $latest,
            'summary' => [
                'in_force' => $inForceToday->count(),
                'stage2' => $inForceToday->where('stage_implied', 2)->count(),
                'stage3' => $inForceToday->where('stage_implied', 3)->count(),
                'proposed' => DB::table('finance_sicr_triggers')->where('status', 'PROPOSED')->count(),
                'closed' => DB::table('finance_sicr_triggers')->whereIn('status', ['CLOSED', 'REJECTED'])->count(),
            ],
            'items' => DB::table('finance_sicr_items as i')->join('finance_sicr_groups as g', 'g.id', '=', 'i.group_id')->where('i.active', true)
                ->orderBy('g.sort_order')->orderBy('g.name')->orderBy('i.stage_implied')->orderBy('i.name')
                ->get(['i.id', 'i.name', 'i.description', 'i.stage_implied', 'i.evidence_required', 'i.standard_reference', 'g.name as group_name']),
            'loans' => $latest === '' ? [] : DB::table('loan_books')->where('reporting_period', $latest)->orderBy('customer_name')
                ->get(['contract_id', 'customer_id', 'customer_name', 'ifrs9stage_post_qualitative as stage']),
            'userId' => (int) auth()->id(),
        ]);
    }

    public function store(Request $request, QualitativeSicrService $register)
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:finance_sicr_items,id'],
            'scope' => ['required', 'in:contract,customer'],
            'contract_id' => ['nullable', 'required_if:scope,contract', 'string', 'max:60'],
            'customer_id' => ['nullable', 'required_if:scope,customer', 'string', 'max:60'],
            'reason' => ['required', 'string', 'max:2000'],
            'evidence' => ['nullable', 'string', 'max:2000'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ], [], ['item_id' => 'item', 'contract_id' => 'loan', 'customer_id' => 'customer', 'effective_from' => 'start date', 'effective_to' => 'end date']);
        try {
            $id = $register->propose($data, (int) $request->user()->id);
        } catch (Throwable $e) {
            return back()->withErrors(['trigger' => $e->getMessage()])->withInput();
        }

        return redirect()->route('sicr-triggers.index', ['show' => 'current'])->with('success', "Trigger {$id} raised. A second person approves it; it reaches the loan's stage when the month is next staged.");
    }

    public function approve(Request $request, int $trigger, QualitativeSicrService $register, GovernanceService $governance)
    {
        return $this->act(fn () => $register->approve($trigger, (int) $request->user()->id, $governance->adminOverrideAllowed($request->user())),
            "Trigger {$trigger} approved. Stage the month again (and re-run PD and ECL) for it to change the loan's stage and ECL.");
    }

    public function reject(Request $request, int $trigger, QualitativeSicrService $register)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->act(fn () => $register->reject($trigger, (int) $request->user()->id, $data['reason']), "Trigger {$trigger} rejected.");
    }

    public function close(Request $request, int $trigger, QualitativeSicrService $register)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500'], 'closed_on' => ['nullable', 'date']]);

        return $this->act(fn () => $register->close($trigger, (int) $request->user()->id, $data['reason'], $data['closed_on'] ?? null),
            "Trigger {$trigger} closed. Month-ends after the closing date no longer see it; the cure period decides when the loan moves down.");
    }

    private function act(callable $fn, string $ok)
    {
        try {
            $fn();
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $ok);
    }

    private function displayStatus(object $t, string $today): string
    {
        return match (true) {
            $t->status === 'PROPOSED' => 'Awaiting approval',
            $t->status === 'REJECTED' => 'Rejected',
            $t->status === 'CLOSED' => 'Closed',
            $t->status === 'APPROVED' && $t->effective_from > $today => 'Approved, not started',
            $t->status === 'APPROVED' && $t->effective_to !== null && $t->effective_to < $today => 'Ended',
            default => 'In force',
        };
    }
}
