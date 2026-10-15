<?php

namespace App\Http\Controllers;

use App\Models\GovernanceSetting;
use App\Services\Eir\GovernanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;
use LogicException;

/**
 * The Governance Centre (spec v3 section 8): every calculation convention
 * the EIR engine uses, with its options, the value in force, its effective
 * date, who proposed and who approved it, and the history of changes.
 */
class EirGovernanceController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:eir.govern']);
    }

    public function index(GovernanceService $governance)
    {
        return Inertia::render('Eir/Governance', [
            'settings' => $governance->overview(),
            'asOf' => now()->toDateString(),
            'userId' => auth()->id(),
            'adminOverride' => $this->adminOverride(),
            // the transmission-method cards beside their setting (spec v4 section 14.7): what each does, its
            // formula, what it implies, its preconditions checked live, a worked example
            'methodCards' => app(\App\Services\Fli\TransmissionMethodCatalogue::class)->cards(),
        ]);
    }

    public function propose(Request $request, GovernanceService $governance)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'in:' . implode(',', GovernanceService::keys())],
            'value' => ['required', 'string', 'max:255'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        try {
            $setting = $governance->propose($data['key'], $data['value'], $data['effective_from'], $data['reason'], auth()->id());
        } catch (InvalidArgumentException|LogicException|\App\Support\BackDatedChangeException $e) {
            return back()->withErrors(['governance' => $e->getMessage()])->withInput();
        }

        return back()->with('success', "Change to \"{$setting->label}\" proposed. It takes effect from {$setting->effective_from->toDateString()} once a second person approves it.");
    }

    public function approve(GovernanceSetting $setting, GovernanceService $governance)
    {
        try {
            $approved = $governance->approve($setting->id, (int) auth()->id(), $this->adminOverride());
        } catch (LogicException|\App\Support\BackDatedChangeException $e) {
            return back()->withErrors(['governance' => $e->getMessage()]);
        }

        return back()->with('success', "\"{$approved->label}\" is \"{$approved->value}\" from {$approved->effective_from->toDateString()}.");
    }

    /** Administrators may approve their own proposal, as with the EIR lock. */
    /**
     * The self-approvals report (Staging Module Specification v1, section 8.9,
     * item 6): every decision made under the maker-checker override, by
     * workflow, person and date, read from the "Maker-Checker Override Used"
     * audit entries; for review at handover. ?download=csv exports it.
     */
    public function selfApprovals(Request $request)
    {
        $rows = \App\Support\MakerChecker::selfApprovals();
        if ($request->query('download') === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['When', 'Workflow', 'Record', 'Person', 'Environment', 'Label']);
                foreach ($rows as $r) {
                    fputcsv($out, [$r['at'], $r['workflow'], is_scalar($r['record']) ? $r['record'] : json_encode($r['record']), $r['name'] ?? ('user ' . $r['person']), $r['environment'], \App\Support\MakerChecker::LABEL]);
                }
                fclose($out);
            }, 'Self-approvals under the maker-checker override ' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
        }

        return Inertia::render('Eir/SelfApprovals', [
            'rows' => $rows->values(),
            'byWorkflow' => $rows->countBy('workflow'),
            'inForce' => \App\Support\MakerChecker::overrideInForce(),
            'environment' => app()->environment(),
        ]);
    }

    private function adminOverride(): bool
    {
        return app(GovernanceService::class)->adminOverrideAllowed(auth()->user()); // governed (audit H9)
    }
}
