<?php

namespace App\Http\Controllers;

use App\Models\EirAccountingRule;
use App\Services\AuditLoggerService;
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
            'rules' => EirAccountingRule::with(['creator:id,name', 'approver:id,name'])
                ->orderBy('priority')->orderBy('name')->get(),
            'feeTypes' => ['arrangement', 'legal', 'appraisal', 'default', 'levy', 'other'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $rule = EirAccountingRule::create($data + ['created_by' => auth()->id()]);
        AuditLoggerService::log('EIR Accounting Rule Created', EirAccountingRule::class, $rule->id, ['new_values' => $rule->toArray()]);
        return back()->with('success', 'Accounting rule created and awaiting approval.');
    }

    public function update(Request $request, EirAccountingRule $rule)
    {
        $old = $rule->toArray();
        $rule->update($this->validated($request) + ['approved_by' => null, 'approved_at' => null]);
        AuditLoggerService::log('EIR Accounting Rule Updated', EirAccountingRule::class, $rule->id, ['old_values' => $old, 'new_values' => $rule->fresh()->toArray()]);
        return back()->with('success', 'Rule updated; approval has been reset.');
    }

    public function approve(EirAccountingRule $rule)
    {
        $rule->update(['approved_by' => auth()->id(), 'approved_at' => now()]);
        AuditLoggerService::log('EIR Accounting Rule Approved', EirAccountingRule::class, $rule->id);
        return back()->with('success', 'Accounting rule approved.');
    }

    public function toggle(EirAccountingRule $rule)
    {
        $rule->update(['active' => ! $rule->active]);
        AuditLoggerService::log('EIR Accounting Rule Status Changed', EirAccountingRule::class, $rule->id, ['new_values' => ['active' => $rule->active]]);
        return back()->with('success', $rule->active ? 'Rule activated.' : 'Rule deactivated.');
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
