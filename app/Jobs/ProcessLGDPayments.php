<?php

namespace App\Jobs;

use App\Models\LGDCalculationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessLGDPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200;
    public $tries = 3;

    protected $calculationId;

    public function __construct(int $calculationId, private int $chunkSize = 500)
    {
        $this->calculationId = $calculationId;
        $this->chunkSize = $chunkSize;
    }

    public function handle(): void
    {
        $calculation = LGDCalculationLog::findOrFail($this->calculationId);

        if ($calculation->status !== 'pending') {
            Log::warning("Calculation {$this->calculationId} not pending");
            return;
        }

        $calculation->startCalculation();
        $portfolioId = $calculation->portfolio_group;
        $startPeriod = $calculation->start_period->format('Y-m');
        $endPeriod = $calculation->end_period->format('Y-m');

        Log::info("LGD Job started", compact('portfolioId', 'startPeriod', 'endPeriod'));

        try {
            // Clear previous results
            DB::table('lgd_payment_tracking_long')
                ->where('calculation_id', $this->calculationId)
                ->delete();

            // Get Stage 3 contracts at start period
            $stage3Contracts = DB::table('loan_books')
                ->where('loan_portfolio_id', $portfolioId)
                ->where('calculated_ifrs9_stage', '3')
                ->where('reporting_year', $calculation->start_period->year)
                ->where('reporting_month', $calculation->start_period->month)
                ->pluck('contract_id')
                ->toArray();

            if (empty($stage3Contracts)) {
                Log::warning("No Stage 3 contracts found for start period");
                $this->completeCalculation($calculation, 0);
                return;
            }

            // Process contracts in chunks
            $contractChunks = array_chunk($stage3Contracts, $this->chunkSize);
            $totalProcessed = 0;

            foreach ($contractChunks as $chunkIndex => $contractIds) {
                if (empty($contractIds)) continue;

                $totalProcessed += count($contractIds);
                $placeholders = implode(',', array_fill(0, count($contractIds), '?'));

                // Calculate placeholder count for safety
                $placeholderCount = (count($contractIds) * 2) + 8; // 2 IN clauses + 8 params
                if ($placeholderCount > 60000) {
                    Log::warning("Skipping chunk - too many placeholders", [
                        'chunk_contracts' => count($contractIds),
                        'placeholder_count' => $placeholderCount
                    ]);
                    continue;
                }

                Log::info("Processing chunk", [
                    'chunk_index' => $chunkIndex + 1,
                    'chunk_contracts' => count($contractIds),
                    'placeholder_count' => $placeholderCount,
                    'total_processed' => $totalProcessed
                ]);

                // Main single query with comprehensive logic
                DB::statement("
                    INSERT INTO lgd_payment_tracking_long (
                        contract_id,
                        portfolio_group,
                        reporting_period,
                        cohort_period,
                        payment_period,
                        starting_balance,
                        ending_balance,
                        payment_amount,
                        disbursement_amount,
                        cumulative_payments,
                        payment_type,
                        ifrs9_stage,
                        months_since_default,
                        is_cured,
                        cure_stage,
                        is_missing,
                        has_gap,
                        gap_months,
                        calculation_id,
                        created_at,
                        updated_at
                    )
                    WITH contract_periods AS (
                        SELECT
                            lb.contract_id,
                            lb.loan_portfolio_id,
                            STR_TO_DATE(CONCAT(lb.reporting_year,'-',LPAD(lb.reporting_month,2,'0'),'-01'), '%Y-%m-%d') AS reporting_period,
                            lb.carrying_amount AS principal_balance,
                            lb.calculated_ifrs9_stage,
                            -- Calculate month sequence for gap detection
                            ROW_NUMBER() OVER (PARTITION BY lb.contract_id ORDER BY lb.reporting_year, lb.reporting_month) as month_seq,
                            -- Total months in sequence for each contract
                            COUNT(*) OVER (PARTITION BY lb.contract_id) as total_months,
                            -- First month when contract entered Stage 3
                            MIN(CASE WHEN lb.calculated_ifrs9_stage = '3' THEN
                                STR_TO_DATE(CONCAT(lb.reporting_year,'-',LPAD(lb.reporting_month,2,'0'),'-01'), '%Y-%m-%d')
                            END) OVER (PARTITION BY lb.contract_id) AS cohort_period
                        FROM loan_books lb
                        WHERE lb.loan_portfolio_id = ?
                        AND CONCAT(lb.reporting_year,'-',LPAD(lb.reporting_month,2,'0')) BETWEEN ? AND ?
                        AND lb.contract_id IN ($placeholders)
                    ),
                    payment_analysis AS (
                        SELECT
                            cp.contract_id,
                            cp.loan_portfolio_id,
                            cp.reporting_period,
                            cp.principal_balance,
                            cp.calculated_ifrs9_stage,
                            cp.cohort_period,
                            cp.month_seq,
                            cp.total_months,
                            -- Previous period balance using LAG
                            LAG(cp.principal_balance) OVER (PARTITION BY cp.contract_id ORDER BY cp.reporting_period) AS prev_balance,
                            -- Next period balance using LEAD
                            LEAD(cp.principal_balance) OVER (PARTITION BY cp.contract_id ORDER BY cp.reporting_period) AS next_balance,
                            -- Check if this is the last period for this contract
                            ROW_NUMBER() OVER (PARTITION BY cp.contract_id ORDER BY cp.reporting_period DESC) = 1 AS is_last_period,
                            -- Check if contract exists in next period (for gap detection)
                            CASE
                                WHEN LEAD(cp.reporting_period) OVER (PARTITION BY cp.contract_id ORDER BY cp.reporting_period) IS NOT NULL
                                THEN DATEDIFF(LEAD(cp.reporting_period) OVER (PARTITION BY cp.contract_id ORDER BY cp.reporting_period), cp.reporting_period) / 30.44
                                ELSE 0
                            END AS months_to_next
                        FROM contract_periods cp
                    )
                    SELECT
                        pa.contract_id,
                        pa.loan_portfolio_id as portfolio_group,
                        pa.reporting_period,
                        pa.cohort_period,
                        -- Payment period: when balance actually changed
                        CASE
                            WHEN pa.prev_balance IS NULL THEN NULL
                            WHEN pa.prev_balance > pa.principal_balance THEN pa.reporting_period
                            WHEN pa.prev_balance < pa.principal_balance THEN pa.reporting_period  -- Disbursement
                            ELSE NULL
                        END AS payment_period,
                        pa.prev_balance AS starting_balance,
                        pa.principal_balance AS ending_balance,
                        -- Payment amount (reductions only)
                        CASE
                            WHEN pa.prev_balance IS NULL THEN 0
                            WHEN pa.prev_balance > pa.principal_balance THEN pa.prev_balance - pa.principal_balance
                            ELSE 0
                        END AS payment_amount,
                        -- Disbursement amount (increases only)
                        CASE
                            WHEN pa.prev_balance IS NULL THEN 0
                            WHEN pa.prev_balance < pa.principal_balance THEN pa.principal_balance - pa.prev_balance
                            ELSE 0
                        END AS disbursement_amount,
                        -- Cumulative payments (running total)
                        SUM(
                            CASE
                                WHEN pa.prev_balance > pa.principal_balance THEN pa.prev_balance - pa.principal_balance
                                ELSE 0
                            END
                        ) OVER (PARTITION BY pa.contract_id ORDER BY pa.reporting_period) AS cumulative_payments,
                        -- Payment classification
                        CASE
                            WHEN pa.prev_balance IS NULL THEN 'none'
                            WHEN pa.prev_balance > pa.principal_balance AND pa.principal_balance = 0 THEN 'full'
                            WHEN pa.prev_balance > pa.principal_balance AND pa.principal_balance > 0 THEN 'partial'
                            WHEN pa.prev_balance < pa.principal_balance THEN 'disbursement'
                            ELSE 'none'
                        END AS payment_type,
                        pa.calculated_ifrs9_stage as ifrs9_stage,
                        -- Months since default (from cohort to current period)
                        TIMESTAMPDIFF(MONTH, pa.cohort_period, pa.reporting_period) AS months_since_default,
                        -- Cure logic: not Stage 3 AND within 12 months
                        CASE
                            WHEN pa.calculated_ifrs9_stage != '3'
                            AND TIMESTAMPDIFF(MONTH, pa.cohort_period, pa.reporting_period) <= 12
                            THEN TRUE ELSE FALSE
                        END AS is_cured,
                        CASE
                            WHEN pa.calculated_ifrs9_stage != '3' THEN pa.calculated_ifrs9_stage
                            ELSE NULL
                        END AS cure_stage,
                        -- Missing contract detection
                        CASE
                            WHEN pa.is_last_period = 1 AND NOT EXISTS (
                                SELECT 1 FROM loan_books lb_check
                                WHERE lb_check.contract_id = pa.contract_id
                                AND lb_check.loan_portfolio_id = pa.loan_portfolio_id
                                AND STR_TO_DATE(CONCAT(lb_check.reporting_year,'-',LPAD(lb_check.reporting_month,2,'0'),'-01'), '%Y-%m-%d') > pa.reporting_period
                                LIMIT 1
                            ) THEN TRUE ELSE FALSE
                        END AS is_missing,
                        -- Gap detection
                        CASE
                            WHEN pa.months_to_next > 1.5 THEN TRUE ELSE FALSE
                        END AS has_gap,
                        -- Gap months (rounded)
                        CASE
                            WHEN pa.months_to_next > 1.5 THEN ROUND(pa.months_to_next) ELSE 0
                        END AS gap_months,
                        ? AS calculation_id,
                        NOW() AS created_at,
                        NOW() AS updated_at
                    FROM payment_analysis pa
                    ORDER BY pa.contract_id, pa.reporting_period
                ", array_merge(
                    [$portfolioId, $startPeriod, $endPeriod], // Main WHERE clause
                    $contractIds, // Main IN clause
                    [$this->calculationId] // Calculation ID
                ));

                unset($contractIds);
                gc_collect_cycles();

                if ($totalProcessed % 1000 == 0) {
                    Log::info("Progress", [
                        'contracts_processed' => $totalProcessed,
                        'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
                    ]);
                }
            }

            // Handle missing contracts separately (mark as missing, not recovered)
            $this->processMissingContracts($stage3Contracts, $portfolioId, $startPeriod, $endPeriod);

            // Final aggregations
            $this->completeCalculation($calculation, $totalProcessed);

            Log::info("LGD Job completed", [
                'total_contracts' => $totalProcessed,
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
            ]);

        } catch (\Exception $e) {
            Log::error("LGD Job failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'calculation_id' => $this->calculationId
            ]);
            throw $e;
        }
    }

    private function processMissingContracts(array $stage3Contracts, int $portfolioId, string $startPeriod, string $endPeriod): void
    {
        // Find contracts that were Stage 3 at start but don't exist after end period
        $missingContracts = DB::table('loan_books as lb_start')
            ->leftJoin('loan_books as lb_end', function ($join) use ($endPeriod) {
                $join->on('lb_start.contract_id', '=', 'lb_end.contract_id')
                    ->where('lb_end.loan_portfolio_id', '=', DB::raw('lb_start.loan_portfolio_id'))
                    ->whereRaw("CONCAT(lb_end.reporting_year,'-',LPAD(lb_end.reporting_month,2,'0')) >= ?", [$endPeriod]);
            })
            ->where('lb_start.loan_portfolio_id', $portfolioId)
            ->where('lb_start.calculated_ifrs9_stage', '3')
            ->whereRaw("CONCAT(lb_start.reporting_year,'-',LPAD(lb_start.reporting_month,2,'0')) = ?", [$startPeriod])
            ->whereNull('lb_end.contract_id')
            ->pluck('lb_start.contract_id')
            ->toArray();

        if (empty($missingContracts)) {
            Log::info("No missing contracts found");
            return;
        }

        Log::info("Processing missing contracts", [
            'missing_count' => count($missingContracts),
            'sample_contracts' => array_slice($missingContracts, 0, 5)
        ]);

        // Insert missing contract records (marked as missing, NOT recovered)
        $chunks = array_chunk($missingContracts, 500);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            DB::statement("
                INSERT INTO lgd_payment_tracking_long (
                    contract_id,
                    portfolio_group,
                    reporting_period,
                    cohort_period,
                    payment_period,
                    starting_balance,
                    ending_balance,
                    payment_amount,
                    disbursement_amount,
                    cumulative_payments,
                    payment_type,
                    ifrs9_stage,
                    months_since_default,
                    is_cured,
                    cure_stage,
                    is_missing,
                    has_gap,
                    gap_months,
                    calculation_id,
                    created_at,
                    updated_at
                )
                SELECT
                    lb.contract_id,
                    ? as portfolio_group,
                    STR_TO_DATE(CONCAT(?, '-01'), '%Y-%m-%d') as reporting_period,

                    STR_TO_DATE(CONCAT(MIN(lb.reporting_year), '-', LPAD(MIN(lb.reporting_month), 2, '0'), '-01'), '%Y-%m-%d') as cohort_period,

                    -- Payment happens at end period
                    STR_TO_DATE(CONCAT(?, '-01'), '%Y-%m-%d') as payment_period,

                    MAX(lb.carrying_amount) as starting_balance,

                    -- FULL RECOVERY
                    0 as ending_balance,
                    MAX(lb.carrying_amount) as payment_amount,
                    0 as disbursement_amount,
                    MAX(lb.carrying_amount) as cumulative_payments,

                    'full' as payment_type,

                    '3' as ifrs9_stage,
                    0 as months_since_default,

                    TRUE as is_cured, -- optional (see note below)
                    NULL as cure_stage,

                    TRUE as is_missing,
                    FALSE as has_gap,
                    0 as gap_months,

                    ? as calculation_id,
                    NOW(),
                    NOW()
                FROM loan_books lb
                WHERE lb.contract_id IN ($placeholders)
                AND lb.loan_portfolio_id = ?
                AND CONCAT(lb.reporting_year,'-',LPAD(lb.reporting_month,2,'0')) < ?
                GROUP BY lb.contract_id
            ", array_merge(
                [$portfolioId, $endPeriod, $endPeriod, $this->calculationId], // Main params
                $chunk, // IN clause
                [$portfolioId, $endPeriod] // WHERE params
            ));
        }
    }

    private function completeCalculation(LGDCalculationLog $calculation, int $totalContracts): void
    {
        $totalRecords = DB::table('lgd_payment_tracking_long')
            ->where('calculation_id', $this->calculationId)
            ->count();

        $totalPayments = DB::table('lgd_payment_tracking_long')
            ->where('calculation_id', $this->calculationId)
            ->where('payment_type', '!=', 'missing')
            ->sum('payment_amount');

        $totalDisbursements = DB::table('lgd_payment_tracking_long')
            ->where('calculation_id', $this->calculationId)
            ->sum('disbursement_amount');

        $totalCured = DB::table('lgd_payment_tracking_long')
            ->where('calculation_id', $this->calculationId)
            ->where('is_cured', true)
            ->distinct('contract_id')
            ->count('contract_id');

        $totalDefaulted = DB::table('loan_books')
            ->where('loan_portfolio_id', $calculation->portfolio_group)
            ->where('calculated_ifrs9_stage', '3')
            ->whereRaw(
                "CONCAT(reporting_year,'-',LPAD(reporting_month,2,'0')) BETWEEN ? AND ?",
                [$calculation->start_period->format('Y-m'), $calculation->end_period->format('Y-m')]
            )
            ->sum('carrying_amount');

        $calculation->completeCalculation(
            totalContracts: $totalContracts,
            totalRecords: $totalRecords,
            totalPayments: $totalPayments,
            totalCured: $totalCured,
            totalDefaulted: $totalDefaulted
        );

        Log::info("LGD calculation completed", [
            'total_contracts' => $totalContracts,
            'total_records' => $totalRecords,
            'total_payments' => $totalPayments,
            'total_disbursements' => $totalDisbursements,
            'total_cured' => $totalCured,
            'total_defaulted' => $totalDefaulted
        ]);
    }
}
