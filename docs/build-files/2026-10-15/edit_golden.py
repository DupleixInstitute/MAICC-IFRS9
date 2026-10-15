import io
B = chr(92)
CRLF = chr(13) + chr(10)
LF = chr(10)
p = r'c:\xampp\htdocs\MAICC-IFRS9\tests\Feature\Ecl\EclGoldenNumberTest.php'
s = io.open(p, encoding='utf-8', newline='').read()
crlf = CRLF in s
s = s.replace(CRLF, LF)


def rep(old, new):
    global s
    old = old.replace('~', B)
    new = new.replace('~', B)
    assert s.count(old) == 1, (old[:80], s.count(old))
    s = s.replace(old, new)


rep("""        app(ExpectedCreditLossController::class)->calculateECL(
            $request,
            app(~App~Services~Ecl~EclDiscountingService::class)
        );""", """        app(ExpectedCreditLossController::class)->calculateECL($request);""")

start = s.index("    /** @test */\n    public function discounted_mode_keeps_undiscounted_ecl_and_names_unresolved_contracts(): void")
end = s.index("\n}", start)
s = s[:start] + """    /**
     * Discounted ECL is the time-phased engine's or nothing (14 October 2026).
     * This schema has no ecl_projection_runs, so the run used to fall back to a
     * single-horizon proxy (EclDiscountingService, CALCULATED_HORIZON_PROXY:
     * 10,000 / 1.1 = 9,090.91 on LOCKED-1); it is now refused with the reason
     * and nothing is written. WrittenOffNilEclTest covers the engine itself.
     *
     * @test
     */
    public function discounted_mode_without_the_time_phased_engine_is_refused_never_proxied(): void
    {
        DB::table('contract_eir')->insert([
            'contract_id' => 'LOCKED-1', 'eir_effective_annual' => 0.10,
            'rate_type' => 'FIXED', 'locked_at' => '2025-01-01',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('loan_books')->insert([
            ['contract_id' => 'LOCKED-1', 'reporting_period' => '2025-11', 'loan_portfolio_id' => 1,
             'ifrs9stage_pre_qualitative' => '1', 'remaining_tenor' => 12, // months (audit C2)
             'pd_prefli' => 0.10, 'collection_lgd' => 0.50, 'carrying_amount' => 200000,
             'commitments' => 0, 'facility_utilisation_rate' => 1],
        ]);

        try {
            $this->runEcl('pd_prefli', 'collection_lgd', 'discounted');
            $this->fail('A discounted run without the time-phased engine was not refused.');
        } catch (~App~Services~Ecl~EclRunRefused $e) {
            $this->assertStringContainsString('No proxy is applied', $e->getMessage());
        }

        $locked = DB::table('loan_books')->where('contract_id', 'LOCKED-1')->first();
        $this->assertNull($locked->ecl_value);
        $this->assertNull($locked->ecl_value_discounted);
        $this->assertSame('NOT_CALCULATED', $locked->ecl_discount_status);
        $this->assertSame(0, DB::table('expected_credit_loss')->count());
    }""".replace('~', B) + s[end:]
io.open(p, 'w', encoding='utf-8', newline='').write(s.replace(LF, CRLF) if crlf else s)
print('ok')
