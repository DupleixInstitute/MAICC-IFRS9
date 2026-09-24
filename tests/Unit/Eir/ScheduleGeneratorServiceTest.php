<?php

namespace Tests\Unit\Eir;

use App\Services\Eir\CalculateEirService;
use App\Services\Eir\ScheduleGeneratorService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures are the real MAIIC sample offer letters (docs/EIR_Build.md s.7):
 * ACADES (quarterly), BERL and EcoGen (FinES, moratoria), the Lake Malawi
 * Aquaculture facility that proves the sanction-against-balance flags matter,
 * plus the structural invariants every generated schedule must satisfy.
 *
 * Pure unit tests - the service touches no database, so the day count is
 * stated in the terms. In the application it is read from the Governance
 * Centre setting "Day count" instead; ScheduleGeneratorGovernanceTest proves
 * that path and proves the generator stops when the setting is missing.
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
     * rate is 7.5414% quarterly (30.166% nominal) - the offer letter's
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
            'day_count'         => 'ACT/365',
        ]);

        $this->assertEqualsWithDelta(17_099_839.71, $result['instalment'], 2_500,
            'ACADES instalment should match the offer letter within rate-rounding tolerance');
        $this->assertCount(8, $result['rows']);

        // Quarterly spacing from the 22 May 2025 origination. Each period is a
        // whole quarter, so each is charged at the nominal rate over four.
        $this->assertSame('2025-08-22', $result['rows'][0]['due_date']);
        $this->assertSame('2025-11-22', $result['rows'][1]['due_date']);
        $this->assertSame('2027-05-22', $result['rows'][7]['due_date']);
        $this->assertSame('INSTALMENT', $result['rows'][0]['phase']);
    }

    /**
     * BERL (FinES): 10% concessional, 48 monthly instalments after a 6-month
     * moratorium of type Both. Nothing is paid during the moratorium; the
     * interest is added to the balance each month on the actual days in that
     * month over 365 (decisions D9 and D10), and the instalments then amortise
     * the enlarged balance. The month-by-month interest is 861,506.58 +
     * 928,237.73 + 905,923.93 + 943,815.54 + 921,127.26 + 959,654.78 =
     * 5,520,265.82 on 29, 31, 30, 31, 30 and 31 days.
     */
    public function test_a_both_moratorium_compounds_monthly_on_the_day_count(): void
    {
        $principal = 108_431_000;

        $result = $this->generator->generate([
            'principal'         => $principal,
            'annual_rate'       => 0.10,
            'payments_per_year' => 12,
            'n_payments'        => 48,
            'start_date'        => '2024-02-24',
            'moratorium_months' => 6,
            'moratorium_type'   => 'Both (Interest + Principle)',
            'day_count'         => 'ACT/365',
        ]);

        $this->assertSame(ScheduleGeneratorService::MORATORIUM_BOTH, $result['moratorium_type']);
        $this->assertSame('CONTRACT', $result['basis_sources']['moratorium_type']);
        $this->assertSame('2024-02-24', $result['moratorium_from']);
        $this->assertSame('2024-08-24', $result['moratorium_end']);

        // The balance after the moratorium is the opening balance plus exactly
        // the interest capitalised, to the cent.
        $this->assertEqualsWithDelta(5_520_265.82, $result['capitalised_interest'], 0.01);
        $this->assertEqualsWithDelta(113_951_265.82, $result['capitalised_principal'], 0.01);
        $this->assertSame(
            round($principal + $result['capitalised_interest'], 2),
            round($result['capitalised_principal'], 2),
            'the balance after the moratorium is the opening balance plus the interest capitalised'
        );

        // Six monthly capitalisations, each on its own day count, and nothing paid.
        $this->assertCount(6, $result['moratorium_rows']);
        $this->assertSame([29, 31, 30, 31, 30, 31], array_column($result['moratorium_rows'], 'days'));
        $this->assertSame(0.0, array_sum(array_column($result['moratorium_rows'], 'interest_paid')));
        $this->assertEqualsWithDelta($result['capitalised_interest'],
            array_sum(array_column($result['moratorium_rows'], 'interest_capitalised')), 0.01);

        // Dividing the annual rate by 12 would have given a different balance.
        $this->assertNotEqualsWithDelta($principal * pow(1 + 0.10 / 12, 6), $result['capitalised_principal'], 1.0);

        // Principal rows retire exactly the capitalised balance, and the first
        // instalment falls one month after the moratorium ends.
        $principalSum = array_sum(array_column($result['rows'], 'principal_due'));
        $this->assertEqualsWithDelta($result['capitalised_principal'], $principalSum, 0.05);
        $this->assertSame('2024-09-24', $result['rows'][0]['due_date']);
        $this->assertCount(48, $result['rows']);
    }

    /**
     * A moratorium of type Principle Only: the borrower pays the interest each
     * month and the balance does not grow, so the 48 instalments still amortise
     * the original 108,431,000. The six interest-only months are cash flows in
     * their own right and belong in the schedule.
     */
    public function test_a_principal_only_moratorium_pays_interest_and_leaves_the_balance_alone(): void
    {
        $principal = 108_431_000;

        $result = $this->generator->generate([
            'principal'         => $principal,
            'annual_rate'       => 0.10,
            'payments_per_year' => 12,
            'n_payments'        => 48,
            'start_date'        => '2024-02-24',
            'moratorium_months' => 6,
            'moratorium_type'   => 'Principle Only',
            'day_count'         => 'ACT/365',
        ]);

        $this->assertSame(ScheduleGeneratorService::MORATORIUM_PRINCIPAL_ONLY, $result['moratorium_type']);
        $this->assertSame(0.0, $result['capitalised_interest']);
        $this->assertEqualsWithDelta($principal, $result['capitalised_principal'], 0.01);

        // Six interest-only months, then the 48 instalments.
        $this->assertCount(54, $result['rows']);
        $serviced = array_slice($result['rows'], 0, 6);
        foreach ($serviced as $row) {
            $this->assertSame('MORATORIUM_INTEREST', $row['phase']);
            $this->assertSame(0.0, $row['principal_due']);
            $this->assertGreaterThan(0, $row['interest_due']);
            $this->assertEqualsWithDelta($principal, $row['closing_balance'], 0.01,
                'a principal-only moratorium leaves the balance where it was');
        }
        // 29 days in the first month (2024 is a leap year), then 31 and 30.
        $this->assertEqualsWithDelta(861_506.58, $serviced[0]['interest_due'], 0.01);
        $this->assertEqualsWithDelta(920_920.82, $serviced[1]['interest_due'], 0.01);
        $this->assertEqualsWithDelta(891_213.70, $serviced[2]['interest_due'], 0.01);

        $this->assertSame('2024-09-24', $result['rows'][6]['due_date']);
        $this->assertGreaterThan(0, $result['rows'][6]['principal_due']);
        $principalSum = array_sum(array_column($result['rows'], 'principal_due'));
        $this->assertEqualsWithDelta($principal, $principalSum, 0.05);
    }

    /** EcoGen (FinES): 10%, 36 monthly instalments, 3-month Both moratorium. */
    public function test_ecogen_three_month_moratorium(): void
    {
        $result = $this->generator->generate([
            'principal'         => 105_060_000,
            'annual_rate'       => 0.10,
            'payments_per_year' => 12,
            'n_payments'        => 36,
            'start_date'        => '2025-01-29',
            'moratorium_months' => 3,
            'moratorium_type'   => 'Both (Interest + Principle)',
            'day_count'         => 'ACT/365',
        ]);

        $this->assertCount(36, $result['rows']);
        // 29 Jan + 3 months holiday + 1 month to the first instalment = 29 May.
        $this->assertSame('2025-05-29', $result['rows'][0]['due_date']);
        $this->assertGreaterThan(105_060_000, $result['capitalised_principal']);
        $this->assertEqualsWithDelta(107_671_862.88, $result['capitalised_principal'], 0.01);
    }

    /**
     * A moratorium with no type stated is refused. The two shapes give
     * different cash flows, so a wrong schedule is worse than none.
     */
    public function test_a_moratorium_without_a_type_is_refused_by_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/MORATORIUM_TYPE_MISSING/');

        $this->generator->generate([
            'principal'         => 108_431_000,
            'annual_rate'       => 0.10,
            'payments_per_year' => 12,
            'n_payments'        => 48,
            'start_date'        => '2024-02-24',
            'moratorium_months' => 6,
            'day_count'         => 'ACT/365',
        ]);
    }

    /** A third shape is refused rather than folded into one of the two. */
    public function test_a_third_moratorium_shape_is_refused_by_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/MORATORIUM_TYPE_NOT_RECOGNISED/');

        $this->generator->generate([
            'principal'         => 108_431_000,
            'annual_rate'       => 0.10,
            'payments_per_year' => 12,
            'n_payments'        => 48,
            'start_date'        => '2024-02-24',
            'moratorium_months' => 6,
            'moratorium_type'   => 'Capital only (interest deferred)',
            'day_count'         => 'ACT/365',
        ]);
    }

    /**
     * Lake Malawi Aquaculture: approved MWK 1,055,473,655, drawn MWK
     * 297,161,905 at 34.75 percent. Charging interest on the sanctioned amount
     * gives 30,564,757.93 a month; charging it on the balance gives
     * 8,605,313.50. The two bases are MWK 21,959,444.43 apart, so the flag is
     * not cosmetic (open choice O6).
     */
    public function test_sanction_and_balance_bases_differ_by_about_twenty_two_million_a_month(): void
    {
        $terms = [
            'principal'         => 297_161_905,
            'approved_amount'   => 1_055_473_655,
            'annual_rate'       => 0.3475,
            'payments_per_year' => 12,
            'n_payments'        => 36,
            'start_date'        => '2025-06-30',
            'day_count'         => 'ACT/365',
        ];

        $sanctionWise = $this->generator->generate($terms + [
            'interest_calc_base' => 'S',
            'installment_based_on' => 'Sanction Amount',
        ]);
        $balanceWise = $this->generator->generate($terms + [
            'interest_calc_base' => 'B',
            'installment_based_on' => 'Disbursement Amount',
        ]);

        $this->assertSame(ScheduleGeneratorService::INTEREST_ON_SANCTION, $sanctionWise['interest_basis']);
        $this->assertSame(ScheduleGeneratorService::INSTALMENT_ON_SANCTION, $sanctionWise['instalment_basis']);
        $this->assertSame(ScheduleGeneratorService::INTEREST_ON_BALANCE, $balanceWise['interest_basis']);
        $this->assertSame(ScheduleGeneratorService::INSTALMENT_ON_DISBURSEMENT, $balanceWise['instalment_basis']);

        $this->assertEqualsWithDelta(30_564_757.93, $sanctionWise['rows'][0]['interest_due'], 0.01);
        $this->assertEqualsWithDelta(8_605_313.50, $balanceWise['rows'][0]['interest_due'], 0.01);
        $this->assertEqualsWithDelta(
            21_959_444.43,
            $sanctionWise['rows'][0]['interest_due'] - $balanceWise['rows'][0]['interest_due'],
            0.01
        );
        $this->assertEqualsWithDelta(22_000_000, $sanctionWise['rows'][0]['interest_due'] - $balanceWise['rows'][0]['interest_due'], 100_000);

        // Sanction-wise, the instalment retires the approved amount.
        $this->assertEqualsWithDelta(1_055_473_655,
            array_sum(array_column($sanctionWise['rows'], 'principal_due')), 0.05);
        $this->assertEqualsWithDelta(297_161_905,
            array_sum(array_column($balanceWise['rows'], 'principal_due')), 0.05);
    }

    /**
     * The same facility with no basis stated on the contract falls back to its
     * scheme; with neither, it is refused. A facility drawn in full needs no
     * decision, because the two bases are the same amount.
     */
    public function test_the_basis_falls_back_to_the_scheme_and_is_otherwise_refused(): void
    {
        $terms = [
            'principal'         => 297_161_905,
            'approved_amount'   => 1_055_473_655,
            'annual_rate'       => 0.3475,
            'payments_per_year' => 12,
            'n_payments'        => 36,
            'start_date'        => '2025-06-30',
            'day_count'         => 'ACT/365',
        ];

        $fromScheme = $this->generator->generate($terms + [
            'scheme' => ['interest_calc_base' => 'B', 'installment_based_on' => 'disbursement'],
        ]);
        $this->assertSame('SCHEME', $fromScheme['basis_sources']['interest_basis']);
        $this->assertSame('SCHEME', $fromScheme['basis_sources']['instalment_basis']);
        $this->assertEqualsWithDelta(8_605_313.50, $fromScheme['rows'][0]['interest_due'], 0.01);

        try {
            $this->generator->generate($terms);
            $this->fail('A partly drawn facility with no interest basis should be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('INTEREST_BASIS_MISSING', $e->getMessage());
        }

        // Drawn in full: no flag is needed because the bases agree.
        $drawnInFull = $this->generator->generate(array_merge($terms, ['approved_amount' => 297_161_905]));
        $this->assertSame('DRAWN_IN_FULL_BASES_AGREE', $drawnInFull['basis_sources']['interest_basis']);
    }

    /**
     * EMI Calc Type P: the principal share is fixed and the interest share
     * falls. Type E keeps one level total for the whole term.
     */
    public function test_equal_principal_holds_the_principal_and_lets_the_interest_fall(): void
    {
        $terms = [
            'principal'         => 60_000_000,
            'annual_rate'       => 0.24,
            'payments_per_year' => 12,
            'n_payments'        => 12,
            'start_date'        => '2025-01-31',
            'day_count'         => 'ACT/365',
        ];

        $equalPrincipal = $this->generator->generate($terms + ['emi_calc_type' => 'P']);
        $this->assertSame('EQUAL_PRINCIPAL', $equalPrincipal['instalment_shape']);
        $this->assertEqualsWithDelta(5_000_000, $equalPrincipal['instalment'], 0.01);

        $principals = array_column($equalPrincipal['rows'], 'principal_due');
        $interests = array_column($equalPrincipal['rows'], 'interest_due');
        foreach ($principals as $i => $principalDue) {
            $this->assertEqualsWithDelta(5_000_000, $principalDue, 0.01, "row {$i}: the principal share is fixed");
        }
        for ($i = 1; $i < count($interests); $i++) {
            $this->assertLessThan($interests[$i - 1], $interests[$i], "row {$i}: the interest share must fall");
        }
        $this->assertEqualsWithDelta(1_200_000, $interests[0], 0.01);
        $this->assertEqualsWithDelta(1_100_000, $interests[1], 0.01);

        $levelInstalment = $this->generator->generate($terms + ['emi_calc_type' => 'E']);
        $this->assertSame('LEVEL_INSTALMENT', $levelInstalment['instalment_shape']);
        $totals = [];
        foreach (array_slice($levelInstalment['rows'], 0, 11) as $row) {
            $totals[] = round($row['principal_due'] + $row['interest_due'], 2);
        }
        $this->assertSame([$levelInstalment['instalment']], array_values(array_unique($totals)),
            'a level-instalment schedule pays the same total every period');
    }

    /** EMI Calc Type F has no worked example, so it is refused by name. */
    public function test_emi_calc_type_f_is_refused_by_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/EMI_CALC_TYPE_F_NOT_BUILT/');

        $this->generator->generate([
            'principal'         => 10_000_000,
            'annual_rate'       => 0.30,
            'payments_per_year' => 12,
            'n_payments'        => 12,
            'start_date'        => '2025-01-01',
            'emi_calc_type'     => 'F',
            'day_count'         => 'ACT/365',
        ]);
    }

    /**
     * An irregular first period is charged, and discounted, on its actual
     * days. Drawn 20 January with the first instalment on 5 February, the
     * first period is 16 days: 50,000,000 x 30 percent x 16/365 =
     * 657,534.25, not the 1,250,000 a whole month would carry. Treating that
     * short period as a whole one understates the solved EIR by more than
     * three percentage points (branch analysis section 10 item 5).
     */
    public function test_an_irregular_first_period_is_charged_and_discounted_on_actual_days(): void
    {
        $result = $this->generator->generate([
            'principal'         => 50_000_000,
            'annual_rate'       => 0.30,
            'payments_per_year' => 12,
            'n_payments'        => 12,
            'start_date'        => '2025-01-20',
            'first_due_date'    => '2025-02-05',
            'day_count'         => 'ACT/365',
        ]);

        $this->assertSame(16, $result['rows'][0]['days']);
        $this->assertSame('INSTALMENT_IRREGULAR_PERIOD', $result['rows'][0]['phase']);
        $this->assertEqualsWithDelta(657_534.25, $result['rows'][0]['interest_due'], 0.01);
        $this->assertNotEqualsWithDelta(1_250_000, $result['rows'][0]['interest_due'], 1.0);

        // The following periods are whole months again.
        $this->assertSame('2025-03-05', $result['rows'][1]['due_date']);
        $this->assertSame(28, $result['rows'][1]['days']);
        $this->assertSame('INSTALMENT', $result['rows'][1]['phase']);

        $solver = new CalculateEirService();
        $flows = $this->generator->cashFlowsForSolver($result);
        $dated = $solver->calculateDated(50_000_000, $flows, 12, '2025-01-20', 'ACT/365');

        $ordinal = [];
        foreach ($flows as $index => $flow) {
            $ordinal[] = ['period' => $index + 1, 'amount' => $flow['amount']];
        }
        $whole = $solver->calculate(50_000_000, $ordinal, 12);

        $this->assertEqualsWithDelta(0.348387, $dated['eir_effective_annual'], 0.000005);
        $this->assertEqualsWithDelta(0.314716, $whole['eir_effective_annual'], 0.000005);
        $this->assertGreaterThan(0.03, $dated['eir_effective_annual'] - $whole['eir_effective_annual'],
            'discounting the short first period as a whole period understates the EIR');
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
            'day_count'         => 'ACT/365',
        ]);

        $balance = $principal;
        foreach ($result['rows'] as $i => $row) {
            $expectedInterest = round($balance * $rate / 12, 2);
            $this->assertEqualsWithDelta($expectedInterest, $row['interest_due'], 0.01,
                "row {$i}: interest must accrue on the opening balance");
            $this->assertEqualsWithDelta($balance, $row['opening_balance'], 0.01);
            $balance = round($balance - $row['principal_due'], 2);
            $this->assertEqualsWithDelta($balance, $row['closing_balance'], 0.01);
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
            'day_count'         => 'ACT/365',
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
            'day_count'         => 'ACT/365',
        ]);

        // Jan 31 + 1 month must clamp to Feb 28, not skid to Mar 3.
        $this->assertSame('2025-02-28', $result['rows'][0]['due_date']);
    }

    /**
     * The grace period is E-Banker's own separate field and is carried
     * through untouched: it never becomes moratorium months.
     */
    public function test_the_grace_period_is_kept_apart_from_the_moratorium(): void
    {
        $result = $this->generator->generate([
            'principal'           => 10_000_000,
            'annual_rate'         => 0.12,
            'payments_per_year'   => 12,
            'n_payments'          => 12,
            'start_date'          => '2025-01-31',
            'grace_period_months' => 3,
            'day_count'           => 'ACT/365',
        ]);

        $this->assertSame(3, $result['grace_period_months']);
        $this->assertSame(0, $result['moratorium_months']);
        $this->assertNull($result['moratorium_type']);
        $this->assertNull($result['moratorium_end']);
        // The grace period has not delayed the first instalment.
        $this->assertSame('2025-02-28', $result['rows'][0]['due_date']);
        $this->assertCount(12, $result['rows']);
    }

    /** With no day count to hand the generator stops rather than picking one. */
    public function test_a_missing_day_count_stops_the_generator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/DAY_COUNT_UNAVAILABLE/');

        $this->generator->generate([
            'principal'         => 10_000_000,
            'annual_rate'       => 0.12,
            'payments_per_year' => 12,
            'n_payments'        => 12,
            'start_date'        => '2025-01-31',
        ]);
    }

    public function test_rejects_invalid_terms(): void
    {
        $base = [
            'principal'         => 1_000_000,
            'annual_rate'       => 0.10,
            'payments_per_year' => 12,
            'n_payments'        => 12,
            'start_date'        => '2025-01-01',
            'day_count'         => 'ACT/365',
        ];

        foreach ([
            ['principal' => 0],
            ['annual_rate' => -0.01],
            ['payments_per_year' => 5],
            ['n_payments' => 0],
            ['moratorium_months' => -1],
            ['day_count' => 'ACT/364'],
            ['first_due_date' => '2024-12-01'],
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
