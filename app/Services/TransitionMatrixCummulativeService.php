<?php

namespace App\Services;

use App\Models\TransitionMatrix;
use App\Models\TransitionMatrixCummulative;
use App\Models\TransitionMatrixCummulativeData;
use App\Models\TransitionMatrixData;
use Illuminate\Support\Facades\DB;
use Exception;

class TransitionMatrixCummulativeService
{
    public static function createCumulativeRecord($startPeriod, $endPeriod, $portfolioGroupId, $transitionProfileId)
    {
        $startPeriod = strlen($startPeriod) === 7 ? $startPeriod . '-01' : $startPeriod;
        $endPeriod = strlen($endPeriod) === 7 ? $endPeriod . '-01' : $endPeriod;

        DB::beginTransaction();
        try {
            // First get all relevant TransitionMatrix records
            $matrices = TransitionMatrix::where('transition_profile_id', $transitionProfileId)
                ->where('portfolio_group_id', $portfolioGroupId)
                ->where('status', 'Closed')
                ->whereBetween('start_reporting_period', [$startPeriod, $endPeriod])
                ->get();

            if(!$matrices){
                return back()->with('error','No closed PD monthly records');
            }

            // Get unique period pairs and total records count
            $periodPairs = $matrices->unique(function ($item) {
                return $item->start_reporting_period.$item->end_reporting_period;
            });
            
            $totalPeriodsCount = $periodPairs->count();
            $recordsCount = $matrices->sum('records_count_transitioned');

            // Get matrix IDs for querying TransitionMatrixData
            $matrixIds = $matrices->pluck('id');

            // Get the transition data for calculations
            $rawData = TransitionMatrixData::whereIn('calculation_header_id', $matrixIds)
                ->where('portfolio_group', $portfolioGroupId)
                ->whereBetween('start_period', [$startPeriod, $endPeriod])
                ->get();

            $startTotals = [];
            $defaultSums = [];
            $pdPercentages = [];
            $groupedMatrix = [];

            foreach ($rawData as $data) {
                $start = $data->start_stage;
                $end = $data->end_stage;
                $amount = $data->transition_balance_month;

                $groupedMatrix[$start][$end] = ($groupedMatrix[$start][$end] ?? 0) + $amount;
                $startTotals[$start] = ($startTotals[$start] ?? 0) + $amount;

                if ($data->default_flag) {
                    $defaultSums[$start] = ($defaultSums[$start] ?? 0) + $amount;
                }
            }

            // Calculate PD percentages
            foreach ($startTotals as $start => $total) {
                $default = $defaultSums[$start] ?? 0;
                $pdPercentages[$start] = $total == 0 ? 0 : round(($default / $total) * 100, 2);
            }

            // Create cumulative record
            $cumulative = TransitionMatrixCummulative::create([
                'start_period' => $startPeriod,
                'end_period' => $endPeriod,
                'portfolio_group' => $portfolioGroupId,
                'transition_profile_id' => $transitionProfileId,
                'periods_count' => $totalPeriodsCount,
                'calculation_source' => 'system',
                'last_reporting_period' => $endPeriod,
                'run_no' => 1,
                'status' => 'draft',
                'created_by' => auth()->id(),
                'user_name' => auth()->user()?->name,
            ]);

            // Prepare data for upsert
            $details = [];
            foreach ($groupedMatrix as $startStage => $endArray) {
                foreach ($endArray as $endStage => $amount) {
                    $details[] = [
                        'cummulative_id' => $cumulative->id,
                        'start_stage' => $startStage,
                        'end_stage' => $endStage,
                        'stage_transitions' => $startStage . 'to' . $endStage,
                        'start_total_cummulated' => $startTotals[$startStage] ?? 0,
                        'transition_balance_cummulated' => $amount,
                        'transition_probability_cummulated' => $pdPercentages[$startStage] ?? 0,
                        'status' => 'draft',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            TransitionMatrixCummulativeData::upsert(
                $details,
                ['cummulative_id', 'start_stage', 'end_stage'],
                ['start_total_cummulated', 'transition_balance_cummulated', 'transition_probability_cummulated', 'status', 'updated_at']
            );
            
            // Update with final calculated values
            $cumulative->update([
                'periods_count' => $totalPeriodsCount,
                'records_counted' => $recordsCount,
                'periods_list' => $periodPairs->map(function ($matrix) {
                    return [
                        'start' => $matrix->start_reporting_period,
                        'end' => $matrix->end_reporting_period
                    ];
                })->toArray(),
            ]);

            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("Failed to create cumulative record: " . $e->getMessage());
        }
    }
}