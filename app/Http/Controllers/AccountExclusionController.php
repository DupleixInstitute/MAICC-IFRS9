<?php

namespace App\Http\Controllers;

use App\Services\Ebanker\AccountExclusionService;
use App\Services\Ebanker\LoanBookBuildService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Account exclusions (Customer & Loan Data): the register of E-Banker accounts
 * that are not loans of the book, with the maker-checker of AccountExclusionService.
 * Viewing needs eir.view; proposing and deciding need eir.govern, as on the other
 * registers (write-offs, restructures).
 */
class AccountExclusionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:eir.view']);
        $this->middleware('permission:eir.govern')->except(['index']);
    }

    public function index(AccountExclusionService $service)
    {
        $users = DB::table('users')->pluck('name', 'id');
        $archived = DB::table('account_exclusion_archive')->selectRaw('exclusion_id, table_name, count(*) n, sum(restored_at is null) held')
            ->groupBy('exclusion_id', 'table_name')->get()->groupBy('exclusion_id');
        $register = DB::table('account_exclusions')->orderByRaw("case status when 'PROPOSED' then 0 when 'APPROVED' then 1 else 2 end")->orderBy('contract_id')->get()
            ->map(function ($e) use ($users, $archived, $service) {
                $tables = ($archived[$e->id] ?? collect())->mapWithKeys(fn ($r) => [$r->table_name => (int) $r->held])->filter()->all();

                return (array) $e + [
                    'category_label' => AccountExclusionService::CATEGORIES[$e->category] ?? $e->category,
                    'proposed_by_name' => $e->proposed_by !== null ? ($users[$e->proposed_by] ?? null) : ($e->source === 'CLIENT_INPUT' ? 'Client input (docs/bootstrap)' : null),
                    'approved_by_name' => $e->approver_label === LoanBookBuildService::BOOTSTRAP_LABEL ? 'System Bootstrap' : ($e->approved_by !== null ? ($users[$e->approved_by] ?? null) : null),
                    'withdrawal_requested_by_name' => $e->withdrawal_requested_by !== null ? ($users[$e->withdrawal_requested_by] ?? null) : null,
                    'closed_by_name' => $e->closed_by !== null ? ($users[$e->closed_by] ?? null) : null,
                    'archived_tables' => $tables,
                    // what a proposal would move if approved now, so the checker sees it before deciding
                    'footprint' => $e->status === 'PROPOSED' ? $service->footprint((string) $e->contract_id) : [],
                    'locked_months' => $e->status === 'PROPOSED' ? $service->lockedMonths((string) $e->contract_id) : [],
                ];
            });

        return Inertia::render('Eir/AccountExclusions/Index', [
            'register' => $register->values(),
            'categories' => AccountExclusionService::CATEGORIES,
            'me' => auth()->id(),
            'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false),
            'summary' => [
                'approved' => $register->where('status', 'APPROVED')->count(),
                'proposed' => $register->where('status', 'PROPOSED')->count(),
                'withdrawals' => $register->where('status', 'APPROVED')->whereNotNull('withdrawal_requested_by')->count(),
                'rows_held' => (int) DB::table('account_exclusion_archive')->whereNull('restored_at')->count(),
            ],
        ]);
    }

    public function store(Request $request, AccountExclusionService $service)
    {
        $data = $request->validate([
            'contract_id' => ['required', 'string', 'max:40'],
            'category' => ['required', 'in:' . implode(',', array_keys(AccountExclusionService::CATEGORIES))],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'evidence' => ['required', 'string', 'min:10', 'max:2000'],
            'sister_contract_id' => ['nullable', 'string', 'max:40'],
        ]);
        try {
            $id = $service->propose($data, (int) $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Exclusion #{$id} proposed. Attach the supporting document on its record; a different person approves it.");
    }

    public function approve(Request $request, int $id, AccountExclusionService $service)
    {
        try {
            $r = $service->approve($id, (int) $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Exclusion #{$id} approved: {$r['archived']} row(s) moved to the archive. Every build now leaves the account out.");
    }

    /** Reject or withdraw a proposal; on an approved exclusion, withdraw asks for the withdrawal. */
    public function close(Request $request, int $id, AccountExclusionService $service)
    {
        $data = $request->validate(['action' => ['required', 'in:reject,withdraw'], 'reason' => ['required', 'string', 'min:10', 'max:1000']]);
        try {
            $service->close($id, (int) $request->user()->id, $data['action'], $data['reason']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Exclusion #{$id}: " . ($data['action'] === 'reject' ? 'rejected.' : 'withdrawn, or its withdrawal requested; a withdrawal of an approved exclusion waits for a second person.'));
    }

    public function approveWithdrawal(Request $request, int $id, AccountExclusionService $service)
    {
        try {
            $r = $service->approveWithdrawal($id, (int) $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Exclusion #{$id} withdrawn: {$r['restored']} row(s) restored. Build the loan book again from the account's first month.");
    }

    public function dropWithdrawal(Request $request, int $id, AccountExclusionService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        try {
            $service->dropWithdrawal($id, (int) $request->user()->id, $data['reason']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Exclusion #{$id}: the withdrawal request is closed; the exclusion stands.");
    }
}
