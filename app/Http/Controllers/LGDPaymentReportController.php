<?php

namespace App\Http\Controllers;

use App\Models\LGDPaymentTrackingLong;
use App\Models\LGDCalculationLog;
use App\Models\LoanPortfolio;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;

class LGDPaymentReportController extends Controller
{


    public function logReportGeneration(Request $request, $query, $chunkNumber)
    {
        Log::info('Starting report generation', ['request' => $request->all()]);
        Log::info('Query built successfully', ['query' => $query->toSql()]);
        Log::info('Processing chunk', ['chunk' => $chunkNumber]);
        Log::info('Report generation completed');
    }

    /**
     * Display payment report interface.
     */
    public function index(Request $request)
    {
        $portfolios = LoanPortfolio::select('id', 'name')->get();

        // Get available calculations for dropdown
        $calculations = LGDCalculationLog::completed()
            ->with('portfolio')
            ->latestFirst()
            ->limit(50)
            ->get()
            ->map(function($calc) {
                return [
                    'id' => $calc->id,
                    'label' => $calc->portfolio->name . ' - ' .
                              $calc->start_period->format('Y-m') . ' to ' .
                              $calc->end_period->format('Y-m') . ' (' .
                              $calc->created_at->format('Y-m-d H:i') . ')'
                ];
            });

        return inertia('RepaymentReports/Index', [
            'portfolios' => $portfolios,
            'calculations' => $calculations,
            'filters' => $request->only(['portfolio_id', 'calculation_id', 'start_period', 'end_period', 'contract_id'])
        ]);
    }

    /**
     * Generate payment report (long format).
     */
    public function generateReport(Request $request)
    {
        // Debug: Log the incoming request
        Log::info('generateReport called with: ' . json_encode($request->all()));

        // The report modal always submits these keys; treat blanks as absent so
        // the nullable|exists rules don't reject empty strings.
        foreach (['calculation_id', 'contract_id'] as $optional) {
            if ($request->input($optional) === '' || $request->input($optional) === null) {
                $request->merge([$optional => null]);
            }
        }

        $request->validate([
            'portfolio_id' => 'required|exists:loan_portfolios,id',
            'start_period' => 'required|date_format:Y-m',
            'end_period' => 'required|date_format:Y-m|after_or_equal:start_period',
            'format' => 'required|in:csv,excel',
            'calculation_id' => 'nullable|exists:lgd_calculation_logs,id',
            'contract_id' => 'nullable|string|max:199',
            'exclude_zero_payments' => 'required|boolean'
        ]);

        // Convert string boolean values to actual boolean
        if (is_string($request->exclude_zero_payments)) {
            $request->merge([
                'exclude_zero_payments' => in_array($request->exclude_zero_payments, ['1', 'true', 'on'], true)
            ]);
        }

        Log::info('Request validation passed');

        $portfolioId = $request->portfolio_id;
        $startPeriod = Carbon::parse($request->start_period . '-01')->format('Y-m-d');
        $endPeriod = Carbon::parse($request->end_period . '-01')->format('Y-m-d');

        // Build optimized query - remove unnecessary relationships for better performance
        $query = LGDPaymentTrackingLong::query()
            ->where('portfolio_group', $portfolioId)
            ->whereBetween('reporting_period', [$startPeriod, $endPeriod])
            ->orderBy('contract_id')
            ->orderBy('reporting_period');

        Log::info('Query after formatting reporting_period', ['query' => $query->toSql()]);

        // Filter by specific calculation if provided
        if ($request->filled('calculation_id')) {
            $query->where('calculation_id', $request->calculation_id);
        }

        // Filter by contract
        if ($request->filled('contract_id')) {
            $query->where('contract_id', $request->contract_id);
        }

        // Filter zero payments if requested
        if ($request->exclude_zero_payments) {
            $query->where('payment_amount', '>', 0);
        }

        // Handle different formats
        Log::info('Processing format: ' . $request->format);
        Log::info('Final query SQL: ' . $query->toSql());
        Log::info('Query bindings: ' . json_encode($query->getBindings()));

        switch ($request->format) {
            case 'csv':
                Log::info('Calling exportToCsv');
                return $this->exportToCsv($query, $request);
            case 'excel':
                Log::info('Calling exportToExcel');
                return $this->exportToExcel($query, $request);
        }
    }

