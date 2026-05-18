<?php
// app/Http/Controllers/RegressionController.php

namespace App\Http\Controllers;

use App\Models\RegressionModel;
use App\Models\CreditLossDefinition;
use App\Models\MacroStatsDefinition;
use App\Models\MacroStatsValue;
use App\Models\LoanPortfolio;
use App\Models\ScenarioProfiles;
use App\Services\RegressionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class RegressionController extends Controller
{
    public function __construct(private RegressionService $regressionService) {}

    public function index()
    {
        $models = RegressionModel::with(['portfolio', 'dependentVariable', 'creator'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Regression/Index', [
            'models' => $models,
        ]);
    }

    public function create()
    {
        return Inertia::render('Regression/TrainModel', [
            'portfolios' => LoanPortfolio::all(),
            'dependentVariables' => CreditLossDefinition::all(),
            'independentVariables' => MacroStatsDefinition::where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request)
        {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|in:pd,lgd,ecl,migration',
                'portfolio_id' => 'required|exists:loan_portfolios,id',
                'dep_var_id' => 'required|exists:macro_credit_loss_definitions,id', 
                'indep_vars' => 'required|array|min:1', 
                'train_start' => 'required|date', 
                'train_end' => 'required|date|after:train_start',
            ]);

            try {
             $dataForService = [
                                    'name' => $validated['name'],
                                    'type' => $validated['type'],
                                    'portfolio_id' => $validated['portfolio_id'],
                                    'dep_var_id' => $validated['dep_var_id'],         // Use old key
                                    'indep_vars' => $validated['indep_vars'],         // Use old key
                                    'train_start' => $validated['train_start'],       // Use old key
                                    'train_end' => $validated['train_end'],           // Use old key
                                ];


                // Train the model using the re-mapped data
                $result = $this->regressionService->trainModel($dataForService);

                // Save the model - using the re-mapped data keys for the database columns
               $model = RegressionModel::create([
                                    'name' => $validated['name'],
                                    'type' => $validated['type'],
                                    'portfolio_id' => $validated['portfolio_id'],
                                    'dep_var_id' => $validated['dep_var_id'],
                                    'indep_vars' => $validated['indep_vars'],
                                    'train_start' => $validated['train_start'],
                                    'train_end' => $validated['train_end'],
                                    'coeffs' => $result['coefficients'],
                                    'r_squared' => $result['r_squared'],
                                    'adj_r_squared' => $result['adjusted_r_squared'],
                                    'stats' => [
                                        'standard_errors' => $result['standard_errors'],
                                        't_statistics' => $result['t_statistics'],
                                        'p_values' => $result['p_values'],
                                        'mean_squared_error' => $result['mean_squared_error'],
                                    ],
                                    'train_periods' => $result['observations'],
                                    'created_by' => auth()->id(),
                                    ]);


                return redirect()->route('regression.view', $model->id)
                    ->with('success', 'Regression model trained successfully!');

            } catch (\Exception $e) {
                Log::error('Regression model training failed: ' . $e->getMessage(), ['exception' => $e]);
                
                return back()->with('error', 'Failed to train model: ' . $e->getMessage());
            }
        }

    public function show(RegressionModel $model)
    {
        $model->load(['portfolio', 'dependentVariable', 'predictions', 'creator']);

        $ids = $model->indep_vars ?? [];

        $independentVars = empty($ids)
            ? collect()
            : MacroStatsDefinition::whereIn('id', $ids)->get()->keyBy('id');

        return Inertia::render('Regression/Show', [
            'model' => $model,
            'independentVariables' => $independentVars,
        ]);
    }


       public function predictForm(Request $request, RegressionModel $model)
            {
                // Load the model relationships
                $model->load(['portfolio', 'dependentVariable', 'predictions', 'creator']);

                // Fetch scenarios
                $scenarios = ScenarioProfiles::select('id', 'profile_code', 'name')->get();

                // Get macro variables used in this model
                $indepVars = $model->indep_vars ?? [];
                $macroVariables = empty($indepVars)
                    ? collect()
                    : MacroStatsDefinition::whereIn('id', $indepVars)->get();

                // Get available forecast periods (distinct periods for this model’s macro variables)
                $availablePeriods = MacroStatsValue::whereIn('macro_stat_definition_id', $indepVars)
                    ->where('is_forecast', true)
                    ->distinct()
                    ->pluck('period')
                    ->sort()
                    ->values();

                $macroData = [];

                // If scenario_id and periods are provided, fetch macro data
                if ($request->has('scenario_id') && $request->has('periods')) {
                    $scenarioId = $request->get('scenario_id');
                    $periods = (array) $request->get('periods');

                    $macroData = \DB::table('macro_statistics_data as v')
                        ->join('macro_statistics as s', 'v.macro_stat_definition_id', '=', 's.id')
                        ->where('v.scenario_profile_id', $scenarioId)
                        ->whereIn('v.period', $periods)
                        ->whereIn('v.macro_stat_definition_id', $indepVars)
                        ->select('v.period', 's.statistic_code', 'v.value')
                        ->get()
                        ->groupBy('period')
                        ->map(function ($periodData) {
                            return $periodData->mapWithKeys(fn($row) => [
                                $row->statistic_code => (float) $row->value
                            ]);
                        })
                        ->toArray();
                }

                \Log::info('Macro Data for Prediction Form', ['macroData' => $macroData]);

                return Inertia::render('Regression/Prediction', [
                    'model' => $model,
                    'scenarios' => $scenarios,
                    'macroVariables' => $macroVariables,
                    'availablePeriods' => $availablePeriods,
                    'macroData' => $macroData, // Send the macro data as a prop
                ]);
            }



    public function predict(Request $request, RegressionModel $model)
        {
            $validated = $request->validate([
                'scenario_id' => 'required|exists:scenario_profiles,id',
                'periods' => 'required|array',
                'macro_data' => 'required|array', // This now contains forecast data from database
            ]);

            try {
                $predictions = $this->regressionService->makePredictions(
                    $model, 
                    $validated['macro_data']
                );
                
                // Save predictions
                foreach ($predictions as $period => $value) {
                    $model->predictions()->updateOrCreate(
                        [
                            'scenario_id' => $validated['scenario_id'],
                            'period' => $period,
                        ],
                        [
                            'pred_value' => $value,
                            'is_actual' => false,
                            'macro_data_used' => $validated['macro_data'][$period] ?? [], // Store inputs for audit
                        ]
                    );
                }

                return back()->with('success', 'Predictions generated successfully!');

            } catch (\Exception $e) {
                \Log::error('Prediction failed: ' . $e->getMessage());
                return back()->with('error', 'Failed to generate predictions: ' . $e->getMessage());
            }
        }

    /**
     * Toggle a regression model active/inactive (referenced by routes & UI).
     */
    public function toggleActive(RegressionModel $model)
    {
        $model->is_active = ! $model->is_active;
        $model->save();

        return back()->with('success', 'Model ' . ($model->is_active ? 'activated' : 'deactivated') . '.');
    }

    /**
     * Approve a regression model for use in ECL/FLI.
     */
    public function approve(RegressionModel $model)
    {
        $model->is_approved = true;
        $model->save();

        return back()->with('success', 'Model approved.');
    }

    /**
     * Return forecast macro data for this model's independent variables,
     * grouped by period (used by the prediction screen).
     */
    public function fetchMacroForecast(RegressionModel $model)
    {
        $indepVars = $model->indep_vars ?? [];

        $data = MacroStatsValue::whereIn('macro_stat_definition_id', $indepVars)
            ->where('is_forecast', true)
            ->orderBy('period')
            ->get(['macro_stat_definition_id', 'period', 'value'])
            ->groupBy(fn ($row) => \Carbon\Carbon::parse($row->period)->format('Y-m'))
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [$r->macro_stat_definition_id => (float) $r->value]));

        return response()->json(['macro_data' => $data]);
    }
    }