<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Generator;
use Illuminate\Support\Facades\DB;

class EclReconciliationService
{
    private const STAGES = ['1', '2', '3'];
    private const MOVEMENT_TYPES = ['ecl_value', 'principal_balance'];

    public static function generate(array $params): array
    {
        $portfolioId = $params['portfolio_id'] ?? null;
        $startPeriod = $params['start_period'] ?? null;
        $endPeriod = $params['end_period'] ?? null;
        $movementType = $params['movement_type'] ?? 'ecl_value';
        $reportType = $params['report_type'] ?? 'summary';
        $detailType = $params['detail_type'] ?? null;

        if (!$portfolioId || !$startPeriod || !$endPeriod) {
            throw new \InvalidArgumentException('Portfolio ID, start period, and end period are required');
        }

        $portfolioId = (int) $portfolioId;

        self::assertMovementType($movementType);

        $valueColumn = $movementType === 'principal_balance' ? 'carrying_amount' : 'ecl_value';

        if ($reportType === 'detailed') {
            return self::generateDetailed($portfolioId, $startPeriod, $endPeriod, $movementType, $valueColumn, $detailType);
        }

        $report = self::initializeReport($movementType);

        $rowCount = 0;
        $startTransitionCount = 0;
        $newTransitionCount = 0;

        foreach (self::fetchStartRowsChunked($portfolioId, $startPeriod, $endPeriod, $valueColumn) as $row) {
            self::processRow($row, $report);
            $rowCount++;
            $startTransitionCount++;
        }

        foreach (self::fetchNewRowsChunked($portfolioId, $startPeriod, $endPeriod, $valueColumn) as $row) {
            self::processRow($row, $report);
            $rowCount++;
            $newTransitionCount++;
        }

        return [
            'portfolio_id' => $portfolioId,
            'start_period' => $startPeriod,
            'end_period' => $endPeriod,
            'movement_type' => $movementType,
            'movement_label' => self::movementLabel($movementType),
            'report_type' => 'summary',
            'rows' => self::formatRows($report),
            'row_count' => $rowCount,
            'start_transition_count' => $startTransitionCount,
            'new_transition_count' => $newTransitionCount,
        ];
    }

