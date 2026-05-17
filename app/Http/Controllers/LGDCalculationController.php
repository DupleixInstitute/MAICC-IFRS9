<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessLGDPayments;
use App\Models\LGDCalculationLog;
use App\Models\LGDPaymentTrackingLong;
use App\Models\LoanBook;
use App\Models\LoanPortfolio;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LGDCalculationController extends Controller
{
    /**
     * Display a listing of calculation logs.
     */
    public function index(Request $request)
    {
        $query = LGDCalculationLog::with(['portfolio', 'triggeredBy', 'childCalculations'])
            ->latestFirst();

        // Filter by portfolio
        if ($request->filled('portfolio_id')) {
            $query->forPortfolio($request->portfolio_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            switch($request->status) {
                case 'completed':
                    $query->completed();
                    break;
                case 'failed':
                    $query->failed();
                    break;
                case 'processing':
                    $query->processing();
                    break;
                case 'pending':
                    $query->pending();
                    break;
            }
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        $calculations = $query->paginate(20)->withQueryString();

        // Debug: Add recalculated status to each calculation
        $calculations->getCollection()->transform(function ($calculation) {
            $calculation->has_been_recalculated = $calculation->childCalculations()->exists();
            $calculation->is_recalculation = $calculation->parent_calculation_id !== null;
            return $calculation;
        });

        // Get portfolios for filter dropdown
        $portfolios = LoanPortfolio::select('id', 'name')->get();

        return inertia('RepaymentReports/Index', [
            'calculations' => $calculations,
            'filters' => $request->only(['portfolio_id', 'status', 'date_from', 'date_to']),
            'portfolios' => $portfolios
        ]);
    }

    /**
     * Show form to create new calculation.
     */
    public function create()
    {
        $portfolios = LoanPortfolio::select('id', 'name')->get();

        // Get available reporting periods from loan_books
        $availablePeriods = LoanBook::selectRaw('DISTINCT CONCAT(reporting_year, "-", LPAD(reporting_month, 2, "0")) as period')
            ->orderBy('reporting_year', 'desc')
            ->orderBy('reporting_month', 'desc')
            ->limit(24)
            ->pluck('period');

        return inertia('RepaymentReports/Create', [
            'portfolios' => $portfolios,
            'availablePeriods' => $availablePeriods
        ]);
    }

    /**
     * Store a new calculation and process payments.
     */
    public function store(Request $request)
    {
        ini_set('max_execution_time', 3000);

        $validator = Validator::make($request->all(), [
            'portfolio_id' => 'required|exists:loan_portfolios,id',
            'start_period' => 'required|date_format:Y-m',
            'end_period' => 'required|date_format:Y-m|after_or_equal:start_period',
            'recalculate_existing' => 'boolean'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $portfolioId = $request->portfolio_id;
        $startPeriod = Carbon::parse($request->start_period)->format('Y-m-01');
        $endPeriod = Carbon::parse($request->end_period)->format('Y-m-01');

        // Check if calculation already exists
        $existingCalculation = LGDCalculationLog::where('portfolio_group', $portfolioId)
            ->where('start_period', $startPeriod)
            ->where('end_period', $endPeriod)
            ->whereIn('status', ['completed', 'processing'])
            ->first();

        if ($existingCalculation && !$request->recalculate_existing) {
            return back()->with('warning', 'A calculation already exists for this period. Use "Recalculate" if you want to run it again.');
        }

        // Create calculation log
        $calculation = LGDCalculationLog::create([
            'start_period' => $startPeriod,
            'end_period' => $endPeriod,
            'portfolio_group' => $portfolioId,
            'status' => 'pending',
            'triggered_by' => auth()->id(),
            'trigger_source' => 'manual',
            'parent_calculation_id' => $existingCalculation->id ?? null,
            'recalculation_reason' => $existingCalculation ? 'User triggered recalculation' : null
        ]);

        // Dispatch job to process payments (for large datasets)
        if ($this->shouldUseQueue($portfolioId, $startPeriod, $endPeriod)) {
            ProcessLGDPayments::dispatch($calculation->id);
            return redirect()->route('lgd-calculations.show', $calculation->id)
                ->with('info', 'Calculation queued for processing. You will be notified when complete.');
        } else {
            // Process synchronously for smaller datasets
            $result = $this->processPayments($calculation);

            if ($result['success']) {
                return redirect()->route('lgd-calculations.show', $calculation->id)
                    ->with('success', "Calculation completed successfully in {$result['duration']}");
            } else {
                return redirect()->route('lgd-calculations.show', $calculation->id)
                    ->with('error', "Calculation failed: {$result['error']}");
            }
        }
    }

    /**
     * Display calculation details.
     */
    public function show($id)
    {
        $calculation = LGDCalculationLog::with([
            'portfolio',
            'triggeredBy',
            'parentCalculation',
            'childCalculations'
        ])->findOrFail($id);

        // Get summary statistics
        $stats = [
            'total_records' => LGDPaymentTrackingLong::fromCalculation($id)->count(),
            'records_with_payments' => LGDPaymentTrackingLong::fromCalculation($id)->withPayments()->count(),
            'total_payments' => LGDPaymentTrackingLong::fromCalculation($id)->withPayments()->sum('payment_amount'),
            'cured_contracts' => LGDPaymentTrackingLong::fromCalculation($id)->cured()->distinct('contract_id')->count('contract_id'),
            'unique_contracts' => LGDPaymentTrackingLong::fromCalculation($id)->distinct('contract_id')->count('contract_id')
        ];

        // Get recent payment records (for preview)
        $recentRecords = LGDPaymentTrackingLong::fromCalculation($id)
            ->with('portfolio')
            ->withPayments()
            ->orderBy('payment_amount', 'desc')
            ->limit(10)
            ->get();

        return inertia('RepaymentReports/Show', [
            'calculation' => $calculation,
            'stats' => $stats,
            'recentRecords' => $recentRecords,
            'summary' => $calculation->summary,
            'recommendedCureRate' => $calculation->recommended_cure_rate,
            'balanceBasedCureRate' => $calculation->balance_based_cure_rate
        ]);
    }

    /**
     * Estimate if queue processing should be used based on dataset size.
     */
    private function shouldUseQueue($portfolioId, $startPeriod, $endPeriod)
    {
        // Estimate total records for the period
        $estimatedRecords = LoanBook::where('loan_portfolio_id', $portfolioId)
            ->where(function($query) use ($startPeriod, $endPeriod) {
                $startYear = Carbon::parse($startPeriod)->year;
                $endYear = Carbon::parse($endPeriod)->year;

                if ($startYear == $endYear) {
                    // Same year case
                    $query->where('reporting_year', $startYear)
                          ->where('reporting_month', '>=', Carbon::parse($startPeriod)->month)
                          ->where('reporting_month', '<=', Carbon::parse($endPeriod)->month);
                } else {
                    // Different years - use string comparison for year-month
                    $query->whereRaw("CONCAT(reporting_year, '-', LPAD(reporting_month, 2, '0')) >= ?",
                          [Carbon::parse($startPeriod)->format('Y-m')])
                          ->whereRaw("CONCAT(reporting_year, '-', LPAD(reporting_month, 2, '0')) <= ?",
                          [Carbon::parse($endPeriod)->format('Y-m')]);
                }
            })
            ->count();

        // Use queue for more than 100,000 records
        return $estimatedRecords > 5000;
    }
    /**
     * Process payments for a calculation.
     */
    private function processPayments(LGDCalculationLog $calculation)
    {
        try {
            $calculation->startCalculation();

            $startTime = microtime(true);

            $portfolioId = $calculation->portfolio_group;
            $startPeriod = $calculation->start_period->format('Y-m-d');
            $endPeriod = $calculation->end_period->format('Y-m-d');

            // Get all contracts in the period
            $contracts = $this->getContractsInPeriod($portfolioId, $startPeriod, $endPeriod);

            $totalRecords = 0;
            $totalPayments = 0;
            $curedContracts = [];

            // Process each contract
            foreach ($contracts as $contractId) {
                $result = $this->processContractPayments(
                    $contractId,
                    $portfolioId,
                    $startPeriod,
                    $endPeriod,
                    $calculation->id
                );

                $totalRecords += $result['records'];
                $totalPayments += $result['total_payments'];
                if ($result['is_cured']) {
                    $curedContracts[] = $contractId;
                }
            }

            // Get total defaulted amount using year/month columns
            $totalDefaulted = LoanBook::where('loan_portfolio_id', $portfolioId)
                ->where(function($query) use ($startPeriod, $endPeriod) {
                    $startYear = Carbon::parse($startPeriod)->year;
                    $endYear = Carbon::parse($endPeriod)->year;

                    if ($startYear == $endYear) {
                        // Same year case
                        $query->where('reporting_year', $startYear)
                              ->where('reporting_month', '>=', Carbon::parse($startPeriod)->month)
                              ->where('reporting_month', '<=', Carbon::parse($endPeriod)->month);
                    } else {
                        // Different years - use string comparison for year-month
                        $query->whereRaw("CONCAT(reporting_year, '-', LPAD(reporting_month, 2, '0')) >= ?",
                              [Carbon::parse($startPeriod)->format('Y-m')])
                              ->whereRaw("CONCAT(reporting_year, '-', LPAD(reporting_month, 2, '0')) <= ?",
                              [Carbon::parse($endPeriod)->format('Y-m')]);
                    }
                })
                ->where('calculated_ifrs9_stage', '3')
                ->sum('carrying_amount');

            $duration = round(microtime(true) - $startTime, 2);

            // Complete calculation
            $calculation->completeCalculation(
                count($contracts),
                $totalRecords,
                $totalPayments,
                count($curedContracts),
                $totalDefaulted
            );

            return [
                'success' => true,
                'duration' => $duration . ' seconds'
            ];
        } catch (\Exception $e) {
            $calculation->fail($e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get unique contracts in the period.
     */
    private function getContractsInPeriod($portfolioId, $startPeriod, $endPeriod)
    {
        return LoanBook::where('loan_portfolio_id', $portfolioId)
            ->where(function($query) use ($startPeriod, $endPeriod) {
                $startYear = Carbon::parse($startPeriod)->year;
                $endYear = Carbon::parse($endPeriod)->year;

                if ($startYear == $endYear) {
                    // Same year case
                    $query->where('reporting_year', $startYear)
                          ->where('reporting_month', '>=', Carbon::parse($startPeriod)->month)
                          ->where('reporting_month', '<=', Carbon::parse($endPeriod)->month);
                } else {
                    // Different years - use string comparison for year-month
                    $query->whereRaw("CONCAT(reporting_year, '-', LPAD(reporting_month, 2, '0')) >= ?",
                          [Carbon::parse($startPeriod)->format('Y-m')])
                          ->whereRaw("CONCAT(reporting_year, '-', LPAD(reporting_month, 2, '0')) <= ?",
                          [Carbon::parse($endPeriod)->format('Y-m')]);
                }
            })
            ->distinct()
            ->pluck('contract_id')
            ->toArray();
    }

    /**
     * Process payments for a single contract.
     */
    private function processContractPayments($contractId, $portfolioId, $startPeriod, $endPeriod, $calculationId)
    {
        // Get all records for this contract
        $records = LoanBook::where('contract_id', $contractId)
            ->where('loan_portfolio_id', $portfolioId)
            ->where(function($query) use ($startPeriod, $endPeriod) {
                $startYear = Carbon::parse($startPeriod)->year;
                $endYear = Carbon::parse($endPeriod)->year;

                if ($startYear == $endYear) {
                    // Same year case
                    $query->where('reporting_year', $startYear)
                          ->where('reporting_month', '>=', Carbon::parse($startPeriod)->month)
                          ->where('reporting_month', '<=', Carbon::parse($endPeriod)->month);
                } else {
                    // Different years - use string comparison for year-month
                    $query->whereRaw("CONCAT(reporting_year, '-', LPAD(reporting_month, 2, '0')) >= ?",
                          [Carbon::parse($startPeriod)->format('Y-m')])
                          ->whereRaw("CONCAT(reporting_year, '-', LPAD(reporting_month, 2, '0')) <= ?",
                          [Carbon::parse($endPeriod)->format('Y-m')]);
                }
            })
            ->orderBy('reporting_year')
            ->orderBy('reporting_month')
            ->get();

        if ($records->isEmpty()) {
            return ['records' => 0, 'total_payments' => 0, 'is_cured' => false];
        }

        // Find first Stage 3 (cohort period)
        $cohortPeriod = null;
        foreach ($records as $record) {
            if ($record->calculated_ifrs9_stage == '3') {
                $cohortPeriod = Carbon::create($record->reporting_year, $record->reporting_month, 1)->startOfMonth();
                break;
            }
        }

        $previousBalance = null;
        $cumulativePayments = 0;
        $recordsCreated = 0;
        $isCured = false;
        $cureStage = null;

        foreach ($records as $record) {
            $currentBalance = $record->carrying_amount;
            $paymentAmount = 0;
            $paymentType = 'none';

            // For first record, set previousBalance to currentBalance (no payment expected)
            if ($previousBalance === null) {
                $previousBalance = $currentBalance;
            } elseif ($currentBalance < $previousBalance) {
                // Payment detected
                $paymentAmount = $previousBalance - $currentBalance;
                $cumulativePayments += $paymentAmount;
                $paymentType = $currentBalance == 0 ? 'full' : 'partial';
            }

            // Create payment tracking record
            $reportingPeriod = Carbon::create($record->reporting_year, $record->reporting_month, 1)->startOfMonth();
            $monthsSinceDefault = $cohortPeriod ? $cohortPeriod->diffInMonths($reportingPeriod) : 0;

            LGDPaymentTrackingLong::updateOrCreate(
                [
                    'contract_id' => $contractId,
                    'reporting_period' => $reportingPeriod
                ],
                [
                    'portfolio_group' => $portfolioId,
                    'cohort_period' => $cohortPeriod,
                    'payment_period' => $reportingPeriod,
                    'starting_balance' => $previousBalance,
                    'ending_balance' => $currentBalance,
                    'payment_amount' => $paymentAmount,
                    'cumulative_payments' => $cumulativePayments,
                    'payment_type' => $paymentType,
                    'ifrs9_stage' => $record->calculated_ifrs9_stage,
                    'months_since_default' => $monthsSinceDefault,
                    'is_cured' => false,
                    'cure_stage' => null,
                    'calculation_id' => $calculationId
                ]
            );

            $recordsCreated++;

            // Check if cured
            if ($cohortPeriod && $record->calculated_ifrs9_stage != '3') {
                $currentPeriod = Carbon::create($record->reporting_year, $record->reporting_month, 1)->startOfMonth();
                $monthsSinceCohort = $cohortPeriod->diffInMonths($currentPeriod);

                if ($monthsSinceCohort <= 12) {
                    $isCured = true;
                    $cureStage = $record->calculated_ifrs9_stage;

                    // Update current record to mark as cured
                    LGDPaymentTrackingLong::where('calculation_id', $calculationId)
                        ->where('contract_id', $contractId)
                        ->where('reporting_period', $reportingPeriod)
                        ->update([
                            'is_cured' => true,
                            'cure_stage' => $cureStage
                        ]);
                }
            }

            $previousBalance = $currentBalance;
        }

        return [
            'records' => $recordsCreated,
            'total_payments' => $cumulativePayments,
            'is_cured' => $isCured,
            'cure_stage' => $cureStage
        ];
    }

    /**
     * Recalculate a specific calculation.
     */
    public function recalculate($id, Request $request)
    {
        $originalCalculation = LGDCalculationLog::findOrFail($id);

        $request->validate([
            'reason' => 'nullable|string|max:500',
            'portfolio_group' => 'required|exists:loan_portfolios,id',
            'start_period' => 'required|date_format:Y-m',
            'end_period' => 'required|date_format:Y-m|after_or_equal:start_period'
        ]);

        // Check if there's already a pending/processing recalculation for the same period and portfolio
        $existingRecalculation = LGDCalculationLog::where('portfolio_group', $originalCalculation->portfolio_group)
            ->where('start_period', $originalCalculation->start_period)
            ->where('end_period', $originalCalculation->end_period)
            ->where('parent_calculation_id', $originalCalculation->id)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existingRecalculation) {
            return back()->with('warning', 'A recalculation is already in progress for this calculation. Please wait for it to complete or cancel it first.');
        }

        $recalculation = $originalCalculation->createRecalculation(
            $request->reason ?? 'User triggered recalculation',
            auth()->id()
        );

        // Process the recalculation
        $result = $this->processPayments($recalculation);

        if ($result['success']) {
            return redirect()->route('lgd-calculations.show', $recalculation->id)
                ->with('success', "Recalculation completed successfully in {$result['duration']}");
        } else {
            return redirect()->route('lgd-calculations.show', $recalculation->id)
                ->with('error', "Recalculation failed: {$result['error']}");
        }
    }

    /**
     * Cancel a pending/processing calculation.
     */
    public function cancel($id)
    {
        $calculation = LGDCalculationLog::findOrFail($id);

        if (!in_array($calculation->status, ['pending', 'processing'])) {
            return back()->with('error', 'Only pending or processing calculations can be cancelled.');
        }

        $calculation->status = 'failed';
        //$calculation->error_message = 'Cancelled by user';
        $calculation->end_time = now();
        $calculation->save();

        return back()->with('success', 'Calculation cancelled successfully.');
    }

    /**
     * Delete a calculation and its records.
     */
    public function destroy($id)
    {
        $calculation = LGDCalculationLog::findOrFail($id);

        // Delete all payment records first
        LGDPaymentTrackingLong::fromCalculation($id)->delete();

        // Delete calculation log
        $calculation->delete();

        return redirect()->route('lgd-calculations.index')
            ->with('success', 'Calculation deleted successfully.');
    }

    /**
     * Compare two calculations.
     */
    public function compare(Request $request)
    {
        $request->validate([
            'calculation_id_1' => 'required|exists:lgd_calculation_logs,id',
            'calculation_id_2' => 'required|exists:lgd_calculation_logs,id|different:calculation_id_1'
        ]);

        $calc1 = LGDCalculationLog::with('portfolio')->find($request->calculation_id_1);
        $calc2 = LGDCalculationLog::with('portfolio')->find($request->calculation_id_2);

        // Get summary statistics for comparison
        $stats1 = [
            'total_records' => LGDPaymentTrackingLong::fromCalculation($calc1->id)->count(),
            'total_payments' => LGDPaymentTrackingLong::fromCalculation($calc1->id)->sum('payment_amount'),
            'cured_count' => LGDPaymentTrackingLong::fromCalculation($calc1->id)->cured()->distinct('contract_id')->count('contract_id'),
            'avg_payment' => LGDPaymentTrackingLong::fromCalculation($calc1->id)->withPayments()->avg('payment_amount')
        ];

        $stats2 = [
            'total_records' => LGDPaymentTrackingLong::fromCalculation($calc2->id)->count(),
            'total_payments' => LGDPaymentTrackingLong::fromCalculation($calc2->id)->sum('payment_amount'),
            'cured_count' => LGDPaymentTrackingLong::fromCalculation($calc2->id)->cured()->distinct('contract_id')->count('contract_id'),
            'avg_payment' => LGDPaymentTrackingLong::fromCalculation($calc2->id)->withPayments()->avg('payment_amount')
        ];

        // Calculate differences
        $differences = [
            'records_diff' => $stats2['total_records'] - $stats1['total_records'],
            'payments_diff' => $stats2['total_payments'] - $stats1['total_payments'],
            'cured_diff' => $stats2['cured_count'] - $stats1['cured_count'],
            'records_diff_percent' => $stats1['total_records'] > 0
                ? round(($stats2['total_records'] - $stats1['total_records']) / $stats1['total_records'] * 100, 2)
                : 0,
            'payments_diff_percent' => $stats1['total_payments'] > 0
                ? round(($stats2['total_payments'] - $stats1['total_payments']) / $stats1['total_payments'] * 100, 2)
                : 0
        ];

        return inertia('RepaymentReports/Compare', [
            'calculation1' => $calc1,
            'calculation2' => $calc2,
            'stats1' => $stats1,
            'stats2' => $stats2,
            'differences' => $differences
        ]);
    }

    /**
     * Export repayment tracking data for a specific calculation.
     */
    public function export($calculationId)
    {
        $calculation = LGDCalculationLog::findOrFail($calculationId);

        // Get all payment tracking records for this calculation
        $data = LGDPaymentTrackingLong::where('calculation_id', $calculationId)
            ->with(['portfolio'])
            ->orderBy('contract_id')
            ->orderBy('reporting_period')
            ->get();

        $filename = 'repayment_export_' . $calculationId . '_' . date('Y-m-d_H-i-s') . '.csv';
        $filePath = storage_path('app/' . $filename);
        $handle = fopen($filePath, 'w+');

        // CSV Headers
        fputcsv($handle, [
            'Contract ID',
            'Reporting Period',
            'Starting Balance',
            'Ending Balance',
            'Payment Amount',
            'Cumulative Payments',
            'Payment Type',
            'IFRS9 Stage',
            'Months Since Default',
            'Is Cured',
            'Cure Stage',
            'Portfolio'
        ]);

        foreach ($data as $row) {
            fputcsv($handle, [
                $row->contract_id,
                $row->reporting_period,
                $row->starting_balance,
                $row->ending_balance,
                $row->payment_amount,
                $row->cumulative_payments,
                $row->payment_type,
                $row->ifrs9_stage,
                $row->months_since_default,
                $row->is_cured ? 'Yes' : 'No',
                $row->cure_stage,
                $row->portfolio->name ?? 'N/A'
            ]);
        }

        fclose($handle);

        return response()->download($filePath)->deleteFileAfterSend(true);
    }
}
