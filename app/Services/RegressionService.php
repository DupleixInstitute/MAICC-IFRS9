<?php
// app/Services/RegressionService.php

namespace App\Services;

use App\Models\CreditLossData;
use App\Models\MacroStatsValue;
use App\Models\MacroStatsDefinition;
use App\Models\RegressionModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RegressionService
{
    /**
     * Train a regression model
     */
    public function trainModel(array $config): array
    {
        $dependentData = $this->getDependentVariableData($config);
        $independentData = $this->getIndependentVariablesData($config);
        
        return $this->multipleLinearRegression($independentData, $dependentData, $config['indep_vars']);
    }
    /**x 
     * Get dependent variable data (PD, LGD, ECL values)
     */
    private function getDependentVariableData(array $config): Collection
    {
        $trainStart = Carbon::parse($config['train_start'])->startOfMonth();
        $trainEnd   = Carbon::parse($config['train_end'])->endOfMonth();

        return CreditLossData::where('definition_id', $config['dep_var_id'])
            ->where('portfolio_id', $config['portfolio_id'])
            ->whereDate('period', '>=', $trainStart->toDateString())
            ->whereDate('period', '<=', $trainEnd->toDateString())
            ->orderBy('period')
            ->get(['period', 'value'])
            ->groupBy(function($item) {
                $period = is_string($item->period) ? Carbon::parse($item->period) : $item->period;
                return $period->format('Y-m');
            })
            ->map(function($group) {
                // If there are multiple entries for the same month, take the first one
                return $group->first()->value;
            });
        }

    /**
     * Get independent variables data (macro statistics)
     */
    private function getIndependentVariablesData(array $config): array
    {
        $trainStart = Carbon::parse($config['train_start'])->startOfMonth();
        $trainEnd   = Carbon::parse($config['train_end'])->endOfMonth();

        $data = [];
        foreach ($config['indep_vars'] as $macroStatId) {
            $macroData = MacroStatsValue::where('macro_stat_definition_id', $macroStatId)
                ->whereDate('period', '>=', $trainStart->toDateString())
                ->whereDate('period', '<=', $trainEnd->toDateString())
                ->where('is_forecast', false)
                ->orderBy('period')
                ->get(['period', 'value'])
                ->groupBy(function($item) {
                    $period = is_string($item->period) ? Carbon::parse($item->period) : $item->period;
                    return $period->format('Y-m');
                })
                ->map(function($group) {
                    // If there are multiple entries for the same month, take the first one
                    return $group->first()->value;
                })
                ->toArray();

            $data[$macroStatId] = $macroData;
        }

            return $data;
        }
    /**
     * Perform multiple linear regression
     */
    public function multipleLinearRegression(array $independentVars, Collection $dependentVar, array $varNames): array
    {
        // Align data by period and convert to arrays
        $alignedData = $this->alignDataByPeriod($independentVars, $dependentVar);
        
        if (count($alignedData['Y']) < 2) {
            // Get counts of data points per variable
            $varCounts = [];
            foreach ($independentVars as $varId => $data) {
                $varCounts[] = "Variable $varId: " . count($data) . ' data points';
            }
            
            throw new \Exception(
                "Insufficient overlapping data for regression analysis.\n" .
                "Need at least 2 months with complete data across all selected variables.\n" .
                "Dependent variable has {$dependentVar->count()} data points.\n" .
                "Independent variables data points:\n- " . 
                implode("\n- ", $varCounts) . "\n" .
                "Only found complete data for these months: " . 
                (empty($alignedData['periods']) ? 'None' : implode(', ', $alignedData['periods']))
            );
        }

        $X = $alignedData['X'];
        $Y = $alignedData['Y'];
        $periods = $alignedData['periods'];
        
        $n = count($Y);
        $k = count($varNames);

        // Add intercept column
        foreach ($X as $i => $row) {
            array_unshift($X[$i], 1); // Intercept term
        }

        // Calculate coefficients using matrix algebra
        $coefficients = $this->calculateCoefficients($X, $Y);
        
        // Calculate statistics
        $stats = $this->calculateRegressionStatistics($X, $Y, $coefficients, $n, $k);

        return [
            'coefficients' => array_combine(['intercept', ...$varNames], $coefficients),
            'r_squared' => $stats['r_squared'],
            'adjusted_r_squared' => $stats['adjusted_r_squared'],
            'standard_errors' => $stats['standard_errors'],
            't_statistics' => $stats['t_statistics'],
            'p_values' => $stats['p_values'],
            'mean_squared_error' => $stats['mean_squared_error'],
            'periods_used' => $periods,
            'observations' => $n,
        ];
    }

    /**
     * Align data by period to ensure same time periods
     */
    private function alignDataByPeriod(array $independentVars, Collection $dependentVar): array
        {
            $depData = $dependentVar->toArray();
            \Log::info('Dependent data in alignDataByPeriod:', $depData);

            // Normalize independent variable keys
            $indepData = [];
            foreach ($independentVars as $varId => $varData) {
                \Log::info("Processing independent var ID: $varId");
                \Log::info('Raw var data:', $varData);
                
                foreach ($varData as $period => $value) {
                    $monthKey = Carbon::parse($period)->format('Y-m');
                    $indepData[$varId][$monthKey] = $value;
                }
                \Log::info("Processed independent data for $varId:", $indepData[$varId] ?? []);
            }

            // Find common periods
            $commonPeriods = array_keys($depData);
            \Log::info('Initial common periods (from dependent var):', $commonPeriods);
            
            foreach ($indepData as $varId => $varData) {
                $varPeriods = array_keys($varData);
                \Log::info("Periods for var $varId:", $varPeriods);
                $commonPeriods = array_intersect($commonPeriods, $varPeriods);
                \Log::info('Common periods after var ' . $varId . ':', $commonPeriods);
            }
            
            sort($commonPeriods);
            \Log::info('Final common periods:', $commonPeriods);

            // Build aligned X and Y
            $X = [];
            $Y = [];
            foreach ($commonPeriods as $period) {
                $row = [];
                foreach ($indepData as $varData) {
                    $row[] = $varData[$period];
                }
                $X[] = $row;
                $Y[] = $depData[$period];
            }

            return [
                'X' => $X,
                'Y' => $Y,
                'periods' => $commonPeriods
            ];
        }



    /**
     * Calculate regression coefficients using normal equations
     */
    private function calculateCoefficients(array $X, array $Y): array
    {
        $Xt = $this->transposeMatrix($X);
        $XtX = $this->matrixMultiply($Xt, $X);
        // Treat Y as a column vector (n x 1)
        $yCol = array_map(function ($v) {
            return [$v];
        }, $Y);
        $XtY = $this->matrixMultiply($Xt, $yCol);
        
        // Solve (X'X)β = X'Y
        $coefficients = $this->solveLinearSystem($XtX, $this->matrixColumn($XtY, 0));
        
        return $coefficients;
    }

    /**
     * Calculate regression statistics
     */
    private function calculateRegressionStatistics(array $X, array $Y, array $coefficients, int $n, int $k): array
    {
        $yMean = array_sum($Y) / $n;
        
        // Calculate predictions and residuals
        $predictions = [];
        $residuals = [];
        $ssTotal = 0;
        $ssResidual = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $prediction = $coefficients[0]; // intercept
            for ($j = 0; $j < $k; $j++) {
                $prediction += $coefficients[$j + 1] * $X[$i][$j + 1];
            }
            $predictions[] = $prediction;
            $residual = $Y[$i] - $prediction;
            $residuals[] = $residual;
            
            $ssTotal += pow($Y[$i] - $yMean, 2);
            $ssResidual += pow($residual, 2);
        }
        
        // R-squared
        $rSquared = 1 - ($ssResidual / $ssTotal);
        $adjustedRSquared = 1 - ((1 - $rSquared) * ($n - 1) / ($n - $k - 1));
        
        // Standard errors
        $mse = $ssResidual / ($n - $k - 1);
        $xtxInverse = $this->matrixInverse($this->matrixMultiply($this->transposeMatrix($X), $X));
        $standardErrors = [];
        
        for ($i = 0; $i < count($coefficients); $i++) {
            $standardErrors[] = sqrt($mse * $xtxInverse[$i][$i]);
        }
        
        // t-statistics and p-values
        $tStats = [];
        $pValues = [];
        
        foreach ($coefficients as $i => $coef) {
            $tStats[] = $coef / ($standardErrors[$i] == 0 ? INF : $standardErrors[$i]);
            $pValues[] = $this->calculatePValue($tStats[$i], $n - $k - 1);
        }
        
        return [
            'r_squared' => $rSquared,
            'adjusted_r_squared' => $adjustedRSquared,
            'standard_errors' => $standardErrors,
            't_statistics' => $tStats,
            'p_values' => $pValues,
            'mean_squared_error' => $mse,
        ];
    }

    /**
     * Extract a column from a 2D array (matrix)
     */
    private function matrixColumn(array $matrix, int $colIndex): array
    {
        $column = [];
        foreach ($matrix as $row) {
            $column[] = $row[$colIndex] ?? 0; // fill missing with 0
        }
        return $column;
    }

    // Matrix operations
    private function transposeMatrix(array $matrix): array
    {
        $transposed = [];
        foreach ($matrix as $i => $row) {
            foreach ($row as $j => $value) {
                $transposed[$j][$i] = $value;
            }
        }
        return $transposed;
    }
    
    private function matrixMultiply(array $a, array $b): array
    {
        $result = [];
        $rowsA = count($a);
        $colsA = count($a[0] ?? []);
        $colsB = count($b[0] ?? []);

        for ($i = 0; $i < $rowsA; $i++) {
            for ($j = 0; $j < $colsB; $j++) {
                $result[$i][$j] = 0;
                for ($k = 0; $k < $colsA; $k++) {

                    if (!isset($a[$i][$k])) {
                        \Log::error('Missing key in $a', [
                            'i' => $i,
                            'k' => $k,
                            'row_a' => $a[$i] ?? 'row not exists',
                        ]);
                        continue;
                    }

                    if (!isset($b[$k][$j])) {
                        \Log::error('Missing key in $b', [
                            'k' => $k,
                            'j' => $j,
                            'row_b' => $b[$k] ?? 'row not exists',
                        ]);
                        continue;
                    }

                    $result[$i][$j] += $a[$i][$k] * $b[$k][$j];
                }
            }
        }

        return $result;
    }

    // Invert a square matrix using Gauss-Jordan elimination
    private function matrixInverse(array $matrix): array
    {
        $n = count($matrix);
        if ($n === 0 || count($matrix[0]) !== $n) {
            throw new \InvalidArgumentException('Matrix must be non-empty and square for inversion');
        }

        // Create augmented matrix [A | I]
        $augmented = [];
        for ($i = 0; $i < $n; $i++) {
            $augmented[$i] = array_merge($matrix[$i], array_fill(0, $n, 0));
            $augmented[$i][$n + $i] = 1; // set identity
        }

        // Forward elimination
        for ($i = 0; $i < $n; $i++) {
            // Find pivot
            $maxRow = $i;
            for ($r = $i + 1; $r < $n; $r++) {
                if (abs($augmented[$r][$i]) > abs($augmented[$maxRow][$i])) {
                    $maxRow = $r;
                }
            }
            if ($augmented[$maxRow][$i] == 0) {
                throw new \RuntimeException('Matrix is singular and cannot be inverted');
            }
            // Swap rows
            if ($maxRow !== $i) {
                [$augmented[$i], $augmented[$maxRow]] = [$augmented[$maxRow], $augmented[$i]];
            }
            // Normalize pivot row
            $pivot = $augmented[$i][0 + $i];
            $rowLen = 2 * $n;
            for ($c = 0; $c < $rowLen; $c++) {
                $augmented[$i][$c] /= $pivot;
            }
            // Eliminate other rows
            for ($r = 0; $r < $n; $r++) {
                if ($r === $i) continue;
                $factor = $augmented[$r][$i];
                for ($c = 0; $c < $rowLen; $c++) {
                    $augmented[$r][$c] -= $factor * $augmented[$i][$c];
                }
            }
        }

        // Extract inverse from augmented matrix
        $inverse = [];
        for ($i = 0; $i < $n; $i++) {
            $inverse[$i] = array_slice($augmented[$i], $n, $n);
        }
        return $inverse;
    }

    /**
     * Solve a linear system using Gaussian elimination
     */
    private function solveLinearSystem(array $A, array $b): array
    {
        $n = count($A);
        
        // Gaussian elimination
        for ($i = 0; $i < $n; $i++) {
            // Find pivot
            $maxRow = $i;
            for ($j = $i + 1; $j < $n; $j++) {
                if (abs($A[$j][$i]) > abs($A[$maxRow][$i])) {
                    $maxRow = $j;
                }
            }
            
            // Swap rows
            if ($maxRow != $i) {
                [$A[$i], $A[$maxRow]] = [$A[$maxRow], $A[$i]];
                [$b[$i], $b[$maxRow]] = [$b[$maxRow], $b[$i]];
            }
            
            // Eliminate
            for ($j = $i + 1; $j < $n; $j++) {
                $factor = $A[$j][$i] / $A[$i][$i];
                for ($k = $i; $k < $n; $k++) {
                    $A[$j][$k] -= $factor * $A[$i][$k];
                }
                $b[$j] -= $factor * $b[$i];
            }
        }
        
        // Back substitution
        $x = array_fill(0, $n, 0);
        for ($i = $n - 1; $i >= 0; $i--) {
            $x[$i] = $b[$i];
            for ($j = $i + 1; $j < $n; $j++) {
                $x[$i] -= $A[$i][$j] * $x[$j];
            }
            $x[$i] /= $A[$i][$i];
        }
        
        return $x;
    }
    
    private function calculatePValue(float $t, int $df): float
    {
        // Normal approximation for t-distribution p-value (two-tailed)
        $z = abs($t);
        $pOneTailed = 1 - $this->normalCdf($z);
        $p = 2 * $pOneTailed;
        return max(0.0, min(1.0, $p));
    }

    // Standard normal CDF using Abramowitz-Stegun approximation
    private function normalCdf(float $x): float
    {
        $x = $x / sqrt(2.0);
        // erf approximation
        $sign = $x < 0 ? -1.0 : 1.0;
        $x = abs($x);
        $a1 = 0.254829592;
        $a2 = -0.284496736;
        $a3 = 1.421413741;
        $a4 = -1.453152027;
        $a5 = 1.061405429;
        $p = 0.3275911;
        $t = 1.0 / (1.0 + $p * $x);
        $poly = (((($a5 * $t + $a4) * $t + $a3) * $t + $a2) * $t + $a1) * $t;
        $y = 1.0 - $poly * exp(-$x * $x);
        $erf = $sign * $y;
        return 0.5 * (1.0 + $erf);
    }

    /**
     * Make predictions using trained model
     */
   /**
 * Make predictions using trained model and forecast macro data
 */
        public function makePredictions(RegressionModel $model, array $macroData): array
        {
            $predictions = [];
            $coefficients = $model->coeffs;
            $independentVars = $model->indep_vars ?? [];

            // Get statistic codes for the independent variables
            $statisticCodes = MacroStatsDefinition::whereIn('id', $independentVars)
                ->pluck('statistic_code', 'id')
                ->toArray();

            foreach ($macroData as $period => $periodMacroData) {
                $prediction = $coefficients['intercept'] ?? 0;
                
                foreach ($independentVars as $varId) {
                    $statisticCode = $statisticCodes[$varId] ?? null;
                    $coefficient = $coefficients[$varId] ?? 0;
                    
                    if ($statisticCode && isset($periodMacroData[$statisticCode])) {
                        $prediction += $coefficient * $periodMacroData[$statisticCode];
                    }
                }
                
                $predictions[$period] = max(0, $prediction);
            }
            
            return $predictions;
        }
}