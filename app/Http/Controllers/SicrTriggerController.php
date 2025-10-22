<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Client;
use App\Models\LoanBook;
use App\Models\SicrItem;
use App\Models\SicrGroup;
use App\Models\SicrTrigger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SicrTriggerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:settings');
    }

    public function index()
    {
        $groups = SicrGroup::orderBy('name')->get(['id','name']);
        $items = SicrItem::orderBy('name')->get(['id','name','group_id']);
        $triggers = SicrTrigger::with(['group','item','user'])->orderByDesc('created_at')->paginate(20);
        return Inertia::render('StageingRules/Triggers', [
            'groups' => $groups,
            'items' => $items,
            'triggers' => $triggers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group_id' => ['required','exists:finance_sicr_groups,id'],
            'item_id' => ['required','exists:finance_sicr_items,id'],
            'customer_id' => ['nullable','string','max:255'],
            'account_number' => ['required','string','max:255'],
            'affect_all' => ['boolean'],
            'reason' => ['required','string'],
            'effective_period' => ['nullable','date'],
            'update_loan_book_now' => ['boolean'],
            'attachment' => ['nullable','file','max:5120'],
        ]);

        // Check if customer is active (if customer_id is provided)
        if (!empty($data['customer_id'])) {
            $activeCustomer = LoanBook::where('external_identity_id', $data['customer_id'])
                ->whereNotNull('external_identity_id')
                ->where('external_identity_id', '!=', '')
                ->first();
                
            if (!$activeCustomer) {
                return back()->withErrors([
                    'customer_id' => 'Customer is not active or does not exist in the loan book.'
                ])->withInput();
            }
        }

        $path = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('sicr_attachments', 'public');
        }

        $trigger = SicrTrigger::create([
            'group_id' => $data['group_id'],
            'item_id' => $data['item_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'account_number' => $data['account_number'],
            'affect_all' => $data['affect_all'] ?? false,
            'reason' => $data['reason'],
            'effective_period' => $data['effective_period'] ?? null,
            'attachment_path' => $path,
            'triggered_by' => Auth::id(),
            'last_update' => now(),
        ]);

        // If "Update Loan Book Now" is checked, apply changes immediately
        if ($data['update_loan_book_now'] ?? false) {
            $this->updateLoanBookForTrigger($trigger);
        }

        return back()->with('success', 'SICR trigger recorded successfully.');
    }

    /**
     * Update loan book from trigger modal
     */
    public function updateLoanBook(Request $request, $id)
    {
        $trigger = SicrTrigger::findOrFail($id);
        
        $data = $request->validate([
            'effective_period' => ['required','date'],
        ]);

        // Check if customer is active (if customer_id is provided)
        if ($trigger->customer_id) {
            $activeCustomer = LoanBook::where('external_identity_id', $trigger->customer_id)
                ->whereNotNull('external_identity_id')
                ->where('external_identity_id', '!=', '')
                ->first();
                
            if (!$activeCustomer) {
                return back()->withErrors([
                    'customer' => 'Customer is not active or does not exist in the loan book.'
                ]);
            }
        }

        $trigger->update([
            'effective_period' => $data['effective_period'],
            'last_update' => now(),
        ]);

        $this->updateLoanBookForTrigger($trigger);

        return back()->with('success', 'Loan book updated successfully for SICR trigger.');
    }

    /**
     * Remove/deactivate a trigger alert
     */
    public function removeAlert($id)
    {
        $trigger = SicrTrigger::findOrFail($id);
        
        $trigger->update([
            'removal_date' => now(),
            'last_update' => now(),
        ]);

        // Optionally reset SICR trigger in loan book
        if ($trigger->affect_all && $trigger->customer_id) {
            // Reset for all customer loans
            $loans = LoanBook::where('external_identity_id', 'LIKE', "%{$trigger->customer_id}%")
                ->orWhere('contract_id', 'LIKE', "%{$trigger->customer_id}%")
                ->get();
            
            foreach ($loans as $loan) {
                $loan->sicr_trigger = 0;
                $loan->save();
            }
        } else {
            // Reset for single account
            $loan = LoanBook::where('contract_id', $trigger->account_number)
                ->orWhere('external_identity_id', $trigger->account_number)
                ->orderByDesc('reporting_period')
                ->first();
            
            if ($loan) {
                $loan->sicr_trigger = 0;
                $loan->save();
            }
        }

        return back()->with('success', 'Alert removed successfully.');
    }

    /**
     * Get customers for autocomplete/search
     */
    public function getCustomers(Request $request)
    {
        $search = $request->get('search', '');
        
        $customers = LoanBook::select('external_identity_id', DB::raw('COUNT(*) as loan_count'))
            ->distinct()
            ->when($search, function ($query, $search) {
                return $query->where('external_identity_id', 'LIKE', "%{$search}%");
            })
            ->whereNotNull('external_identity_id')
            ->where('external_identity_id', '!=', '')
            ->groupBy('external_identity_id')
            ->orderBy('external_identity_id')
            ->limit(20)
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->external_identity_id,
                    'external_identity_id' => $customer->external_identity_id,
                    'name' => $customer->external_identity_id, // Use ID as name for now
                    'loan_count' => $customer->loan_count
                ];
            });

        return response()->json(['customers' => $customers]);
    }

    /**
     * Core logic to update loan book based on trigger
     */
    private function updateLoanBookForTrigger(SicrTrigger $trigger)
    {
        if ($trigger->affect_all && $trigger->customer_id) {
            // Apply to all loans under this customer
            $loans = LoanBook::where('external_identity_id', 'LIKE', "%{$trigger->customer_id}%")
                ->orWhere('contract_id', 'LIKE', "%{$trigger->customer_id}%")
                ->get();
            
            foreach ($loans as $loan) {
                $this->applyTriggerToLoan($loan);
            }
        } else {
            // Apply to single account
            $loan = LoanBook::where('contract_id', $trigger->account_number)
                ->orWhere('external_identity_id', $trigger->account_number)
                ->orderByDesc('reporting_period')
                ->first();
            
            if ($loan) {
                $this->applyTriggerToLoan($loan);
            }
        }
    }

    /**
     * Apply SICR trigger logic to individual loan
     */
    private function applyTriggerToLoan(LoanBook $loan)
    {
        $loan->sicr_trigger = 1;
        
        // Qualitative stage conditional: if Stage 1, move to Stage 2
        $preStage = (int)($loan->ifrs9_stage_prequalitative ?? $loan->ifrs9_stage ?? 1);
        
        if ($preStage == 1) {
            $loan->ifrs9_stage_postqualitative = 2;
        }
        // If already Stage 2 or 3, no change needed
        
        $loan->save();
    }
}
