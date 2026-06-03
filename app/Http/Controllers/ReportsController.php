<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\Reports\EclReconciliationService;
use App\Services\Reports\LoanBookReconciliationService;
use App\Services\Reports\LoanBookExportService;
use App\Services\Reports\ECLExportService;
use App\Services\Reports\DisbursementReportService;

class ReportsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:reports'])->only(['index']);
    }

    /**
     * Reports hub - lists all available system reports as links.
     */
    public function index()
    {
        return Inertia::render('Reports/Index', [
            'reports' => [
                [
                    'symbol' => 'A',
                    'name' => 'Reconciliation Reports',
                    'reports' => [
                        [
                            'symbol' => 1,
                            'name' => 'ECL Reconciliation',
                            'route' => 'reports.ecl-reconciliation',
                            'permission' => 'reports',
                        ],
                        [
                            'symbol' => 2,
                            'name' => 'Loan Book Reconciliation',
                            'route' => 'reports.loan-book-reconciliation',
                            'permission' => 'reports',
                        ],
                    ],
                ],
                [
                    'symbol' => 'B',
                    'name' => 'Export Reports',
                    'reports' => [
                        [
                            'symbol' => 1,
                            'name' => 'Loan Book Export',
                            'route' => 'reports.loan-book-export',
                            'permission' => 'reports',
                        ],
                        [
                            'symbol' => 2,
                            'name' => 'ECL Export',
                            'route' => 'reports.ecl-export',
                            'permission' => 'reports',
                        ],
                        [
                            'symbol' => 3,
                            'name' => 'Disbursement Report',
                            'route' => 'reports.disbursement-report',
                            'permission' => 'reports',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * ECL Reconciliation Report
     */
    public function eclReconciliation(Request $request)
    {
        $report = null;
        $portfolioId = $request->portfolio_id;
        $startPeriod = $request->start_period;
        $endPeriod = $request->end_period;
        $movementType = $request->movement_type ?? 'ecl_value';
        $reportType = $request->report_type ?? 'summary';
        $detailType = $request->detail_type ?: null;

        $serviceParams = [
            'portfolio_id' => $portfolioId,
            'start_period' => $startPeriod,
            'end_period' => $endPeriod,
            'movement_type' => $movementType,
            'report_type' => $reportType,
            'detail_type' => $detailType,
        ];

        if ($request->export && $portfolioId && $startPeriod && $endPeriod) {
            try {
                return EclReconciliationService::exportToCsv($serviceParams);
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to export report: ' . $e->getMessage());
            }
        }

        if ($request->generate && $portfolioId && $startPeriod && $endPeriod) {
            try {
                $report = EclReconciliationService::generate($serviceParams);
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
            }
        }

        return Inertia::render('Reports/EclReconciliation', [
            'report' => $report,
            'portfolios' => EclReconciliationService::getAvailablePortfolios(),
            'periods' => $portfolioId ? EclReconciliationService::getAvailablePeriods((int) $portfolioId) : [],
            'selectedPortfolio' => $portfolioId,
            'selectedStartPeriod' => $startPeriod,
            'selectedEndPeriod' => $endPeriod,
            'selectedMovementType' => $movementType,
            'selectedReportType' => $reportType,
            'selectedDetailType' => $detailType,
        ]);
    }

    /**
     * Loan Book Reconciliation Report
     */
    public function loanBookReconciliation(Request $request)
    {
        $report = null;
        $portfolioId = $request->portfolio_id;
        $startPeriod = $request->start_period;
        $endPeriod = $request->end_period;

        if ($request->generate && $portfolioId && $startPeriod && $endPeriod) {
            try {
                $report = LoanBookReconciliationService::generate([
                    'portfolio_id' => $portfolioId,
                    'start_period' => $startPeriod,
                    'end_period' => $endPeriod,
                ]);
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
            }
        }

        if ($request->export && $portfolioId && $startPeriod && $endPeriod) {
            try {
                return LoanBookReconciliationService::exportToCsv([
                    'portfolio_id' => $portfolioId,
                    'start_period' => $startPeriod,
                    'end_period' => $endPeriod,
                ]);
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to export report: ' . $e->getMessage());
            }
        }

        return Inertia::render('Reports/LoanBookReconciliation', [
            'report' => $report,
            'portfolios' => LoanBookReconciliationService::getAvailablePortfolios(),
            'periods' => $portfolioId ? LoanBookReconciliationService::getAvailablePeriods((int) $portfolioId) : [],
            'selectedPortfolio' => $portfolioId,
            'selectedStartPeriod' => $startPeriod,
            'selectedEndPeriod' => $endPeriod,
        ]);
    }

    /**
     * Loan Book Export Report
     */
    public function loanBookExport(Request $request)
    {
        $portfolioId = $request->portfolio_id;
        $startPeriod = $request->start_period;
        $endPeriod = $request->end_period;
        $mode = $request->mode ?? 'summary';

        if ($request->export && $startPeriod && $endPeriod) {
            try {
                return LoanBookExportService::exportToCsv([
                    'portfolio_id' => $portfolioId,
                    'start_period' => $startPeriod,
                    'end_period' => $endPeriod,
                    'mode' => $mode,
                ]);
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
            }
        }

        return Inertia::render('Reports/LoanBookExport', [
            'portfolios' => LoanBookExportService::getAvailablePortfolios(),
            'periods' => $portfolioId ? LoanBookExportService::getAvailablePeriods((int) $portfolioId) : [],
            'selectedPortfolio' => $portfolioId,
            'selectedStartPeriod' => $startPeriod,
            'selectedEndPeriod' => $endPeriod,
            'selectedMode' => $mode,
        ]);
    }

    /**
     * ECL Export Report
     */
    public function eclExport(Request $request)
    {
        $portfolioId = $request->portfolio_id;
        $reportingPeriod = $request->reporting_period;
        $mode = $request->mode ?? 'summary';
        $columns = $request->columns ?? [];

        if ($request->export && $portfolioId && $reportingPeriod) {
            try {
                return ECLExportService::exportToCsv([
                    'portfolio_id' => $portfolioId,
                    'reporting_period' => $reportingPeriod,
                    'mode' => $mode,
                    'columns' => $columns,
                ]);
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
            }
        }

        return Inertia::render('Reports/ECLExport', [
            'portfolios' => ECLExportService::getAvailablePortfolios(),
            'periods' => $portfolioId ? ECLExportService::getAvailablePeriods((int) $portfolioId) : [],
            'availableColumns' => ECLExportService::getAvailableColumns(),
            'selectedPortfolio' => $portfolioId,
            'selectedPeriod' => $reportingPeriod,
            'selectedMode' => $mode,
            'selectedColumns' => $columns,
        ]);
    }

    /**
     * Disbursement Report
     */
    public function disbursementReport(Request $request)
    {
        $portfolioId = $request->portfolio_id;
        $startPeriod = $request->start_period;
        $endPeriod = $request->end_period;
        $mode = $request->mode ?? 'summary';

        if ($request->export && $startPeriod && $endPeriod) {
            try {
                return DisbursementReportService::exportToCsv([
                    'portfolio_id' => $portfolioId,
                    'start_period' => $startPeriod,
                    'end_period' => $endPeriod,
                    'mode' => $mode,
                ]);
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
            }
        }

        return Inertia::render('Reports/DisbursementReport', [
            'portfolios' => DisbursementReportService::getAvailablePortfolios(),
            'periods' => $portfolioId ? DisbursementReportService::getAvailablePeriods((int) $portfolioId) : [],
            'selectedPortfolio' => $portfolioId,
            'selectedStartPeriod' => $startPeriod,
            'selectedEndPeriod' => $endPeriod,
            'selectedMode' => $mode,
        ]);
    }
}
