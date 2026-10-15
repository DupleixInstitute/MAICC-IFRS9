import os
os.chdir('C:/xampp/htdocs/MAICC-IFRS9')
p = 'tests/Feature/StressTestingReconciliationTest.php'
s = open(p, encoding='utf-8').read()
reps = [
("""    /**
     * Three loans, one per stage. EAD = carrying_amount (no commitments).
     *   Stage 1: EAD 1,000,000  PD 0.05  LGD 0.40
     *   Stage 2: EAD   500,000  PD 0.20  LGD 0.50
     *   Stage 3: EAD   200,000  PD 0.90  LGD 0.60
     */""",
"""    /**
     * Three loans, one per stage. EAD = carrying_amount (no commitments).
     *   Stage 1: EAD 1,000,000  PD 0.05  LGD 0.40
     *   Stage 2: EAD   500,000  PD 0.20  LGD 0.50
     *   Stage 3: EAD   200,000  PD 0.90  LGD 0.60
     * Each carries its ECL (EAD x stage PD x LGD, undiscounted) and the booked
     * allowance discounted at the EIR, 95 percent of it: the stress base is the
     * booked allowance (documentation audit of 14 October 2026).
     */"""),
("""        foreach ($rows as [$stage, $ead, $pd, $lgd]) {
            DB::table('loan_books')->insert([""",
"""        foreach ($rows as [$stage, $ead, $pd, $lgd]) {
            $ecl = $ead * ($stage === 3 ? 1.0 : $pd) * $lgd;
            DB::table('loan_books')->insert([
                'ecl_value' => $ecl,
                'ecl_value_discounted' => round($ecl * self::BOOKED, 2),
                'ecl_discount_status' => 'CALCULATED_TIME_PHASED',"""),
("""    private const PERIOD = '2099-01';""",
"""    private const PERIOD = '2099-01';

    /** The booked (discounted) allowance as a share of the undiscounted ECL on every seeded loan. */
    private const BOOKED = 0.95;"""),
("""        $this->assertEqualsWithDelta($base, $response->json('total_base_ecl'), 0.01);
        $this->assertEqualsWithDelta($stress, $response->json('total_stress_ecl'), 0.01);
        $this->assertEqualsWithDelta($stress - $base, $response->json('delta'), 0.01);""",
"""        // the base is the booked allowance, and the stress scales it: both on the booked basis
        $this->assertEqualsWithDelta($base * self::BOOKED, $response->json('total_base_ecl'), 0.05);
        $this->assertEqualsWithDelta($stress * self::BOOKED, $response->json('total_stress_ecl'), 0.05);
        $this->assertEqualsWithDelta(($stress - $base) * self::BOOKED, $response->json('delta'), 0.05);
        // the undiscounted measure beside it
        $this->assertEqualsWithDelta($base, $response->json('basis.undiscounted.base_ecl'), 0.05);
        $this->assertEqualsWithDelta($stress, $response->json('basis.undiscounted.stress_ecl'), 0.05);
        $this->assertSame('booked', $response->json('basis.base'));"""),
("""        $this->assertEqualsWithDelta(1700000.0, $response->json('total_stress_ecl'), 0.01);""",
"""        $this->assertEqualsWithDelta(1700000.0 * self::BOOKED, $response->json('total_stress_ecl'), 0.05);"""),
]
for a, b in reps:
    assert a in s, a[:70]
    s = s.replace(a, b, 1)
idx = s.rstrip().rindex('}')
s = s[:idx] + """
    /** A loan with no ECL calculated has no allowance to stress: counted with its exposure, never estimated. */
    public function test_a_loan_without_an_ecl_is_not_measured_but_counted()
    {
        $this->seedBook();
        DB::table('loan_books')->where('contract_id', 'STRESS-1')->update(['ecl_value' => null, 'ecl_value_discounted' => null, 'ecl_discount_status' => null]);

        $response = $this->actingAs($this->admin())->postJson(route('stress-testing.run'), ['period' => self::PERIOD, 's1_pd_mult' => 2, 's2_pd_mult' => 2, 's3_pd_mult' => 1]);

        $response->assertOk();
        $this->assertSame(1, $response->json('basis.not_measured.loans'));
        $this->assertEqualsWithDelta(1000000, $response->json('basis.not_measured.exposure'), 0.01);
        $this->assertEqualsWithDelta((50000 + 120000) * self::BOOKED, $response->json('total_base_ecl'), 0.05);
    }
}
"""
open(p, 'w', encoding='utf-8', newline='').write(s)
print('done')
