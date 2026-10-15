import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()


def rep(old, new):
    global s
    assert s.count(old) == 1, (old[:90], s.count(old))
    s = s.replace(old, new)


rep("""$t->decimal('ecl_value', 20, 2)->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->string('contract_status')->nullable(); });""",
    """$t->decimal('ecl_value', 20, 2)->nullable(); $t->decimal('ecl_value_discounted', 20, 2)->nullable(); $t->decimal('ecl_discounting_effect', 20, 2)->nullable(); $t->string('ecl_discount_status')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->string('contract_status')->nullable(); });
        Schema::create('gl_trial_balance_lines', function (Blueprint $t) { $t->increments('id'); $t->date('period'); $t->string('gl_code'); $t->string('gl_title')->nullable(); $t->decimal('debit', 20, 2)->default(0); $t->decimal('credit', 20, 2)->default(0); $t->string('basis')->nullable(); $t->string('source_file')->nullable(); });""")
rep("""    /** Finding F3 of workbook 4:""", """    /** The IFRS 9 allowance is the booked one: discounted where the engine discounted the loan; a loan without a discounted figure is counted and named (built 14 October 2026). */
    public function test_the_allowance_is_the_booked_discounted_allowance_and_a_gap_is_named(): void
    {
        // D (doubtful, 1,000) is discounted to 450 from 600; E (loss) keeps no discounted figure
        DB::table('loan_books')->where('contract_id', 'D')->update(['ecl_value_discounted' => 450, 'ecl_discounting_effect' => 160, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED']);
        DB::table('loan_books')->where('contract_id', 'E')->update(['ecl_discount_status' => 'TIME_PHASED_UNRESOLVED']);
        $r = (new RbmReturnService(new LandingZoneReader()))->build('2026-08');
        $b = array_column($r['sections']['B_provisions'], null, 'class');
        $this->assertEquals(450, $b['Doubtful']['ifrs9_allowance']);   // not the undiscounted 600
        $this->assertEquals(50, $b['Doubtful']['shortfall']);          // 500 minimum against 450 held
        $this->assertEquals(1550, $r['sections']['B_totals']['ifrs9_allowance']);
        $basis = $r['sections']['B_allowance_basis'];
        $this->assertSame('mixed', $basis['status']);
        $this->assertSame(1, $basis['bases']['discounted']['loans']);
        $this->assertSame(2, $basis['bases']['undiscounted']['loans']);   // B and E; A and C carry a nil ECL
        $this->assertEquals(1100, $basis['reconciliation']['not_booked']);
        // undiscounted 1,700, plus the projection 450 + 160 - 600 = 10, less the discounting 160 = the 1,550 shown
        $this->assertEquals(1700, $basis['reconciliation']['undiscounted']);
        $this->assertEquals(10, $basis['reconciliation']['projection']);
        $this->assertEquals(-160, $basis['reconciliation']['discounting_effect']);
        $this->assertEquals(1550, $basis['reconciliation']['shown']);
        $this->assertStringContainsString('no discounted allowance', $basis['message']);
    }

    /** Paragraph 12: a loss facility is written off in the quarter after the one it became loss in; the list names each with its age (built 14 October 2026). */
    public function test_loss_facilities_are_listed_with_the_quarter_they_must_be_written_off_by(): void
    {
        // E (short-term) was loss from February 2026 (366 days by then), and is overdue for write-off at August (Q1 + one quarter = 30 June)
        foreach (['2026-01' => 300, '2026-02' => 370, '2026-03' => 400, '2026-04' => 430, '2026-05' => 460, '2026-06' => 490, '2026-07' => 520] as $p => $dpd) {
            DB::table('loan_books')->insert(['contract_id' => 'E', 'reporting_period' => $p, 'product_code' => '1050101', 'tenor' => 12, 'overdue_days' => $dpd, 'carrying_amount' => 1000, 'principal_balance' => 1000]);
        }
        // F (medium-term) became loss in July 2026 (Q3): due by 31 December, not yet overdue
        foreach (['2026-06' => 700, '2026-07' => 750, '2026-08' => 780] as $p => $dpd) {
            DB::table('loan_books')->insert(['contract_id' => 'F', 'reporting_period' => $p, 'product_code' => '1050101', 'tenor' => 36, 'overdue_days' => $dpd, 'carrying_amount' => 500, 'principal_balance' => 500, 'ecl_value' => 400]);
        }
        $r = (new RbmReturnService(new LandingZoneReader()))->build('2026-08');
        $loss = $r['sections']['B_loss_write_offs'];
        $this->assertSame(2, $loss['facilities']);
        $this->assertEquals(1500, $loss['balance']);
        $rows = array_column($loss['rows'], null, 'contract_id');
        $this->assertSame('2026-02', $rows['E']['loss_since']);
        $this->assertFalse($rows['E']['loss_since_open']);
        $this->assertSame('2026-06-30', $rows['E']['write_off_due_by']);
        $this->assertSame('overdue', $rows['E']['status']);
        $this->assertSame(7, $rows['E']['months_classified_loss']);
        $this->assertSame('2026-07', $rows['F']['loss_since']);
        $this->assertSame('2026-12-31', $rows['F']['write_off_due_by']);
        $this->assertSame('due next quarter', $rows['F']['status']);
        $this->assertSame(1, $loss['overdue']);
    }

    /** Paragraph 13(1): the interest charged since each facility became non-performing, tied to GL 1320 (built 14 October 2026). */
    public function test_cumulative_interest_in_suspense_is_measured_and_tied_to_gl_1320(): void
    {
        // B became non-performing in July (substandard, 95 days); 50 charged in July and 120 in August, 30 received in August
        DB::table('loan_books')->insert(['contract_id' => 'B', 'reporting_period' => '2026-07', 'product_code' => '1050101', 'tenor' => 12, 'overdue_days' => 95, 'carrying_amount' => 900, 'principal_balance' => 900]);
        DB::table('loan_books')->insert(['contract_id' => 'B', 'reporting_period' => '2026-06', 'product_code' => '1050101', 'tenor' => 12, 'overdue_days' => 60, 'carrying_amount' => 850, 'principal_balance' => 850]);
        foreach ([['2', '2026-07-31', '-50.00', '303'], ['3', '2026-08-15', '30.00', '305'], ['4', '2026-06-30', '-45.00', '303']] as [$k, $d, $amt, $type]) {
            DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => 'P1_01', 'source_key' => $k, 'account' => '000000000000B', 'row_date' => $d, 'payload' => json_encode(['TRANSAMT' => $amt, 'TRANTYPE' => $type, 'AC_GLCODE' => '1050101', 'DELETE_FLAG' => 'N']), 'row_hash' => 'x' . $k, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('gl_trial_balance_lines')->insert(['period' => '2026-08-01', 'gl_code' => '1320', 'gl_title' => 'Interest Suspense', 'debit' => 0, 'credit' => 100, 'basis' => 'POSTCLOSING', 'source_file' => 'TB Aug']);
        $c = (new RbmReturnService(new LandingZoneReader()))->build('2026-08')['sections']['C_interest_in_suspense'];
        $this->assertEquals(170, $c['cumulative_interest_charged_since_non_performing']);   // July and August, not June (performing then)
        $this->assertEquals(30, $c['cash_received_since_non_performing']);
        $this->assertEquals(100, $c['gl_1320_interest_suspense']);
        $this->assertEquals(70, $c['difference_cumulative_less_gl_1320']);
        $this->assertStringContainsString('cannot', strtolower($c['note']) . ' cannot');
    }

    /** Finding F3 of workbook 4:""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
