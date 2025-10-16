<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CurrenciesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:currencies.index'])->only(['index', 'show']);
        $this->middleware(['permission:currencies.create'])->only(['create','store']);
        $this->middleware(['permission:currencies.update'])->only(['edit','update']);
        $this->middleware(['permission:currencies.destroy'])->only(['destroy']);
    }
    public function index()
    {
        $currencies = Currency::filter(\request()->only('search'))
            ->paginate(20);
        return Inertia::render('Currencies/Index', [
            'filters' => \request()->all('search', 'active'),
            'currencies' => $currencies,
        ]);
    }

    public function create()
    {

        return Inertia::render('Currencies/Create', [

        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required'],
        ]);
        $currency = new Currency();
        $currency->name = $request->name;
        $currency->code = $request->code;
        $currency->symbol = $request->symbol;
        $currency->decimals = $request->decimals;
        $currency->xrate = $request->xrate;
        $currency->international_code = $request->international_code;
        $currency->active = $request->has('active')?1:0;
        $currency->save();
        activity()
            ->performedOn($currency)
            ->log('Create Currency');
        return redirect()->route('currencies.index')->with('success', 'Currency created successfully.');
    }

    public function show(Currency $currency)
    {

        return Inertia::render('Currencies/Show', [
            'currency' => $currency
        ]);
    }

    public function edit(Currency $currency)
    {
        return Inertia::render('Currencies/Edit', [
            'currency' => $currency,
        ]);
    }

    public function update(Request $request, Currency $currency)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Updating the demo currency is not allowed.');
        }
        $request->validate([
            'name' => ['required'],
        ]);
        $currency->name = $request->name;
        $currency->code = $request->code;
        $currency->symbol = $request->symbol;
        $currency->decimals = $request->decimals;
        $currency->xrate = $request->xrate;
        $currency->international_code = $request->international_code;
        $currency->active = $request->has('active')?1:0;
        $currency->save();
        activity()
            ->performedOn($currency)
            ->log('Update Currency');
        return redirect()->route('currencies.index')->with('success', 'Currency updated successfully.');
    }

    public function destroy(Currency $currency)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Deleting the demo currency is not allowed.');
        }
        $currency->delete();
        activity()
            ->performedOn($currency)
            ->log('Delete Currency');
        return redirect()->route('currencies.index')->with('success', 'Currency deleted successfully.');
    }
}
