<?php

namespace App\Http\Controllers;

use App\Services\Eir\RateResetService;
use App\Services\Eir\RestructureCandidateService;
use App\Services\Eir\RestructureRegisterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Restructures and rate resets (specification v4 sections 3.9, P5, P6 and P7):
 * the candidates found in E-Banker's reschedule history, the governed register
 * that confirms them under maker-checker, what the revenue run booked for each
 * confirmed event, and the register of every move of a floating loan's rate.
 */
class EirRestructureController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:eir.view']);
        $this->middleware('permission:eir.govern')->except(['index']);
    }

    public function index(Request $request)
    {
        $users = DB::table('users')->pluck('name', 'id');
        $mods = DB::table('contract_modifications')->orderByDesc('id')->get()->keyBy('restructure_event_id');
        $events = DB::table('restructure_events')
            ->orderByRaw("CASE status WHEN 'PROPOSED' THEN 0 WHEN 'CANDIDATE' THEN 1 ELSE 2 END")->orderByDesc('event_date')->orderBy('contract_id')->get()
            ->map(function ($e) use ($users, $mods) {
                $m = $mods[$e->id] ?? null;

                return array_merge((array) $e, [
                    'old_terms' => json_decode((string) $e->old_terms, true), 'new_terms' => json_decode((string) $e->new_terms, true),
                    'new_flows_count' => count(json_decode((string) $e->new_flows, true) ?: []),
                    'qualitative_factors' => json_decode((string) $e->qualitative_factors, true) ?: [],
                    'proposed_by_name' => $users[$e->proposed_by] ?? null, 'approved_by_name' => $e->approver_label ?: ($users[$e->approved_by] ?? null),
                    'closed_by_name' => $users[$e->closed_by] ?? null,
                    'modification' => $m === null ? null : (array) $m,
                ]);
            })->map(fn ($e) => collect($e)->except(['new_flows'])->all())->values();
        $resets = DB::table('rate_reset_events')->whereNotNull('detection_key')
            ->orderByRaw("CASE classification_status WHEN 'PROPOSED' THEN 0 WHEN 'DETECTED' THEN 1 ELSE 2 END")->orderByDesc('reporting_period')->orderBy('contract_id')->orderBy('event_type')->get()
            ->map(fn ($r) => (array) $r + ['proposed_by_name' => $users[$r->proposed_by] ?? null, 'approved_by_name' => $r->approver_label ?: ($users[$r->approved_by] ?? null)])->values();
        $names = DB::table('loan_books')->whereIn('contract_id', $resets->pluck('contract_id')->unique()->values()->all() ?: ['-'])->whereNotNull('customer_name')
            ->pluck('customer_name', 'contract_id')->all();
        $resets = $resets->map(fn ($r) => $r + ['customer_name' => $names[$r['contract_id']] ?? null]);
        $year = (string) now()->year;

        return Inertia::render('Eir/Restructures/Index', [
            'tab' => in_array($request->query('tab'), ['register', 'resets'], true) ? $request->query('tab') : 'register',
            'events' => $events,
            'resets' => $resets,
            'me' => auth()->id(),
            'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false),
            'classifications' => RestructureRegisterService::CLASSIFICATIONS,
            'factors' => RestructureRegisterService::QUALITATIVE_FACTORS,
            'summary' => [
                'candidates' => $events->where('status', 'CANDIDATE')->count(),
                'incomplete' => $events->where('status', 'CANDIDATE')->whereNotNull('incomplete_reason')->count(),
                'proposed' => $events->where('status', 'PROPOSED')->count(),
                'confirmed' => $events->where('status', 'CONFIRMED')->count(),
                'restructured' => $events->where('status', 'CONFIRMED')->where('classification', 'MODIFICATION')->count(),
                'applied' => $mods->where('status', 'APPLIED')->count(), 'blocked' => $mods->where('status', 'BLOCKED')->count(), 'pending' => $mods->where('status', 'PENDING')->count(),
                // the register's maker-checker state is classification_status; status is the reset pipeline's
                'resets_detected' => $resets->where('classification_status', 'DETECTED')->count(), 'resets_proposed' => $resets->where('classification_status', 'PROPOSED')->count(),
                'resets_approved' => $resets->where('classification_status', 'APPROVED')->count(), 'spread_changes' => $resets->where('event_type', 'SPREAD_CHANGE')->count(),
                'resets_held' => $resets->where('status', 'BLOCKED')->count(), 'resets_scheduled' => $resets->where('status', 'SCHEDULED')->count(),
                'disclosure_year' => $year,
                'disclosures' => app(RestructureRegisterService::class)->ifrs7Disclosures("{$year}-01-01", "{$year}-12-31"),
                'last_detected' => DB::table('restructure_events')->max('updated_at'),
            ],
        ]);
    }

    /** Find the candidates and the rate moves again from the landing zone; nothing is confirmed. */
    public function detect(Request $request, RestructureCandidateService $candidates, RateResetService $resets)
    {
        \App\Support\LedgerReadLimits::raise();
        try {
            $c = $candidates->detect($request->user()->id);
            $r = $resets->detect($request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', 'Detection stopped: ' . $e->getMessage());
        }

        return back()->with('success', sprintf('%d restructure candidates from the reschedule history (%d cannot be booked: no date, old terms or new schedule) and %d fixed-rate moves; %d rate moves of floating loans recorded (%d PLR resets, %d spread changes). Nothing was confirmed: each needs a classification by one person and approval by another.',
            $c['found'], $c['incomplete'], $r['fixed_rate_candidates'], $r['plr_resets'] + $r['spread_changes'], $r['plr_resets'], $r['spread_changes']));
    }

    public function propose(Request $request, int $id, RestructureRegisterService $service)
    {
        $data = $request->validate([
            'classification' => ['required', 'in:' . implode(',', RestructureRegisterService::CLASSIFICATIONS)],
            'reason' => ['required', 'string', 'max:1000'],
            'qualitative_factors' => ['nullable', 'array'], 'qualitative_factors.*' => ['in:' . implode(',', array_keys(RestructureRegisterService::QUALITATIVE_FACTORS))],
            'board_approval_ref' => ['nullable', 'string', 'max:200'], 'board_approval_date' => ['nullable', 'date'],
            'fair_value' => ['nullable', 'numeric', 'gt:0'], 'fair_value_basis' => ['nullable', 'string', 'max:500', 'required_with:fair_value'],
            'modification_fees' => ['nullable', 'numeric'],
        ]);
        try {
            $service->propose($id, $data, $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Event #{$id} proposed as " . strtolower(str_replace('_', ' ', $data['classification'])) . '. A different person approves it before it reaches the accounts.');
    }

    public function approve(Request $request, int $id, RestructureRegisterService $service)
    {
        try {
            $service->approve($id, $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $m = DB::table('contract_modifications')->where('restructure_event_id', $id)->where('status', 'PENDING')->first();

        return back()->with('success', "Event #{$id} confirmed." . ($m ? " Schedule version {$m->new_schedule_version} is loaded; re-run the EIR revenue from {$m->reporting_period} (with a reason) to book it." : ' It changes no figure.'));
    }

    public function close(Request $request, int $id, RestructureRegisterService $service)
    {
        $data = $request->validate(['action' => ['required', 'in:reject,withdraw,reverse'], 'reason' => ['required', 'string', 'max:500']]);
        try {
            $service->close($id, $request->user()->id, $data['action'], $data['reason']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Event #{$id}: " . ['reject' => 'rejected', 'withdraw' => 'withdrawn', 'reverse' => 'reversed'][$data['action']] . '; it is a candidate again.');
    }

    public function proposeResets(Request $request, RateResetService $service)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer'], 'classification' => ['required', 'in:' . implode(',', RateResetService::CLASSIFICATIONS)],
            'reason' => ['required', 'string', 'max:1000']]);
        try {
            $n = $service->propose($data['ids'], $data['classification'], $data['reason'], $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$n} rate move(s) proposed as " . ($data['classification'] === 'IN_CONTRACT' ? 'in-contract resets' : 'negotiated') . '. A different person approves.');
    }

    public function approveResets(Request $request, RateResetService $service)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']]);
        try {
            $n = $service->approve($data['ids'], $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$n} rate move(s) approved. A negotiated one is now a candidate on the restructure register.");
    }

    public function closeReset(Request $request, int $id, RateResetService $service)
    {
        $data = $request->validate(['action' => ['required', 'in:reject,withdraw'], 'reason' => ['required', 'string', 'max:500']]);
        try {
            $service->close($id, $request->user()->id, $data['action'], $data['reason']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Rate move #{$id}: " . ($data['action'] === 'reject' ? 'rejected' : 'withdrawn') . '; it awaits a classification again.');
    }
}
