p = 'C:/xampp/htdocs/MAICC-IFRS9/tests/Feature/Eir/StagingServiceTest.php'
s = open(p, encoding='utf-8').read()
old = "$t->integer('tenor')->default(0); $t->integer('overdue_days')->default(0);"
assert s.count(old) == 1
s = s.replace(old, "$t->integer('tenor')->default(0); $t->integer('overdue_days')->default(0); $t->decimal('carrying_amount', 20, 2)->default(0);")
old_test = r"""    public function test_four_unpaid_instalments_lift_a_current_loan_to_stage_3(): void
    {
        $a = '000104420000005';
        DB::table('loan_books')->insert(['contract_id' => '104420000005', 'external_identity_id' => $a, 'reporting_period' => '2025-12', 'tenor' => 24, 'overdue_days' => 0]);
        foreach (['2025-07-31', '2025-08-31', '2025-09-30', '2025-10-31', '2025-11-30', '2025-12-31'] as $i => $due) {
            $this->raw('P2_10', (string) (100 + $i), $a, $due, ['INSTALLMENT_DATE' => $due, 'INSTALLMENT_AMT' => '100000.00', 'ACTIVE_FLAG' => 'Y', 'DELETE_FLAG' => 'N']);
        }
        // receipts cover the first two instalments only
        $this->raw('P1_01', '1', $a, '2025-08-31', ['TRANSAMT' => '200000.00', 'TRANTYPE' => '305', 'DELETE_FLAG' => 'N']);"""
new_test = r"""    public function test_four_unpaid_instalments_lift_a_current_loan_to_stage_3(): void
    {
        $a = '000104420000005';
        DB::table('loan_books')->insert(['contract_id' => '104420000005', 'external_identity_id' => $a, 'reporting_period' => '2025-12', 'tenor' => 24, 'overdue_days' => 0, 'carrying_amount' => 600000]);
        foreach (['2025-07-31', '2025-08-31', '2025-09-30', '2025-10-31', '2025-11-30', '2025-12-31'] as $i => $due) {
            $this->raw('P2_10', (string) (100 + $i), $a, $due, ['INST_CHART_DET_ID' => (string) (100 + $i), 'INSTALLMENT_DATE' => $due, 'INSTALLMENT_AMT' => '100000.00', 'ACTIVE_FLAG' => 'Y', 'DELETE_FLAG' => 'N', 'TRANSACTION_DATE' => '6/30/2025']);
        }
        $this->raw('P1_01', '0', $a, '2025-06-30', ['TRANSAMT' => '-800000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N']);
        // receipts cover the first two instalments only
        $this->raw('P1_01', '1', $a, '2025-08-31', ['TRANSAMT' => '200000.00', 'TRANTYPE' => '305', 'DELETE_FLAG' => 'N']);"""
assert s.count(old_test) == 1
s = s.replace(old_test, new_test)
anchor = """    /**
     * The cure period (system audit of 9 October 2026, finding M11): a loan"""
new_tests = r"""    /** A fixture loan: 800,000 drawn on 30 June 2025, a chart created then of six instalments of 100,000. */
    private function trigger(string $a, float $balance, string $created = '6/30/2025'): void
    {
        DB::table('loan_books')->insert(['contract_id' => ltrim($a, '0'), 'external_identity_id' => $a, 'reporting_period' => '2025-12', 'tenor' => 24, 'overdue_days' => 0, 'carrying_amount' => $balance]);
        $this->raw('P1_01', $a . '-d', $a, '2025-06-30', ['TRANSAMT' => '-800000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N']);
        foreach (['2025-07-31', '2025-08-31', '2025-09-30', '2025-10-31', '2025-11-30', '2025-12-31'] as $i => $due) {
            $this->raw('P2_10', $a . $i, $a, $due, ['INST_CHART_DET_ID' => $a . $i, 'INSTALLMENT_DATE' => $due, 'INSTALLMENT_AMT' => '100000.00', 'ACTIVE_FLAG' => 'Y', 'DELETE_FLAG' => 'N', 'TRANSACTION_DATE' => $created]);
        }
    }

    /**
     * Verification of 14 October 2026. The trigger read the live chart of
     * 6 October 2026 for every month-end: a chart regenerated after the
     * month-end (Happie Foods' of 5 August 2026) is not the schedule in force
     * at December 2025, and with no version in force the trigger is not
     * evaluated and is counted, never assumed.
     */
    public function test_the_trigger_reads_the_chart_in_force_at_the_month_end_not_a_later_one(): void
    {
        $this->trigger('000104420000062', 600000, '8/5/2026');
        $c = (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $this->assertSame('1', DB::table('loan_books')->value('ifrs9stage_post_qualitative'));
        $this->assertSame(1, $c['instalments_no_chart']);
        $this->assertSame(0, $c['by_instalments']);
    }

    /** A receipt later reversed pays nothing (Chisitu Tea Blenders, 135m refunded as another party's sale proceeds). */
    public function test_a_reversed_receipt_does_not_pay_an_instalment(): void
    {
        $a = '000104450000069';
        $this->trigger($a, 600000);
        $this->raw('P1_01', 'r1', $a, '2025-07-31', ['TRANSAMT' => '300000.00', 'TRANTYPE' => '305', 'DELETE_FLAG' => 'N']);
        (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $this->assertSame('1', DB::table('loan_books')->value('ifrs9stage_post_qualitative'), 'three of six paid: three missed, below the trigger');
        $this->raw('P1_01', 'r2', $a, '2025-11-30', ['TRANSAMT' => '-300000.00', 'TRANTYPE' => '306', 'PARTICULARS' => 'Refund of Sale Proceeds', 'DELETE_FLAG' => 'N']);
        (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $this->assertSame('3', DB::table('loan_books')->value('ifrs9stage_post_qualitative'), 'the refund undoes the receipt: six missed');
    }

    /** A loan that owes nothing has no instalment outstanding (Micholess Creamery, nil balance, was lifted to Stage 3). */
    public function test_a_loan_that_owes_nothing_is_not_lifted_by_the_trigger(): void
    {
        $this->trigger('000104420000003', 0.0);
        $c = (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $this->assertSame('1', DB::table('loan_books')->value('ifrs9stage_post_qualitative'));
        $this->assertSame(0, $c['by_instalments']);
    }

    /**
     * A chart that asks for more than the ledger has ever charged the loan is
     * not the loan's schedule (Lenziemill Milling in 2024: 576m due on the
     * chart against 517m drawn and charged): it is not read, and the loan is
     * named rather than lifted.
     */
    public function test_a_chart_asking_more_than_the_ledger_ever_debited_is_not_evaluated(): void
    {
        $a = '000104420000007';
        $this->trigger($a, 600000);
        DB::table('ebanker_raw_rows')->where('query_id', 'P1_01')->where('account', $a)->update(['payload' => json_encode(['TRANSAMT' => '-400000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N'])]);
        $c = (new StagingService(new GovernanceService(), new LandingZoneReader()))->stage('2025-12', 1);
        $this->assertSame('1', DB::table('loan_books')->value('ifrs9stage_post_qualitative'));
        $this->assertSame(1, $c['instalments_chart_contradicts_ledger']);
        $log = json_decode(DB::table('audit_logs')->where('action', 'Loan Book Staged')->value('new_values'), true);
        $this->assertArrayHasKey('104420000007', $log['instalment_trigger_not_evaluated']);
    }

"""
assert s.count(anchor) == 1
s = s.replace(anchor, new_tests + anchor)
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