    /**
     * Export to CSV.
     */
    private function exportToCsv($query, $request)
    {

        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        ini_set('max_input_time', 0);

        $filename = 'LGD_Payment_Report_' . $request->start_period . '_to_' . $request->end_period . '.csv';
        $csvPath = storage_path("app/{$filename}");

        // Open file for writing
        $handle = fopen($csvPath, 'w');
        if ($handle === false) {
            Log::error('Failed to open file for writing: ' . $csvPath);
            return back()->with('error', 'Failed to create CSV file');
        }

        // Add UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        // Write header row
        $csvHeader = [
            'Contract ID',
            'Reporting Period',
            'Payment Period',
            'Cohort Period',
            'Starting Balance',
            'Ending Balance',
            'Payment Amount',
            'Cumulative Payments',
            'Payment Type',
            'IFRS9 Stage',
            'Months Since Default',
            'Is Cured',
            'Cure Stage'
        ];

        // Add report title
        fputcsv($handle, ["LGD Payment Report: From {$request->start_period} To {$request->end_period}"]);
        fputcsv($handle, []); // empty row for spacing
        fputcsv($handle, $csvHeader);

        // Stream data directly to file to avoid memory issues
        $recordCount = 0;
        $chunkCount = 0;
        $chunkSize = 5000; // Increased chunk size for better performance
        $query->chunk($chunkSize, function($records) use ($handle, &$recordCount, &$chunkCount) {
            $chunkCount++;
            foreach ($records as $record) {
                $recordCount++;

                // Debug: Log first few records only
                if ($recordCount <= 3) {
                    Log::info('Record ' . $recordCount . ': ' . json_encode([
                        'contract_id' => $record->contract_id,
                        'reporting_period' => $record->reporting_period,
                        'payment_amount' => $record->payment_amount,
                        'is_cured' => $record->is_cured
                    ]));
                }

                // Log progress every 10,000 records
                if ($recordCount % 10000 === 0) {
                    Log::info("Processed {$recordCount} records in {$chunkCount} chunks");
                }

                // Write directly to file
                fputcsv($handle, [
                    $record->contract_id,
                    $record->reporting_period ? $record->reporting_period->format('Y-m-d') : '',
                    $record->payment_period ? $record->payment_period->format('Y-m-d') : '',
                    $record->cohort_period ? $record->cohort_period->format('Y-m-d') : '',
                    $record->starting_balance,
                    $record->ending_balance,
                    $record->payment_amount,
                    $record->cumulative_payments,
                    $record->payment_type,
                    $record->ifrs9_stage,
                    $record->months_since_default,
                    $record->is_cured ? 'Yes' : 'No',
                    $record->cure_stage ?? ''
                ]);
            }

            // Force write to disk every chunk
            fflush($handle);
        });

        fclose($handle);

        Log::info('Total records processed: ' . $recordCount);
        Log::info('CSV file created at: ' . $csvPath . ', size: ' . filesize($csvPath) . ' bytes');

        // Check if file was created successfully
        if (!file_exists($csvPath) || filesize($csvPath) === 0) {
            Log::error('CSV file creation failed or file is empty');
            return back()->with('error', 'Failed to create CSV file or file is empty');
        }

        // Prepare download response
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => filesize($csvPath),
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ];

        Log::info('Starting download response with headers: ' . json_encode($headers));

