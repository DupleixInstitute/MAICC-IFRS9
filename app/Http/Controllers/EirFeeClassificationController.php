<?php

namespace App\Http\Controllers;

use App\Models\ContractFee;
use App\Models\EirAccountingRule;
use App\Models\EirFeeClassificationEvent;
use App\Services\AuditLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EirFeeClassificationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:settings']);
    }

    public function index(Request $request)
    {
        $query = ContractFee::query()->with('suggestedRule:id,name,proposed_integral');
        if ($request->filled('status')) $query->where('classification_status', $request->input('status'));
        if ($request->filled('fee_type')) $query->where('fee_type', $request->input('fee_type'));
        if ($request->filled('contract_id')) $query->where('contract_id', 'like', '%' . $request->input('contract_id') . '%');

        return Inertia::render('Eir/FeeClassification', [
            'fees' => $query->orderByRaw("CASE classification_status WHEN 'PENDING' THEN 1 WHEN 'CLASSIFIED' THEN 2 ELSE 3 END")
                ->orderByDesc('id')->paginate(30)->withQueryString(),
            'rules' => EirAccountingRule::where('active', true)->whereNotNull('approved_at')->orderBy('priority')->get(['id', 'name', 'proposed_integral']),
            'filters' => $request->only(['status', 'fee_type', 'contract_id']),
            'summary' => ContractFee::select('classification_status', DB::raw('COUNT(*) as line_count'), DB::raw('SUM(amount) as total_amount'))
                ->groupBy('classification_status')->get()->keyBy('classification_status'),
        ]);
    }

    public function classify(Request $request)
    {
        $data = $request->validate([
            'fee_ids' => ['required', 'array', 'min:1'],
            'fee_ids.*' => ['integer', 'exists:contract_fees,id'],
            'integral' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:2000'],
            'accounting_rule_id' => ['nullable', 'exists:eir_accounting_rules,id'],
        ]);

        DB::transaction(function () use ($data) {
            $fees = ContractFee::whereIn('id', $data['fee_ids'])->lockForUpdate()->get();
            foreach ($fees as $fee) {
                if ($fee->classification_status === 'REVIEWED') continue;
                $fee->update([
                    'integral' => $data['integral'],
                    'classification_status' => 'CLASSIFIED',
                    'classification_reason' => $data['reason'],
                    'classified_by' => auth()->id(),
                    'classified_at' => now(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);
                EirFeeClassificationEvent::create([
                    'contract_fee_id' => $fee->id, 'action' => 'CLASSIFIED',
                    'integral' => $data['integral'], 'reason' => $data['reason'],
                    'accounting_rule_id' => $data['accounting_rule_id'] ?? null,
                    'performed_by' => auth()->id(),
                ]);
            }
        });
        AuditLoggerService::log('EIR Fees Classified', ContractFee::class, null, ['meta' => ['fee_ids' => $data['fee_ids'], 'integral' => $data['integral']]]);
        return back()->with('success', count($data['fee_ids']) . ' fee/cost line(s) classified.');
    }

    public function review(Request $request)
    {
        $data = $request->validate(['fee_ids' => ['required', 'array', 'min:1'], 'fee_ids.*' => ['integer', 'exists:contract_fees,id']]);
        $fees = ContractFee::whereIn('id', $data['fee_ids'])->where('classification_status', 'CLASSIFIED')->get();
        $own = $fees->where('classified_by', auth()->id());
        if ($own->isNotEmpty()) return back()->withErrors(['fee_ids' => 'A classifier cannot review their own decision.']);

        DB::transaction(function () use ($fees) {
            foreach ($fees as $fee) {
                $fee->update(['classification_status' => 'REVIEWED', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
                EirFeeClassificationEvent::create(['contract_fee_id' => $fee->id, 'action' => 'REVIEWED', 'integral' => $fee->integral, 'reason' => $fee->classification_reason, 'performed_by' => auth()->id()]);
            }
        });
        AuditLoggerService::log('EIR Fee Classifications Reviewed', ContractFee::class, null, ['meta' => ['fee_ids' => $fees->pluck('id')->all()]]);
        return back()->with('success', $fees->count() . ' classification(s) approved.');
    }
}
