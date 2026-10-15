p = r'C:\xampp\htdocs\MAICC-IFRS9\tests\Feature\Eir\ResetDetectionServiceTest.php'
s = open(p, encoding='utf-8').read()
reps = [
    ("""        $this->createResetSchema();
        $this->seedJatPrimeRates();
        $this->loan(self::JAT, ['interest_policy' => 'P', 'portfolio' => 'MAIIC', 'product_type' => 'MAIIC Industrial Loans']);
    }""",
     """        $this->createResetSchema();
        $this->seedJatPrimeRates();
        $this->loan(self::JAT, ['rate_type' => 'FLOATING', 'interest_policy' => 'P', 'portfolio' => 'MAIIC', 'product_type' => 'MAIIC Industrial Loans']);
        // each change on its own Reserve Bank date, as JAT's EMI chart regenerates; the default
        // (the whole month at the new rate) has its own test below
        $this->setting('plr_mid_period', 'Pro rata from the effective date');
    }"""),
    ("""        $this->assertSame('INTEREST_POLICY', $jat['rate_behaviour_source']);""",
     """        $this->assertSame('RATE_TYPE', $jat['rate_behaviour_source'], 'the test the EIR in force applies (D38)');"""),
    ("""    public function test_two_changes_in_one_month_are_one_reset_when_applied_at_month_end(): void
    {
        $this->setting('plr_mid_period', 'From the next month-end');

        $resets = collect($this->detect()['details'][self::JAT]['resets'])->keyBy('reset_date');

        $this->assertCount(10, $resets, 'the 5 and 9 March 2026 changes land on 31 March together');
        $march = $resets['2026-03-31'];
        $this->assertSame(24.7, $march['old_reference']);
        $this->assertSame(22.4, $march['new_reference']);
        $this->assertSame('2026-03-09', $march['plr_effective_date']);
    }""",
     """    public function test_on_the_default_the_whole_month_is_at_the_new_rate_and_two_changes_in_it_are_one_reset(): void
    {
        $this->setting('plr_mid_period', 'Whole month at the month-end rate (E-Banker)');

        $resets = collect($this->detect()['details'][self::JAT]['resets'])->keyBy('reset_date');

        $this->assertCount(10, $resets, 'the 5 and 9 March 2026 changes are one reset from 1 March (spec v4 section 3.1)');
        $march = $resets['2026-03-01'];
        $this->assertSame(24.7, $march['old_reference']);
        $this->assertSame(22.4, $march['new_reference']);
        $this->assertSame('2026-03-09', $march['plr_effective_date']);
        $this->assertArrayHasKey('2026-02-01', $resets->all(), 'the 4 February change charges all of February');

        // the rows are the register's too: one PLR_RESET each, awaiting a person's classification
        $row = RateResetEvent::whereDate('reset_date', '2026-03-01')->sole();
        $this->assertSame(['PLR_RESET', '2026-03', 'IN_CONTRACT', 'DETECTED', 'DETECTED', 'PLR|' . self::JAT . '|2026-03-01'],
            [$row->event_type, $row->reporting_period, $row->suggested_classification, $row->status, $row->classification_status, $row->detection_key]);
    }

    public function test_a_loan_with_no_rate_type_or_an_unknown_timing_rule_is_held_not_guessed(): void
    {
        $this->loan('104420000001', ['rate_type' => null, 'interest_policy' => 'P', 'portfolio' => 'MAIIC', 'product_type' => 'MAIIC Industrial Loans']);
        $held = $this->detect('104420000001')['details']['104420000001'];
        $this->assertSame(ResetDetectionService::STATUS_BLOCKED, $held['status']);
        $this->assertStringStartsWith('NO_RATE_TYPE', $held['reason'], 'never the Interest Policy or the product family in its place');

        $this->setting('plr_mid_period', 'Some other rule');
        $jat = $this->detect()['details'][self::JAT];
        $this->assertSame(ResetDetectionService::STATUS_BLOCKED, $jat['status']);
        $this->assertStringStartsWith('UNKNOWN_PLR_TIMING', $jat['reason']);
        $this->assertSame(0, RateResetEvent::count());
    }

    public function test_a_classified_reset_is_kept_and_a_register_held_move_is_taken_over(): void
    {
        $this->detect();
        // a person proposed the 1 Oct 2025 reset; the series is then corrected
        RateResetEvent::whereDate('reset_date', '2025-10-01')->update(['classification_status' => 'PROPOSED', 'classification' => 'IN_CONTRACT']);
        // the register recorded the 4 Feb 2026 move while the pipeline could not date it
        $feb = RateResetEvent::whereDate('reset_date', '2026-02-04')->sole();
        $feb->forceFill(['status' => 'BLOCKED', 'note' => 'Held from the reset pipeline', 'detection_key' => 'MOVE|' . self::JAT . '|2026-02'])->save();
        DB::table('reference_rate_series')->where('effective_date', '2025-10-01')->update(['rate' => 25.5]);

        $jat = $this->detect()['details'][self::JAT];

        $oct = RateResetEvent::whereDate('reset_date', '2025-10-01')->sole();
        $this->assertEqualsWithDelta(25.4, $oct->new_reference_rate, 1e-9, 'a classified reset is not rewritten');
        $this->assertNotEmpty(array_filter($jat['kept'], fn ($n) => str_contains($n, 'classified PROPOSED')));
        $feb = RateResetEvent::whereDate('reset_date', '2026-02-04')->sole();
        $this->assertSame(['DETECTED', 'PLR|' . self::JAT . '|2026-02-04', null], [$feb->status, $feb->detection_key, $feb->note], 'one row per reset');
        $this->assertSame(11, RateResetEvent::count());
    }"""),
    ("""        $this->loan('104460000098', ['portfolio' => 'FInES', 'product_type' => 'FInES Industrial Loans', 'contractual_rate' => 0.10]);""",
     """        $this->loan('104460000098', ['rate_type' => 'FIXED', 'portfolio' => 'FInES', 'product_type' => 'FInES Industrial Loans', 'contractual_rate' => 0.10]);"""),
    ("""        $this->assertSame('PRODUCT_FAMILY', $fixed['rate_behaviour_source'], 'no Interest Policy supplied, so the product family decides');""",
     """        $this->assertSame('RATE_TYPE', $fixed['rate_behaviour_source'], 'the contract master states it fixed');"""),
]
for old, new in reps:
    if old not in s:
        raise SystemExit('missing: ' + old[:80])
    s = s.replace(old, new, 1)
s = s.replace(""" * Policy P, first disbursement 23 Jul 2025 at 33.30 percent with the prime rate""", """ * Policy P (rate type FLOATING), first disbursement 23 Jul 2025 at 33.30 percent with the prime rate""", 1)
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
