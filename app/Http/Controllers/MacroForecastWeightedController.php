<?php

namespace App\Http\Controllers;

use App\Models\MacroStatsDefinition;
use Illuminate\Http\Request;
use App\Services\MacroForecastWeightedService;
use App\Models\MacroForecastWeighted;
use App\Models\ScenarioProfiles;
use Inertia\Inertia;

class MacroForecastWeightedController extends Controller
{
    public function index()
    {
        $forecasts = MacroForecastWeighted::with(['scenarioProfile', 'macroStatistic', 'reportingPeriod'])
        ->paginate(10);
        $profiles = ScenarioProfiles::all();
        $macroVariable = MacroStatsDefinition::all();

        return Inertia::render('FLI/ForecastWeighted/Index', [
            'forecasts' => $forecasts,
            'profiles' => $profiles,
            'macroVariable' => $macroVariable,
        ]);
    }

    public function store(){
        $form = request()->validate([
            'start_period' => 'required|date',
            'end_period' => 'required|date',
            'profile_id' => 'required|exists:scenario_profiles,id',
        ]);

        MacroForecastWeighted::create($form);

        return redirect()->back()->with('success', 'Forecast created successfully.');
    }

    public function calculate(Request $request)
    {
        $request->validate([
            'scenario_profile_id' => 'required|exists:scenario_profiles,id',
            'start_period' => 'required|string',
            'end_period' => 'required|string',
        ]);

        try {
            MacroForecastWeightedService::calculateWeightedForecast($request->scenario_profile_id, $request->start_period, $request->end_period);
            return redirect()->back()->with('success', 'Weighted forecast calculated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
 
    public function rerunForecast($id)
    {
        $forecast = MacroForecastWeighted::findOrFail($id);

        try {
           MacroForecastWeightedService::calculateWeightedForecast($forecast->scenario_profile_id, $forecast->start_period, $forecast   ->end_period);
            return redirect()->back()->with('success', 'Weighted forecast recalculated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $forecast = MacroForecastWeighted::findOrFail($id);
        $forecast->delete();

        return redirect()->back()->with('success', 'Forecast deleted successfully.');
    }
}