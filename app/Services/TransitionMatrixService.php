<?php

namespace App\Services;

use App\Models\TransitionMatrix;
use App\Models\TransitionMatrixData;
use App\Models\TransitionProfileDefinition;
use App\Models\TransitionProfileOption;
use Illuminate\Support\Facades\DB;
use Exception;

class TransitionMatrixService
{
        /**
         * The stage a profile grades on, as a SQL expression. The measured
         * stage of a loan is the post-qualitative one (the instalment trigger,
         * the SICR flag and the cure hold are in it); where a book row carries
         * none, the calculated stage stands in, then the DPD stage, so a loan
         * the instalment trigger moved to Stage 3 is a default at either end
         * of the window (system audit of 9 October 2026, finding M10). Any
         * other grading column is read as it is.
         */
        public static function gradeExpression(string $alias, string $column): string
        {
            if ($column !== 'ifrs9stage_post_qualitative') {
                return "{$alias}.{$column}";
            }

            return "COALESCE(NULLIF({$alias}.ifrs9stage_post_qualitative, ''), NULLIF({$alias}.calculated_ifrs9_stage, ''), {$alias}.ifrs9stage_pre_qualitative)";
        }

        /** The contract statuses a loan's last book row may carry to count as settled when it leaves the book. */
        public const SETTLED_STATUSES = ['closed', 'paid', 'settled', 'paid_up', 'repaid', 'matured'];

        /**
         * The end grade of a loan missing from the end book (finding M10): "Paid"
         * only when its last row inside the window shows it settled (a zero
         * balance, or a closed or paid contract status); otherwise the window's
         * default end grade, because a balance that vanished without being
         * repaid was written off, not repaid. Before this every missing end row
         * was read as "Paid", which flattered the default rate.
         */
        public static function exitGrade(string $endTable, string $clientIdCol, string $clientId, string $startPeriod, string $endPeriod, string $defaultGrade, bool $hasStatus, bool $hasBalance = true): string
        {
            if (! $hasBalance) {
                return 'Paid'; // a profile graded on a table without balances keeps the old reading
            }
            $last = DB::table($endTable)->where($clientIdCol, $clientId)
                ->where('reporting_period', '>=', $startPeriod)->where('reporting_period', '<', $endPeriod)
                ->orderByDesc('reporting_period')->first(array_merge(['carrying_amount'], $hasStatus ? ['contract_status'] : []));
            if ($last === null) {
                return $defaultGrade;
            }
            $settled = (float) ($last->carrying_amount ?? 0) <= 0.0
                || ($hasStatus && in_array(strtolower(trim((string) ($last->contract_status ?? ''))), self::SETTLED_STATUSES, true));

            return $settled ? 'Paid' : $defaultGrade;
        }

