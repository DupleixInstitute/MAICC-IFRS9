<?php

namespace Tests\Unit\Eir;

use App\Services\Eir\ScheduleGeneratorService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures are the real MAIIC sample offer letters (docs/EIR_Build.md §7):
 * ACADES (quarterly), BERL and EcoGen (FinES, moratoria), plus structural
 * invariants every generated schedule must satisfy.
 *
 * Pure unit tests — the service touches no database.
 */
class ScheduleGeneratorServiceTest extends TestCase
{
    private ScheduleGeneratorService $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ScheduleGeneratorService();
    }

    /**
     * ACADES Holdings: MK100m, 8 equal quarterly instalments of
     * MK17,099,839.71 per the offer letter. The instalment-implied period
     * rate is 7.5414% quarterly (30.166% nominal) — the offer letter's
     * quoted 32.1% "daily basis payable quarterly" reflects a different
     * accrual convention (open item #1/#8 in docs/Development_of_EIR.md).
     * Feeding the implied nominal rate back through the annuity formula
     * must reproduce the offer letter's instalment.
     */
    public function test_acades_quarterly_instalment_reproduced(): void
    {
        $result = $this->generator->generate([
            'principal'         => 100_000_000,
            'annual_rate'       => 0.0754138 * 4,
            'payments_per_year' => 4,
            'n_payments'        => 8,
            'start_date'        => '2025-05-22',
            'moratorium_months' => 0,
        ]);

        $this->assertEqualsWithDelta(17_099_839.71, $result['instalment'], 2_500,
            'ACADES instalment should match the offer letter within rate-rounding tolerance');
        $this->assertCount(8, $result['rows']);

        // Quarterly spacing from the 22 May 2025 origination.
        $this->assertSame('2025-08-22', $result['rows'][0]['due_date']);
        $this->assertSame('2025-11-22', $result['rows'][1]['due_date']);
        $this->assertSame('2027-05-22', $result['rows'][7]['due_date']);
    }

    /**
     * BERL (FinES): 10% concessional, 48 monthly instalments after a
     * 6-month capital+interest moratorium. Interest capitalises during the
     * holiday, so instalments amortise the grown balance.
     */
    public function test_berl_moratorium_capitalises_interest(): void
    {
        $principal = 108_431_000;

        $result = $this->generator->generate([
            'principal'         => $principal,
            'annual_rate'       => 0.10,
            'payments_per_year' => 12,
            'n_payments'        => 48,
            'start_date'        => '2024-02-24',
            'moratorium_months' => 6,
        ]);

        $expectedCapitalised = $principal * pow(1 + 0.10 / 12, 6);
        $this->assertEqualsWithDelta($expectedCapitalised, $result['capitalised_principal'], 0.01);
        $this->assertGreaterThan($principal, $result['capitalised_principal']);

        // Principal rows retire exactly the capitalised balance.
        $principalSum = array_sum(array_column($result['rows'], 'principal_due'));
        $this->assertEqualsWithDelta($result['capitalised_principal'], $principalSum, 0.05);

        // First instalment falls after the 6-month holiday plus one period.
        $this->assertSame('2024-09-24', $result['rows'][0]['due_date']);
        $this->assertCount(48, $result['rows']);
    }

    /**
     * EcoGen (FinES): 10%, 36 monthly instalments, 3-month moratorium.
     */
    public function test_ecogen_three_month_moratorium(): void
    {
        $result = $this->generator->generate([
            'principal'         => 105_060_000,
            'annual_rate'       => 0.10,
            'payments_per_year' => 12,
            'n_payments'        => 36,
            'start_date'        => '2025-01-29',
            'moratorium_months' => 3,
        ]);

        $this->assertCount(36, $result['rows']);
        // 29 Jan + 3 months holiday + 1 month to first instalment = 29 May.
        $this->assertSame('2025-05-29', $result['rows'][0]['due_date']);
        $this->assertGreaterThan(105_060_000, $result['capitalised_principal']);
    }

    /**
     * Structural invariants for any generated schedule: interest accrues on
     * the declining opening balance at the period rate, and the balance
     * amortises to exactly zero.
     */
    public function test_amortisation_invariants(): void
    {
        $principal = 50_000_000;
        $rate      = 0.321;

        $result = $this->generator->generate([
            'principal'         => $principal,
            'annual_rate'       => $rate,
            'payments_per_year' => 12,
            'n_payments'        => 24,
            'start_date'        => '2024-09-16',
            'moratorium_months' => 0,
        ]);

        $balance = $principal;
        foreach ($result['rows'] as $i => $row) {
            $expectedInterest = round($balance * $rate / 12, 2);
            $this->assertEqualsWithDelta($expectedInterest, $row['interest_due'], 0.01,
                "row {$i}: interest must accrue on the opening balance");
            $balance = round($balance - $row['principal_due'], 2);
        }

        $this->assertEqualsWithDelta(0.0, $balance, 0.01, 'schedule must amortise to zero');
    }

    /** Zero-rate facilities amortise straight-line rather than dividing by zero. */
    public function test_zero_rate_straight_line(): void
    {
        $result = $this->generator->generate([
            'principal'         => 12_000_000,
            'annual_rate'       => 0.0,
            'payments_per_year' => 12,
            'n_payments'        => 12,
            'start_date'        => '2025-01-01',
        ]);

        $this->assertEqualsWithDelta(1_000_000, $result['instalment'], 0.01);
        $this->assertSame(0.0, array_sum(array_column($result['rows'], 'interest_due')));
    }

    /** Month-end origination dates must not overflow into the next month. */
    public function test_month_end_dates_do_not_overflow(): void
    {
        $result = $this->generator->generate([
            'principal'         => 10_000_000,
            'annual_rate'       => 0.12,
            'payments_per_year' => 12,
            'n_payments'        => 3,
            'start_date'        => '2025-01-31',
        ]);

        // Jan 31 + 1 month must clamp to Feb 28, not skid to Mar 3.
        $this->assertSame('2025-02-28', $result['rows'][0]['due_date']);
    }

    public function test_rejects_invalid_terms(): void
    {
        $base = [
            'principal'         => 1_000_000,
            'annual_rate'       => 0.10,
            'payments_per_year' => 12,
            'n_payments'        => 12,
            'start_date'        => '2025-01-01',
        ];

        foreach ([
            ['principal' => 0],
            ['annual_rate' => -0.01],
            ['payments_per_year' => 5],
            ['n_payments' => 0],
        ] as $override) {
            try {
                $this->generator->generate(array_merge($base, $override));
                $this->fail('Expected InvalidArgumentException for ' . json_encode($override));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
