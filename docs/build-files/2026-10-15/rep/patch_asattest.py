import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()


def rep(old, new):
    global s
    assert s.count(old) == 1, (old[:90], s.count(old))
    s = s.replace(old, new)


rep("""    /** Finding M13 (a): the part month is simple interest at the contractual rate on ACT/365, not the EIR compounded. */
    public function test_a_date_inside_a_month_accrues_the_part_month_at_the_contractual_rate_on_actual_days_less_cash_received(): void
    {
        $v = $this->service()->contract('104420000005', '2026-01-15');
        $this->assertFalse($v['is_month_end']);
        $expectedInterest = round(943500 * 0.26 * 15 / 365, 2);
        $this->assertEquals($expectedInterest, $v['interest']['eir_period_to_date']);
        $this->assertEquals(round(943500 + $expectedInterest - 30000, 2), $v['amortised_cost']);
        $this->assertStringContainsString('contractual rate 26.0000% simple on 15/365 days (section 3)', $v['amortised_cost_basis']);
        $this->assertEquals(911500, $v['gross_carrying_amount']);            // 941,500 - 30,000 received on 10 Jan
        $this->assertEquals(0, $v['interest']['contractual_period_to_date']);  // the January charge is posted on the 31st
        $this->assertEquals($expectedInterest, $v['interest']['eir_year_to_date']);""", """    /**
     * Review item R12 (built 14 October 2026): inside a month the EIR interest is the monthly EIR on the prior
     * month-end amortised cost for the days elapsed (so the month-end equals the revenue run's month), and the
     * contractual accrual (finding M13: the contractual rate, simple, ACT/365, on the prior month-end ledger
     * balance) is its own figure. The test had asserted the defect: the contractual accrual returned as the EIR interest.
     */
    public function test_a_date_inside_a_month_accrues_the_eir_interest_and_the_contractual_accrual_apart(): void
    {
        $v = $this->service()->contract('104420000005', '2026-01-15');
        $this->assertFalse($v['is_month_end']);
        $monthly = 1.30 ** (1 / 12) - 1;
        $expectedInterest = round(943500 * $monthly * 15 / 31, 2);
        $this->assertEquals($expectedInterest, $v['interest']['eir_period_to_date']);
        $this->assertEquals(round(943500 + $expectedInterest - 30000, 2), $v['amortised_cost']);
        $this->assertStringContainsString('at the monthly EIR', $v['amortised_cost_basis']);
        $this->assertStringContainsString('for 15 of 31 days', $v['amortised_cost_basis']);
        // the contractual accrual, at 26 percent simple on the 941,500 the ledger held at 31 December, for 15/365 days
        $accrued = round(941500 * 0.26 * 15 / 365, 2);
        $this->assertEquals($accrued, $v['interest']['contractual_accrued_period_to_date']);
        $this->assertStringContainsString('contractual rate 26.0000% simple on 15/365 days (section 3)', $v['interest']['contractual_accrued_basis']);
        $this->assertNotEquals($v['interest']['contractual_accrued_period_to_date'], $v['interest']['eir_period_to_date']);
        $this->assertEquals(911500, $v['gross_carrying_amount']);            // 941,500 - 30,000 received on 10 Jan
        $this->assertEquals(0, $v['interest']['contractual_period_to_date']);  // the January charge is posted on the 31st
        $this->assertEquals($expectedInterest, $v['interest']['eir_year_to_date']);
        $this->assertEquals($accrued, $v['interest']['contractual_year_to_date']);   // posted (nil) plus accrued: like with like""")
rep("""        $febInterest = round($janClosing * 0.26 * 10 / 365, 2);  // then the part month at the contractual rate""",
    """        $febInterest = round($janClosing * $monthly * 10 / 28, 2);  // then the part month at the monthly EIR for 10 of 28 days""")
rep("""        $this->assertEquals(20000, $v['interest']['contractual_year_to_date']);""",
    """        // posted (20,000 in January) plus February accrued at the contractual rate on the 931,500 the ledger held at 31 January
        $this->assertEquals(round(20000 + round(931500 * 0.26 * 10 / 365, 2), 2), $v['interest']['contractual_year_to_date']);""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
