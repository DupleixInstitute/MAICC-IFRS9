p = 'C:/xampp/htdocs/MAICC-IFRS9/tests/Feature/Ebanker/LoanBookBuildServiceTest.php'
s = open(p, encoding='utf-8').read()
anchor = "    public function test_method_a_copies_the_stored_run_and_derives_marked_where_none_exists(): void"
new = r"""    /** A loan of 120,000 drawn 1 July 2024, its stored August run saying what $run says, and the chart rows given. */
    private function chartLoan(string $a, array $run, array $chart, array $receipts = [], array $definitions = []): void
    {
        $this->raw('P1_02', $a, $a, '2024-07-01', ['NEW_AC_NUMBER' => $a, 'GLCODE' => '1050101', 'CUSTOMER_ID' => '900', 'ACCOUNT_NAME' => 'Chart Ltd', 'STATUS_CODE' => 'A', 'INTEREST_RATE' => '10.00', 'ACCOUNT_OPEN_DATE' => '7/1/2024']);
        $this->raw('P1_01', $a . 'd', $a, '2024-07-01', ['CUMVOUCH_DET_ID' => '1', 'NEW_AC_NUMBER' => $a, 'TRANSACTION_DATE' => '7/1/2024', 'TRANSAMT' => '-120000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N']);
        foreach ($receipts as $i => [$date, $amount]) {
            $this->raw('P1_01', $a . 'r' . $i, $a, $date, ['CUMVOUCH_DET_ID' => (string) (10 + $i), 'NEW_AC_NUMBER' => $a, 'TRANSACTION_DATE' => $date, 'TRANSAMT' => $amount, 'TRANTYPE' => '305', 'DELETE_FLAG' => 'N']);
        }
        $this->raw('P2_08', $a . 'run', $a, '2024-08-31', $run + ['LOAN_BOOK_DET_ID_A' => '9' . substr($a, -3), 'ASONDATE' => '8/31/2024', 'NEW_AC_NUMBER' => $a, 'GLCODE' => '1050101', 'VALUE_DATE' => '7/1/2024', 'MATURITY_DATE' => '6/1/2026', 'CARRYING_AMOUNT' => '120000.00', 'DAY_91_180' => '0.00', 'DAY_181_270' => '0.00', 'DAY_271_360' => '0.00']);
        foreach ($chart as $i => [$due, $amount, $det, $serial]) {
            $this->raw('P2_10', $a . 'c' . $i, $a, $due, ['INST_CHART_DET_ID' => $a . $i, 'NEW_AC_NUMBER' => $a, 'INST_DET_ID' => $det, 'INST_SR_NO' => (string) $serial, 'INSTALLMENT_DATE' => $due, 'INSTALLMENT_AMT' => $amount, 'ACTIVE_FLAG' => 'Y', 'DELETE_FLAG' => 'N', 'TRANSACTION_DATE' => '7/1/2024']);
        }
        foreach ($definitions as $det => $mode) {
            $this->raw('P3_11', $a . 'def' . $det, $a, '2024-07-01', ['INST_DET_ID' => (string) $det, 'NEW_AC_NUMBER' => $a, 'INSTALLMENT_PAY_MODE' => $mode, 'DELETE_FLAG' => 'N']);
        }
    }

    /**
     * Verification of 14 October 2026: the as-at reports re-run in September
     * 2026 aged monthly instalments on loans whose chart in force had nothing
     * due until 2026 (Namalowe Eco Farm, Mchinji Civil Servants SACCO). No
     * contractual payment had fallen due, so none was past due; the row says
     * why it differs from the report.
     */
    public function test_reported_arrears_are_not_accepted_when_the_chart_in_force_has_nothing_due(): void
    {
        $a = '000104420000064';
        $this->chartLoan($a, ['OVERDUE_PRINCI_DATE' => '7/1/2024', 'ARREAS_TOTAL' => '5000.00', 'DAY_1_30' => '5000.00', 'DAY_31_91' => '5000.00'],
            [['2024-08-01', '0.00', '1', 1], ['2025-03-03', '60000.00', '2', 1], ['2025-09-03', '60000.00', '2', 2]]);
        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $row = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104420000064')->first();
        $this->assertSame(0, (int) $row->overdue_days);
        $this->assertNull($row->overdue_principal_date);
        $this->assertStringContainsString('no instalment due until 2025-03-03', $row->build_flag);
        // a loan with no chart keeps the report's count
        $this->assertSame(61, (int) DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104420000005')->value('overdue_days'));
    }

    /**
     * Udon Business and BenCal Technologies: the migrated chart dates the rest
     * of a monthly definition on one day, and from that day the report shows
     * the whole balance overdue from the value date. Dated by the definition,
     * the instalments due are covered by the ledger receipts.
     */
    public function test_a_collapsed_chart_is_counted_on_its_monthly_definition_against_the_ledger(): void
    {
        $a = '000104450000056';
        $chart = [['2024-07-01', '20000.00', '7', 1], ['2024-08-01', '20000.00', '7', 2]];
        foreach (range(3, 6) as $serial) {
            $chart[] = ['2024-08-01', '20000.00', '7', $serial]; // serials 3 to 6 all dated 1 August 2024
        }
        $this->chartLoan($a, ['OVERDUE_PRINCI_DATE' => '7/1/2024', 'ARREAS_TOTAL' => '120000.00', 'DAY_31_91' => '120000.00'], $chart,
            [['2024-07-01', '20000.00'], ['2024-08-01', '20000.00']], ['7' => 'M']);
        $this->service()->build('2024-08', '2024-08', 'B', 1, 2);
        $row = DB::table('loan_books')->where('reporting_period', '2024-08')->where('contract_id', '104450000056')->first();
        $this->assertSame(0, (int) $row->overdue_days, 'July and August are paid; September onward is not yet due');
        $this->assertStringContainsString('dates several instalments on one day', $row->build_flag);
        $this->assertStringContainsString('the report says 61', $row->build_flag);
    }

"""
assert s.count(anchor) == 1
s = s.replace(anchor, new + anchor)
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
