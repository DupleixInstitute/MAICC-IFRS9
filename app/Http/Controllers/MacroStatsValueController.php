<?php

namespace App\Http\Controllers;

use App\Models\MacroStatsDefinition;
use App\Models\MacroStatsValue;
use App\Models\Scenarios;
use App\Models\ScenarioProfiles;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class MacroStatsValueController extends Controller
{
    /**
     * Display macro stat values for a specific definition.
     */
public function index(MacroStatsDefinition $stat)
{
    $stat->load([
        'values' => fn($q) => $q->orderBy('period')->with(['creator', 'scenario']),
    ]);

    $profiles = ScenarioProfiles::with('scenarios')->get();

    return Inertia::render('FLI/MacroStats/MacroValue', [
        'statistic' => $stat,
        'values' => $stat->values,
        'profiles' => $profiles, 
    ]);
}

    /**
     * Show the form for creating a new macro stat value.
     */
    public function create($statId)
    {
        $stat = MacroStatsDefinition::findOrFail($statId);
        dd(Scenarios::all());
        return Inertia::render('FLI/MacroStats/ValueForm', [
            'statistic' => $stat,
            'scenarios' => Scenarios::all()->toArray(),
        ]);
    }

    /**
     * Store a newly created macro stat value.
     */

    public function store(Request $request, MacroStatsDefinition $stat)
    {
            $validated = $request->validate([
                'period' => ['required', 'date_format:Y-m'],
                'value' => 'required|numeric',
                'scenario_profile_id' => 'nullable|exists:scenario_profiles,id',
                'scenario_id' => 'nullable|exists:scenarios,id',
                'is_forecast' => 'nullable|boolean',
                'source' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            $validated['period'] = substr($validated['period'], 0, 7);
            $validated['macro_stat_definition_id'] = $stat->id;
            $validated['created_by'] = auth()->id();

            MacroStatsValue::create($validated);
        return redirect()->route('macro-values.index', $stat)
                        ->with('success', 'Value added successfully!');
    }

    /**
     * Update an existing macro stat value.
     */
 public function update(Request $request, MacroStatsValue $value)
        {
            $validated = $request->validate([
                'period' => ['required', 'date_format:Y-m'],
                'value' => ['required', 'numeric'],
                'scenario_profile_id' => ['nullable', 'exists:scenario_profiles,id'],
                'scenario_id' => ['nullable', 'exists:scenarios,id'],
                'is_forecast' => ['nullable', 'boolean'],
                'source' => ['nullable', 'string'],
                'notes' => ['nullable', 'string'],
            ]);

            $validated['period'] = substr($validated['period'], 0, 7); // ensure consistent format

            $value->update($validated);

            return redirect()->back()->with('success', 'Value updated successfully!');
        }


    public function import(Request $request, MacroStatsDefinition $stat){
        $request->validate([
            'file'=>'required|file|mimes:csv,text|max:2048',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();
        $rows = array_map('str_getcsv', file($path));

        if(isset($rows[0]) && strtolower($rows[0][0])==='period'){
                array_shift($rows);
        }

        foreach($rows as $row ){
            $validator = Validator::make([
                'period' => $row[0] ?? null,
                'value' => $row[1],
            ],[
                'period' => ['requires','date_format:Y-m'],
                'value' => ['required','decimal']
            ]
        );

        if($validator->fails()){
            continue;
        }

        MacroStatsValue::upsert([
            'macro_stat_definition_id' => $stat->id,
            'period' => substr($row[0],0,7),
        ],[
            'value' => $row[1],
            'created_by' => auth()->id(),
            'source' => $row[2] ?? null,
            'notes' => $row[3] ?? null,
        ]);
        }
         return redirect()->route('macro-values.index', $stat)
        ->with('success', 'Macro values imported successfully.');
    }

    /**
     * Delete a macro stat value.
     */
    public function destroy(MacroStatsValue $value)
    {
        $value->delete();

        return redirect()->back()->with('success', 'Value deleted.');
    }

    public function getScenarios()
    {
        return Scenarios::all()->toArray();
    }
}
