<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ECLExportService
{
    /**
     * Generate ECL Export Report
     */
    public static function generate(array $params): array
    {
        $portfolioId = $params['portfolio_id'] ?? null;
        $reportingPeriod = $params['reporting_period'] ?? null;
        $mode = $params['mode'] ?? 'summary';
        $columns = $params['columns'] ?? [];

        if (!$portfolioId || !$reportingPeriod) {
            throw new \InvalidArgumentException('Portfolio ID and reporting period are required');
        }

        if ($mode === 'summary') {
            return self::generateSummaryData($portfolioId, $reportingPeriod, $columns);
        } elseif ($mode === 'totalLoanBook') {
            return self::generateTotalLoanBookData($portfolioId, $reportingPeriod, $columns);
        }

        throw new \InvalidArgumentException('Invalid mode specified');
    }

    /**
     * Export ECL Report to CSV
     */
    public static function exportToCsv(array $params): BinaryFileResponse
    {
        $data = self::generate($params);
        $mode = $params['mode'] ?? 'summary';
        $portfolioName = DB::table('loan_portfolios')
            ->where('id', $params['portfolio_id'])
            ->value('name') ?? 'Unknown Portfolio';

        $filename = sprintf(
            'ecl_export_%s_%s_%s.csv',
            str_replace(' ', '_', $portfolioName),
            $params['reporting_period'],
            $mode
        );

        $filePath = storage_path('app/' . $filename);
        $handle = fopen($filePath, 'w');

        // Add header information
        fputcsv($handle, ['ECL Export Report']);
        fputcsv($handle, ['Portfolio', $portfolioName]);
        fputcsv($handle, ['Reporting Period', $params['reporting_period']]);
        fputcsv($handle, ['Export Mode', $mode]);
        fputcsv($handle, ['Export Date', now()->format('Y-m-d H:i:s')]);
        fputcsv($handle, []);

        if (!empty($data)) {
            fputcsv($handle, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($handle, array_values($row));
            }
        } else {
            fputcsv($handle, ['No records found for the given criteria.']);
        }

        fclose($handle);

        return response()->download($filePath, null, ['Content-Type' => 'text/csv'])->deleteFileAfterSend(true);
    }

    /**
     * Generate summary data
     */
    private static function generateSummaryData($portfolioId, $reportingPeriod, $columns): array
    {
        // By the stage the ECL was provided on (after the qualitative
        // triggers), with the EAD the engine measured (carrying amount plus
        // the drawn share of undrawn commitments) and the PD after the
        // forward-looking adjustment, so the totals tie to the saved ECL run.
        $ead = 'COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1)';
        $data = DB::table('loan_books')
            ->selectRaw("
                ifrs9stage_post_qualitative as stage,
                COUNT(*) as loans,
                SUM(COALESCE(carrying_amount,0)) as gross,
                SUM({$ead}) as total_ead,
                SUM(COALESCE(pd_post_fli, pd_prefli, 0) * ({$ead})) as pd_x_ead,
                SUM(COALESCE(lgd_value, 0) * ({$ead})) as lgd_x_ead,
                SUM(COALESCE(ecl_value,0)) as total_ecl
            ")
            ->where('loan_portfolio_id', $portfolioId)
            ->where('reporting_period', $reportingPeriod)
            ->groupBy('ifrs9stage_post_qualitative')
            ->orderBy('ifrs9stage_post_qualitative')
            ->get();

        $row = fn ($stage, $loans, $gross, $ead, $pdx, $lgdx, $ecl) => [
            'Stage' => $stage,
            'Loans' => (int) $loans,
            'Gross carrying amount' => round((float) $gross, 2),
            'EAD' => round((float) $ead, 2),
            'PD (EAD-weighted)' => $ead != 0 ? round($pdx / $ead, 6) : null,
            'LGD (EAD-weighted)' => $ead != 0 ? round($lgdx / $ead, 6) : null,
            'ECL' => round((float) $ecl, 2),
            'Coverage of EAD' => $ead != 0 ? round($ecl / $ead, 6) : null,
        ];

        $rows = $data->map(fn ($r) => $row($r->stage, $r->loans, $r->gross, (float) $r->total_ead, (float) $r->pd_x_ead, (float) $r->lgd_x_ead, (float) $r->total_ecl))->values();
        if ($rows->isNotEmpty()) {
            $rows->push($row('Total', $data->sum('loans'), $data->sum('gross'), (float) $data->sum('total_ead'),
                (float) $data->sum('pd_x_ead'), (float) $data->sum('lgd_x_ead'), (float) $data->sum('total_ecl')));
        }

        return $rows->toArray();
    }

    /**
     * Generate total loan book data
     */
    private static function generateTotalLoanBookData($portfolioId, $reportingPeriod, $columns): array
    {
        $exportable = [
            'id',
            'contract_id',
            'principal_balance',
            'pd_value',
            'lgd_value',
            'ecl_value',
            'calculated_ifrs9_stage',
            'reporting_period',
            'external_identity_id',
            'create_date',
            'due_date',
            'contract_status',
            'overdue_days',
        ];

        $selected = !empty($columns) ? array_unique(array_merge(['id'], $columns)) : $exportable;

        // "Principal balance" resolves to the carrying_amount column.
        $select = array_map(function ($column) {
            return $column === 'principal_balance'
                ? DB::raw('carrying_amount as principal_balance')
                : $column;
        }, $selected);

        $data = DB::table('loan_books')
            ->select($select)
            ->where('loan_portfolio_id', $portfolioId)
            ->where('reporting_period', $reportingPeriod)
            ->orderBy('calculated_ifrs9_stage')
            ->orderBy('carrying_amount', 'desc')
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->toArray();

        return $data;
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

    /**
     * Get available columns for export
     */
    public static function getAvailableColumns(): array
    {
        return [
            'contract_id' => 'Contract ID',
            'principal_balance' => 'Carrying Amount',
            'pd_value' => 'PD',
            'lgd_value' => 'LGD',
            'ecl_value' => 'ECL Value',
            'calculated_ifrs9_stage' => 'IFRS9 Stage',
            'reporting_period' => 'Reporting Period',
            'external_identity_id' => 'External ID',
            'create_date' => 'Create Date',
            'due_date' => 'Due Date',
            'contract_status' => 'Contract Status',
            'overdue_days' => 'Overdue Days',
        ];
    }
}
