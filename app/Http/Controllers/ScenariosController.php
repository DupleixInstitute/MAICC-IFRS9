<?php

namespace App\Http\Controllers;

use App\Models\Scenarios;
use App\Models\ScenarioProfiles;
use App\Models\MacroStatsValue;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ScenariosController extends Controller
{

    // Scenario Profiles (FLI module specification v1): a profile is a reusable
    // template of scenarios (names, weights, narratives, anchors and shocks).
    // Starting a scenario set from a profile creates a DRAFT governed set for a
    // month, which then goes through the set's own propose and approve.
    public function profiles()
        {
            $profiles = ScenarioProfiles::with(['scenarios' => fn ($q) => $q->orderByDesc('is_base_case')->orderBy('id')])->orderBy('id')->get()
                ->map(function ($p) {
                    $data = is_array($p->data) ? $p->data : (json_decode((string) $p->data, true) ?: []);
                    $sum = (float) $p->scenarios->sum('probability');

                    return $p->toArray() + ['design' => $data['scenarios'] ?? [], 'weights_sum' => $sum <= 1.0001 ? round($sum * 100, 2) : round($sum, 2),
                        'sets_started' => \Illuminate\Support\Facades\DB::table('governed_scenario_sets')->where('source_vintage', 'like', '%profile ' . $p->profile_code . '%')->count()];
                });
            $periods = \Illuminate\Support\Facades\DB::table('loan_books')->distinct()->orderByDesc('reporting_period')->limit(36)->pluck('reporting_period');
            $latest = $periods->first();
            $next = $latest ? \Carbon\CarbonImmutable::parse($latest . '-01')->addMonth()->format('Y-m') : null;

            return Inertia::render('FLI/Scenarios/ScenarioProfiles', [
                'profiles' => $profiles,
                'periods' => $next ? $periods->prepend($next)->values() : $periods,
                'defaultPeriod' => $latest,
                'setsByPeriod' => \Illuminate\Support\Facades\DB::table('governed_scenario_sets')->selectRaw('reporting_period, max(status) status, count(*) n')->groupBy('reporting_period')->pluck('n', 'reporting_period'),
                'canGovern' => (bool) auth()->user()?->can('eir.govern'),
            ]);
        }

    /** The standard profile: the four scenarios of spec 15.8, with their shocks and anchors. */
    public function seedStandard()
    {
        $existing = ScenarioProfiles::where('profile_code', 'MAIIC4')->first();
        if ($existing) {
            return back()->with('info', 'The standard profile is already held.');
        }
        $design = \App\Services\Scenario\ScenarioSetService::firstSetDesign();
        \Illuminate\Support\Facades\DB::transaction(function () use ($design) {
            $profile = ScenarioProfiles::create(['name' => 'MAIIC four scenarios', 'profile_code' => 'MAIIC4', 'created_by' => auth()->id(), 'complete' => 1,
                'description' => 'Base 50, Upside 15, Downside 25 and Severe 10, each anchored to a Malawi episode: the 2021 recovery, the 2023 devaluation and the 2016 drought. The weights are a starting point for the CFO to set.',
                'data' => ['scenarios' => $design]]);
            foreach ($design as $d) {
                Scenarios::create(['profile_id' => $profile->id, 'name' => $d['name'], 'description' => $d['anchored_to'] ?? null, 'probability' => $d['weight'] / 100,
                    'is_base_case' => (bool) ($d['is_base'] ?? false), 'is_active' => true, 'key_drivers' => $d['calibration_note'] ?? null, 'status' => 'draft', 'version' => 1]);
            }
        });

        return back()->with('success', 'Standard profile added: four scenarios with their shocks and anchors.');
    }

    /** Start a DRAFT governed scenario set for a month from a profile. */
    public function startSet(Request $request, ScenarioProfiles $profile, \App\Services\Scenario\ScenarioSetService $sets)
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $design = collect((is_array($profile->data) ? $profile->data : [])['scenarios'] ?? [])->keyBy('name');
        $rows = $profile->scenarios()->where('is_active', true)->orderByDesc('is_base_case')->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return back()->with('error', 'The profile has no active scenario; add its scenarios first.');
        }
        $sum = (float) $rows->sum('probability');
        $fraction = $sum <= 1.0001;
        $scenarios = $rows->map(function ($r) use ($design, $fraction) {
            $d = $design[$r->name] ?? [];

            return ['name' => $r->name, 'weight' => round((float) $r->probability * ($fraction ? 100 : 1), 3), 'is_base' => (bool) $r->is_base_case,
                'narrative' => $r->description, 'anchored_to' => $d['anchored_to'] ?? $r->description, 'calibration_note' => $d['calibration_note'] ?? $r->key_drivers,
                'pd_multiplier' => $d['pd_multiplier'] ?? null, 'shocks' => $d['shocks'] ?? []];
        })->values()->all();
        try {
            $id = $sets->create($data['period'], $profile->name, $scenarios, (int) $request->user()->id, $profile->description, 'from profile ' . $profile->profile_code);
            $check = $sets->validate($id);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('scenario-sets.index', ['period' => $data['period']])->with($check['ok'] ? 'success' : 'warning',
            "Draft set {$id} started for {$data['period']} from the profile" . ($check['ok'] ? '; review it, then propose it.' : '. It cannot be proposed yet: ' . implode('; ', $check['problems'])));
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
