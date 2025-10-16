<?php

namespace App\Http\Controllers;

use App\Events\InvoiceCreated;
use App\Events\InvoiceReconciled;
use App\Models\Branch;
use App\Models\CourseMaterial;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\LoanProductScoringAttribute;
use App\Models\MemberRelationship;
use App\Models\PaymentType;
use App\Models\Setting;
use App\Models\Tariff;
use App\Models\TaxRate;
use App\Notifications\InvoiceGeneratedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use PDF;

class InvoicesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:billing.invoices.index'])->only(['index', 'show']);
        $this->middleware(['permission:billing.invoices.create'])->only(['create', 'store']);
        $this->middleware(['permission:billing.invoices.update'])->only(['edit', 'update']);
        $this->middleware(['permission:billing.invoices.reconcile'])->only(['reconcile', 'updateReconcile']);
        $this->middleware(['permission:billing.invoices.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $invoices = Invoice::with(['doctor', 'member', 'coPayer', 'invoiceItems', 'currency'])
            ->filter(\request()->only('search', 'status', 'co_payer_id', 'sponsor', 'doctor_id', 'member_id', 'date_range', 'currency_id'))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('Invoices/Index', [
            'filters' => \request()->all('search', 'status', 'co_payer_id', 'sponsor', 'doctor_id', 'member_id', 'date_range', 'currency_id'),
            'invoices' => $invoices,
            'coPayers' => CourseMaterial::get()->map(function ($item) {
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

    public function show(Invoice $invoice)
    {
        $invoice->load(['doctor', 'member', 'coPayer', 'invoiceItems', 'invoicePayments', 'invoicePayments.paymentType', 'currency']);
        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
            'printInvoicePayment' => request('print_invoice_payment') ? true : false,
            'invoicePaymentID' => request('invoice_payment_id'),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    public function create()
    {

        return Inertia::render('Invoices/Create', [
            'invoiceAllowEditingExchangeRate' => Setting::where('setting_key', 'invoice_edit_exchange_rate')->first()->setting_value,
            'invoiceTerms' => Setting::where('setting_key', 'invoice_terms_and_conditions')->first()->setting_value,
            'invoiceDueAfterDays' => Setting::where('setting_key', 'invoice_due_after_days')->first()->setting_value,
            'taxRates' => TaxRate::get(),
            'branches' => Branch::get(),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                    'xrate' => $currency->xrate,
                ];
            }),
            'coPayers' => CourseMaterial::get()->transform(function ($coPayer) {
                return [
                    'value' => $coPayer->id,
                    'label' => $coPayer->name,
                ];
            }),
            'memberRelationships' => MemberRelationship::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'defaultTariffs' => Tariff::where('auto_select', 1)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => ['required', 'date'],
            'currency_id' => ['required'],
            'items' => ['required', 'array'],
            'sponsor' => ['required'],
            'co_payer_id' => ['required_if:sponsor,co_payer'],
            'co_payer_membership_number' => ['required_if:sponsor,co_payer'],
            'co_payer_member_relationship_id' => ['required_if:sponsor,co_payer'],
            'co_payer_member_name' => ['required_if:sponsor,co_payer'],
            'co_payer_suffix' => ['required_if:sponsor,co_payer'],
        ]);
        $invoiceAllowEditingExchangeRate = Setting::where('setting_key', 'invoice_edit_exchange_rate')->first()->setting_value;
        $currency = Currency::find($request->currency_id);
        $invoice = new Invoice();
        $invoice->created_by_id = Auth::id();
        $invoice->currency_id = $request->currency_id;
        //set xrate
        if ($invoiceAllowEditingExchangeRate === 'yes') {
            $invoice->xrate = $request->xrate ?? $currency->xrate;
        } else {
            $invoice->xrate = $currency->xrate;
        }
        $invoice->member_id = $request->member_id;
        $invoice->doctor_id = $request->doctor_id;
        $invoice->co_payer_id = $request->co_payer_id;
        $invoice->co_payer_membership_number = $request->co_payer_membership_number;
        $invoice->co_payer_member_relationship_id = $request->co_payer_member_relationship_id;
        $invoice->co_payer_member_name = $request->co_payer_member_name;
        $invoice->co_payer_suffix = $request->co_payer_suffix;
        $invoice->date = $request->date;
        $invoice->due_date = $request->due_date ?: null;
        $invoice->sponsor = $request->sponsor;
        $invoice->tax_total = $request->tax_total;
        $invoice->amount = $request->amount;
        $invoice->cash_amount = $request->cash_amount;
        $invoice->co_payer_amount = $request->co_payer_amount;
        $invoice->cash_balance = $request->cash_amount;
        $invoice->co_payer_balance = $request->co_payer_amount;
        $invoice->balance = $request->amount;
        $invoice->description = $request->description;
        $invoice->terms = $request->terms;
        $invoice->client_notes = $request->client_notes;
        $invoice->admin_notes = $request->admin_notes;
        $invoice->save();
        //generate invoice reference
        $invoice->reference = generate_reference($invoice->id);
        $invoice->save();
        //invoice items
        foreach ($request->items as $key) {
            $tariff = Tariff::find($key['tariff_id']);
            $invoiceItem = new InvoiceItem();
            $invoiceItem->invoice_id = $invoice->id;
            $invoiceItem->xrate = $invoice->xrate;
            $invoiceItem->tariff_id = $key['tariff_id'];
            $invoiceItem->qty = $key['qty'];
            $invoiceItem->base_currency_unit_cost = $tariff->amount;
            $invoiceItem->base_currency_co_payer_amount = $tariff->co_payer_amount;
            $invoiceItem->base_currency_cash_amount = $tariff->cash_amount;
            $invoiceItem->unit_cost = $key['unit_cost'];
            $invoiceItem->co_payer_amount = $key['co_payer_amount'];
            $invoiceItem->cash_amount = $key['cash_amount'];
            $invoiceItem->tax_total = $key['tax_total'];
            $invoiceItem->total = $key['total'];
            $invoiceItem->type = $key['type'];
            $invoiceItem->is_claimable = $key['is_claimable'] ? 1 : 0;
            $invoiceItem->name = $key['name'];
            $invoiceItem->save();
        }

        $invoice->save();
        event(new InvoiceCreated($invoice));
        activity()
            ->performedOn($invoice)
            ->log('Create Invoice');
        return redirect()->route('billing.invoices.show', $invoice->id)->with('success', 'Invoice created successfully.');

    }

    public function edit(Invoice $invoice)
    {
        $invoice->load(['consultation', 'consultation.procedures', 'consultation.procedures.tariff', 'doctor', 'member', 'coPayer', 'invoiceItems', 'member.coPayers', 'member.coPayers.coPayer']);
        return Inertia::render('Invoices/Edit', [
            'invoice' => $invoice,
            'invoiceAllowEditingExchangeRate' => Setting::where('setting_key', 'invoice_edit_exchange_rate')->first()->setting_value,
            'taxRates' => TaxRate::get(),
            'branches' => Branch::get(),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                    'xrate' => $currency->xrate,
                ];
            }),
            'coPayers' => CourseMaterial::get()->transform(function ($coPayer) {
                return [
                    'value' => $coPayer->id,
                    'label' => $coPayer->name,
                ];
            }),
            'memberRelationships' => MemberRelationship::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        $request->validate([
            'date' => ['required', 'date'],
            'currency_id' => ['required'],
            'items' => ['required', 'array'],
            'sponsor' => ['required'],
            'co_payer_id' => ['required_if:sponsor,co_payer'],
            'co_payer_membership_number' => ['required_if:sponsor,co_payer'],
            'co_payer_member_relationship_id' => ['required_if:sponsor,co_payer'],
            'co_payer_member_name' => ['required_if:sponsor,co_payer'],
            'co_payer_suffix' => ['required_if:sponsor,co_payer'],
        ]);
        $invoice->currency_id = $request->currency_id;
        //set xrate
        $invoiceAllowEditingExchangeRate = Setting::where('setting_key', 'invoice_edit_exchange_rate')->first()->setting_value;
        $currency = Currency::find($request->currency_id);
        if ($invoiceAllowEditingExchangeRate === 'yes') {
            $invoice->xrate = $request->xrate ?? $currency->xrate;
        } else if (!$invoice->originalIsEquivalent('currency_id')) {
            $invoice->xrate = $currency->xrate;
        }
        $invoice->member_id = $request->member_id;
        $invoice->doctor_id = $request->doctor_id;
        $invoice->date = $request->date;
        $invoice->due_date = $request->due_date ?: null;
        $invoice->sponsor = $request->sponsor;
        $invoice->co_payer_id = $request->co_payer_id;
        $invoice->co_payer_membership_number = $request->co_payer_membership_number;
        $invoice->co_payer_member_relationship_id = $request->co_payer_member_relationship_id;
        $invoice->co_payer_member_name = $request->co_payer_member_name;
        $invoice->co_payer_suffix = $request->co_payer_suffix;
        $invoice->tax_total = $request->tax_total;
        $invoice->amount = $request->amount;
        $invoice->cash_amount = $request->cash_amount;
        $invoice->co_payer_amount = $request->co_payer_amount;
        $invoice->balance = $invoice->amount - $invoice->invoicePayments->sum('amount');
        $invoice->cash_balance = $request->cash_amount - $invoice->invoicePayments->where('paid_by', 'member')->sum('amount');
        $invoice->co_payer_balance = $request->co_payer_amount - $invoice->invoicePayments->where('paid_by', 'co_payer')->sum('amount');
        $invoice->description = $request->description;
        $invoice->terms = $request->terms;
        $invoice->client_notes = $request->client_notes;
        $invoice->admin_notes = $request->admin_notes;
        if ($invoice->balance <= 0) {
            $invoice->status = 'paid';
        } elseif ($invoice->balance > 0 && $invoice->balance < $invoice->amount) {
            $invoice->status = 'partially_paid';
        } else {
            $invoice->status = 'unpaid';
        }
        $invoice->save();
        //invoice items
        $existing = $invoice->invoiceItems->pluck('id')->all();
        foreach ($request->items as $key) {
            $tariff = Tariff::find($key['tariff_id']);
            if (in_array($key['id'], $existing)) {
                $invoiceItem = InvoiceItem::find($key['id']);
            } else {
                $invoiceItem = new InvoiceItem();
                $invoiceItem->tariff_id = $key['tariff_id'];
                $invoiceItem->invoice_id = $invoice->id;
            }
            $invoiceItem->base_currency_unit_cost = $tariff->amount;
            $invoiceItem->base_currency_co_payer_amount = $tariff->co_payer_amount;
            $invoiceItem->base_currency_cash_amount = $tariff->cash_amount;
            $invoiceItem->tariff_code = $tariff->code;
            $invoiceItem->xrate = $invoice->xrate;
            $invoiceItem->qty = $key['qty'];
            $invoiceItem->unit_cost = $key['unit_cost'];
            $invoiceItem->co_payer_amount = $key['co_payer_amount'];
            $invoiceItem->cash_amount = $key['cash_amount'];
            $invoiceItem->tax_total = $key['tax_total'];
            $invoiceItem->total = $key['total'];
            $invoiceItem->type = $key['type'];
            $invoiceItem->is_claimable = $key['is_claimable'] ? 1 : 0;
            $invoiceItem->name = $key['name'];
            $invoiceItem->save();
            foreach ($existing as $k => $v) {
                if ($v == $key['id']) {
                    unset($existing[$k]);
                }
            }
        }
        foreach ($existing as $key) {
            InvoiceItem::destroy($key);
        }
        $invoice->save();
        activity()
            ->performedOn($invoice)
            ->log('Update Invoice');
        return redirect()->route('billing.invoices.show', $invoice->id)->with('success', 'Invoice updated successfully.');

    }

    public function destroy(Invoice $invoice)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Deleting the demo invoice is not allowed.');
        }
        $invoice->invoiceItems()->delete();
        $invoice->invoicePayments()->delete();
        $invoice->delete();
        activity()
            ->performedOn($invoice)
            ->log('Delete Invoice');
        return redirect()->route('billing.invoices.index')->with('success', 'Invoice deleted successfully.');

    }

    public function reconcile(Invoice $invoice)
    {
        $invoice->load(['invoiceItems'=>function($query){
            $query->where('co_payer_amount','>',0);
        }]);


        return Inertia::render('Invoices/Reconcile', [
            'invoice' => $invoice,
            'paymentTypes' => PaymentType::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'coPayers' => CourseMaterial::get()->transform(function ($coPayer) {
                return [
                    'value' => $coPayer->id,
                    'label' => $coPayer->name,
                ];
            }),
        ]);
    }

    public function updateReconcile(Request $request, Invoice $invoice)
    {
        $invoice->load(['invoiceItems']);
        if ($request->record_payment) {
            $request->validate([
                'date' => ['required', 'date'],
                'amount' => ['required'],
                'payment_type_id' => ['required'],
            ]);
            $invoicePayment = new InvoicePayment();
            $invoicePayment->created_by_id = Auth::id();
            $invoicePayment->invoice_id = $invoice->id;
            $invoicePayment->currency_id = $request->currency_id ?: $invoice->currency_id;
            $invoicePayment->member_id = $invoice->member_id;
            $invoicePayment->paid_by = 'co_payer';
            $invoicePayment->co_payer_id = $request->co_payer_id;
            $invoicePayment->date = $request->date;
            $invoicePayment->receipt = $request->receipt;
            $invoicePayment->payment_type_id = $request->payment_type_id;
            $invoicePayment->amount = $request->amount;
            $invoicePayment->description = $request->description;
            $invoicePayment->save();
            $invoice->refresh();
            $invoice->balance = $invoice->amount - $invoice->invoicePayments->sum('amount');
            if ($invoice->balance <= 0) {
                $invoice->status = 'paid';
            } elseif ($invoice->balance > 0 && $invoice->balance < $invoice->amount) {
                $invoice->status = 'partially_paid';
            } else {
                $invoice->status = 'unpaid';
            }
            $invoice->save();
        }
        foreach ($request->items as $item) {
            $invoiceItem = InvoiceItem::find($item['id']);
            $invoiceItem->award = $item['award'];
            $invoiceItem->shortfall = $item['shortfall'] ?? null;
            $invoiceItem->shortfall_reason = $item['shortfall_reason'] ?? null;
            $invoiceItem->save();
        }
        $invoice->refresh();
        $invoice->reconciled = 1;

        //update member cash and copayer amount
        $invoice->co_payer_amount = $invoice->invoiceItems->sum('award');
        $invoice->shortfall = $invoice->invoiceItems->sum('shortfall');
        $invoice->cash_amount = $invoice->amount - $invoice->co_payer_amount;
        $invoice->cash_balance = $invoice->cash_amount - $invoice->invoicePayments->where('paid_by', 'member')->sum('amount');
        $invoice->co_payer_balance = $invoice->co_payer_amount - $invoice->invoicePayments->where('paid_by', 'co_payer')->sum('amount');
        $invoice->save();
        $claim=$invoice->claim;
        $claim->accepted_amount=$invoice->co_payer_amount;
        $claim->shortfall= $invoice->shortfall;
        $claim->reconciled = 1;
        if($invoice->shortfall>0 && $claim->status!=='partially_accepted'){
            $claim->status ='partially_accepted';
        }
        $claim->save();
        event(new InvoiceReconciled($invoice));
        activity()
            ->performedOn($invoice)
            ->log('Reconciled Invoice');
        $params = ['invoice' => $invoice->id];
        return redirect()->route('billing.invoices.show', $params)->with('success', 'Invoice reconciled successfully.');

    }

    public function pdfInvoice(Request $request, Invoice $invoice)
    {
        $invoice->load(['doctor', 'member', 'coPayer', 'invoiceItems', 'invoicePayments', 'invoicePayments.paymentType', 'currency']);
        $pdf = PDF::loadView('invoices.print', ['invoice' => $invoice]);
        return $pdf->download('invoice.pdf');

    }

    public function printInvoice(Request $request, Invoice $invoice)
    {
        $invoice->load(['doctor', 'member', 'coPayer', 'invoiceItems', 'invoicePayments', 'invoicePayments.paymentType', 'currency']);
        return View::make('invoices.print', ['invoice' => $invoice])->render();

    }

    public function pdfClaimForm(Request $request, Invoice $invoice)
    {
        $invoice->load(['doctor', 'member', 'coPayer', 'invoiceItems', 'invoicePayments', 'invoicePayments.paymentType', 'currency']);
        $pdf = PDF::loadView('invoices.print_claim_form', ['invoice' => $invoice]);
        return $pdf->download('claim.pdf');

    }

    public function printClaimForm(Request $request, Invoice $invoice)
    {
        $invoice->load(['doctor', 'member', 'coPayer', 'invoiceItems', 'invoicePayments', 'invoicePayments.paymentType', 'currency']);
        return View::make('invoices.print_claim_form', ['invoice' => $invoice])->render();

    }

    public function email(Request $request, Invoice $invoice)
    {
        if ($invoice->member->email) {
            Notification::route('mail', $invoice->member->email)->notify(new InvoiceGeneratedNotification($invoice));
            return redirect()->back()->with('success', 'Email sent.');
        }
        return redirect()->back()->with('error', 'Member has no email set.');
    }
}
