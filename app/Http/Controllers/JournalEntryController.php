<?php

namespace App\Http\Controllers;

use App\Events\ClaimBatchClosed;
use App\Events\ClaimBatchSent;
use App\Events\ClaimSent;
use App\Events\InvoiceClaimed;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\Claim;
use App\Models\ClaimBatch;
use App\Models\CourseMaterial;
use App\Models\Invoice;
use App\Models\PaymentDetail;
use App\Models\PaymentType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class JournalEntryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:accounting.journal_entries.index'])->only(['index', 'show']);
        $this->middleware(['permission:accounting.journal_entries.create'])->only(['create', 'store']);
        $this->middleware(['permission:accounting.journal_entries.update'])->only(['edit', 'update']);
        $this->middleware(['permission:accounting.journal_entries.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $journalEntries = JournalEntry::with(['branch', 'chartOfAccount', 'currency'])
            ->filter(\request()->only('search', 'branch_id', 'currency_id', 'chart_of_account_id','date_range', 'start_date', 'end_date'))
            ->orderBy('id', 'desc')
            ->paginate(20);
        return Inertia::render('JournalEntries/Index', [
            'journalEntries' => $journalEntries,
            'chartOfAccounts' => ChartOfAccount::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name . '(' . $item->gl_code . ')'
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'filters' => \request()->all('search', 'branch_id', 'currency_id', 'chart_of_account_id','date_range', 'start_date', 'end_date'),
        ]);
    }

    public function create()
    {

        return Inertia::render('JournalEntries/Create', [
            'chartOfAccounts' => ChartOfAccount::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name . '(' . $item->gl_code . ')'
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'amount' => ['required'],
            'credit' => ['required'],
            'debit' => ['required'],
            'currency_id' => ['required'],
            'date' => ['required', 'date']
        ]);
        $paymentDetail = new PaymentDetail();
        $paymentDetail->created_by_id = Auth::id();
        $paymentDetail->payment_type_id = $request->payment_type_id;
        $paymentDetail->transaction_type = 'journal_manual_entry';
        $paymentDetail->cheque_number = $request->cheque_number;
        $paymentDetail->receipt = $request->receipt;
        $paymentDetail->account_number = $request->account_number;
        $paymentDetail->bank_name = $request->bank_name;
        $paymentDetail->routing_code = $request->routing_code;
        $paymentDetail->save();
        //debit account
        $transaction_number = uniqid();
        $journalEntry = new JournalEntry();
        $journalEntry->created_by_id = Auth::id();
        $journalEntry->payment_detail_id = $paymentDetail->id;
        $journalEntry->transaction_number = $transaction_number;
        $journalEntry->branch_id = $request->branch_id;
        $journalEntry->currency_id = $request->currency_id;
        $journalEntry->chart_of_account_id = $request->debit;
        $journalEntry->transaction_type = 'manual_entry';
        $journalEntry->date = $request->date;
        $journalEntry->debit = $request->amount;
        $journalEntry->reference = $request->reference;
        $journalEntry->manual_entry = 1;
        $journalEntry->description = $request->description;
        $journalEntry->save();
        //credit account
        $journalEntry = new JournalEntry();
        $journalEntry->created_by_id = Auth::id();
        $journalEntry->transaction_number = $transaction_number;
        $journalEntry->payment_detail_id = $paymentDetail->id;
        $journalEntry->branch_id = $request->branch_id;
        $journalEntry->currency_id = $request->currency_id;
        $journalEntry->chart_of_account_id = $request->credit;
        $journalEntry->transaction_type = 'manual_entry';
        $journalEntry->date = $request->date;
        $journalEntry->credit = $request->amount;
        $journalEntry->reference = $request->reference;
        $journalEntry->manual_entry = 1;
        $journalEntry->description = $request->description;
        $journalEntry->save();
        activity()
            ->performedOn($journalEntry)
            ->log('Create  Journal Entry');
        return redirect()->route('accounting.journal_entries.index')->with('success', 'Journal Entry successfully.');

    }

    public function show(JournalEntry $journalEntry)
    {
        return Inertia::render('JournalEntries/Show', [
            'journalEntry' => $journalEntry
        ]);
    }


    public function destroy(JournalEntry $journalEntry)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Deleting the demo chart of account is not allowed.');
        }
        $journalEntry->delete();
        activity()
            ->performedOn($journalEntry)
            ->log('Delete Journal Entry');
        return redirect()->route('accounting.journal_entries.index')->with('success', 'Journal Entry deleted successfully.');
    }

}
