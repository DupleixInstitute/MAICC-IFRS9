<?php

namespace Tests\Feature\Eir;

use App\Models\User;
use App\Services\Ebanker\ContractInputsBuildService;
use App\Services\Ebanker\InstalmentChartHistory;
use App\Services\Ebanker\LandingZoneReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pack 11 (16 October 2026): a loan closed before the last month-end keeps
 * the terms it was lent on. E-Banker prints a closed loan in its Loan Book
 * Report with every figure blank or nil; the contract master reads the last
 * run that states the facility, so the drawn amount and the value date reach
 * contract_eir (JVD Agro, Nature's Gift, Kadava). And pack 11's instalment
 * chart (IC_01) is read with the live chart for an account P2_10 did not hold,
 * without replacing a row P2_10 holds.
 */
class ClosedLoanTermsTest extends TestCase
{
    use RefreshDatabase;

    private const JVD = '000104460000109';

    protected function setUp(): void
    {
        parent::setUp();
        $u = User::create(['name' => 'Analyst', 'email' => 'a@example.com', 'password' => bcrypt('x')]);
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('b', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ebanker_loads')->insert(['id' => 2, 'pack_hash' => str_repeat('c', 64), 'pack_name' => 'pack 11', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('loan_portfolios')->insert(['name' => 'Loans', 'created_by_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('loan_books')->insert(['contract_id' => ltrim(self::JVD, '0'), 'customer_id' => '3225', 'customer_name' => 'JVD AGRO LIMITED', 'external_identity_id' => self::JVD, 'reporting_period' => '2026-02',
            'reporting_year' => 2026, 'reporting_month' => 2, 'loan_portfolio_id' => 1, 'carrying_amount' => 266483229.94, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function raw(string $q, string $key, ?string $date, array $payload, int $load = 1): void
    {
        DB::table('ebanker_raw_rows')->insert(['load_id' => $load, 'query_id' => $q, 'source_key' => $key, 'account' => self::JVD, 'row_date' => $date,
            'payload' => json_encode($payload), 'row_hash' => md5($q . $key . $load), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_a_closed_loan_takes_its_terms_from_the_last_run_that_states_them(): void
    {
        $this->raw('P1_02', self::JVD, '2025-05-09', ['NEW_AC_NUMBER' => self::JVD, 'GLCODE' => '1050401', 'CUSTOMER_ID' => '3225', 'ACCOUNT_NAME' => 'JVD AGRO LIMITED', 'STATUS_CODE' => 'C', 'INTEREST_RATE' => '10.00', 'ACCOUNT_OPEN_DATE' => '5/9/2025', 'INTEREST_POLICY' => 'F']);
        $this->raw('P1_03', self::JVD, '2025-05-09', ['NEW_AC_NUMBER' => self::JVD, 'SANCTION_AMOUNT' => '250000000.00', 'PERIOD_YEARS' => '5', 'PERIOD_MONTHS' => '0', 'EXPIRY_DATE' => '5/9/2030', 'INST_FREQ' => 'M']);
        // the run of February 2026 states the loan; the run of March, after the refinance, prints it blank
        $this->raw('P2_08', '9001', '2026-02-28', ['LOAN_BOOK_DET_ID_A' => '9001', 'ASONDATE' => '2/28/2026', 'NEW_AC_NUMBER' => self::JVD, 'GLCODE' => '1050401', 'VALUE_DATE' => '5/9/2025', 'MATURITY_DATE' => '5/9/2030',
            'DISBURSD' => '250000000.00', 'APPROVED' => '250000000.00', 'INTEREST_RATE' => '10.000000', 'CARRYING_AMOUNT' => '266483229.94']);
        $this->raw('P2_08', '9002', '2026-03-31', ['LOAN_BOOK_DET_ID_A' => '9002', 'ASONDATE' => '3/31/2026', 'NEW_AC_NUMBER' => self::JVD, 'GLCODE' => '1050401', 'VALUE_DATE' => '', 'MATURITY_DATE' => '',
            'DISBURSD' => '0.00', 'APPROVED' => '0.00', 'INTEREST_RATE' => '', 'CARRYING_AMOUNT' => '0.00']);

        $this->assertFalse(ContractInputsBuildService::statesTheFacility(['VALUE_DATE' => '', 'DISBURSD' => '0.00']));
        $this->assertTrue(ContractInputsBuildService::statesTheFacility(['VALUE_DATE' => '', 'DISBURSD' => '250,000,000.00']));
        app(ContractInputsBuildService::class)->contractMaster();
        $c = DB::table('contract_eir')->where('contract_id', ltrim(self::JVD, '0'))->first();
        $this->assertNotNull($c);
        $this->assertEquals(250000000, (float) $c->drawn_amount);
        $this->assertSame('2025-05-09', substr((string) $c->origination_date, 0, 10));
        $this->assertSame('2030-05-09', substr((string) $c->maturity_date, 0, 10));
        $this->assertEquals(0.10, (float) $c->contractual_rate);
    }

    public function test_the_pack_11_chart_is_read_for_an_account_the_live_extract_did_not_hold(): void
    {
        $row = fn (string $id, string $due, string $amount, string $extra = '') => ['INST_CHART_DET_ID' => $id, 'NEW_AC_NUMBER' => self::JVD, 'TRANSACTION_DATE' => '5/12/2025', 'INST_DET_ID' => '2142',
            'INST_SR_NO' => (string) ((int) $id - 88718), 'INSTALLMENT_DATE' => $due, 'INSTALLMENT_AMT' => $amount, 'ACTIVE_FLAG' => 'Y', 'DELETE_FLAG' => 'N', 'VERIFY_FLAG' => 'N', 'REMARK' => $extra];
        $this->raw('IC_01', '88719', '2025-06-01', $row('88719', '6/1/2025', '5311761.00'), 2);
        $this->raw('IC_01', '88720', '2025-07-01', $row('88720', '7/1/2025', '5311761.00'), 2);
        $charts = new InstalmentChartHistory(new LandingZoneReader());
        $this->assertTrue($charts->hasChart(self::JVD));
        $this->assertSame([['2025-06-01', 5311761.0], ['2025-07-01', 5311761.0]], $charts->at(self::JVD, '2025-07-31')['instalments']);

        // a row the live extract holds stays as P2_10 has it: IC_01 adds only what P2_10 does not hold
        $this->raw('P2_10', '88720', '2025-07-01', $row('88720', '7/1/2025', '5300000.00'));
        $this->assertSame([['2025-06-01', 5311761.0], ['2025-07-01', 5300000.0]], (new InstalmentChartHistory(new LandingZoneReader()))->at(self::JVD, '2025-07-31')['instalments']);
    }
}
