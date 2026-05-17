<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoanBookExportService
{
    /**
     * Generate Loan Book Export Report
     */
    public static function generate(array $params): array
    {
        $portfolioId = $params['portfolio_id'] ?? null;
        $startPeriod = $params['start_period'] ?? null;
        $endPeriod = $params['end_period'] ?? null;
        $mode = $params['mode'] ?? 'summary';

        if (!$startPeriod || !$endPeriod) {
            throw new \InvalidArgumentException('Start period and end period are required');
        }

        if ($mode === 'summary') {
            return self::generateSummaryData($startPeriod, $endPeriod, $portfolioId);
        }

        if ($mode === 'detailed') {
            return self::generateDetailedData($startPeriod, $endPeriod, $portfolioId);
        }

        throw new \InvalidArgumentException('Invalid mode specified');
    }

    /**
     * Export Loan Book Report to CSV
     */
    public static function exportToCsv(array $params): StreamedResponse
    {
        $data = self::generate($params);
        $startPeriod = $params['start_period'];
        $endPeriod = $params['end_period'];
        $mode = $params['mode'] ?? 'summary';

        return self::createCsvResponse($data, $mode, $startPeriod, $endPeriod);
    }

    /**
     * Generate summary data (aggregated by period and stage)
     */
    private static function generateSummaryData(string $startPeriod, string $endPeriod, ?int $portfolioId): array
    {
        $query = DB::table('loan_books as lb')
            ->select([
                'lb.reporting_year',
                'lb.reporting_month',
                DB::raw('CONCAT(lb.reporting_year, "-", LPAD(lb.reporting_month, 2, "0")) as reporting_period'),
                DB::raw('SUM(CASE WHEN lb.calculated_ifrs9_stage = 1 THEN lb.carrying_amount ELSE 0 END) as stage_1_balance'),
                DB::raw('SUM(CASE WHEN lb.calculated_ifrs9_stage = 2 THEN lb.carrying_amount ELSE 0 END) as stage_2_balance'),
                DB::raw('SUM(CASE WHEN lb.calculated_ifrs9_stage = 3 THEN lb.carrying_amount ELSE 0 END) as stage_3_balance'),
                DB::raw('SUM(lb.carrying_amount) as total_principal_balance'),
                DB::raw('COUNT(CASE WHEN lb.calculated_ifrs9_stage = 1 THEN 1 END) as stage_1_count'),
                DB::raw('COUNT(CASE WHEN lb.calculated_ifrs9_stage = 2 THEN 1 END) as stage_2_count'),
                DB::raw('COUNT(CASE WHEN lb.calculated_ifrs9_stage = 3 THEN 1 END) as stage_3_count'),
                DB::raw('COUNT(*) as total_count'),
            ])
            ->whereRaw("CONCAT(lb.reporting_year, '-', LPAD(lb.reporting_month, 2, '0')) >= ?", [$startPeriod])
            ->whereRaw("CONCAT(lb.reporting_year, '-', LPAD(lb.reporting_month, 2, '0')) <= ?", [$endPeriod])
            ->groupBy('lb.reporting_year', 'lb.reporting_month')
            ->orderBy('lb.reporting_year', 'desc')
            ->orderBy('lb.reporting_month', 'desc');

        if ($portfolioId) {
            $query->where('lb.loan_portfolio_id', $portfolioId);
        }

        return $query->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    private static function generateDetailedData(string $startPeriod, string $endPeriod, ?int $portfolioId): array
    {
        $query = DB::table('loan_books as lb')
            ->select([
                'lb.contract_id',
                DB::raw('lb.carrying_amount as principal_balance'),
                'lb.calculated_ifrs9_stage',
                DB::raw('CONCAT(lb.reporting_year, "-", LPAD(lb.reporting_month, 2, "0")) as reporting_period'),
            ])
            ->whereRaw("CONCAT(lb.reporting_year, '-', LPAD(lb.reporting_month, 2, '0')) >= ?", [$startPeriod])
            ->whereRaw("CONCAT(lb.reporting_year, '-', LPAD(lb.reporting_month, 2, '0')) <= ?", [$endPeriod])
            ->orderBy('lb.reporting_year', 'desc')
            ->orderBy('lb.reporting_month', 'desc')
            ->orderBy('lb.contract_id');

        if ($portfolioId) {
            $query->where('lb.loan_portfolio_id', $portfolioId);
        }

        return $query->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Create CSV response based on export mode
     */
    private static function createCsvResponse(array $data, string $mode, string $startPeriod, string $endPeriod): StreamedResponse
    {
        $filename = sprintf(
            'loan_book_export_%s_%s_to_%s.csv',
            $mode,
            str_replace('-', '_', $startPeriod),
            str_replace('-', '_', $endPeriod)
        );

        return response()->streamDownload(function () use ($data, $startPeriod, $endPeriod, $mode) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Loan Book Export Report']);
            fputcsv($handle, ['Export Date Range: ' . $startPeriod . ' to ' . $endPeriod]);
            fputcsv($handle, ['Export Mode', $mode]);
            fputcsv($handle, []);

            if ($mode === 'summary') {
                fputcsv($handle, [
                    'Reporting Period',
                    'Stage 1',
                    'Stage 2',
                    'Stage 3',
                    'Total Carrying Amount',
                    'Stage 1 Count',
                    'Stage 2 Count',
                    'Stage 3 Count',
                    'Total Count',
                ]);

                foreach ($data as $row) {
                    fputcsv($handle, [
                        $row['reporting_period'],
                        number_format((float) $row['stage_1_balance'], 2, '.', ','),
                        number_format((float) $row['stage_2_balance'], 2, '.', ','),
                        number_format((float) $row['stage_3_balance'], 2, '.', ','),
                        number_format((float) $row['total_principal_balance'], 2, '.', ','),
                        $row['stage_1_count'],
                        $row['stage_2_count'],
                        $row['stage_3_count'],
                        $row['total_count'],
                    ]);
                }
            } else {
                fputcsv($handle, [
                    'Contract ID',
                    'Carrying Amount',
                    'IFRS9 Stage',
                    'Reporting Period',
                ]);

                foreach ($data as $row) {
                    fputcsv($handle, [
                        $row['contract_id'],
                        number_format((float) $row['principal_balance'], 2, '.', ','),
                        $row['calculated_ifrs9_stage'],
                        $row['reporting_period'],
                    ]);
                }
            }

            fclose($handle);
        }, $filename);
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
