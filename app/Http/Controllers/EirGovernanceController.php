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
        ]);
    }

    public function propose(Request $request, GovernanceService $governance)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'in:' . implode(',', GovernanceService::keys())],
            'value' => ['required', 'string', 'max:60'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        try {
            $setting = $governance->propose($data['key'], $data['value'], $data['effective_from'], $data['reason'], auth()->id());
        } catch (InvalidArgumentException|LogicException $e) {
            return back()->withErrors(['governance' => $e->getMessage()])->withInput();
        }

        return back()->with('success', "Change to \"{$setting->label}\" proposed. It takes effect from {$setting->effective_from->toDateString()} once a second person approves it.");
    }

    public function approve(GovernanceSetting $setting, GovernanceService $governance)
    {
        try {
            $approved = $governance->approve($setting->id, (int) auth()->id(), $this->adminOverride());
        } catch (LogicException $e) {
            return back()->withErrors(['governance' => $e->getMessage()]);
        }

        return back()->with('success', "\"{$approved->label}\" is \"{$approved->value}\" from {$approved->effective_from->toDateString()}.");
    }

    /** Administrators may approve their own proposal, as with the EIR lock. */
    private function adminOverride(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }
}
