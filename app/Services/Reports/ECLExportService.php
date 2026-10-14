<?php

namespace App\Services\Reports;

use App\Services\Ecl\EclExposure;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The ECL export (Reports hub, Exports). Every figure is on the reports'
 * basis (documentation audit of 14 October 2026; it read the undiscounted
 * ecl_value and the calculated stage until then): the loss allowance is the
 * booked allowance (BookedEcl: discounted at the EIR, the Mega Farm programme
 * measure, or the undiscounted ECL where no discounted figure exists, named by
 * its basis), with the undiscounted ECL beside it; the stage is the one the
 * ECL was provided on, after the qualitative triggers
 * (EclBuildUpService::STAGE_SQL); the EAD is the engine's (EclExposure).
 */
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
            ->value('name') ?? 'All portfolios';

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
        // triggers), with the EAD the engine measured (EclExposure: floored at
        // nil, nil for a loan written off to nil) and the PD after the
        // forward-looking adjustment; the allowance is the booked one, with the
        // undiscounted ECL beside it and the loans not on the booked basis counted.
        $ead = EclExposure::sql();
        $stage = EclBuildUpService::STAGE_SQL;
        $data = DB::table('loan_books')
            ->selectRaw("
                {$stage} as stage,
                COUNT(*) as loans,
                SUM(COALESCE(carrying_amount,0)) as gross,
                SUM({$ead}) as total_ead,
                SUM(COALESCE(pd_post_fli, pd_prefli, 0) * ({$ead})) as pd_x_ead,
                SUM(COALESCE(lgd_value, 0) * ({$ead})) as lgd_x_ead,
                SUM(" . BookedEcl::sql() . ") as total_ecl,
                SUM(COALESCE(ecl_value,0)) as total_ecl_undiscounted,
                SUM(CASE WHEN " . BookedEcl::basisSql() . " = 'undiscounted' THEN 1 ELSE 0 END) as not_booked
            ")
            ->where('loan_portfolio_id', $portfolioId)
            ->where('reporting_period', $reportingPeriod)
            ->groupByRaw($stage)
            ->orderByRaw($stage)
            ->get();

        $row = fn ($stage, $loans, $gross, $ead, $pdx, $lgdx, $ecl, $undiscounted, $notBooked) => [
            'Stage' => $stage,
            'Loans' => (int) $loans,
            'Gross carrying amount' => round((float) $gross, 2),
            'EAD' => round((float) $ead, 2),
            'PD (EAD-weighted)' => $ead != 0 ? round($pdx / $ead, 6) : null,
            'LGD (EAD-weighted)' => $ead != 0 ? round($lgdx / $ead, 6) : null,
            'Loss allowance (booked)' => round((float) $ecl, 2),
            'ECL undiscounted' => round((float) $undiscounted, 2),
            'Loans without a discounted allowance' => (int) $notBooked,
            'Coverage of EAD' => $ead != 0 ? round($ecl / $ead, 6) : null,
        ];

        $rows = $data->map(fn ($r) => $row($r->stage, $r->loans, $r->gross, (float) $r->total_ead, (float) $r->pd_x_ead, (float) $r->lgd_x_ead, (float) $r->total_ecl, (float) $r->total_ecl_undiscounted, $r->not_booked))->values();
        if ($rows->isNotEmpty()) {
            $rows->push($row('Total', $data->sum('loans'), $data->sum('gross'), (float) $data->sum('total_ead'),
                (float) $data->sum('pd_x_ead'), (float) $data->sum('lgd_x_ead'), (float) $data->sum('total_ecl'), (float) $data->sum('total_ecl_undiscounted'), $data->sum('not_booked')));
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
            'ecl_booked',
            'ecl_value',
            'ecl_basis',
            'stage',
            'reporting_period',
            'external_identity_id',
            'create_date',
            'due_date',
            'contract_status',
            'overdue_days',
        ];

        // only the columns offered; a name outside the list is never passed to the query
        $columns = array_values(array_intersect($columns, $exportable));
        $selected = !empty($columns) ? array_unique(array_merge(['id'], $columns)) : $exportable;

        // "Principal balance" resolves to the carrying_amount column.
        $select = array_map(function ($column) {
            return match ($column) {
                'principal_balance' => DB::raw('carrying_amount as principal_balance'),
                // the PD the ECL used: after FLI where the route ran, else before FLI; pd_value is the legacy column
                'pd_value' => DB::raw('COALESCE(pd_post_fli, pd_prefli, pd_value) as pd_value'),
                // the booked allowance, its basis, and the stage the ECL was provided on
                'ecl_booked' => DB::raw(BookedEcl::sql() . ' as ecl_booked'),
                'ecl_basis' => DB::raw(BookedEcl::basisSql() . ' as ecl_basis'),
                'stage' => DB::raw(EclBuildUpService::STAGE_SQL . ' as stage'),
                default => $column,
            };
        }, $selected);

        $data = DB::table('loan_books')
            ->select($select)
            ->where('loan_portfolio_id', $portfolioId)
            ->where('reporting_period', $reportingPeriod)
            ->orderByRaw(EclBuildUpService::STAGE_SQL)
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
            'ecl_booked' => 'Loss allowance (booked)',
            'ecl_value' => 'ECL undiscounted',
            'ecl_basis' => 'Allowance basis',
            'stage' => 'IFRS 9 stage (after qualitative triggers)',
            'reporting_period' => 'Reporting Period',
            'external_identity_id' => 'External ID',
            'create_date' => 'Create Date',
            'due_date' => 'Due Date',
            'contract_status' => 'Contract Status',
            'overdue_days' => 'Overdue Days',
        ];
    }
}