    public static function exportToCsv(array $params)
    {
        $report = self::generate($params);

        $portfolioName = DB::table('loan_portfolios')
            ->where('id', $report['portfolio_id'])
            ->value('name') ?? 'Unknown Portfolio';

        if (($report['report_type'] ?? 'summary') === 'detailed') {
            return self::exportDetailedToCsv($report, $portfolioName, $params);
        }

        $filename = sprintf(
            'ecl_reconciliation_%s_%s_to_%s.csv',
            $report['movement_type'] ?? 'ecl_value',
            str_replace('-', '_', $report['start_period']),
            str_replace('-', '_', $report['end_period'])
        );

        return response()->streamDownload(function () use ($report, $portfolioName) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Report', 'ECL Reconciliation']);
            fputcsv($handle, ['Loan Portfolio', $portfolioName]);
            fputcsv($handle, ['Start Period', $report['start_period']]);
            fputcsv($handle, ['End Period', $report['end_period']]);
            fputcsv($handle, ['Movement Type', $report['movement_label'] ?? self::movementLabel($report['movement_type'] ?? 'ecl_value')]);
            fputcsv($handle, ['Date of Export', now()->format('Y-m-d H:i:s')]);
            fputcsv($handle, []);
            fputcsv($handle, ['Particulars', 'Stage 1', 'Stage 2', 'Stage 3', 'Total']);

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['particulars'],
                    number_format($row['stage_1'], 2, '.', ''),
                    number_format($row['stage_2'], 2, '.', ''),
                    number_format($row['stage_3'], 2, '.', ''),
                    number_format($row['total'], 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private static function exportDetailedToCsv(array $report, string $portfolioName, array $params)
    {
        $headers = $report['detail_headers'];
        $detailType = $report['detail_type'];
        $valueColumn = ($report['movement_type'] ?? 'ecl_value') === 'principal_balance' ? 'carrying_amount' : 'ecl_value';

        $filename = sprintf(
            'ecl_reconciliation_detailed_%s_%s_%s_to_%s.csv',
            $detailType,
            $report['movement_type'] ?? 'ecl_value',
            str_replace('-', '_', $report['start_period']),
            str_replace('-', '_', $report['end_period'])
        );

        $valueHeaders = ['start_movement_value', 'end_movement_value', 'movement_value'];

        return response()->streamDownload(function () use ($report, $portfolioName, $headers, $detailType, $valueColumn, $valueHeaders) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Report', 'ECL Reconciliation (Detailed)']);
            fputcsv($handle, ['Loan Portfolio', $portfolioName]);
            fputcsv($handle, ['Start Period', $report['start_period']]);
            fputcsv($handle, ['End Period', $report['end_period']]);
            fputcsv($handle, ['Movement Type', $report['movement_label']]);
            fputcsv($handle, ['Detail Section', $report['detail_label']]);
            fputcsv($handle, ['Date of Export', now()->format('Y-m-d H:i:s')]);
            fputcsv($handle, []);
            fputcsv($handle, array_map(
                fn ($h) => ucwords(str_replace('_', ' ', $h)),
                $headers
            ));

            $cursor = self::detailRowsCursor(
                $report['portfolio_id'],
                $report['start_period'],
                $report['end_period'],
                $valueColumn,
                $detailType
            );

            foreach ($cursor as $row) {
                fputcsv($handle, array_map(function ($h) use ($row, $valueHeaders) {
                    $value = $row[$h] ?? '';
                    return in_array($h, $valueHeaders, true)
                        ? number_format((float) $value, 2, '.', '')
                        : $value;
                }, $headers));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private static function initializeReport(string $movementType): array
    {
        $label = self::movementLabel($movementType);

        return [
            "Opening {$label}" => self::emptyStageRow(),
            'Transfers to stage 1' => self::emptyStageRow(),
            'Transfers to stage 2' => self::emptyStageRow(),
            'Transfers to stage 3' => self::emptyStageRow(),
            "Net remeasurement of {$label}" => self::emptyStageRow(),
            'New financial assets originated' => self::emptyStageRow(),
            'Financial assets derecognised' => self::emptyStageRow(),
            'Amounts written off' => self::emptyStageRow(),
            "Closing {$label}" => self::emptyStageRow(),
        ];
    }

    private static function movementLabel(string $movementType): string
    {
        return $movementType === 'principal_balance' ? 'Carrying Amount' : 'ECL Allowance';
    }

    private const DETAIL_TYPES = ['new_loans', 'derecognized_loans', 'stage_transitions'];
    private const DETAIL_PREVIEW_LIMIT = 100;

    private static function assertDetailType(?string $detailType): string
    {
        if (!in_array($detailType, self::DETAIL_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid or missing detailed section');
        }

        return $detailType;
    }

    private static function detailLabel(string $detailType): string
    {
        return [
            'new_loans' => 'New Loans',
            'derecognized_loans' => 'Derecognized Loans',
            'stage_transitions' => 'Stage Transitions',
        ][$detailType];
    }

    private static function detailHeaders(string $detailType): array
    {
        return [
            'new_loans' => ['contract_id', 'external_identity_id', 'end_stage', 'end_movement_value'],
            'derecognized_loans' => ['contract_id', 'external_identity_id', 'start_stage', 'start_movement_value'],
            'stage_transitions' => ['contract_id', 'external_identity_id', 'start_stage', 'end_stage', 'start_movement_value', 'end_movement_value', 'movement_value'],
        ][$detailType];
    }

    /**
     * Build the (unlimited) query for a detailed section. $valueColumn is
     * internally derived ('carrying_amount' or 'ecl_value'), never user input.
     */
    private static function detailQuery(
        int $portfolioId,
        string $startPeriod,
        string $endPeriod,
        string $valueColumn,
        string $detailType
    ) {
        if ($detailType === 'new_loans') {
            return DB::table('loan_books as lb_end')
                ->leftJoin('loan_books as lb_start', function ($join) use ($startPeriod) {
                    $join->on('lb_start.contract_id', '=', 'lb_end.contract_id')
                        ->on('lb_start.loan_portfolio_id', '=', 'lb_end.loan_portfolio_id')
                        ->where('lb_start.reporting_period', '=', $startPeriod);
                })
                ->where('lb_end.reporting_period', $endPeriod)
                ->where('lb_end.loan_portfolio_id', $portfolioId)
                ->whereNull('lb_start.contract_id')
                ->select([
                    'lb_end.contract_id',
                    'lb_end.external_identity_id',
                    'lb_end.calculated_ifrs9_stage as end_stage',
                    DB::raw("COALESCE(lb_end.{$valueColumn}, 0) as end_movement_value"),
                ])
                ->orderBy('lb_end.contract_id');
        }

        if ($detailType === 'derecognized_loans') {
            return DB::table('loan_books as lb_start')
                ->leftJoin('loan_books as lb_end', function ($join) use ($endPeriod) {
                    $join->on('lb_start.contract_id', '=', 'lb_end.contract_id')
                        ->on('lb_start.loan_portfolio_id', '=', 'lb_end.loan_portfolio_id')
                        ->where('lb_end.reporting_period', '=', $endPeriod);
                })
                ->where('lb_start.reporting_period', $startPeriod)
                ->where('lb_start.loan_portfolio_id', $portfolioId)
                ->whereNull('lb_end.contract_id')
                ->select([
                    'lb_start.contract_id',
                    'lb_start.external_identity_id',
                    'lb_start.calculated_ifrs9_stage as start_stage',
                    DB::raw("COALESCE(lb_start.{$valueColumn}, 0) as start_movement_value"),
                ])
                ->orderBy('lb_start.contract_id');
        }

        // stage_transitions: present at both periods with a changed IFRS9 stage
        return DB::table('loan_books as lb_start')
            ->join('loan_books as lb_end', function ($join) use ($endPeriod) {
                $join->on('lb_start.contract_id', '=', 'lb_end.contract_id')
                    ->on('lb_start.loan_portfolio_id', '=', 'lb_end.loan_portfolio_id')
                    ->where('lb_end.reporting_period', '=', $endPeriod);
            })
            ->where('lb_start.reporting_period', $startPeriod)
            ->where('lb_start.loan_portfolio_id', $portfolioId)
            ->whereColumn('lb_start.calculated_ifrs9_stage', '!=', 'lb_end.calculated_ifrs9_stage')
            ->select([
                'lb_start.contract_id',
                'lb_start.external_identity_id',
                'lb_start.calculated_ifrs9_stage as start_stage',
                'lb_end.calculated_ifrs9_stage as end_stage',
                DB::raw("COALESCE(lb_start.{$valueColumn}, 0) as start_movement_value"),
                DB::raw("COALESCE(lb_end.{$valueColumn}, 0) as end_movement_value"),
                DB::raw("(COALESCE(lb_end.{$valueColumn}, 0) - COALESCE(lb_start.{$valueColumn}, 0)) as movement_value"),
            ])
            ->orderBy('lb_start.contract_id');
    }

    private static function generateDetailed(
        int $portfolioId,
        string $startPeriod,
        string $endPeriod,
        string $movementType,
        string $valueColumn,
        ?string $detailType
    ): array {
        $detailType = self::assertDetailType($detailType);
        $headers = self::detailHeaders($detailType);
        $query = self::detailQuery($portfolioId, $startPeriod, $endPeriod, $valueColumn, $detailType);

        $total = (clone $query)->count();

        $rows = (clone $query)
            ->limit(self::DETAIL_PREVIEW_LIMIT)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        return [
            'portfolio_id' => $portfolioId,
            'start_period' => $startPeriod,
            'end_period' => $endPeriod,
            'movement_type' => $movementType,
            'movement_label' => self::movementLabel($movementType),
            'report_type' => 'detailed',
            'detail_type' => $detailType,
            'detail_label' => self::detailLabel($detailType),
            'detail_headers' => $headers,
            'detail_rows' => $rows,
            'detail_preview_count' => count($rows),
            'detail_total_rows' => $total,
        ];
    }

    /**
     * Stream every row of a detailed section (no preview cap) for CSV export.
     */
    private static function detailRowsCursor(
        int $portfolioId,
        string $startPeriod,
        string $endPeriod,
        string $valueColumn,
        string $detailType
    ): Generator {
        $query = self::detailQuery($portfolioId, $startPeriod, $endPeriod, $valueColumn, $detailType);

        foreach ($query->cursor() as $row) {
            yield (array) $row;
        }
    }

    private static function formatRows(array $report): array
    {
        $formattedRows = [];

        foreach ($report as $label => $values) {
            $formattedRows[] = [
                'particulars' => $label,
                'stage_1' => round($values['1'], 2),
                'stage_2' => round($values['2'], 2),
                'stage_3' => round($values['3'], 2),
                'total' => round($values['1'] + $values['2'] + $values['3'], 2),
            ];
        }

        return $formattedRows;
    }

    private static function fetchStartRowsChunked(
        int $portfolioId,
        string $startPeriod,
        string $endPeriod,
        string $valueColumn
    ): Generator {
        $query = DB::table('loan_books as lb_start')
            ->leftJoin('loan_books as lb_end', function ($join) use ($endPeriod) {
                $join->on('lb_start.contract_id', '=', 'lb_end.contract_id')
                    ->on('lb_start.loan_portfolio_id', '=', 'lb_end.loan_portfolio_id')
                    ->where('lb_end.reporting_period', '=', $endPeriod);
            })
            ->where('lb_start.reporting_period', $startPeriod)
            ->where('lb_start.loan_portfolio_id', $portfolioId)
            ->select([
                'lb_start.contract_id',
                DB::raw('1 as has_start_record'),
                DB::raw('(lb_end.contract_id IS NOT NULL) as has_end_record'),
                DB::raw("COALESCE(lb_start.{$valueColumn}, 0) as start_value"),
                'lb_start.calculated_ifrs9_stage as start_stage',
                DB::raw("COALESCE(lb_end.{$valueColumn}, 0) as end_value"),
                'lb_end.calculated_ifrs9_stage as end_stage',
                'lb_end.contract_status as end_contract_status',
                DB::raw('0 as is_written_off_in_period'),
            ])
            ->orderBy('lb_start.contract_id');

        foreach ($query->cursor() as $row) {
            yield $row;
        }
    }

    private static function fetchNewRowsChunked(
        int $portfolioId,
        string $startPeriod,
        string $endPeriod,
        string $valueColumn
    ): Generator {
        $query = DB::table('loan_books as lb_end')
            ->leftJoin('loan_books as lb_start', function ($join) use ($startPeriod) {
                $join->on('lb_start.contract_id', '=', 'lb_end.contract_id')
                    ->on('lb_start.loan_portfolio_id', '=', 'lb_end.loan_portfolio_id')
                    ->where('lb_start.reporting_period', '=', $startPeriod);
            })
            ->where('lb_end.reporting_period', $endPeriod)
            ->where('lb_end.loan_portfolio_id', $portfolioId)
            ->whereNull('lb_start.contract_id')
            ->select([
                'lb_end.contract_id',
                DB::raw('0 as has_start_record'),
                DB::raw('1 as has_end_record'),
                DB::raw('0 as start_value'),
                DB::raw('NULL as start_stage'),
                DB::raw("COALESCE(lb_end.{$valueColumn}, 0) as end_value"),
                'lb_end.calculated_ifrs9_stage as end_stage',
                'lb_end.contract_status as end_contract_status',
                DB::raw('0 as is_written_off_in_period'),
            ])
            ->orderBy('lb_end.contract_id');

        foreach ($query->cursor() as $row) {
            yield $row;
        }
    }

    private static function processRow(object $row, array &$report): void
    {
        $startStage = self::normalizeStage($row->start_stage);
        $endStage = self::normalizeStage($row->end_stage);
        $startValue = (float) $row->start_value;
        $endValue = (float) $row->end_value;

        $openingKey = array_key_first($report);
        $closingKey = array_key_last($report);
        $remeasurementKey = array_keys($report)[4];

        if ($startStage !== null) {
            $report[$openingKey][$startStage] += $startValue;
        }

        if ($endStage !== null) {
            $report[$closingKey][$endStage] += $endValue;
        }

        $hasStart = $row->has_start_record == 1;
        $hasEnd = $row->has_end_record == 1;

        if ($hasStart && $hasEnd && $startStage !== null && $endStage !== null) {
            if ($startStage !== $endStage) {
                $transferLabel = 'Transfers to stage ' . $endStage;
                $report[$transferLabel][$startStage] -= $startValue;
                $report[$transferLabel][$endStage] += $endValue;
                return;
            }

            $report[$remeasurementKey][$startStage] += ($endValue - $startValue);
            return;
        }

        if (!$hasStart && $hasEnd && $endStage !== null) {
            $report['New financial assets originated'][$endStage] += $endValue;
            return;
        }

        if ($hasStart && !$hasEnd && $startStage !== null) {
            if ($row->is_written_off_in_period ?? false) {
                $report['Amounts written off'][$startStage] -= $startValue;
            } else {
                $report['Financial assets derecognised'][$startStage] -= $startValue;
            }
        }
    }

    private static function emptyStageRow(): array
    {
        return ['1' => 0.0, '2' => 0.0, '3' => 0.0];
    }

    private static function normalizeStage($stage): ?string
    {
        if ($stage === null) {
            return null;
        }

        $normalized = trim((string) $stage);

        return in_array($normalized, self::STAGES, true) ? $normalized : null;
    }

    private static function assertMovementType(string $movementType): void
    {
        if (!in_array($movementType, self::MOVEMENT_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid movement type supplied');
        }
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
