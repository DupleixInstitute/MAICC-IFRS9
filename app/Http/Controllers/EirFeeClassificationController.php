<?php

namespace App\Http\Controllers;

use App\Models\ContractFee;
use App\Models\EirAccountingRule;
use App\Models\EirFeeClassificationEvent;
use App\Services\AuditLoggerService;
use App\Services\Eir\FeeRuleMatcher;
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
            // Drives the re-apply prompt: an operator needs to see that pending
            // lines carry no suggestion before being told to use rule mode.
            'ruleCoverage' => [
                'pending' => ContractFee::where('classification_status', 'PENDING')->count(),
                'pending_matched' => ContractFee::where('classification_status', 'PENDING')->whereNotNull('suggested_rule_id')->count(),
                'approved_rules' => EirAccountingRule::where('active', true)->whereNotNull('approved_at')->count(),
                'draft_rules' => EirAccountingRule::where('active', true)->whereNull('approved_at')->count(),
            ],
        ]);
    }

    /**
     * Re-applies the approved rulebook to PENDING lines already on file.
     *
     * Deliberately a separate action rather than a side effect of approving a
     * rule: approving one rule should not silently re-tag thousands of fee
     * lines. The operator triggers it and sees what moved.
     */
    public function rematch(FeeRuleMatcher $matcher)
    {
        $result = $matcher->sweepPending();

        AuditLoggerService::log('EIR Fee Rules Re-applied', ContractFee::class, null, ['meta' => $result]);

        if ($result['examined'] === 0) {
            return back()->with('success', 'No pending fee lines to re-match.');
        }

        $message = "Re-applied the rulebook to {$result['examined']} pending line(s): "
            . "{$result['matched']} matched, {$result['unmatched']} still unmatched, "
            . "{$result['changed']} suggestion(s) updated.";
        if ($result['left_alone'] > 0) {
            $message .= " {$result['left_alone']} already-classified line(s) were left untouched.";
        }

        return back()->with('success', $message);
    }

    /**
     * Two modes.
     *
     * MANUAL applies one treatment to every selected line — correct when a
     * human has looked at the selection and judged it as a set.
     *
     * RULE applies each line's OWN matched rule, which is what makes the
     * rulebook worth authoring: a mixed selection of arrangement fees and
     * default charges resolves correctly in a single action instead of
     * everything inheriting whichever boolean was clicked. A line with no
     * matched rule is refused rather than defaulted — there is no rule to
     * apply, and guessing is what the rulebook exists to stop. That refusal is
     * absolute and not subject to the admin override, because it is a data
     * question rather than a segregation-of-duties one.
     */
    public function classify(Request $request)
    {
        $data = $request->validate([
            'fee_ids' => ['required', 'array', 'min:1'],
            'fee_ids.*' => ['integer', 'exists:contract_fees,id'],
            'mode' => ['nullable', 'in:manual,rule'],
            'integral' => ['required_if:mode,manual', 'nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:2000'],
            'accounting_rule_id' => ['nullable', 'exists:eir_accounting_rules,id'],
        ]);

        $byRule = ($data['mode'] ?? 'manual') === 'rule';
        $applied = 0;
        $refused = [];

        DB::transaction(function () use ($data, $byRule, &$applied, &$refused) {
            $fees = ContractFee::whereIn('id', $data['fee_ids'])->lockForUpdate()->get();
            foreach ($fees as $fee) {
                if ($fee->classification_status === 'REVIEWED') continue;

                if ($byRule && $fee->suggested_rule_id === null) {
                    $refused[] = $fee->contract_id . ' / ' . $fee->fee_type;
                    continue;
                }

                $integral = $byRule ? (bool) $fee->suggested_integral : (bool) $data['integral'];
                $ruleId = $byRule ? $fee->suggested_rule_id : ($data['accounting_rule_id'] ?? null);
                $reason = $byRule
                    ? $data['reason'] . ' [applied by rule: ' . ($fee->suggestedRule?->name ?? 'unknown') . ']'
                    : $data['reason'];

                $fee->update([
                    'integral' => $integral,
                    'classification_status' => 'CLASSIFIED',
                    'classification_reason' => $reason,
                    'classified_by' => auth()->id(),
                    'classified_at' => now(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);
                EirFeeClassificationEvent::create([
                    'contract_fee_id' => $fee->id, 'action' => 'CLASSIFIED',
                    'integral' => $integral, 'reason' => $reason,
                    'accounting_rule_id' => $ruleId,
                    'performed_by' => auth()->id(),
                ]);
                $applied++;
            }
        });

        AuditLoggerService::log('EIR Fees Classified', ContractFee::class, null, ['meta' => [
            'fee_ids' => $data['fee_ids'], 'mode' => $byRule ? 'RULE' : 'MANUAL',
            'integral' => $byRule ? null : $data['integral'],
            'applied' => $applied, 'refused' => count($refused),
        ]]);

        if ($applied === 0 && $refused !== []) {
            return back()->withErrors(['fee_ids' => 'No line could be classified: none of the selected lines has a matching rule. Re-apply the rulebook, or classify them manually.']);
        }

        $message = $applied . ' fee/cost line(s) classified' . ($byRule ? ' using their matched rules.' : '.');
        if ($refused !== []) {
            $message .= ' ' . count($refused) . ' line(s) were skipped for having no matching rule: '
                . implode(', ', array_slice($refused, 0, 5)) . (count($refused) > 5 ? ' ...' : '');
        }

        return back()->with('success', $message);
    }

    public function review(Request $request)
    {
        $data = $request->validate(['fee_ids' => ['required', 'array', 'min:1'], 'fee_ids.*' => ['integer', 'exists:contract_fees,id']]);
        $fees = ContractFee::whereIn('id', $data['fee_ids'])->where('classification_status', 'CLASSIFIED')->get();
        $own = $fees->where('classified_by', auth()->id());
        $adminOverride = (bool) auth()->user()?->hasRole('admin');
        if ($own->isNotEmpty() && ! $adminOverride) return back()->withErrors(['fee_ids' => 'A classifier cannot review their own decision.']);

        DB::transaction(function () use ($fees) {
            foreach ($fees as $fee) {
                $fee->update(['classification_status' => 'REVIEWED', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
                EirFeeClassificationEvent::create(['contract_fee_id' => $fee->id, 'action' => 'REVIEWED', 'integral' => $fee->integral, 'reason' => $fee->classification_reason, 'performed_by' => auth()->id()]);
            }
        });
        AuditLoggerService::log('EIR Fee Classifications Reviewed', ContractFee::class, null, [
            'meta' => [
                'fee_ids' => $fees->pluck('id')->all(),
                'maker_checker_override' => $adminOverride,
            ],
        ]);
        return back()->with('success', $fees->count() . ' classification(s) approved.');
    }
}
