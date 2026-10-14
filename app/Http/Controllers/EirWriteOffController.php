<?php

namespace App\Http\Controllers;

use App\Services\Eir\WriteOffRegisterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/** Write-offs: those the ledger carries, and the governed register for those it does not (spec v4 s.3.8). */
class EirWriteOffController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:eir.view']);
        $this->middleware('permission:eir.govern')->except(['index']);
    }

    public function index(WriteOffRegisterService $service)
    {
        \App\Support\LedgerReadLimits::raise(); // reads the whole landing-zone ledger
        $users = DB::table('users')->pluck('name', 'id');
        $register = DB::table('loan_write_offs')->orderByDesc('id')->get()->map(fn ($w) => (array) $w + [
            'proposed_by_name' => $users[$w->proposed_by] ?? null,
            'approved_by_name' => $w->approver_label ?: ($users[$w->approved_by] ?? null),
            'closed_by_name' => $users[$w->closed_by] ?? null,
        ]);
        $ledger = $service->ledgerWriteOffs();

        return Inertia::render('Eir/WriteOffs/Index', [
            'ledger' => $ledger,
            'register' => $register,
            'contracts' => DB::table('loan_books')->select('contract_id', 'customer_name')->distinct()->orderBy('customer_name')->get(),
            'me' => auth()->id(),
            'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false),
            'summary' => [
                'ledger_count' => count($ledger), 'ledger_amount' => round(array_sum(array_column($ledger, 'amount')), 2),
                'approved_count' => $register->where('status', 'APPROVED')->count(), 'approved_amount' => round((float) $register->where('status', 'APPROVED')->sum('amount'), 2),
                'proposed' => $register->where('status', 'PROPOSED')->count(),
            ],
        ]);
    }

    public function store(Request $request, WriteOffRegisterService $service)
    {
        $data = $request->validate([
            'contract_id' => ['required', 'string', 'max:50'],
            'write_off_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
            'evidence' => ['required', 'string', 'max:2000'],
            'gl_reference' => ['nullable', 'string', 'max:100'],
        ]);
        try {
            $id = $service->propose($data, $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Write-off #{$id} proposed. A different person approves it before it reaches the engines.");
    }

    public function approve(Request $request, int $id, WriteOffRegisterService $service)
    {
        try {
            $service->approve($id, $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Write-off #{$id} approved: it is on the loan's actual transactions as a write-off; re-run the EIR revenue from its month.");
    }

    public function close(Request $request, int $id, WriteOffRegisterService $service)
    {
        $data = $request->validate(['action' => ['required', 'in:reject,withdraw,reverse'], 'reason' => ['required', 'string', 'max:500']]);
        try {
            $service->close($id, $request->user()->id, $data['action'], $data['reason']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Write-off #{$id}: " . ['reject' => 'rejected', 'withdraw' => 'withdrawn', 'reverse' => 'reversed'][$data['action']] . '.');
    }
}
