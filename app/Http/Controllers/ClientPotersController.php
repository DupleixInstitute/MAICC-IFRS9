<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\PorterFiveForcesAnalysis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClientPotersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:clients.poters_five_forces_analysis.index'])->only(['index', 'show']);
        $this->middleware(['permission:clients.poters_five_forces_analysis.create'])->only(['create', 'store']);
        $this->middleware(['permission:clients.poters_five_forces_analysis.update'])->only(['edit', 'update']);
        $this->middleware(['permission:clients.poters_five_forces_analysis.destroy'])->only(['destroy']);
    }

    public function index(Client $client)
    {
        $poter = PorterFiveForcesAnalysis::where('client_id', $client->id)
            ->first();
        return Inertia::render('Clients/Poters/Index', [
            'client' => $client,
            'poter' => $poter,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Client $client)
    {
        return Inertia::render('Clients/Poters/Create', [
            'client' => $client,

        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Client $client)
    {
        $request->validate([
            //'name' => ['required'],
        ]);
        $poter = PorterFiveForcesAnalysis::where('client_id', $client->id)
            ->first();
        if(empty($poter)){
            $poter = new PorterFiveForcesAnalysis();
            //$poter->created_by_id = Auth::id();
            $poter->client_id = $client->id;

        }


        $poter->industry_cyclicality = $request->industry_cyclicality;
        $poter->industry_performance = $request->industry_performance;
        $poter->time_and_cost_of_entry = $request->time_and_cost_of_entry;
        $poter->time_and_cost_of_entry_comment = $request->time_and_cost_of_entry_comment;
        $poter->specialist_knowledge = $request->specialist_knowledge;
        $poter->specialist_knowledge_comment = $request->specialist_knowledge_comment;
        $poter->economies_of_scale = $request->economies_of_scale;
        $poter->economies_of_scale_comment = $request->economies_of_scale_comment;
        $poter->cost_advantages = $request->cost_advantages;
        $poter->cost_advantages_comment = $request->cost_advantages_comment;
        $poter->technology_protection = $request->technology_protection;
        $poter->technology_protection_comment = $request->technology_protection_comment;
        $poter->barriers_to_entry = $request->barriers_to_entry;
        $poter->barriers_to_entry_comment = $request->barriers_to_entry_comment;
        $poter->threats_of_new_entry = $request->threats_of_new_entry;
        $poter->number_of_competitors = $request->number_of_competitors;
        $poter->number_of_competitors_comment = $request->number_of_competitors_comment;
        $poter->quality_differences = $request->quality_differences;
        $poter->quality_differences_comment = $request->quality_differences_comment;
        $poter->other_differences = $request->other_differences;
        $poter->other_differences_comment = $request->other_differences_comment;
        $poter->switching_costs = $request->switching_costs;
        $poter->switching_costs_comment = $request->switching_costs_comment;
        $poter->customer_loyalty = $request->customer_loyalty;
        $poter->customer_loyalty_comment = $request->customer_loyalty_comment;
        $poter->competitive_rivalry = $request->competitive_rivalry;
        $poter->number_of_suppliers = $request->number_of_suppliers;
        $poter->number_of_suppliers_comment = $request->number_of_suppliers_comment;
        $poter->size_of_suppliers = $request->size_of_suppliers;
        $poter->size_of_suppliers_comment = $request->size_of_suppliers_comment;
        $poter->uniqueness_of_service = $request->uniqueness_of_service;
        $poter->uniqueness_of_service_comment = $request->uniqueness_of_service_comment;
        $poter->costs_of_supplier_change = $request->costs_of_supplier_change;
        $poter->costs_of_supplier_change_comment = $request->costs_of_supplier_change_comment;
        $poter->supplier_switching_costs = $request->supplier_switching_costs;
        $poter->supplier_switching_costs_comment = $request->supplier_switching_costs_comment;
        $poter->supplier_power = $request->supplier_power;
        $poter->substitute_performance = $request->substitute_performance;
        $poter->substitute_performance_comment = $request->substitute_performance_comment;
        $poter->costs_of_substitution = $request->costs_of_substitution;
        $poter->costs_of_substitution_comment = $request->costs_of_substitution_comment;
        $poter->threats_of_substitution = $request->threats_of_substitution;
        $poter->number_of_customers = $request->number_of_customers;
        $poter->number_of_customers_comment = $request->number_of_customers_comment;
        $poter->single_order_size = $request->single_order_size;
        $poter->single_order_size_comment = $request->single_order_size_comment;
        $poter->competitor_differences = $request->competitor_differences;
        $poter->competitor_differences_comment = $request->competitor_differences_comment;
        $poter->price_sensitivity = $request->price_sensitivity;
        $poter->price_sensitivity_comment = $request->price_sensitivity_comment;
        $poter->ability_to_substitute = $request->ability_to_substitute;
        $poter->ability_to_substitute_comment = $request->ability_to_substitute_comment;
        $poter->customers_switching_costs = $request->customers_switching_costs;
        $poter->customers_switching_costs_comment = $request->customers_switching_costs_comment;
        $poter->buyer_power = $request->buyer_power;
        $poter->grand_total = $request->grand_total;
        $poter->save();
        activity()
            ->performedOn($client)
            ->log('Create PorterFiveForcesAnalysis');
        return redirect()->route('clients.poter.index', [$client->id])->with('success', 'Saved successfully.');

    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit(PorterFiveForcesAnalysis $poter)
    {
        $client = $poter->client;

        return Inertia::render('Clients/Poters/Edit', [
            'client' => $client,
            'poter' => $poter,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PorterFiveForcesAnalysis $poter)
    {

        $client = $poter->client;
        $request->validate([
            //'name' => ['required'],
        ]);
        $poter->industry_cyclicality = $request->industry_cyclicality;
        $poter->industry_performance = $request->industry_performance;
        $poter->time_and_cost_of_entry = $request->time_and_cost_of_entry;
        $poter->time_and_cost_of_entry_comment = $request->time_and_cost_of_entry_comment;
        $poter->specialist_knowledge = $request->specialist_knowledge;
        $poter->specialist_knowledge_comment = $request->specialist_knowledge_comment;
        $poter->economies_of_scale = $request->economies_of_scale;
        $poter->economies_of_scale_comment = $request->economies_of_scale_comment;
        $poter->cost_advantages = $request->cost_advantages;
        $poter->cost_advantages_comment = $request->cost_advantages_comment;
        $poter->technology_protection = $request->technology_protection;
        $poter->technology_protection_comment = $request->technology_protection_comment;
        $poter->barriers_to_entry = $request->barriers_to_entry;
        $poter->barriers_to_entry_comment = $request->barriers_to_entry_comment;
        $poter->threats_of_new_entry = $request->threats_of_new_entry;
        $poter->number_of_competitors = $request->number_of_competitors;
        $poter->number_of_competitors_comment = $request->number_of_competitors_comment;
        $poter->quality_differences = $request->quality_differences;
        $poter->quality_differences_comment = $request->quality_differences_comment;
        $poter->other_differences = $request->other_differences;
        $poter->other_differences_comment = $request->other_differences_comment;
        $poter->switching_costs = $request->switching_costs;
        $poter->switching_costs_comment = $request->switching_costs_comment;
        $poter->customer_loyalty = $request->customer_loyalty;
        $poter->customer_loyalty_comment = $request->customer_loyalty_comment;
        $poter->competitive_rivalry = $request->competitive_rivalry;
        $poter->number_of_suppliers = $request->number_of_suppliers;
        $poter->number_of_suppliers_comment = $request->number_of_suppliers_comment;
        $poter->size_of_suppliers = $request->size_of_suppliers;
        $poter->size_of_suppliers_comment = $request->size_of_suppliers_comment;
        $poter->uniqueness_of_service = $request->uniqueness_of_service;
        $poter->uniqueness_of_service_comment = $request->uniqueness_of_service_comment;
        $poter->costs_of_supplier_change = $request->costs_of_supplier_change;
        $poter->costs_of_supplier_change_comment = $request->costs_of_supplier_change_comment;
        $poter->supplier_switching_costs = $request->supplier_switching_costs;
        $poter->supplier_switching_costs_comment = $request->supplier_switching_costs_comment;
        $poter->supplier_power = $request->supplier_power;
        $poter->substitute_performance = $request->substitute_performance;
        $poter->substitute_performance_comment = $request->substitute_performance_comment;
        $poter->costs_of_substitution = $request->costs_of_substitution;
        $poter->costs_of_substitution_comment = $request->costs_of_substitution_comment;
        $poter->threats_of_substitution = $request->threats_of_substitution;
        $poter->number_of_customers = $request->number_of_customers;
        $poter->number_of_customers_comment = $request->number_of_customers_comment;
        $poter->single_order_size = $request->single_order_size;
        $poter->single_order_size_comment = $request->single_order_size_comment;
        $poter->competitor_differences = $request->competitor_differences;
        $poter->competitor_differences_comment = $request->competitor_differences_comment;
        $poter->price_sensitivity = $request->price_sensitivity;
        $poter->price_sensitivity_comment = $request->price_sensitivity_comment;
        $poter->ability_to_substitute = $request->ability_to_substitute;
        $poter->ability_to_substitute_comment = $request->ability_to_substitute_comment;
        $poter->customers_switching_costs = $request->customers_switching_costs;
        $poter->customers_switching_costs_comment = $request->customers_switching_costs_comment;
        $poter->buyer_power = $request->buyer_power;
        $poter->grand_total = $request->grand_total;
        $poter->save();

        activity()
            ->performedOn($client)
            ->log('Update PorterFiveForcesAnalysis');
        return redirect()->route('clients.poter.index', [$client->id])->with('success', 'Updated successfully.');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(PorterFiveForcesAnalysis $poter)
    {

        $poter->delete();
        activity()
            ->performedOn($poter)
            ->log('Delete PorterFiveForcesAnalysis');
        return redirect()->route('clients.poter.index', [$poter->client_id])->with('success', 'Deleted successfully.');

    }
}