        public static function processTransitionMatrixData(TransitionMatrix $transitionMatrix)
        {
            DB::beginTransaction();

            try {
                $startPeriodParts = explode('-', $transitionMatrix->start_reporting_period);
                $endPeriodParts = explode('-', $transitionMatrix->end_reporting_period);
                $start_year = $startPeriodParts[0];
                $start_month = $startPeriodParts[1];
                $end_year = $endPeriodParts[0];
                $end_month = $endPeriodParts[1];
                $transition_years = $transitionMatrix->transition_years;

                $profileDefinition = TransitionProfileDefinition::findOrFail($transitionMatrix->transition_profile_id);

                $startStages = TransitionProfileOption::where('profile_id', $transitionMatrix->transition_profile_id)
                    ->where('is_start_or_end', 'Start')
                    ->orderBy('ordering_index')->get();

                $endStages = TransitionProfileOption::where('profile_id', $transitionMatrix->transition_profile_id)
                    ->where('is_start_or_end', 'End')
                    ->orderBy('ordering_index')->get();

                // Determine filter column and value depending on level
                if ($transitionMatrix->pd_calculation_level === 'portfolio') {
                    $filterColumn = 'loan_portfolio_id';
                    $filterValue  = $transitionMatrix->pd_calculation_id;
                } elseif ($transitionMatrix->pd_calculation_level === 'sector') {
                    // loan book sector column is 'sector' (string code)
                    $filterColumn = 'industry_code';
                    $filterValue  = $transitionMatrix->pd_calculation_code;
                } else {
                    throw new Exception('Invalid PD calculation level');
                }

                $matrix = [];
                $start_total = [];
                $end_total = [];
                $start_count = [];
                $matrix_count = [];
                $is_default = [];

                // ensure you use the correct field name (default_flag or default_value)
                foreach ($endStages as $endStage) {
                    $is_default[$endStage->category_name] = $endStage->default_flag ?? ($endStage->default_value ?? 0);
                }

                // get table/col names from profile definition (ensure these are trusted values)
                $start_table = $profileDefinition->start_table;
                $end_table = $profileDefinition->end_table;
                $start_grading_col = $profileDefinition->start_grading_col;
                $end_grading_col = $profileDefinition->end_grading_col;
                $start_client_id_col = $profileDefinition->start_client_id_col;
                $end_client_id_col = $profileDefinition->end_client_id_col;
                $balance_column = 'carrying_amount'; // adjust if needed

                // Build SQL - NOTE: only values are bound, table/column names are interpolated.
                // A loan with no end row comes back with a null end grade and is
                // sorted by exitGrade() below: "Paid" or the default end grade.
                $startGradeExpr = self::gradeExpression('start_tbl', $start_grading_col);
                $endGradeExpr = self::gradeExpression('end_tbl', $end_grading_col);
                $matrix_sql = "
                    SELECT
                        start_tbl.{$start_client_id_col} AS client_id,
                        start_tbl.{$balance_column} AS start_bal,
                        {$startGradeExpr} AS start_grade,
                        {$endGradeExpr} AS end_grade,
                        end_tbl.{$end_client_id_col} AS end_client_id
                    FROM {$start_table} AS start_tbl
                    LEFT JOIN {$end_table} AS end_tbl
                        ON start_tbl.{$start_client_id_col} = end_tbl.{$end_client_id_col}
                        AND end_tbl.reporting_period = ?
                    WHERE start_tbl.reporting_period = ?
                    AND start_tbl.{$filterColumn} = ?
                ";

                // Pass the third binding (filter value) so the SQL has all required params
                $matrix_rows = DB::select($matrix_sql, [
                    $transitionMatrix->end_reporting_period,
                    $transitionMatrix->start_reporting_period,
                    $filterValue
                ]);

                // the default end grade of the profile (the bucket a written-off exit falls into)
                $defaultEndGrade = 'Paid';
                foreach ($endStages as $endStage) {
                    if ((int) ($endStage->default_flag ?? ($endStage->default_value ?? 0)) === 1) {
                        $defaultEndGrade = $endStage->category_name;
                        break;
                    }
                }
                $hasStatus = \Illuminate\Support\Facades\Schema::hasColumn($end_table, 'contract_status');
                $hasBalance = \Illuminate\Support\Facades\Schema::hasColumn($end_table, 'carrying_amount');

                foreach ($matrix_rows as $row) {
                    $start_grade = $row->start_grade;
                    $end_grade = $row->end_grade;
                    if ($row->end_client_id === null) {
                        $end_grade = self::exitGrade($end_table, $end_client_id_col, (string) $row->client_id, $transitionMatrix->start_reporting_period, $transitionMatrix->end_reporting_period, $defaultEndGrade, $hasStatus, $hasBalance);
                    } elseif ($end_grade === null) {
                        $end_grade = 'Paid';
                    }
                    $bal = (float) $row->start_bal;

                    $start_total[$start_grade] = ($start_total[$start_grade] ?? 0) + $bal;
                    $start_count[$start_grade] = ($start_count[$start_grade] ?? 0) + 1;
                    $end_total[$end_grade] = ($end_total[$end_grade] ?? 0) + $bal;
                    $matrix[$start_grade][$end_grade] = ($matrix[$start_grade][$end_grade] ?? 0) + $bal;
                    $matrix_count[$start_grade][$end_grade] = ($matrix_count[$start_grade][$end_grade] ?? 0) + 1;
                }

                $dataToInsert = [];
                $records_count_transitioned = 0;
                $transition_balance = 0.00;

                foreach ($startStages as $startStage) {
                    $start_grade = $startStage->category_name;
                    // tiny non-zero to avoid division by zero (explain in comment)
                    $start_total_balance_month = $start_total[$start_grade] ?? 0.00001;

                    foreach ($endStages as $endStage) {
                        $end_grade = $endStage->category_name;
                        $transition_balance_month = $matrix[$start_grade][$end_grade] ?? 0;
                        $transition_probability_month = ($start_total_balance_month > 0) ? $transition_balance_month / $start_total_balance_month : 0;

                        $default_flag = $is_default[$end_grade] ?? 0;

                        $row = [
                            'calculation_header_id'       => $transitionMatrix->id,
                            'is_payments_included'        => 1,
                            'start_period'                => $transitionMatrix->start_reporting_period,
                            'start_year'                  => $start_year,
                            'start_month'                 => $start_month,
                            'start_stage'                 => $start_grade,
                            'end_period'                  => $transitionMatrix->end_reporting_period,
                            'end_year'                    => $end_year,
                            'end_month'                   => $end_month,
                            'end_stage'                   => $end_grade,
                            'stage_transition'            => $start_grade . 'to' . $end_grade,
                            'transition_years'            => $transition_years,
                            'transition_balance_month'    => $transition_balance_month,
                            'start_total_balance_month'   => $start_total_balance_month,
                            'transition_probability_month'=> $transition_probability_month * 100,
                            'default_flag'                => $default_flag,
                            'created_at'                  => now(),
                            'updated_at'                  => now(),
                        ];

                        $dataToInsert[] = $row;

                        $records_count_transitioned += $matrix_count[$start_grade][$end_grade] ?? 0;
                        $transition_balance += $transition_balance_month;
                    }
                }

                // Upsert: include pd_calculation_code and pd_calculation_id in update list
                TransitionMatrixData::upsert(
                    $dataToInsert,
                    ['calculation_header_id', 'start_period', 'end_period', 'stage_transition'],
                    [
                        'is_payments_included',
                        'start_year',
                        'start_month',
                        'start_stage',
                        'end_year',
                        'end_month',
                        'end_stage',
                        'transition_years',
                        'transition_balance_month',
                        'start_total_balance_month',
                        'transition_probability_month',
                        'default_flag',
                        'updated_at',
                    ]
                );

                $transitionMatrix->update([
                    'records_count_transitioned' => $records_count_transitioned,
                    'transition_balance'         => $transition_balance,
                    'run_no'                     => $transitionMatrix->run_no + 1,
                    'last_calculation_date'      => now(),
                ]);

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }

}
