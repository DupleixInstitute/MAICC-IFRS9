import os
os.chdir('C:/xampp/htdocs/MAICC-IFRS9')

def sub(path, pairs):
    s = open(path, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (path, a[:80])
        s = s.replace(a, b, 1)
    open(path, 'w', encoding='utf-8', newline='').write(s)

sub('tests/Feature/Eir/ModificationAccountingTest.php', [
("""$t->double('ecl_value')->nullable(); $t->double('interest_rate')->nullable(); });""",
 """$t->double('ecl_value')->nullable(); $t->double('interest_rate')->nullable(); $t->string('ifrs9stage_pre_qualitative')->nullable(); $t->double('ecl_value_discounted')->nullable(); $t->string('ecl_discount_status')->nullable(); $t->string('product_code')->nullable(); });"""),
("""        DB::table('loan_books')->where('reporting_period', '2025-01')->update(['calculated_ifrs9_stage' => 3]);
        $this->confirmed('MODIFICATION', 700 * 1.126825, ['fair_value' => 650]);""",
 """        DB::table('loan_books')->where('reporting_period', '2025-01')->update(['calculated_ifrs9_stage' => 3]);
        // the booked allowance at the December month-end, which the net basis nets (IFRS 9 5.4.1(b))
        DB::table('loan_books')->insert(['contract_id' => 'C-1', 'reporting_period' => '2024-12', 'calculated_ifrs9_stage' => 3, 'ecl_value' => 290, 'ecl_value_discounted' => 300, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED']);
        $this->confirmed('MODIFICATION', 700 * 1.126825, ['fair_value' => 650]);"""),
])

sub('tests/Feature/Eir/EirRevenueServiceTest.php', [
("""$t->integer('ifrs9_stage')->nullable(); $t->double('expected_loss_provision')->default(0); $t->double('ecl_value')->nullable(); });""",
 """$t->integer('ifrs9_stage')->nullable(); $t->integer('ifrs9stage_pre_qualitative')->nullable(); $t->double('expected_loss_provision')->default(0); $t->double('ecl_value')->nullable(); $t->double('ecl_value_discounted')->nullable(); $t->string('ecl_discount_status')->nullable(); $t->string('product_code')->nullable(); });"""),
("""    private function loan(string $id, string $period, int $stage, float $allowance = 0): void
    {
        DB::table('loan_books')->insert(['contract_id' => $id, 'reporting_period' => $period,
            'calculated_ifrs9_stage' => $stage, 'expected_loss_provision' => $allowance]);
    }""",
 """    private function loan(string $id, string $period, int $stage): void
    {
        DB::table('loan_books')->insert(['contract_id' => $id, 'reporting_period' => $period, 'calculated_ifrs9_stage' => $stage]);
    }

    /** A month-end row carrying the booked allowance: the ECL discounted at the EIR (BookedEcl). */
    private function booked(string $id, string $period, float $allowance, int $stage = 3): void
    {
        DB::table('loan_books')->insert(['contract_id' => $id, 'reporting_period' => $period, 'calculated_ifrs9_stage' => $stage,
            'ecl_value' => round($allowance * 0.97, 2), 'ecl_value_discounted' => $allowance, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED']);
    }"""),
("""    /** Audit C6: the net basis nets the allowance the engine wrote on the prior month's row, not a legacy column. */
    public function test_stage_three_nets_the_opening_allowance_the_ecl_engine_wrote(): void
    {
        $this->seedLocked();
        DB::table('loan_books')->insert(['contract_id' => 'C-1', 'reporting_period' => '2025-01', 'calculated_ifrs9_stage' => 3, 'expected_loss_provision' => 0, 'ecl_value' => 600]);
        DB::table('loan_books')->insert(['contract_id' => 'C-1', 'reporting_period' => '2025-02', 'calculated_ifrs9_stage' => 3, 'expected_loss_provision' => 0, 'ecl_value' => 650]);

        (new EirRevenueService())->run('C-1', '2025-02');
        $row = DB::table('eir_amortisation')->where('reporting_period', '2025-02')->first();

        // opening 1,000 (the PV of the remaining schedule), allowance 600 from the January row: interest on 400
        $monthly = pow(1.126825, 1 / 12) - 1;
        $this->assertEqualsWithDelta(600, $row->ecl_allowance, 0.01);
        $this->assertEqualsWithDelta($monthly * ($row->opening_gross - 600), $row->interest_accrued, 0.01);
        $this->assertSame('NET', $row->interest_basis);
    }""",
 """    /**
     * Audit C6 and the documentation audit of 14 October 2026: the net basis nets
     * the booked allowance at the start of the month, the prior month's
     * discounted ECL (BookedEcl), not its undiscounted ecl_value, the current
     * month's figure or the legacy expected_loss_provision column.
     */
    public function test_stage_three_nets_the_booked_allowance_at_the_prior_month_end(): void
    {
        $this->seedLocked();
        DB::table('loan_books')->insert(['contract_id' => 'C-1', 'reporting_period' => '2025-01', 'calculated_ifrs9_stage' => 3, 'expected_loss_provision' => 999,
            'ecl_value' => 600, 'ecl_value_discounted' => 615, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED']);
        DB::table('loan_books')->insert(['contract_id' => 'C-1', 'reporting_period' => '2025-02', 'calculated_ifrs9_stage' => 3, 'expected_loss_provision' => 999,
            'ecl_value' => 650, 'ecl_value_discounted' => 670, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED']);

        (new EirRevenueService())->run('C-1', '2025-02');
        $row = DB::table('eir_amortisation')->where('reporting_period', '2025-02')->first();

        // opening 1,000 (the PV of the remaining schedule), booked allowance 615 at 31 January: interest on 385
        $monthly = pow(1.126825, 1 / 12) - 1;
        $this->assertEqualsWithDelta(615, $row->ecl_allowance, 0.01);
        $this->assertEqualsWithDelta($monthly * ($row->opening_gross - 615), $row->interest_accrued, 0.01);
        $this->assertSame('NET', $row->interest_basis);
    }

    /** Fail closed: no fallback to the current month, the undiscounted ECL or the legacy column. */
    public function test_a_stage_three_month_without_a_booked_opening_allowance_is_refused_with_the_reason(): void
    {
        $this->seedLocked();
        // no row at all for January
        DB::table('loan_books')->insert(['contract_id' => 'C-1', 'reporting_period' => '2025-02', 'calculated_ifrs9_stage' => 3, 'expected_loss_provision' => 500,
            'ecl_value' => 650, 'ecl_value_discounted' => 670, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED']);
        $r = (new EirRevenueService())->run('C-1', '2025-02');
        $this->assertSame('BLOCKED', $r['status']);
        $this->assertStringContainsString('no loan-book row exists for 2025-01', $r['error']);

        // January held, but only undiscounted: not booked
        DB::table('loan_books')->insert(['contract_id' => 'C-1', 'reporting_period' => '2025-01', 'calculated_ifrs9_stage' => 3, 'ecl_value' => 600, 'ecl_discount_status' => 'TIME_PHASED_UNRESOLVED']);
        $r = (new EirRevenueService())->run('C-1', '2025-02');
        $this->assertSame('BLOCKED', $r['status']);
        $this->assertStringContainsString('no discounted (booked) figure', $r['error']);

        // January held with no ECL calculated
        DB::table('loan_books')->where('reporting_period', '2025-01')->update(['ecl_value' => null, 'ecl_discount_status' => null]);
        $r = (new EirRevenueService())->run('C-1', '2025-02');
        $this->assertSame('BLOCKED', $r['status']);
        $this->assertStringContainsString('no ECL was calculated for 2025-01', $r['error']);
        $this->assertSame(0, DB::table('eir_amortisation')->count());
    }

    /** The stage is the one the ECL was provided on: after the qualitative triggers, before the calculated one. */
    public function test_the_stage_is_read_after_the_qualitative_triggers(): void
    {
        $this->seedLocked();
        $this->booked('C-1', '2024-12', 200);
        DB::table('loan_books')->insert(['contract_id' => 'C-1', 'reporting_period' => '2025-01', 'calculated_ifrs9_stage' => 1, 'ifrs9stage_post_qualitative' => 3]);

        (new EirRevenueService())->run('C-1', '2025-01');
        $row = DB::table('eir_amortisation')->first();
        $this->assertSame('NET', $row->interest_basis);
        $this->assertEqualsWithDelta(200, $row->ecl_allowance, 0.01);

        // a performing loan accrues on gross and needs no allowance
        DB::table('loan_books')->insert(['contract_id' => 'C-1', 'reporting_period' => '2025-02', 'calculated_ifrs9_stage' => 3, 'ifrs9stage_post_qualitative' => 2]);
        (new EirRevenueService())->run('C-1', '2025-02');
        $this->assertSame('GROSS', DB::table('eir_amortisation')->where('reporting_period', '2025-02')->value('interest_basis'));
    }"""),
("""        $this->seedLocked();
        $this->loan('C-1', '2025-06', 3);""",
 """        $this->seedLocked();
        $this->booked('C-1', '2025-05', 0);
        $this->loan('C-1', '2025-06', 3);"""),
("""        $this->seedLocked();
        $this->loan('C-1', '2025-01', 3);
        $this->actual('C-1', '2025-01-10', 'Principal+Interest', 100);""",
 """        $this->seedLocked();
        $this->booked('C-1', '2024-12', 0);
        $this->loan('C-1', '2025-01', 3);
        $this->actual('C-1', '2025-01-10', 'Principal+Interest', 100);"""),
("""        $this->seedLocked();
        $this->loan('C-1', '2025-01', 3, 200);
        $result = (new EirRevenueService())->run('C-1', '202501');""",
 """        $this->seedLocked();
        $this->booked('C-1', '2024-12', 200);
        $this->loan('C-1', '2025-01', 3);
        $result = (new EirRevenueService())->run('C-1', '202501');"""),
("""        $this->seedLocked();
        $this->loan('C-1', '2025-01', 3, 200);
        $this->loan('C-1', '2025-02', 3, 200);""",
 """        $this->seedLocked();
        $this->booked('C-1', '2024-12', 200);
        $this->loan('C-1', '2025-01', 3);
        $this->loan('C-1', '2025-02', 3);"""),
("""        $this->loan('C-1', '2024-06', 3, 200);""", """        $this->loan('C-1', '2024-06', 3);"""),
])
print('ok')
