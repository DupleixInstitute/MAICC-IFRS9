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
     * Economic Scenarios, the scenario workbench (FLI module specification
     * v1, section 4): for a governed scenario set, each driver's actuals over
     * the last years known at the month-end and each scenario's path for the
     * forecast years, with the weights and the narrative, set against the
     * previous version of the month or the last set of the month before. The
     * set is edited, proposed and approved on Scenario Sets; this screen is
     * for reading and comparing it. The older named sets (scenario_sets)
     * are listed below it as they were.
     */
    public function index(Request $request, \App\Services\Scenario\ScenarioSetService $service)
    {
        $periods = DB::table('governed_scenario_sets')->distinct()->orderByDesc('reporting_period')->pluck('reporting_period');
        $period = (string) $request->query('period', $periods->first() ?? now()->format('Y-m'));
        $sets = DB::table('governed_scenario_sets')->where('reporting_period', $period)->orderByDesc('version')->get(['id', 'name', 'version', 'status', 'narrative', 'source_vintage', 'approver_label', 'approved_at']);
        $setId = (int) $request->query('set', $sets->firstWhere('status', 'APPROVED')->id ?? ($sets->first()->id ?? 0));
        $compare = DB::table('governed_scenario_sets')->where('reporting_period', $period)->where('id', '<', $setId)->orderByDesc('version')->first()
            ?? DB::table('governed_scenario_sets')->where('reporting_period', '<', $period)->whereIn('status', ['APPROVED', 'LOCKED', 'SUPERSEDED'])->orderByDesc('reporting_period')->orderByDesc('version')->first();
        $plain = new \App\Support\Fli\FliPlainLanguage();
        $workbench = null;
        if ($setId) {
            $paths = $service->paths($setId);
            $cmp = $compare ? $service->paths((int) $compare->id) : null;
            $year = (int) substr($period, 0, 4);
            $asOf = \App\Support\Fli\AsAtSeries::ym($period);
            $history = \App\Support\Fli\AsAtSeries::macro(null, $asOf, ['actual'], array_keys($paths['base']));
            $series = [];
            foreach ($paths['base'] as $code => $base) {
                $hist = [];
                for ($y = $year - 4; $y < $year; $y++) {
                    $inYear = array_filter(array_keys($history[$code] ?? []), fn ($k) => str_starts_with((string) $k, (string) $y));
                    $hist[$y] = $inYear === [] ? null : round((float) $history[$code][max($inYear)], 4);
                }
                $scen = [];
                $moved = false;
                foreach ($paths['scenarios'] as $name => $sc) {
                    $vals = array_map(fn ($v) => $v === null ? null : round((float) $v, 4), $sc['path'][$code] ?? []);
                    $prev = $cmp['scenarios'][$name]['path'][$code] ?? null;
                    $scen[] = ['name' => $name, 'is_base' => $sc['is_base'], 'values' => $vals, 'previous' => $prev === null ? null : array_map(fn ($v) => $v === null ? null : round((float) $v, 4), $prev)];
                    foreach ($vals as $o => $v) {
                        $moved = $moved || ($v !== null && ($base[$o] ?? null) !== null && abs($v - (float) $base[$o]) > 1e-9);
                    }
                }
                $hasData = array_filter($hist, fn ($v) => $v !== null) !== [] || array_filter(array_merge(...array_map(fn ($x) => $x['values'], $scen)), fn ($v) => $v !== null) !== [];
                if (! $hasData) {
                    continue; // a driver with nothing known at the month-end has nothing to show
                }
                $series[] = ['code' => $code, 'name' => $plain->driver($code), 'history' => $hist, 'source' => $paths['base_source'][$code] ?? [], 'scenarios' => $scen, 'shocked' => $moved];
            }
            usort($series, fn ($a, $b) => [$b['shocked'], $a['name']] <=> [$a['shocked'], $b['name']]);
            $scenarioRows = DB::table('governed_scenarios')->where('set_id', $setId)->orderBy('order_position')->get(['name', 'weight', 'is_base', 'narrative', 'anchored_to', 'calibration_note']);
            $prevWeights = $compare ? DB::table('governed_scenarios')->where('set_id', $compare->id)->pluck('weight', 'name') : collect();
            $workbench = ['series' => $series, 'as_of' => substr((string) $paths['as_of'], 0, 4) . '-' . substr((string) $paths['as_of'], 4, 2), 'year' => $year,
                'scenarios' => $scenarioRows->map(fn ($r) => (array) $r + ['previous_weight' => isset($prevWeights[$r->name]) ? (float) $prevWeights[$r->name] : null])->values(),
                'compare' => $compare ? ['id' => (int) $compare->id, 'name' => $compare->name, 'version' => (int) $compare->version, 'period' => $compare->reporting_period, 'status' => $compare->status] : null];
        }

        return Inertia::render('FLI/ScenarioSet/Index', [
            'period' => $period, 'periods' => $periods, 'sets' => $sets, 'setId' => $setId, 'workbench' => $workbench,
            // the older named sets, as they were
            'scenarioSets' => ScenarioSet::with(['creator'])->orderBy('created_at', 'desc')->get(['id', 'name', 'description', 'is_active', 'created_by', 'created_at']),
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
