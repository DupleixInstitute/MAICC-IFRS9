<?php

namespace App\Services;

use App\Models\MacroStatsDefinition;
use App\Models\MacroStatsValue;
use App\Models\ScenarioProfiles;
use App\Models\Scenarios;
use App\Models\MacroForecastWeighted;
use App\Models\ReportingPeriods;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MacroForecastWeightedService
{
    public static function calculateWeightedForecasts(int $scenarioProfileId)
    {
        Log::info("=== START WEIGHTED FORECAST CALCULATION ===");
        
        $profile = ScenarioProfiles::with('scenarios')->find($scenarioProfileId);
        if (!$profile) {
            Log::error("Scenario Profile NOT FOUND");
            return false;
        }

        $scenarios = $profile->scenarios;
        if ($scenarios->isEmpty()) {
            Log::warning("No scenarios found");
            return false;
        }

        $macroDefinitions = MacroStatsDefinition::all();
        
        foreach ($macroDefinitions as $macro) {
            Log::info("Processing: {$macro->statistic_name}");
            
            // Get all periods for this macro
            $periods = MacroStatsValue::where('macro_stat_definition_id', $macro->id)
                ->where('scenario_profile_id', $scenarioProfileId)
                ->where('is_forecast', 1)
                ->pluck('period')
                ->unique();

            foreach ($periods as $period) {
                $weightedSum = 0;
                $hasData = false;

                foreach ($scenarios as $scenario) {
                    $record = MacroStatsValue::where('macro_stat_definition_id', $macro->id)
                        ->where('scenario_profile_id', $scenarioProfileId)
                        ->where('scenario_id', $scenario->id)
                        ->where('period', $period)
                        ->first();

                    if ($record) {
                        $weightedSum += $record->value * ($scenario->probability / 100);
                        $hasData = true;
                        Log::info(" - Scenario {$scenario->id}: {$record->value} × {$scenario->probability}%");
                    }
                }

                if (!$hasData) {
                    Log::warning("No data for period {$period}");
                    continue;
                }

                Log::info("Period {$period}: Weighted = {$weightedSum}");

                // 1. Create/Get reporting period
                $carbonPeriod = Carbon::parse($period);
                $reportingPeriod = ReportingPeriods::firstOrCreate(
                    ['period' => $period],
                    [
                        'reporting_year' => $carbonPeriod->startOfYear()->format('Y-m-d'),
                        'reporting_month' => $carbonPeriod->startOfMonth()->format('Y-m-d'),
                    ]
                );

                Log::info("✅ Reporting Period ID: {$reportingPeriod->id}");

                // 2. Save to MacroStatsValue
                MacroStatsValue::updateOrCreate(
                    [
                        'macro_stat_definition_id' => $macro->id,
                        'scenario_profile_id' => $scenarioProfileId,
                        'scenario_id' => null,
                        'period' => $period,
                        'is_forecast' => 1,
                        'is_fli' => 0,
                    ],
                    [
                        'value' => $weightedSum,
                        'source' => 'Weighted Calculation',
                        'created_by' => 1,
                    ]
                );

                // 3. ✅ FIXED: Save to MacroForecastWeighted
                try {
                    MacroForecastWeighted::updateOrCreate(
                        [
                            'macro_statistic_id' => $macro->id,
                            'scenario_profile_id' => $scenarioProfileId,
                            'start_period' => $period,
                            'end_period' => $period,
                        ],
                        [
                            'reporting_period_id' => $reportingPeriod->id, // ✅ Moved here
                            'weighted_value' => $weightedSum,
                            'revision' => 1,
                            'is_current' => true,
                            'confidence_level' => 0.95,
                        ]
                    );

                    Log::info("✅ Saved weighted forecast for {$period}");
                    
                } catch (\Exception $e) {
                    Log::error("❌ Error saving weighted forecast: " . $e->getMessage());
                    Log::error("Period: {$period}, Macro: {$macro->id}, Reporting Period ID: {$reportingPeriod->id}");
                }
            }
        }

        Log::info("=== CALCULATION COMPLETE ===");
        return true;
    }
}