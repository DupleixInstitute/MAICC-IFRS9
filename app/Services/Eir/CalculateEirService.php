<?php

namespace App\Services\Eir;

use InvalidArgumentException;
use RuntimeException;

/** Pure periodic IRR solver. Database orchestration belongs in CalculateEirJob. */
class CalculateEirService
{
    private const RATE_FLOOR = -0.999999;

    /**
     * @param list<array{period:int|float,amount:int|float}> $cashFlows future contractual receipts
     * @return array{eir_period:float,eir_nominal_annual:float,eir_effective_annual:float,solver_iterations:int,solver_residual:float,method:string,input_snapshot:array}
     */
    public function calculate(float $initialNetInvestment, array $cashFlows, int $paymentsPerYear, float $guess = 0.02): array
    {
        $this->validate($initialNetInvestment, $cashFlows, $paymentsPerYear);
        $flows = array_map(fn ($f) => ['period' => (float) $f['period'], 'amount' => (float) $f['amount']], $cashFlows);

        [$rate, $iterations, $method] = $this->newton($initialNetInvestment, $flows, $guess);
        if ($rate === null) {
            [$rate, $iterations] = $this->bisection($initialNetInvestment, $flows);
            $method = 'BISECTION';
        }

        $residual = $this->npv($rate, $initialNetInvestment, $flows);
        $scale = max(1.0, $initialNetInvestment);
        if (abs($residual) > $scale * 1.0e-9) {
            throw new RuntimeException('EIR solver did not converge within the required tolerance.');
        }

        return [
            'eir_period' => $rate,
            'eir_nominal_annual' => $rate * $paymentsPerYear,
            'eir_effective_annual' => pow(1 + $rate, $paymentsPerYear) - 1,
            'solver_iterations' => $iterations,
            'solver_residual' => $residual,
            'method' => $method,
            'input_snapshot' => ['initial_net_investment' => $initialNetInvestment, 'payments_per_year' => $paymentsPerYear, 'cash_flows' => $flows],
        ];
    }

    private function validate(float $initial, array $flows, int $ppy): void
    {
        if ($initial <= 0) throw new InvalidArgumentException('Initial net investment must be greater than zero.');
        if (! in_array($ppy, [1, 2, 4, 6, 12], true)) throw new InvalidArgumentException('Payments per year must be one of 1, 2, 4, 6 or 12.');
        if ($flows === []) throw new InvalidArgumentException('At least one future contractual cash flow is required.');
        $periods = [];
        foreach ($flows as $i => $flow) {
            if (! isset($flow['period'], $flow['amount']) || ! is_numeric($flow['period']) || ! is_numeric($flow['amount'])) throw new InvalidArgumentException("Cash flow {$i} needs numeric period and amount.");
            if ((float) $flow['period'] <= 0 || (float) $flow['amount'] < 0) throw new InvalidArgumentException('Future periods must be positive and receipt amounts cannot be negative.');
            if (isset($periods[(string) $flow['period']])) throw new InvalidArgumentException('Cash-flow periods must be unique; aggregate flows in the same period.');
            $periods[(string) $flow['period']] = true;
        }
        if (array_sum(array_column($flows, 'amount')) <= 0) throw new InvalidArgumentException('Future contractual receipts must be greater than zero.');
    }

    private function newton(float $initial, array $flows, float $guess): array
    {
        $rate = max(self::RATE_FLOOR + 1e-6, $guess);
        for ($i = 1; $i <= 100; $i++) {
            $value = $this->npv($rate, $initial, $flows);
            if (abs($value) <= max(1.0, $initial) * 1e-10) return [$rate, $i, 'NEWTON_RAPHSON'];
            $derivative = 0.0;
            foreach ($flows as $flow) $derivative -= $flow['period'] * $flow['amount'] / pow(1 + $rate, $flow['period'] + 1);
            if (! is_finite($derivative) || abs($derivative) < 1e-14) return [null, $i, ''];
            $next = $rate - ($value / $derivative);
            if (! is_finite($next) || $next <= self::RATE_FLOOR || $next > 100) return [null, $i, ''];
            if (abs($next - $rate) < 1e-13) return [$next, $i, 'NEWTON_RAPHSON'];
            $rate = $next;
        }
        return [null, 100, ''];
    }

    private function bisection(float $initial, array $flows): array
    {
        $low = self::RATE_FLOOR; $high = 1.0;
        $fLow = $this->npv($low, $initial, $flows); $fHigh = $this->npv($high, $initial, $flows);
        while ($fLow * $fHigh > 0 && $high < 1024) { $high *= 2; $fHigh = $this->npv($high, $initial, $flows); }
        if ($fLow * $fHigh > 0) throw new RuntimeException('No EIR root could be bracketed for the supplied cash flows.');
        for ($i = 1; $i <= 250; $i++) {
            $mid = ($low + $high) / 2; $fMid = $this->npv($mid, $initial, $flows);
            if (abs($fMid) <= max(1.0, $initial) * 1e-10 || abs($high - $low) < 1e-13) return [$mid, $i];
            if ($fLow * $fMid <= 0) { $high = $mid; } else { $low = $mid; $fLow = $fMid; }
        }
        throw new RuntimeException('Bisection did not converge.');
    }

    private function npv(float $rate, float $initial, array $flows): float
    {
        $npv = -$initial;
        foreach ($flows as $flow) $npv += $flow['amount'] / pow(1 + $rate, $flow['period']);
        return $npv;
    }
}
