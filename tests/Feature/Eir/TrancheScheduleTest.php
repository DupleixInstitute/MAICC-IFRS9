<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\CalculateEirService;
use App\Services\Eir\ScheduleGeneratorService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * A facility drawn in tranches (results review of 13 October 2026, E1).
 *
 * Mphunzitsi SACCO was signed on 15 July 2025 for 1,047.7 million and drawn
 * 397.7 million on 8 August, 350 million on 28 November and 300 million on
 * 2 February 2026. Its schedule was generated on the whole facility from the
 * signing date, so it charged interest on money not yet lent and the EIR
 * solved on it was 41.3 percent against a contractual 29.0 with no fee at
 * all. On the drawdown profile the schedule retires exactly what was lent,
 * and its EIR with no fee is the contractual rate.
 */
class TrancheScheduleTest extends TestCase
{
    private const RATE = 0.29;

    private function terms(array $over = []): array
    {
        return $over + [
            'principal' => 1047653000.0,
            'approved_amount' => 1047653000.0,
            'annual_rate' => self::RATE,
            'payments_per_year' => 12,
            'n_payments' => 24,
            'start_date' => '2025-07-15',
            'interest_start_date' => '2025-07-15',
            'first_due_date' => '2025-08-15',
            'day_count' => 'ACT/365',
            'drawdowns' => [
                ['date' => '2025-08-08', 'amount' => 397653000.0],
                ['date' => '2025-11-28', 'amount' => 350000000.0],
                ['date' => '2026-02-02', 'amount' => 300000000.0],
            ],
            'interest_calc_base_observed' => ['basis' => 'B', 'evidence' => ['posted' => 1.0]],
        ];
    }

    /** The EIR vector as EirContractInputService builds it: the first tranche on its own date, the later ones as outflows on theirs. */
    private function eir(array $result, float $fees = 0.0): float
    {
        $tranches = $result['drawdowns'];
        $first = array_shift($tranches);
        $flows = (new ScheduleGeneratorService())->cashFlowsForSolver($result);
        foreach ($tranches as $t) {
            $flows[] = ['due_date' => $t['date'], 'amount' => -$t['amount'], 'flow_type' => CalculateEirService::FLOW_DISBURSEMENT];
        }
        usort($flows, fn ($a, $b) => $a['due_date'] <=> $b['due_date']);
        $solved = (new CalculateEirService())->calculateDated($first['amount'] - $fees, $flows, 12, $first['date'], 'ACT/365');

        return $solved['eir_nominal_annual'];
    }

    public function test_the_schedule_retires_what_was_lent_and_solves_to_the_contractual_rate(): void
    {
        $r = (new ScheduleGeneratorService())->generate($this->terms());

        $this->assertTrue($r['tranched']);
        $this->assertSame('TRANCHES_AS_DRAWN', $r['basis_sources']['drawdown_profile']);
        $this->assertSame(ScheduleGeneratorService::BASIS_OBSERVED, $r['basis_sources']['interest_basis']);
        $this->assertSame('RESIZED_AT_EACH_DRAWDOWN', $r['basis_sources']['instalment_basis']);
        $this->assertEqualsWithDelta(1047653000.0, $r['totals']['principal'], 0.01);
        $this->assertEqualsWithDelta(1047653000.0, $r['retires'], 0.01);
        $this->assertEquals(0.0, end($r['rows'])['closing_balance']);
        $this->assertTrue(collect($r['rows'])->every(fn ($row) => $row['principal_due'] >= 0 && $row['interest_due'] >= 0));

        // the first instalment's interest runs only from the first drawdown: 7 days on 397.7 million
        $this->assertSame('2025-08-15', $r['rows'][0]['due_date']);
        $this->assertEqualsWithDelta(397653000 * self::RATE * 7 / 365, $r['rows'][0]['interest_due'], 0.01);
        // the period with the second tranche is charged part on each balance
        $december = collect($r['rows'])->firstWhere('due_date', '2025-12-15');
        $this->assertSame('INSTALMENT_TRANCHE_PERIOD', $december['phase']);

        // no fee: the EIR is the contractual rate (it read 41.3 percent on the old schedule)
        $this->assertEqualsWithDelta(self::RATE, $this->eir($r), 0.003);
        // with the 47.7 million of fees in the drawdown batches the fee effect on a two-year loan is several points
        $withFees = $this->eir($r, 47653000.0);
        $this->assertGreaterThan(self::RATE + 0.03, $withFees);
        $this->assertLessThan(self::RATE + 0.09, $withFees);
    }

    public function test_the_old_schedule_on_the_whole_facility_from_signing_inflates_the_eir(): void
    {
        $old = (new ScheduleGeneratorService())->generate(array_diff_key($this->terms(), ['drawdowns' => 1, 'interest_calc_base_observed' => 1]));
        $old['drawdowns'] = $this->terms()['drawdowns'];
        $this->assertFalse($old['tranched']);
        $this->assertGreaterThan(0.38, $this->eir($old));
    }

