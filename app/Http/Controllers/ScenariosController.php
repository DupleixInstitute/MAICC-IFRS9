<?php

namespace App\Http\Controllers;

use App\Models\Scenarios;
use App\Models\ScenarioProfiles;
use App\Models\MacroStatsValue;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ScenariosController extends Controller
{

    //Scenario Profiles Logic
    public function profiles()
        {
            $profiles = ScenarioProfiles::with('scenarios')->get();

            return Inertia::render('FLI/Scenarios/ScenarioProfiles', [
                'profiles' => $profiles
            ]);
        }


  public function storeProfile(Request $request)
        {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'profile_code' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            $validated['created_by'] = auth()->user()->id; // or auth()->id

            ScenarioProfiles::create($validated);

            

            return redirect()->back()->with('success', 'Profile created');
        }


    public function updateProfile(Request $request, ScenarioProfiles $profile)
        {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'profile_code' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);
            $profile->update($validated);
            return redirect()->back()->with('success', 'Profile updated');
        }

    public function destroyProfile(ScenarioProfiles $profile)
    {
        $profile->delete();
        return redirect()->back()->with('success', 'Profile deleted');
    }

        // Scenarios Logic

    public function index($id)
    {
        $profile = ScenarioProfiles::with('scenarios')->findOrFail($id);
        $scenarios = $profile->scenarios()->orderBy('is_base_case', 'desc')->get();

        return Inertia::render('FLI/Scenarios/Index', [
            'scenarios' => $scenarios,
            'profile'   => $profile,
        ]);
    }


    public function store(Request $request)
        {
            $validated = $request->validate([
                'profile_id' => 'required|exists:scenario_profiles,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'probability' => 'required|numeric|between:0,100',
                'is_base_case' => 'boolean',
                'tags' => 'nullable|array',
            ]);

            if ($validated['is_base_case']) {
                Scenarios::where('is_base_case', true)->update(['is_base_case' => false]);
            }

            Scenarios::create([
                ...$validated,
                'is_active' => true,
            ]);

            return redirect()->back()->with('success', 'Scenario created');
        }

        
    public function update(Request $request, Scenarios $scenario)
        {
            $validated = $request->validate([

                'profile_id' => 'required|exists:scenario_profiles,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'probability' => 'required|numeric|between:0,100',
                'is_base_case' => 'boolean',
                'tags' => 'nullable|array',
            ]);

            if ($validated['is_base_case']) {
                Scenarios::where('is_base_case', true)->where('id', '!=', $scenario->id)->update(['is_base_case' => false]);
            }

            $scenario->update($validated);

            return redirect()->back()->with('success', 'Scenario updated');
        }

        public function destroy(Scenarios $scenario)
        {
            // Delete related macro statistics data
            MacroStatsValue::where('scenario_id', $scenario->id)->delete();

            // Now delete the scenario
            $scenario->delete();

            return redirect()->back()->with('success', 'Scenario deleted');
        }
}
