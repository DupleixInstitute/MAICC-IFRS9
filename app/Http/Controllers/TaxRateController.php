<?php

namespace App\Http\Controllers;

use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TaxRateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:tax_rates.index'])->only(['index', 'show']);
        $this->middleware(['permission:tax_rates.create'])->only(['create','store']);
        $this->middleware(['permission:tax_rates.update'])->only(['edit','update']);
        $this->middleware(['permission:tax_rates.destroy'])->only(['destroy']);
    }
    public function index()
    {
        $taxRates = TaxRate::filter(\request()->only('search'))
            ->paginate(20);
        return Inertia::render('TaxRates/Index', [
            'filters' => \request()->all('search', 'active'),
            'taxRates' => $taxRates,
        ]);
    }

    public function create()
    {

        return Inertia::render('TaxRates/Create', [

        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required'],
            'type' => ['required'],
        ]);
        $taxRate = new TaxRate();
        $taxRate->name = $request->name;
        $taxRate->type = $request->type;
        $taxRate->amount = $request->amount;
        $taxRate->description = $request->description;
        $taxRate->active = $request->active?1:0;
        $taxRate->save();
        activity()
            ->performedOn($taxRate)
            ->log('Create TaxRate');
        return redirect()->route('tax_rates.index')->with('success', 'TaxRate created successfully.');
    }

    public function show(TaxRate $taxRate)
    {

        return Inertia::render('TaxRates/Show', [
            'taxRate' => $taxRate
        ]);
    }

    public function edit(TaxRate $taxRate)
    {
        return Inertia::render('TaxRates/Edit', [
            'taxRate' => $taxRate,
        ]);
    }

    public function update(Request $request, TaxRate $taxRate)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Updating the demo taxRate is not allowed.');
        }
        $request->validate([
            'name' => ['required'],
            'type' => ['required'],
        ]);
        $taxRate->name = $request->name;
        $taxRate->type = $request->type;
        $taxRate->amount = $request->amount;
        $taxRate->description = $request->description;
        $taxRate->active = $request->active?1:0;
        $taxRate->save();
        activity()
            ->performedOn($taxRate)
            ->log('Update TaxRate');
        return redirect()->route('tax_rates.index')->with('success', 'TaxRate updated successfully.');
    }

    public function destroy(TaxRate $taxRate)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Deleting the demo taxRate is not allowed.');
        }
        $taxRate->delete();
        activity()
            ->performedOn($taxRate)
            ->log('Delete TaxRate');
        return redirect()->route('tax_rates.index')->with('success', 'TaxRate deleted successfully.');
    }
}
