<?php

namespace App\Http\Controllers\FLI;

use App\Http\Controllers\Controller;
use App\Models\ScenarioSet;
use App\Models\FliReportingPeriodParameter;
use App\Models\FliAdj;
use App\Models\LoanBook;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ExternalCalculationsController extends Controller
{
    /**
     * Display the external calculations page.
     */
    public function index()
    {
        $scenarioSets = ScenarioSet::where('is_active', true)
            ->with('probabilities')
            ->get();

        $economicStatistics = [
            ['value' => 'inflation', 'label' => 'Inflation'],
            ['value' => 'exchange_rates', 'label' => 'Exchange Rates'],
            ['value' => 'credit_index', 'label' => 'Credit Index'],
            ['value' => 'unemployment_rate', 'label' => 'Unemployment Rate'],
            ['value' => 'interest_rates', 'label' => 'Interest Rates'],
        ];

        $pdProxyStatistics = [
            ['value' => 'NPLs', 'label' => 'NPLs (Non-Performing Loans)'],
            ['value' => '12_months_PDs', 'label' => '12 Months PDs'],
        ];

        return Inertia::render('FLI/ExternalCalculations/Index', [
            'scenarioSets' => $scenarioSets,
            'economicStatistics' => $economicStatistics,
            'pdProxyStatistics' => $pdProxyStatistics,
        ]);
    }

    /**
     * Display list of all external calculations with history.
     */
    public function list()
    {
        $parameters = FliReportingPeriodParameter::with([
            'scenarioSet.probabilities',
            'creator:id,name'
        ])
        ->orderBy('reporting_period', 'desc')
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($param) {
            return [
                'id' => $param->id,
                'reporting_period' => $param->reporting_period ? $param->reporting_period->format('Y-m') : null,
                'scenario_set_name' => $param->scenarioSet?->name,
                'number_of_forecasting_periods' => $param->number_of_forecasting_periods,
                'forecasting_period_length_months' => $param->forecasting_period_length_months,
                'economic_data_statistic' => $param->economic_data_statistic,
                'pd_proxy_statistic' => $param->pd_proxy_statistic,
                'base_forecast_period' => $param->base_forecast_period ? $param->base_forecast_period->format('Y-m') : null,
                'base_macro_data_value' => $param->base_macro_data_value,
                'base_pd_proxy_value' => $param->base_pd_proxy_value,
                'regression_slope' => $param->regression_slope,
                'regression_intercept' => $param->regression_intercept,
                'created_by_name' => $param->creator?->name,
                'created_at' => $param->created_at?->format('Y-m-d H:i:s'),
                'scenarios' => $param->scenarioSet?->probabilities->map(function($prob) {
                    return [
                        'name' => $prob->scenario_name,
                        'probability' => $prob->probability,
                    ];
                }),
            ];
        });

        // Get adjustment counts per reporting period
        $adjustmentCounts = FliAdj::select('reporting_period', DB::raw('count(*) as count'))
            ->groupBy('reporting_period')
            ->pluck('count', 'reporting_period');

        // Get loan book update statistics
        $loanBookStats = LoanBook::whereNotNull('fli_adj')
            ->select('reporting_period', 
                DB::raw('count(*) as total_loans'),
                DB::raw('avg(fli_adj) as avg_fli_adj'),
                DB::raw('min(fli_adj) as min_fli_adj'),
                DB::raw('max(fli_adj) as max_fli_adj')
            )
            ->groupBy('reporting_period')
            ->get()
            ->keyBy('reporting_period');

        return Inertia::render('FLI/ExternalCalculations/List', [
            'parameters' => $parameters,
            'adjustmentCounts' => $adjustmentCounts,
            'loanBookStats' => $loanBookStats,
        ]);
    }

    /**
     * Save FLI parameters.
     */
    public function saveParameters(Request $request)
    {
        $validated = $request->validate([
            'reporting_period' => 'required|date_format:Y-m',
            'scenario_set_id' => 'required|exists:scenario_sets,id',
            'number_of_forecasting_periods' => 'required|integer|min:1|max:120',
            'forecasting_period_length_months' => 'required|integer|min:1|max:12',
            'economic_data_statistic' => 'required|in:inflation,exchange_rates,credit_index,unemployment_rate,interest_rates',
            'pd_proxy_statistic' => 'required|in:NPLs,12_months_PDs',
            'base_forecast_period' => 'required|date_format:Y-m',
            'base_macro_data_value' => 'required|numeric',
            'base_pd_proxy_value' => 'required|numeric|min:0|max:100',
            'regression_slope' => 'required|numeric',
            'regression_intercept' => 'required|numeric',
        ]);

        try {
            $parameter = FliReportingPeriodParameter::create([
                'reporting_period' => Carbon::createFromFormat('Y-m', $validated['reporting_period'])->startOfMonth(),
                'scenario_set_id' => $validated['scenario_set_id'],
                'number_of_forecasting_periods' => $validated['number_of_forecasting_periods'],
                'forecasting_period_length_months' => $validated['forecasting_period_length_months'],
                'economic_data_statistic' => $validated['economic_data_statistic'],
                'pd_proxy_statistic' => $validated['pd_proxy_statistic'],
                'base_forecast_period' => Carbon::createFromFormat('Y-m', $validated['base_forecast_period'])->startOfMonth(),
                'base_macro_data_value' => $validated['base_macro_data_value'],
                'base_pd_proxy_value' => $validated['base_pd_proxy_value'],
                'regression_slope' => $validated['regression_slope'],
                'regression_intercept' => $validated['regression_intercept'],
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Parameters saved successfully',
                'parameter_id' => $parameter->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save parameters: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate forecast periods table.
     */
    public function generateForecasts(Request $request)
    {
        $validated = $request->validate([
            'parameter_id' => 'required|exists:fli_reporting_periods_parameters,id',
        ]);

        $parameter = FliReportingPeriodParameter::findOrFail($validated['parameter_id']);

        $forecasts = [];
        $reportingPeriod = Carbon::parse($parameter->reporting_period);

        // Generate forecast periods from 0 to number_of_forecasting_periods
        for ($i = 0; $i <= $parameter->number_of_forecasting_periods; $i++) {
            $forecastWindowMonths = $i * $parameter->forecasting_period_length_months;
            $forecastPeriod = $reportingPeriod->copy()->addMonths($forecastWindowMonths);

            $forecasts[] = [
                'period_window' => $i,
                'forecast_period' => $forecastPeriod->format('Y-m'),
                'forecast_window_in_months' => $forecastWindowMonths,
                'weighted_macro_data_value' => $i === 0 ? $parameter->base_macro_data_value : null,
                'predicted_value' => null,
                'fli_adj' => null,
            ];
        }

        return response()->json([
            'success' => true,
            'forecasts' => $forecasts,
            'parameter' => $parameter,
        ]);
    }

    /**
     * Save FLI adjustments to database.
     */
    public function saveAdjustments(Request $request)
    {
        $validated = $request->validate([
            'parameter_id' => 'required|exists:fli_reporting_periods_parameters,id',
            'forecasts' => 'required|array',
            'forecasts.*.forecast_period' => 'required|date_format:Y-m',
            'forecasts.*.forecast_window_in_months' => 'required|integer|min:0',
            'forecasts.*.weighted_macro_data_value' => 'required|numeric',
        ]);

        $parameter = FliReportingPeriodParameter::findOrFail($validated['parameter_id']);
        $reportingPeriod = Carbon::parse($parameter->reporting_period);

        DB::beginTransaction();
        try {
            // Delete existing adjustments for this reporting period and scenario set
            FliAdj::where('reporting_period', $reportingPeriod)
                ->where('scenario_set_id', $parameter->scenario_set_id)
                ->delete();

            $basePredictedValue = null;

            foreach ($validated['forecasts'] as $forecast) {
                $macroValue = $forecast['weighted_macro_data_value'];
                
                // Calculate predicted value using regression formula
                $predictedValue = FliAdj::calculatePredictedValue(
                    $macroValue,
                    $parameter->regression_slope,
                    $parameter->regression_intercept
                );

                // Store base predicted value (period 0)
                if ($forecast['forecast_window_in_months'] == 0) {
                    $basePredictedValue = $predictedValue;
                }

                // Calculate FLI adjustment
                $fliAdj = FliAdj::calculateFliAdj($predictedValue, $basePredictedValue ?? $predictedValue);

                FliAdj::create([
                    'reporting_period' => $reportingPeriod,
                    'scenario_set_id' => $parameter->scenario_set_id,
                    'forecast_period' => Carbon::createFromFormat('Y-m', $forecast['forecast_period'])->startOfMonth(),
                    'forecast_window_in_months' => $forecast['forecast_window_in_months'],
                    'weighted_macro_data_value' => $macroValue,
                    'predicted_value' => $predictedValue,
                    'fli_adj' => $fliAdj,
                    'created_by' => Auth::id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'FLI adjustments saved successfully',
                'count' => count($validated['forecasts']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save adjustments: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update loan book with FLI adjustments.
     */
    public function updateLoanBook(Request $request)
    {
        $validated = $request->validate([
            'reporting_period' => 'required|date_format:Y-m',
            'scenario_set_id' => 'required|exists:scenario_sets,id',
        ]);

        $reportingPeriod = Carbon::createFromFormat('Y-m', $validated['reporting_period'])->startOfMonth();

        DB::beginTransaction();
        try {
            $stats = [
                'total_loans' => 0,
                'stage_1_updated' => 0,
                'stage_2_updated' => 0,
                'stage_3_skipped' => 0,
                'floored_at_zero' => 0,
                'capped_at_100' => 0,
                'no_matching_fli' => 0,
            ];

            // Get all loans for this reporting period
            $loans = LoanBook::where('reporting_period', $reportingPeriod->format('Y-m'))->get();
            $stats['total_loans'] = $loans->count();

            if ($stats['total_loans'] == 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No loans found for reporting period ' . $reportingPeriod->format('Y-m'),
                ], 404);
            }

            foreach ($loans as $loan) {
                // Skip Stage 3 loans (100% PD, no FLI adjustment)
                if ($loan->ifrs9stage_post_qualitative == 3) {
                    $loan->fli_adj = null;
                    $loan->pd_post_fli_adj = 1.0; // 100%
                    $loan->save();
                    $stats['stage_3_skipped']++;
                    continue;
                }

                // Determine forecast window based on stage
                // Get remaining life in months (using remaining_tenor from database)
                $remainingLife = $loan->remaining_tenor ?? 12; // Default to 12 if missing
                $forecastWindow = $loan->ifrs9stage_post_qualitative == 1
                    ? 12  // 12-month window for Stage 1
                    : (int) round($remainingLife);  // Lifetime for Stage 2 (convert to integer months)

                // Find matching FLI adjustment
                $fliAdj = FliAdj::where('reporting_period', $reportingPeriod)
                    ->where('scenario_set_id', $validated['scenario_set_id'])
                    ->where('forecast_window_in_months', $forecastWindow)
                    ->first();

                if ($fliAdj) {
                    $loan->fli_adj = $fliAdj->fli_adj;
                    
                    // Calculate PD after FLI adjustment
                    $pdPostFli = $loan->pd_value * (1 + $fliAdj->fli_adj);

                    // Apply bounds validation
                    if ($pdPostFli < 0) {
                        $pdPostFli = 0;
                        $stats['floored_at_zero']++;
                    } elseif ($pdPostFli > 1.0) {
                        $pdPostFli = 1.0;
                        $stats['capped_at_100']++;
                    }

                    $loan->pd_post_fli_adj = $pdPostFli;
                    $loan->save();

                    if ($loan->ifrs9stage_post_qualitative == 1) {
                        $stats['stage_1_updated']++;
                    } else {
                        $stats['stage_2_updated']++;
                    }
                } else {
                    $stats['no_matching_fli']++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Loan book updated successfully',
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update loan book: ' . $e->getMessage(),
            ], 500);
        }
    }
}
