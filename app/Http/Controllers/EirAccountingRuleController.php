<?php

namespace App\Http\Controllers;

use App\Models\EirAccountingRule;
use App\Services\Eir\AccountingRuleService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EirAccountingRuleController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:settings']);
    }

    public function index()
    {
        return Inertia::render('Eir/AccountingRules', [
            'userId' => auth()->id(),
            'uatMode' => \App\Support\MakerChecker::allowedFor(auth()->user()),
            'rules' => EirAccountingRule::with(['creator:id,name', 'approver:id,name'])
                ->orderBy('priority')->orderBy('name')->get(),
            'feeTypes' => ['arrangement', 'legal', 'appraisal', 'default', 'levy', 'other'],
        ]);
    }

    public function store(Request $request, AccountingRuleService $rules)
    {
        $rules->create($this->validated($request), (int) auth()->id());

        return back()->with('success', 'Rule proposed. A second person approves it, with a reason, before the fee matcher uses it.');
    }

    public function update(Request $request, EirAccountingRule $rule, AccountingRuleService $rules)
    {
        $rules->update($rule, $this->validated($request), (int) auth()->id());

        return back()->with('success', 'Rule changed and proposed again; the fee matcher stops using it until a second person approves the new wording.');
    }

    /**
     * The decisions on a rule (Staging Module Specification v1, section 8.6):
     * approve, reject or withdraw the rule; propose switching it on or off;
     * approve or reject a proposed switch. Each needs a reason, and approving
     * needs a second person (the one maker-checker check, MakerChecker).
     */
    public function decide(Request $request, EirAccountingRule $rule, AccountingRuleService $rules)
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,reject,withdraw,propose_switch,approve_switch,reject_switch'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $user = (int) auth()->id();
        try {
            $message = match ($data['action']) {
                'approve' => ($rules->approve($rule, $user, $data['reason']) ? 'Rule approved; the fee matcher uses it from now on.' : ''),
                'reject' => ($rules->reject($rule, $user, $data['reason']) ? 'Rule rejected.' : ''),
                'withdraw' => ($rules->withdraw($rule, $user, $data['reason']) ? 'Rule withdrawn.' : ''),
                'propose_switch' => ($rules->proposeSwitch($rule, ! $rule->active, $user, $data['reason']) ? 'Switching the rule ' . ($rule->active ? 'off' : 'on') . ' is proposed; a second person approves it.' : ''),
                'approve_switch' => ($rules->approveSwitch($rule, $user, $data['reason'])->active ? 'Rule switched on.' : 'Rule switched off.'),
                'reject_switch' => ($rules->rejectSwitch($rule, $user, $data['reason']) ? 'The proposed switch is rejected; the rule stays as it was.' : ''),
            };
        } catch (\InvalidArgumentException|\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'fee_type' => ['nullable', 'in:arrangement,legal,appraisal,default,levy,other'],
            'description_contains' => ['nullable', 'string', 'max:255'],
            'gl_account_ref' => ['nullable', 'string', 'max:50'],
            'cashflow_direction' => ['nullable', 'in:RECEIVED,PAID'],
            'proposed_integral' => ['required', 'boolean'],
            'rationale' => ['required', 'string', 'max:2000'],
            'priority' => ['required', 'integer', 'min:1', 'max:65535'],
            'active' => ['required', 'boolean'],
        ]);
    }
}
