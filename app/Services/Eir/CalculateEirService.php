<?php

namespace App\Services\Eir;

use Carbon\CarbonImmutable;
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

    /** Date-sensitive effective annual rate for contractual cash-flow dates. */
    public function calculateDated(float $initialNetInvestment, array $cashFlows, int $paymentsPerYear,
        string $originationDate, string $dayCountBasis = 'ACT/365', float $guess = 0.10): array
    {
        if ($initialNetInvestment <= 0) throw new InvalidArgumentException('Initial net investment must be greater than zero.');
        if (! in_array($paymentsPerYear, [1,2,4,6,12], true)) throw new InvalidArgumentException('Payments per year must be one of 1, 2, 4, 6 or 12.');
        $origin = CarbonImmutable::parse($originationDate)->startOfDay(); $flows = [];
        foreach ($cashFlows as $i => $flow) {
            if (empty($flow['due_date']) || !isset($flow['amount']) || !is_numeric($flow['amount'])) throw new InvalidArgumentException("Dated cash flow {$i} needs a due_date and numeric amount.");
            $date = CarbonImmutable::parse($flow['due_date'])->startOfDay();
            if ($date->lte($origin)) throw new InvalidArgumentException('Every future contractual cash flow must fall after origination.');
            if ((float)$flow['amount'] < 0) throw new InvalidArgumentException('Future receipt amounts cannot be negative.');
            $flows[] = ['due_date'=>$date->toDateString(),'amount'=>(float)$flow['amount'],'exponent'=>$this->yearFraction($origin,$date,$dayCountBasis)];
        }
        if ($flows === [] || array_sum(array_column($flows,'amount')) <= 0) throw new InvalidArgumentException('Future contractual receipts must be greater than zero.');
        [$rate,$iterations,$method]=$this->datedNewton($initialNetInvestment,$flows,$guess);
        if ($rate===null) { [$rate,$iterations]=$this->datedBisection($initialNetInvestment,$flows); $method='DATED_BISECTION'; }
        $residual=$this->datedNpv($rate,$initialNetInvestment,$flows);
        if (abs($residual)>max(1.0,$initialNetInvestment)*1e-9) throw new RuntimeException('Date-sensitive EIR solver did not converge within the required tolerance.');
        $periodic=pow(1+$rate,1/$paymentsPerYear)-1;
        return ['eir_period'=>$periodic,'eir_nominal_annual'=>$periodic*$paymentsPerYear,'eir_effective_annual'=>$rate,
            'solver_iterations'=>$iterations,'solver_residual'=>$residual,'method'=>$method,
            'input_snapshot'=>['initial_net_investment'=>$initialNetInvestment,'payments_per_year'=>$paymentsPerYear,
                'origination_date'=>$origin->toDateString(),'day_count_basis'=>$dayCountBasis,'cash_flows'=>$flows]];
    }

    private function yearFraction(CarbonImmutable $from, CarbonImmutable $to, string $basis): float
    {
        $basis=strtoupper(trim($basis));
        if (in_array($basis,['30/360','30E/360'],true)) {
            $d1=min(30,$from->day); $d2=min(30,$to->day);
            return (($to->year-$from->year)*360+($to->month-$from->month)*30+($d2-$d1))/360;
        }
        return $from->diffInDays($to)/($basis==='ACT/360'?360:365);
    }
    private function datedNpv(float $rate,float $initial,array $flows): float { $npv=-$initial; foreach($flows as $f)$npv+=$f['amount']/pow(1+$rate,$f['exponent']); return $npv; }
    private function datedNewton(float $initial,array $flows,float $guess): array
    {
        $rate=max(self::RATE_FLOOR+1e-6,$guess);
        for($i=1;$i<=100;$i++){ $value=$this->datedNpv($rate,$initial,$flows); if(abs($value)<=max(1,$initial)*1e-10)return[$rate,$i,'DATED_NEWTON_RAPHSON'];
            $derivative=0.0;foreach($flows as $f)$derivative-=$f['exponent']*$f['amount']/pow(1+$rate,$f['exponent']+1);
            if(!is_finite($derivative)||abs($derivative)<1e-14)return[null,$i,''];$next=$rate-$value/$derivative;
            if(!is_finite($next)||$next<=self::RATE_FLOOR||$next>100)return[null,$i,''];if(abs($next-$rate)<1e-13)return[$next,$i,'DATED_NEWTON_RAPHSON'];$rate=$next; }
        return[null,100,''];
    }
    private function datedBisection(float $initial,array $flows): array
    {
        $low=self::RATE_FLOOR;$high=1.0;$fl=$this->datedNpv($low,$initial,$flows);$fh=$this->datedNpv($high,$initial,$flows);
        while($fl*$fh>0&&$high<1024){$high*=2;$fh=$this->datedNpv($high,$initial,$flows);}if($fl*$fh>0)throw new RuntimeException('No date-sensitive EIR root could be bracketed.');
        for($i=1;$i<=250;$i++){$mid=($low+$high)/2;$fm=$this->datedNpv($mid,$initial,$flows);if(abs($fm)<=max(1,$initial)*1e-10||abs($high-$low)<1e-13)return[$mid,$i];if($fl*$fm<=0)$high=$mid;else{$low=$mid;$fl=$fm;}}
        throw new RuntimeException('Date-sensitive bisection did not converge.');
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
