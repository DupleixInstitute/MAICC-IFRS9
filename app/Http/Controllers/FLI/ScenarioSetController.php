<?php

namespace App\Http\Controllers\FLI;

use App\Http\Controllers\Controller;
use App\Models\ScenarioSet;
use App\Models\ScenarioProbability;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ScenarioSetController extends Controller
{
    /**
     * Display a listing of scenario sets.
     */
    public function index(Request $request)
    {
        $filters = $request->all('search');

        $scenarioSets = ScenarioSet::with(['probabilities', 'creator'])
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('FLI/ScenarioSet/Index', [
            'scenarioSets' => $scenarioSets,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new scenario set.
     */
    public function create()
    {
        return Inertia::render('FLI/ScenarioSet/Create');
    }

    /**
     * Store a newly created scenario set in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:scenario_sets,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'scenarios' => 'required|array|min:2',
            'scenarios.*.scenario_name' => 'required|string|max:255',
            'scenarios.*.probability' => 'required|numeric|min:0|max:100',
        ]);

        // Validate that probabilities sum to 100%
        $totalProbability = collect($validated['scenarios'])->sum('probability');
        if (abs($totalProbability - 100) > 0.01) {
            return back()->withErrors([
                'scenarios' => 'Scenario probabilities must sum to 100%. Current total: ' . $totalProbability . '%'
            ])->withInput();
        }

        DB::beginTransaction();
        try {
            $scenarioSet = ScenarioSet::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['scenarios'] as $index => $scenario) {
                ScenarioProbability::create([
                    'scenario_set_id' => $scenarioSet->id,
                    'scenario_name' => $scenario['scenario_name'],
                    'probability' => $scenario['probability'],
                    'order_position' => $index + 1,
                ]);
            }

            DB::commit();

            return redirect()->route('fli.scenarios.index')
                ->with('success', 'Scenario set "' . $scenarioSet->name . '" created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors([
                'error' => 'Failed to create scenario set: ' . $e->getMessage()
            ])->withInput();
        }
    }

    /**
     * Display the specified scenario set.
     */
    public function show(ScenarioSet $scenarioSet)
    {
        $scenarioSet->load('probabilities');

        return Inertia::render('FLI/ScenarioSet/Show', [
            'scenarioSet' => $scenarioSet,
        ]);
    }

    /**
     * Show the form for editing the specified scenario set.
     */
    public function edit(ScenarioSet $scenarioSet)
    {
        $scenarioSet->load('probabilities');

        return Inertia::render('FLI/ScenarioSet/Edit', [
            'scenarioSet' => $scenarioSet,
        ]);
    }

    /**
     * Update the specified scenario set in storage.
     */
    public function update(Request $request, ScenarioSet $scenarioSet)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:scenario_sets,name,' . $scenarioSet->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'scenarios' => 'required|array|min:2',
            'scenarios.*.scenario_name' => 'required|string|max:255',
            'scenarios.*.probability' => 'required|numeric|min:0|max:100',
        ]);

        // Validate that probabilities sum to 100%
        $totalProbability = collect($validated['scenarios'])->sum('probability');
        if (abs($totalProbability - 100) > 0.01) {
            return back()->withErrors([
                'scenarios' => 'Scenario probabilities must sum to 100%. Current total: ' . $totalProbability . '%'
            ])->withInput();
        }

        DB::beginTransaction();
        try {
            $scenarioSet->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Delete existing probabilities and recreate
            $scenarioSet->probabilities()->delete();

            foreach ($validated['scenarios'] as $index => $scenario) {
                ScenarioProbability::create([
                    'scenario_set_id' => $scenarioSet->id,
                    'scenario_name' => $scenario['scenario_name'],
                    'probability' => $scenario['probability'],
                    'order_position' => $index + 1,
                ]);
            }

            DB::commit();

            return redirect()->route('fli.scenarios.index')
                ->with('success', 'Scenario set "' . $scenarioSet->name . '" updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors([
                'error' => 'Failed to update scenario set: ' . $e->getMessage()
            ])->withInput();
        }
    }

    /**
     * Remove the specified scenario set from storage.
     */
    public function destroy(ScenarioSet $scenarioSet)
    {
        try {
            $name = $scenarioSet->name;
            $scenarioSet->delete();
            
            return redirect()->route('fli.scenarios.index')
                ->with('success', 'Scenario set "' . $name . '" deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Failed to delete scenario set: ' . $e->getMessage()
            ]);
        }
    }
}
