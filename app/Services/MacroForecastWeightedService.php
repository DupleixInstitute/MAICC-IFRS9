<?php

namespace App\Services;

use App\Models\ScenarioProfiles;
use App\Models\MacroForecastWeighted;
use App\Models\ReportingPeriods;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

class MacroForecastWeightedService
{
    public static function calculateWeightedForecast($scenarioProfileId, $startPeriod, $endPeriod)
    {
        DB::beginTransaction();
        try {
            $start = Carbon::parse($startPeriod)->startOfMonth();
            $end   = Carbon::parse($endPeriod)->startOfMonth();

            // Load profile with scenarios and macro data
            $profile = ScenarioProfiles::with([
                'scenarios.macroData' => function ($q) use ($start, $end) {
                    $q->whereBetween('period', [$start->format('Y-m'), $end->format('Y-m')]);
                }
            ])->findOrFail($scenarioProfileId);

            $scenarios = $profile->scenarios;

            // Completeness check
            $totalProbability = $scenarios->sum('probability');
            if ($totalProbability != 100) {
                throw new Exception("Scenario probabilities in profile must sum to 100%");
            }

            // Loop through each reporting month between start and end
            $current = $start->copy();
            while ($current->lte($end)) {

                // Find or create reporting period
                $reportingPeriod = ReportingPeriods::firstOrCreate(
                    ['period' => $current->format('Y-m')],
                    [
                        'reporting_year' => $current->copy()->startOfYear(),
                        'reporting_month' => $current->copy(),
                    ]
                );

                // Group macro variables for this month
                $macroVariables = [];
                foreach ($scenarios as $scenario) {
                    foreach ($scenario->macroData->where('period', $current->format('Y-m')) as $data)
                         {
                        $macroVariables[$data->macro_stat_definition_id][] = [
                            'value' => $data->value,
                            'probability' => $scenario->probability
                        ];
                    }
                }

                // Save weighted forecast per macro variable
                foreach ($macroVariables as $macroVarId => $points) {
                    $weightedValue = collect($points)->sum(function ($p) {
                        return $p['value'] * ($p['probability'] / 100);
                    });

                    MacroForecastWeighted::updateOrCreate(
                        [
                            'reporting_period_id' => $reportingPeriod->id,
                            'scenario_profile_id' => $profile->id,
                            'macro_statistic_id'  => $macroVarId,
                        ],
                        [
                            'weighted_value' => $weightedValue,
                        ]
                    );
                }

                $current->addMonth();
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
