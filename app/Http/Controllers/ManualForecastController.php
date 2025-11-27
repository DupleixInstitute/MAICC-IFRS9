<?php
// app/Http/Controllers/ManualForecastController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class ManualForecastController extends Controller
{
    public function show(Request $request)
    {
        // Get forecast results from session if they exist
        $forecastResults = $request->session()->get('forecastResults');
        
        return Inertia::render('FLI/Forecasting/Manual', [
            'forecastResults' => $forecastResults
        ]);
    }
    
    public function process(Request $request)
    {
        Log::info('Manual forecast request received', $request->all());
        
        $request->validate([
            'dependent_variable' => 'required|string',
            'regression_intercept' => 'required|numeric',
            'regression_coefficient' => 'required|numeric',
            'baseline_value' => 'required|numeric',
            'scenarios' => 'required|array|min:1',
            'scenarios.*.name' => 'required|string',
            'scenarios.*.probability' => 'required|numeric|min:0|max:100',
            'scenarios.*.macro_forecast' => 'required|array|min:1',
            'scenarios.*.macro_forecast.*' => 'required|numeric',
            'save_session' => 'boolean',
            'session_name' => 'nullable|string|max:255',
        ]);
        
        // Only require session_name if save_session is true
        if ($request->save_session && empty($request->session_name)) {
            Log::warning('Session name required but not provided');
            return back()->withErrors([
                'session_name' => 'Session name is required when saving the session.'
            ]);
        }
        
        // Calculate forecasts
        $results = $this->calculateManualForecast($request);
        
        Log::info('Forecast calculation completed', ['results' => $results]);
        
        // Store in session AND return to the page with the data
        return redirect()->route('forecasting.manual')->with([
            'forecastResults' => $results
        ]);
    }
    
    private function calculateManualForecast(Request $request)
    {
        $scenarios = collect($request->scenarios);
        $coefficients = [
            'intercept' => $request->regression_intercept,
            'coeff_0' => $request->regression_coefficient
        ];
        $baselineValue = $request->baseline_value;
        $forecastHorizon = 5;
        
        Log::info('Starting forecast calculation', [
            'coefficients' => $coefficients,
            'baseline' => $baselineValue,
            'scenarios_count' => $scenarios->count()
        ]);
        
        // Calculate weighted average for each period
        $weightedForecasts = [];
        for ($period = 0; $period < $forecastHorizon; $period++) {
            $weightedMacroValue = 0;
            
            foreach ($scenarios as $scenario) {
                $macroValue = $scenario['macro_forecast'][$period];
                $probability = $scenario['probability'] / 100;
                $weightedMacroValue += $macroValue * $probability;
            }
            
            $weightedForecasts[] = [
                'period' => $period,
                'year' => now()->addYears($period)->year,
                'weighted_macro_value' => $weightedMacroValue,
            ];
        }
        
        Log::info('Weighted forecasts calculated', $weightedForecasts);
        
        // Calculate forecasts using regression
        $forecastResults = [];
        foreach ($weightedForecasts as $forecast) {
            $macroValue = $forecast['weighted_macro_value'];
            $forecastValue = $coefficients['intercept'] + ($coefficients['coeff_0'] * $macroValue);
            $changeFromBaseline = ($baselineValue != 0) ? ($forecastValue - $baselineValue) / $baselineValue : 0;
            
            $forecastResults[] = [
                'period' => $forecast['period'],
                'year' => $forecast['year'],
                'weighted_macro_driver' => $macroValue,
                'forecast_value' => $forecastValue,
                'change_from_baseline' => $changeFromBaseline * 100, // as percentage
            ];
        }
        
        // Calculate individual scenario forecasts
        $scenarioForecasts = [];
        foreach ($scenarios as $scenario) {
            $scenarioResults = [];
            for ($period = 0; $period < $forecastHorizon; $period++) {
                $macroValue = $scenario['macro_forecast'][$period];
                $forecastValue = $coefficients['intercept'] + ($coefficients['coeff_0'] * $macroValue);
                
                $scenarioResults[] = [
                    'period' => $period,
                    'macro_value' => $macroValue,
                    'forecast_value' => $forecastValue,
                ];
            }
            
            $scenarioForecasts[] = [
                'name' => $scenario['name'],
                'probability' => $scenario['probability'],
                'forecasts' => $scenarioResults,
            ];
        }
        
        $finalResults = [
            'weighted_forecast' => $forecastResults,
            'scenario_forecasts' => $scenarioForecasts,
            'regression_equation' => "Forecast = {$coefficients['intercept']} + ({$coefficients['coeff_0']} × Macro Driver)",
        ];
        
        Log::info('Final results prepared', $finalResults);
        
        return $finalResults;
    }
}