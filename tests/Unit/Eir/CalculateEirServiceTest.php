<?php

namespace Tests\Unit\Eir;

use App\Services\Eir\CalculateEirService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CalculateEirServiceTest extends TestCase
{
    public function test_acades_golden_eir(): void
    {
        $flows = [];
        for ($period = 1; $period <= 8; $period++) $flows[] = ['period' => $period, 'amount' => 17_099_839.71];
        $result = (new CalculateEirService())->calculate(95_990_000, $flows, 4);
        $this->assertEqualsWithDelta(0.086217, $result['eir_period'], 0.00001);
        $this->assertEqualsWithDelta(0.344868, $result['eir_nominal_annual'], 0.00005);
        $this->assertEqualsWithDelta(0.3921, $result['eir_effective_annual'], 0.0002);
        $this->assertLessThan(0.01, abs($result['solver_residual']));
    }

    public function test_zero_yield_cashflows_solve_to_zero(): void
    {
        $result = (new CalculateEirService())->calculate(100, [['period' => 1, 'amount' => 50], ['period' => 2, 'amount' => 50]], 12);
        $this->assertEqualsWithDelta(0.0, $result['eir_period'], 1e-10);
    }

    public function test_invalid_frequency_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CalculateEirService())->calculate(100, [['period' => 1, 'amount' => 110]], 5);
    }

    public function test_duplicate_period_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CalculateEirService())->calculate(100, [['period' => 1, 'amount' => 60], ['period' => 1, 'amount' => 60]], 12);
    }
}