    public function test_a_tranche_window_with_no_basis_stated_or_observed_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('INTEREST_BASIS_MISSING: the facility was drawn in tranches');
        (new ScheduleGeneratorService())->generate(array_diff_key($this->terms(), ['interest_calc_base_observed' => 1]));
    }

    public function test_one_drawdown_after_the_start_needs_no_basis_and_earns_interest_from_its_date(): void
    {
        $r = (new ScheduleGeneratorService())->generate($this->terms([
            'principal' => 150000000.0, 'approved_amount' => 150000000.0, 'start_date' => '2025-04-17', 'interest_start_date' => '2025-04-17',
            'first_due_date' => '2025-05-17', 'n_payments' => 24, 'drawdowns' => [['date' => '2025-06-18', 'amount' => 150000000.0]],
            'interest_calc_base_observed' => null,
        ]));
        $this->assertSame('DRAWN_IN_FULL_BASES_AGREE', $r['basis_sources']['interest_basis']);
        // two due dates fall before anything is drawn and carry nothing
        $this->assertSame(2, $r['due_dates_before_first_drawdown']);
        $this->assertSame('2025-07-17', $r['rows'][0]['due_date']);
        $this->assertEqualsWithDelta(150000000 * self::RATE * 29 / 365, $r['rows'][0]['interest_due'], 0.01);
        $this->assertEqualsWithDelta(150000000.0, $r['totals']['principal'], 0.01);
        $this->assertTrue(collect($r['rows'])->every(fn ($row) => $row['principal_due'] >= 0));
        $this->assertEqualsWithDelta(self::RATE, $this->eir($r), 0.003);
    }

    /** A semi-annual facility drawn inside its first period still ends at nil, with no negative row (Chikomanyama Farms). */
    public function test_a_long_first_period_with_a_tranche_ends_exactly_at_nil(): void
    {
        $r = (new ScheduleGeneratorService())->generate($this->terms([
            'principal' => 150000000.0, 'approved_amount' => 150000000.0, 'payments_per_year' => 2, 'n_payments' => 10,
            'start_date' => '2025-04-17', 'interest_start_date' => '2025-04-17', 'first_due_date' => '2025-10-17',
            'period_rate_basis' => 'Monthly compounded (E-Banker)',
            'drawdowns' => [['date' => '2025-06-18', 'amount' => 150000000.0]], 'interest_calc_base_observed' => null,
        ]));
        $this->assertCount(10, $r['rows']);
        $this->assertTrue(collect($r['rows'])->every(fn ($row) => $row['principal_due'] > 0 && $row['interest_due'] > 0));
        $level = array_unique(array_map(fn ($row) => round($row['principal_due'] + $row['interest_due'], 0), array_slice($r['rows'], 0, 9)));
        $this->assertCount(1, $level, 'the instalment is level once sized at the drawdown');
        $this->assertEquals(0.0, end($r['rows'])['closing_balance']);
    }

    public function test_tranches_that_do_not_add_up_to_the_amount_drawn_are_refused(): void
    {
        $this->expectExceptionMessage('DRAWDOWNS_DO_NOT_ADD_UP');
        (new ScheduleGeneratorService())->generate($this->terms(['principal' => 1000000000.0, 'approved_amount' => 1000000000.0]));
    }

    /**
     * Interest on the whole sanction while only part is drawn, with the
     * instalment sized on what is drawn, never repays principal: the schedule
     * is refused with the reason, as it is for any partly drawn facility.
     */
    public function test_an_observed_sanction_basis_with_tranche_sized_instalments_is_refused(): void
    {
        $this->expectExceptionMessage('is charged on the approved amount');
        (new ScheduleGeneratorService())->generate($this->terms(['interest_calc_base_observed' => ['basis' => 'S', 'evidence' => []]]));
    }

    public function test_a_stated_sanction_instalment_basis_with_tranches_is_not_built(): void
    {
        $this->expectExceptionMessage('INSTALMENT_ON_SANCTION_WITH_TRANCHES');
        (new ScheduleGeneratorService())->generate($this->terms(['installment_based_on' => 'Sanction']));
    }

    public function test_without_a_drawdown_profile_the_schedule_is_unchanged(): void
    {
        $terms = array_diff_key($this->terms(), ['drawdowns' => 1, 'interest_calc_base_observed' => 1]);
        $r = (new ScheduleGeneratorService())->generate($terms);
        $this->assertFalse($r['tranched']);
        $this->assertSame('NOT_GIVEN', $r['basis_sources']['drawdown_profile']);
        $this->assertEqualsWithDelta(1047653000 * self::RATE / 12, $r['rows'][0]['interest_due'], 0.01);
        $this->assertEqualsWithDelta($r['amortising_opening_balance'], $r['retires'], 0.001);
    }
}