        return response()->download($csvPath, $filename, $headers)->deleteFileAfterSend(true);
    }

    /**
     * Export to Excel (CSV format with .xls extension).
     */
    private function exportToExcel($query, $request)
    {
        // Increase execution time and memory limits for large exports
        set_time_limit(0); // No time limit for large exports
        ini_set('memory_limit', '1024M'); // Increased memory limit
        ini_set('max_input_time', 0); // No input time limit

        $filename = 'LGD_Payment_Report_' . $request->start_period . '_to_' . $request->end_period . '.xls';
        $csvPath = storage_path("app/{$filename}");

        // Open file for writing
        $handle = fopen($csvPath, 'w');
        if ($handle === false) {
            Log::error('Failed to open Excel file for writing: ' . $csvPath);
            return back()->with('error', 'Failed to create Excel file');
        }

        // Add UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        // Write header row
        $csvHeader = [
            'Contract ID',
            'Reporting Period',
            'Payment Period',
            'Cohort Period',
            'Starting Balance',
            'Ending Balance',
            'Payment Amount',
            'Cumulative Payments',
            'Payment Type',
            'IFRS9 Stage',
            'Months Since Default',
            'Is Cured',
            'Cure Stage'
        ];

        // Add report title
        fputcsv($handle, ["LGD Payment Report: From {$request->start_period} To {$request->end_period}"]);
        fputcsv($handle, []); // empty row for spacing
        fputcsv($handle, $csvHeader);

        // Stream data directly to file to avoid memory issues
        $totalPayments = 0;
        $recordCount = 0;
        $chunkCount = 0;
        $chunkSize = 5000; // Increased chunk size for better performance
        $query->chunk($chunkSize, function($records) use ($handle, &$totalPayments, &$recordCount, &$chunkCount) {
            $chunkCount++;
            foreach ($records as $record) {
                $recordCount++;
                $totalPayments += $record->payment_amount;

                // Log progress every 10,000 records
                if ($recordCount % 10000 === 0) {
                    Log::info("Excel export - Processed {$recordCount} records in {$chunkCount} chunks");
                }

                // Write directly to file
                fputcsv($handle, [
                    $record->contract_id,
                    $record->reporting_period ? $record->reporting_period->format('Y-m-d') : '',
                    $record->payment_period ? $record->payment_period->format('Y-m-d') : '',
                    $record->cohort_period ? $record->cohort_period->format('Y-m-d') : '',
                    $record->starting_balance,
                    $record->ending_balance,
                    $record->payment_amount,
                    $record->cumulative_payments,
                    $record->payment_type,
                    $record->ifrs9_stage,
                    $record->months_since_default,
                    $record->is_cured ? 'Yes' : 'No',
                    $record->cure_stage ?? ''
                ]);
            }

            // Force write to disk every chunk
            fflush($handle);
        });

        // Add summary
        fputcsv($handle, []); // empty row for spacing
        fputcsv($handle, ["SUMMARY"]);
        fputcsv($handle, ["Total Payments:", number_format($totalPayments, 2)]);

        fclose($handle);

        Log::info('Excel export - Total records processed: ' . $recordCount);
        Log::info('Excel file created at: ' . $csvPath . ', size: ' . filesize($csvPath) . ' bytes');

        // Check if file was created successfully
        if (!file_exists($csvPath) || filesize($csvPath) === 0) {
            Log::error('Excel file creation failed or file is empty');
            return back()->with('error', 'Failed to create Excel file or file is empty');
        }

        // Prepare download response
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => filesize($csvPath),
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ];

        Log::info('Starting Excel download response with headers: ' . json_encode($headers));

        return response()->download($csvPath, $filename, $headers)->deleteFileAfterSend(true);
    }

    /**
     * Get payment summary by month.
     */
    public function monthlySummary(Request $request)
    {
        $request->validate([
            'portfolio_id' => 'required|exists:loan_portfolios,id',
            'year' => 'required|integer|min:2000|max:2100'
        ]);

        $portfolioId = $request->portfolio_id;
        $year = $request->year;

        $startPeriod = $year . '-01-01';
        $endPeriod = $year . '-12-01';

        $monthlyData = LGDPaymentTrackingLong::forPortfolio($portfolioId)
            ->inReportingPeriodRange($startPeriod, $endPeriod)
            ->select(
                DB::raw('MONTH(reporting_period) as month'),
                DB::raw('COUNT(DISTINCT contract_id) as unique_contracts'),
                DB::raw('COUNT(*) as total_records'),
                DB::raw('SUM(payment_amount) as total_payments'),
                DB::raw('AVG(payment_amount) as avg_payment'),
                DB::raw('SUM(CASE WHEN payment_type = "full" THEN 1 ELSE 0 END) as full_payments'),
                DB::raw('SUM(CASE WHEN payment_type = "partial" THEN 1 ELSE 0 END) as partial_payments'),
                DB::raw('SUM(CASE WHEN is_cured = 1 THEN 1 ELSE 0 END) as cured_contracts')
            )
            ->groupBy(DB::raw('MONTH(reporting_period)'))
            ->orderBy('month')
            ->get();

        // Fill in missing months
        $result = [];
        for ($month = 1; $month <= 12; $month++) {
            $data = $monthlyData->firstWhere('month', $month);
            $result[] = [
                'month' => $month,
                'month_name' => Carbon::createFromDate($year, $month, 1)->format('F'),
                'unique_contracts' => $data->unique_contracts ?? 0,
                'total_records' => $data->total_records ?? 0,
                'total_payments' => $data->total_payments ?? 0,
                'avg_payment' => $data->avg_payment ?? 0,
                'full_payments' => $data->full_payments ?? 0,
                'partial_payments' => $data->partial_payments ?? 0,
                'cured_contracts' => $data->cured_contracts ?? 0
            ];
        }

        return response()->json([
            'year' => $year,
            'portfolio' => LoanPortfolio::find($portfolioId)->name,
            'data' => $result,
            'total_for_year' => [
                'total_payments' => array_sum(array_column($result, 'total_payments')),
                'total_full_payments' => array_sum(array_column($result, 'full_payments')),
                'total_partial_payments' => array_sum(array_column($result, 'partial_payments')),
                'total_cured' => array_sum(array_column($result, 'cured_contracts'))
            ]
        ]);
    }

    /**
     * Get contract details with payment history.
     */
    public function contractDetails(Request $request)
    {
        $request->validate([
            'contract_id' => 'required|string|max:199',
            'portfolio_id' => 'required|exists:loan_portfolios,id'
        ]);

        $contractId = $request->contract_id;
        $portfolioId = $request->portfolio_id;

        $history = LGDPaymentTrackingLong::forPortfolio($portfolioId)
            ->forContract($contractId)
            ->ordered()
            ->get();

        if ($history->isEmpty()) {
            return response()->json(['error' => 'Contract not found'], 404);
        }

        $summary = [
            'contract_id' => $contractId,
            'first_seen' => $history->first()->reporting_period->format('Y-m-d'),
            'last_seen' => $history->last()->reporting_period->format('Y-m-d'),
            'total_payments' => $history->sum('payment_amount'),
            'payment_count' => $history->where('payment_amount', '>', 0)->count(),
            'current_balance' => $history->last()->ending_balance,
            'is_cured' => $history->where('is_cured', true)->isNotEmpty(),
            'cohort_period' => $history->firstWhere('cohort_period', '!=', null)?->cohort_period->format('Y-m-d')
        ];

        return response()->json([
            'summary' => $summary,
            'history' => $history->map(function($record) {
                return [
                    'period' => $record->reporting_period ? $record->reporting_period->format('Y-m-d') : '',
                    'balance' => $record->ending_balance,
                    'payment' => $record->payment_amount,
                    'payment_type' => $record->payment_type,
                    'stage' => $record->ifrs9_stage,
                    'cumulative' => $record->cumulative_payments,
                    'months_since_default' => $record->months_since_default
                ];
            })
        ]);
    }

    /**
     * Download comparison report between two calculations.
     */
    public function downloadComparison($id1, $id2)
    {
        $calc1 = LGDCalculationLog::findOrFail($id1);
        $calc2 = LGDCalculationLog::findOrFail($id2);

        $filename = 'lgd_comparison_' . $calc1->id . '_vs_' . $calc2->id . '_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($calc1, $calc2) {
            $file = fopen('php://output', 'w');

            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['LGD CALCULATION COMPARISON REPORT']);
            fputcsv($file, ['']);
            fputcsv($file, ['Calculation 1:', $calc1->id, $calc1->created_at->format('Y-m-d H:i:s'), 'Status:', $calc1->status]);
            fputcsv($file, ['Calculation 2:', $calc2->id, $calc2->created_at->format('Y-m-d H:i:s'), 'Status:', $calc2->status]);
            fputcsv($file, ['']);

            // Get contracts that exist in both calculations
            $contracts1 = LGDPaymentTrackingLong::fromCalculation($calc1->id)
                ->distinct('contract_id')
                ->pluck('contract_id');

            $contracts2 = LGDPaymentTrackingLong::fromCalculation($calc2->id)
                ->distinct('contract_id')
                ->pluck('contract_id');

            $commonContracts = $contracts1->intersect($contracts2);
            $onlyIn1 = $contracts1->diff($contracts2);
            $onlyIn2 = $contracts2->diff($contracts1);

            fputcsv($file, ['CONTRACT COMPARISON']);
            fputcsv($file, ['Total Contracts in Calc 1:', $contracts1->count()]);
            fputcsv($file, ['Total Contracts in Calc 2:', $contracts2->count()]);
            fputcsv($file, ['Common Contracts:', $commonContracts->count()]);
            fputcsv($file, ['Only in Calc 1:', $onlyIn1->count()]);
            fputcsv($file, ['Only in Calc 2:', $onlyIn2->count()]);
            fputcsv($file, ['']);

            // Payment comparison for common contracts
            fputcsv($file, ['PAYMENT COMPARISON FOR COMMON CONTRACTS']);
            fputcsv($file, ['Contract ID', 'Calc1 Payments', 'Calc2 Payments', 'Difference', 'Difference %']);

            foreach ($commonContracts as $contractId) {
                $payments1 = LGDPaymentTrackingLong::fromCalculation($calc1->id)
                    ->forContract($contractId)
                    ->sum('payment_amount');

                $payments2 = LGDPaymentTrackingLong::fromCalculation($calc2->id)
                    ->forContract($contractId)
                    ->sum('payment_amount');

                $diff = $payments2 - $payments1;
                $diffPercent = $payments1 > 0 ? round(($diff / $payments1) * 100, 2) : 0;

                fputcsv($file, [
                    $contractId,
                    number_format($payments1, 2),
                    number_format($payments2, 2),
                    number_format($diff, 2),
                    $diffPercent . '%'
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }
}
