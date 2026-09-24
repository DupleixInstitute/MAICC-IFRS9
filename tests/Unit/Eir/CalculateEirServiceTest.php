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

    /**
     * A facility drawn in tranches pays money out again after origination, and
     * that second drawdown belongs in the vector as an outflow (spec v3 section
     * 7.6). The row has to say it is a drawdown; an unmarked negative is still
     * refused, because that is what a wrong sign in a delivered file looks like.
     */
    public function test_a_later_drawdown_is_accepted_as_an_outflow(): void
    {
        // 100,000 advanced, a second tranche of 50,000 paid out in month two,
        // and 220,000 received back over the four months that follow.
        $flows = [
            ['period' => 1, 'amount' => 20_000],
            ['period' => 2, 'amount' => -50_000, 'flow_type' => 'DISBURSEMENT'],
            ['period' => 3, 'amount' => 60_000],
            ['period' => 4, 'amount' => 60_000],
            ['period' => 5, 'amount' => 60_000],
            ['period' => 6, 'amount' => 60_000],
        ];

        $result = (new CalculateEirService())->calculate(100_000, $flows, 12);

        $this->assertGreaterThan(0.0, $result['eir_period']);
        $this->assertLessThan(0.01, abs($result['solver_residual']));
        $this->assertTrue(CalculateEirService::isDrawdown(['flow_type' => 'disbursement']));
        $this->assertTrue(CalculateEirService::isDrawdown(['is_drawdown' => true]));
        $this->assertFalse(CalculateEirService::isDrawdown(['amount' => -1]));
    }

    public function test_an_unmarked_negative_amount_is_still_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be negative/');
        (new CalculateEirService())->calculate(100, [['period' => 1, 'amount' => 200], ['period' => 2, 'amount' => -50]], 12);
    }

    /** The dated path follows the same rule, on dates instead of periods. */
    public function test_the_dated_solver_takes_a_marked_drawdown_and_refuses_an_unmarked_one(): void
    {
        $service = new CalculateEirService();
        $flows = [
            ['due_date' => '2025-02-01', 'amount' => 20_000],
            ['due_date' => '2025-03-01', 'amount' => -50_000, 'flow_type' => 'DISBURSEMENT'],
            ['due_date' => '2025-04-01', 'amount' => 60_000],
            ['due_date' => '2025-05-01', 'amount' => 60_000],
            ['due_date' => '2025-06-01', 'amount' => 60_000],
            ['due_date' => '2025-07-01', 'amount' => 60_000],
        ];

        $result = $service->calculateDated(100_000, $flows, 12, '2025-01-01', 'ACT/365');
        $this->assertGreaterThan(0.0, $result['eir_effective_annual']);

        $this->expectException(InvalidArgumentException::class);
        $service->calculateDated(100_000, [
            ['due_date' => '2025-02-01', 'amount' => 200_000],
            ['due_date' => '2025-03-01', 'amount' => -50_000],
        ], 12, '2025-01-01', 'ACT/365');
    }
}
