<?php

namespace App\Http\Controllers;

use App\Models\IndustryType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IndustryTypesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:industry_types.index'])->only(['index', 'show']);
        $this->middleware(['permission:industry_types.create'])->only(['create', 'store']);
        $this->middleware(['permission:industry_types.update'])->only(['edit', 'update']);
        $this->middleware(['permission:industry_types.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $types = IndustryType::filter(\request()->only('search'))
            ->paginate();
        return Inertia::render('IndustryTypes/Index', [
            'filters' => \request()->all('search'),
            'types' => $types,
        ]);
    }

    public function create()
    {

        return Inertia::render('IndustryTypes/Create', [

        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required'],
        ]);
        $type = new IndustryType();
        $type->name = $request->name;
        $type->current_ratio = $request->current_ratio;
        $type->quick_ratio = $request->quick_ratio;
        $type->debtor_days = $request->debtor_days;
        $type->creditor_days = $request->creditor_days;
        $type->working_capital = $request->working_capital;
        $type->turnover_growth = $request->turnover_growth;
        $type->gross_profit = $request->gross_profit;
        $type->operating_profit_margin = $request->operating_profit_margin;
        $type->net_profit_margin = $request->net_profit_margin;
        $type->return_on_equity = $request->return_on_equity;
        $type->return_on_assets = $request->return_on_assets;
        $type->return_on_investment = $request->return_on_investment;
        $type->gearing_ratio = $request->gearing_ratio;
        $type->long_term_debt = $request->long_term_debt;
        $type->tangible_net_worth = $request->tangible_net_worth;
        $type->total_assets = $request->total_assets;
        $type->solvency = $request->solvency;
        $type->interest_cover = $request->interest_cover;
        $type->gross_interest_debts = $request->gross_interest_debts;
        $type->total_assets_turnover = $request->total_assets_turnover;
        $type->fixed_assets_turn_over = $request->fixed_assets_turn_over;
        $type->description = $request->description;
        $type->save();
        activity()
            ->performedOn($type)
            ->log('Create Industry Type');
        return redirect()->route('industry_types.index')->with('success', 'Industry Type created successfully.');
    }

    public function show(IndustryType $type)
    {

        return Inertia::render('IndustryTypes/Show', [
            'type' => $type
        ]);
    }

    public function edit(IndustryType $type)
    {
        return Inertia::render('IndustryTypes/Edit', [
            'type' => $type,
        ]);
    }

    public function update(Request $request, IndustryType $type)
    {
        $request->validate([
            'name' => ['required'],
        ]);
        $type->name = $request->name;
        $type->current_ratio = $request->current_ratio;
        $type->quick_ratio = $request->quick_ratio;
        $type->debtor_days = $request->debtor_days;
        $type->creditor_days = $request->creditor_days;
        $type->working_capital = $request->working_capital;
        $type->turnover_growth = $request->turnover_growth;
        $type->gross_profit = $request->gross_profit;
        $type->operating_profit_margin = $request->operating_profit_margin;
        $type->net_profit_margin = $request->net_profit_margin;
        $type->return_on_equity = $request->return_on_equity;
        $type->return_on_assets = $request->return_on_assets;
        $type->return_on_investment = $request->return_on_investment;
        $type->gearing_ratio = $request->gearing_ratio;
        $type->long_term_debt = $request->long_term_debt;
        $type->tangible_net_worth = $request->tangible_net_worth;
        $type->total_assets = $request->total_assets;
        $type->solvency = $request->solvency;
        $type->interest_cover = $request->interest_cover;
        $type->gross_interest_debts = $request->gross_interest_debts;
        $type->total_assets_turnover = $request->total_assets_turnover;
        $type->fixed_assets_turn_over = $request->fixed_assets_turn_over;
        $type->description = $request->description;
        $type->save();
        activity()
            ->performedOn($type)
            ->log('Update Industry Type');
        return redirect()->route('industry_types.index')->with('success', 'Industry Type updated successfully.');
    }

    public function destroy(IndustryType $type)
    {
        $type->delete();
        activity()
            ->performedOn($type)
            ->log('Delete Industry Type');
        return redirect()->route('industry_types.index')->with('success', 'Industry Type deleted successfully.');
    }
}
