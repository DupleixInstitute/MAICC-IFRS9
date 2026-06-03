<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

class LoanBookReconciliationService
{
    /**
     * Generate Loan Book Reconciliation Report
     */
    public static function generate(array $params): array
    {
        $portfolioId = $params['portfolio_id'] ?? null;
        $startPeriod = $params['start_period'] ?? null;
        $endPeriod = $params['end_period'] ?? null;

        if (!$portfolioId || !$startPeriod || !$endPeriod) {
            throw new \InvalidArgumentException('Portfolio ID, start period, and end period are required');
        }

        // Get opening balance
        $openingBalance = DB::table('loan_books')
            ->where('loan_portfolio_id', $portfolioId)
            ->where('reporting_period', $startPeriod)
            ->sum('carrying_amount');

        // Get closing balance
        $closingBalance = DB::table('loan_books')
            ->where('loan_portfolio_id', $portfolioId)
            ->where('reporting_period', $endPeriod)
            ->sum('carrying_amount');

        // Get new disbursements during the period
        $newDisbursements = DB::table('loan_books as lb_end')
            ->leftJoin('loan_books as lb_start', function ($join) use ($startPeriod) {
                $join->on('lb_start.contract_id', '=', 'lb_end.contract_id')
                    ->on('lb_start.loan_portfolio_id', '=', 'lb_end.loan_portfolio_id')
                    ->where('lb_start.reporting_period', '=', $startPeriod);
            })
            ->where('lb_end.loan_portfolio_id', $portfolioId)
            ->where('lb_end.reporting_period', $endPeriod)
            ->whereNull('lb_start.contract_id')
            ->sum('lb_end.carrying_amount');

        // Get repayments during the period
        $repayments = DB::table('loan_books as lb_start')
            ->leftJoin('loan_books as lb_end', function ($join) use ($endPeriod) {
                $join->on('lb_start.contract_id', '=', 'lb_end.contract_id')
                    ->on('lb_start.loan_portfolio_id', '=', 'lb_end.loan_portfolio_id')
                    ->where('lb_end.reporting_period', '=', $endPeriod);
            })
            ->where('lb_start.loan_portfolio_id', $portfolioId)
            ->where('lb_start.reporting_period', $startPeriod)
            ->whereNotNull('lb_end.contract_id')
            ->selectRaw('SUM(lb_start.carrying_amount - lb_end.carrying_amount) as total_repayment')
            ->value('total_repayment') ?? 0;

        // Get write-offs during the period
        $writeOffs = DB::table('loan_books')
            ->where('loan_portfolio_id', $portfolioId)
            ->where('reporting_period', $endPeriod)
            ->where('contract_status', 'like', '%write%')
            ->sum('carrying_amount');

        return [
            'portfolio_id' => $portfolioId,
            'start_period' => $startPeriod,
            'end_period' => $endPeriod,
            'opening_balance' => (float) $openingBalance,
            'closing_balance' => (float) $closingBalance,
            'new_disbursements' => (float) $newDisbursements,
            'repayments' => (float) $repayments,
            'write_offs' => (float) $writeOffs,
            'reconciliation' => [
                'opening_balance' => (float) $openingBalance,
                'add_new_disbursements' => (float) $newDisbursements,
                'less_repayments' => (float) $repayments,
                'less_write_offs' => (float) $writeOffs,
                'expected_closing_balance' => (float) ($openingBalance + $newDisbursements - $repayments - $writeOffs),
                'actual_closing_balance' => (float) $closingBalance,
                'variance' => (float) ($closingBalance - ($openingBalance + $newDisbursements - $repayments - $writeOffs)),
            ]
        ];
    }

    /**
     * Export Loan Book Reconciliation Report to CSV
     */
    public static function exportToCsv(array $params): Response
    {
        $report = self::generate($params);
        $portfolioName = DB::table('loan_portfolios')
            ->where('id', $report['portfolio_id'])
            ->value('name') ?? 'Unknown Portfolio';

        $filename = sprintf(
            'loan_book_reconciliation_%s_%s_to_%s.csv',
            str_replace(' ', '_', $portfolioName),
            str_replace('-', '_', $report['start_period']),
            str_replace('-', '_', $report['end_period'])
        );

        return response()->streamDownload(function () use ($report, $portfolioName) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Loan Book Reconciliation Report']);
            fputcsv($handle, ['Portfolio', $portfolioName]);
            fputcsv($handle, ['Start Period', $report['start_period']]);
            fputcsv($handle, ['End Period', $report['end_period']]);
            fputcsv($handle, ['Date of Export', now()->format('Y-m-d H:i:s')]);
            fputcsv($handle, []);

            fputcsv($handle, ['Reconciliation Item', 'Amount']);
            fputcsv($handle, ['Opening Balance', number_format($report['opening_balance'], 2)]);
            fputcsv($handle, ['Add: New Disbursements', number_format($report['new_disbursements'], 2)]);
            fputcsv($handle, ['Less: Repayments', number_format($report['repayments'], 2)]);
            fputcsv($handle, ['Less: Write-offs', number_format($report['write_offs'], 2)]);
            fputcsv($handle, []);
            fputcsv($handle, ['Expected Closing Balance', number_format($report['reconciliation']['expected_closing_balance'], 2)]);
            fputcsv($handle, ['Actual Closing Balance', number_format($report['reconciliation']['actual_closing_balance'], 2)]);
            fputcsv($handle, ['Variance', number_format($report['reconciliation']['variance'], 2)]);

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Get available portfolios for the report
     */
    public static function getAvailablePortfolios(): array
    {
        return DB::table('loan_portfolios')
            ->where('active', 1)
            ->orderBy('name')
            ->get()
            ->map(function ($portfolio) {
                return [
                    'value' => $portfolio->id,
                    'label' => $portfolio->name,
                ];
            })
            ->toArray();
    }

    /**
     * Get available reporting periods for a portfolio
     */
    public static function getAvailablePeriods(int $portfolioId): array
    {
        return DB::table('loan_books')
            ->where('loan_portfolio_id', $portfolioId)
            ->select('reporting_period')
            ->distinct()
            ->orderBy('reporting_period', 'desc')
            ->limit(24)
            ->pluck('reporting_period')
            ->map(function ($period) {
                return [
                    'value' => $period,
                    'label' => $period,
                ];
            })
            ->toArray();
    }
}
