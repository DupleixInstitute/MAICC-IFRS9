<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DisbursementReportService
{
    /**
     * Generate Disbursement Report
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

        if (!in_array($mode, ['summary', 'detailed'], true)) {
            throw new \InvalidArgumentException('Invalid mode specified');
        }

        return [
            'summary' => self::generateSummaryData($startPeriod, $endPeriod, $portfolioId),
            'detailed' => $mode === 'detailed' ? self::generateDetailedData($startPeriod, $endPeriod, $portfolioId) : [],
            'mode' => $mode,
        ];
    }

    /**
     * Export Disbursement Report to CSV
     */
    public static function exportToCsv(array $params): StreamedResponse
    {
        $data = self::generate($params);
        $startPeriod = $params['start_period'];
        $endPeriod = $params['end_period'];
        $mode = $params['mode'] ?? 'summary';
        $portfolioName = DB::table('loan_portfolios')
            ->where('id', $params['portfolio_id'])
            ->value('name') ?? 'Unknown Portfolio';

        return self::createCsvResponse($data, $startPeriod, $endPeriod, $portfolioName, $mode);
    }

    /**
     * Generate summary data
     *
     * "Disbursed amount" / outstanding movements resolve to the carrying_amount
     * column (the IFRS9 source of truth in the LoanBook), not principal_balance.
     */
    private static function generateSummaryData(string $startPeriod, string $endPeriod, ?int $portfolioId): array
    {
        $sql = "
            SELECT
                orig_month as reporting_period,
                COALESCE(SUM(disbursed_amt), 0) AS total_disbursement,
                COALESCE(COUNT(DISTINCT contract_id), 0) AS contract_count,
                COALESCE(AVG(disbursed_amt), 0) AS average_disbursement,
                COALESCE(SUM(out_1m), 0) AS movement_30_days,
                COALESCE(SUM(out_2m), 0) AS movement_60_days,
                COALESCE(SUM(out_3m), 0) AS movement_90_days
            FROM (
                SELECT
                    DATE_FORMAT(l.create_date, '%Y-%m') AS orig_month,
                    l.contract_id,
                    SUM(lb0.carrying_amount) AS disbursed_amt,
                    SUM(lb1.carrying_amount) AS out_1m,
                    SUM(lb2.carrying_amount) AS out_2m,
                    SUM(lb3.carrying_amount) AS out_3m
                FROM (
                    SELECT DISTINCT contract_id, create_date
                    FROM loan_books
                    WHERE create_date >= CONCAT(?, '-01')
                      AND create_date < DATE_ADD(CONCAT(?, '-01'), INTERVAL 1 MONTH)
                      " . ($portfolioId ? "AND loan_portfolio_id = ?" : "") . "
                ) l
                LEFT JOIN loan_books lb0
                    ON l.contract_id = lb0.contract_id
                    AND lb0.reporting_year = YEAR(l.create_date)
                    AND lb0.reporting_month = MONTH(l.create_date)
                    " . ($portfolioId ? "AND lb0.loan_portfolio_id = ?" : "") . "
                LEFT JOIN loan_books lb1
                    ON l.contract_id = lb1.contract_id
                    AND lb1.reporting_year = YEAR(l.create_date + INTERVAL 1 MONTH)
                    AND lb1.reporting_month = MONTH(l.create_date + INTERVAL 1 MONTH)
                    " . ($portfolioId ? "AND lb1.loan_portfolio_id = ?" : "") . "
                LEFT JOIN loan_books lb2
                    ON l.contract_id = lb2.contract_id
                    AND lb2.reporting_year = YEAR(l.create_date + INTERVAL 2 MONTH)
                    AND lb2.reporting_month = MONTH(l.create_date + INTERVAL 2 MONTH)
                    " . ($portfolioId ? "AND lb2.loan_portfolio_id = ?" : "") . "
                LEFT JOIN loan_books lb3
                    ON l.contract_id = lb3.contract_id
                    AND lb3.reporting_year = YEAR(l.create_date + INTERVAL 3 MONTH)
                    AND lb3.reporting_month = MONTH(l.create_date + INTERVAL 3 MONTH)
                    " . ($portfolioId ? "AND lb3.loan_portfolio_id = ?" : "") . "
                GROUP BY orig_month, l.contract_id
            ) AS cohort_detail
            GROUP BY orig_month
            ORDER BY orig_month
        ";

        $params = [$startPeriod, $endPeriod];
        if ($portfolioId) {
            $params = array_merge($params, [$portfolioId, $portfolioId, $portfolioId, $portfolioId, $portfolioId]);
        }

        return collect(DB::select($sql, $params))
            ->map(function ($row) {
            return [
                'period' => $row->reporting_period,
                'contract_count' => (int) $row->contract_count,
                'total_disbursement' => (float) $row->total_disbursement,
                'average_disbursement' => (float) $row->average_disbursement,
                'movement_30_days' => (float) $row->movement_30_days,
                'movement_60_days' => (float) $row->movement_60_days,
                'movement_90_days' => (float) $row->movement_90_days,
            ];
        })->toArray();
    }

    private static function generateDetailedData(string $startPeriod, string $endPeriod, ?int $portfolioId): array
    {
        $query = DB::table('loan_books as lb')
            ->select([
                'lb.reporting_period',
                'lb.contract_id',
                'lb.external_identity_id',
                DB::raw('lb.carrying_amount as principal_balance'),
                'lb.create_date',
                'lb.portfolio_group',
            ])
            ->whereBetween('lb.reporting_period', [$startPeriod, $endPeriod])
            ->whereRaw("DATE_FORMAT(lb.create_date, '%Y-%m') = lb.reporting_period")
            ->orderBy('lb.reporting_period')
            ->orderBy('lb.create_date')
            ->orderBy('lb.contract_id');

        if ($portfolioId) {
            $query->where('lb.loan_portfolio_id', $portfolioId);
        }

        return $query->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Create CSV response for disbursement export
     */
    private static function createCsvResponse(array $data, string $startPeriod, string $endPeriod, string $portfolioName, string $mode): StreamedResponse
    {
        $filename = 'disbursement_export_' . $mode . '_' . $startPeriod . '_to_' . $endPeriod . ($portfolioName ? '_portfolio_' . str_replace(' ', '_', $portfolioName) : '') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($data, $startPeriod, $endPeriod, $portfolioName, $mode) {
            $file = fopen('php://output', 'w');

            // Add header information
            fputcsv($file, ['Loan Disbursement Export Report']);
            fputcsv($file, ['Period Range: ' . $startPeriod . ' to ' . $endPeriod]);
            fputcsv($file, ['Export Mode: ' . ucfirst($mode)]);
            fputcsv($file, ['Portfolio : ' . $portfolioName]);
            fputcsv($file, ['Export Date: ' . now()->format('Y-m-d H:i:s')]);
            fputcsv($file, []); // Empty row for spacing

            fputcsv($file, ['DISBURSEMENT SUMMARY']);
            fputcsv($file, ['reporting_period', 'Total Disbursement', 'No Of Contacts', 'Average Disbursement', 'Movement 30days', 'Movement 60 Days', 'Movement 90days']);

            foreach ($data['summary'] as $summary) {
                fputcsv($file, [
                    $summary['period'],
                    number_format($summary['total_disbursement'], 2, '.', ','),
                    $summary['contract_count'],
                    number_format($summary['average_disbursement'], 2, '.', ','),
                    number_format($summary['movement_30_days'], 2, '.', ','),
                    number_format($summary['movement_60_days'], 2, '.', ','),
                    number_format($summary['movement_90_days'], 2, '.', ','),
                ]);
            }

            if ($mode === 'detailed' && !empty($data['detailed'])) {
                fputcsv($file, []);
                fputcsv($file, ['DETAILED CONTRACT BREAKDOWN']);
                fputcsv($file, [
                    'Contract ID',
                    'External Identity ID',
                    'Disbursement Amount',
                    'Create Date',
                    'Portfolio Group',
                ]);

                foreach ($data['detailed'] as $contract) {
                    fputcsv($file, [
                        $contract['contract_id'],
                        $contract['external_identity_id'],
                        number_format((float) $contract['principal_balance'], 2, '.', ','),
                        $contract['create_date'],
                        $contract['portfolio_group'],
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

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
