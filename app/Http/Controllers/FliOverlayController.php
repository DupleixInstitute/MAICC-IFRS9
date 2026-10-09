<?php

namespace App\Http\Controllers;

use App\Services\Fli\OverlayService;
use App\Services\Scenario\ScenarioSetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Governance Centre, Manual Overlays (spec v4 sections 14.6 and 15.7): the
 * register of judgement added to the forward-looking adjustment, by
 * period, with its scope, reason, evidence, owner, expiry and the ECL line
 * each overlay adds; propose, approve (a second person), reject, expire.
 * Built for the system audit of 9 October 2026, finding M3.
 */
class FliOverlayController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:eir.govern')->only(['propose', 'approve', 'reject', 'expire']);
    }

    public function index(Request $request, OverlayService $overlays, ScenarioSetService $sets)
    {
        $period = (string) $request->query('period', DB::table('fli_overlays')->max('reporting_period') ?? DB::table('governed_scenario_sets')->max('reporting_period') ?? DB::table('loan_books')->max('reporting_period') ?? now()->format('Y-m'));
        $register = $overlays->register($period);
        $set = DB::table('governed_scenario_sets')->where('reporting_period', $period)->whereIn('status', ['APPROVED', 'LOCKED'])->orderByDesc('version')->first(['id', 'name', 'version', 'status']);
        $inForce = $register->where('in_force', true);

        return Inertia::render('Governance/Overlays', [
            'period' => $period, 'overlays' => $register->values(), 'rules' => $sets->rules($period), 'approvedSet' => $set,
            'summary' => ['in_force' => $inForce->count(), 'proposed' => $register->where('status', 'PROPOSED')->count(), 'ecl_line' => round((float) $inForce->sum('ecl_line'), 2), 'loans' => $overlays->loansOf($period)->count()],
            'periods' => DB::table('fli_overlays')->distinct()->orderByDesc('reporting_period')->pluck('reporting_period'),
            'productGroups' => DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('product_group')->distinct()->orderBy('product_group')->pluck('product_group'),
            'users' => DB::table('users')->orderBy('name')->get(['id', 'name']),
            'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false),
        ]);
    }

    public function propose(Request $request, OverlayService $overlays)
    {
        $data = $request->validate([
            'reporting_period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'scope' => ['required', 'in:book,product_group,contract'],
            'scope_value' => ['nullable', 'string', 'max:120'],
            'adjustment_pct' => ['required', 'numeric', 'gt:-100', 'lte:1000'],
            'reason' => ['required', 'string', 'max:2000'],
            'evidence' => ['nullable', 'string', 'max:2000'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'expiry_period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        try {
            $id = $overlays->propose(['reporting_period' => $data['reporting_period'], 'scope' => $data['scope'], 'scope_value' => $data['scope_value'] ?? null,
                'adjustment' => (float) $data['adjustment_pct'] / 100, 'reason' => $data['reason'], 'evidence' => $data['evidence'] ?? null,
                'owner_id' => $data['owner_id'] ?? $request->user()->id, 'expiry_period' => $data['expiry_period']], $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('fli-overlays.index', ['period' => $data['reporting_period']])->with('success', "Overlay {$id} proposed; a second person approves it before it reaches any loan.");
    }

    public function approve(Request $request, int $overlay, OverlayService $overlays)
    {
        return $this->act(fn () => $overlays->approve($overlay, $request->user()->id), "Overlay {$overlay} approved: it is in force until its expiry period.");
    }

    public function reject(Request $request, int $overlay, OverlayService $overlays)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->act(fn () => $overlays->reject($overlay, $request->user()->id, $data['reason']), "Overlay {$overlay} rejected.");
    }

    public function expire(Request $request, int $overlay, OverlayService $overlays)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->act(fn () => $overlays->expire($overlay, $request->user()->id, $data['reason'] ?? null), "Overlay {$overlay} expired: it no longer reaches any loan.");
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
}
