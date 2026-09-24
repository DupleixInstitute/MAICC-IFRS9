<?php

namespace Tests\Feature\Eir;

use App\Exceptions\GovernanceSettingMissingException;
use App\Services\Eir\EirGlReconciliationService;
use App\Services\Eir\GovernanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

class EirGlReconciliationServiceTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('portfolio')->nullable(); $t->double('drawn_amount')->default(0); $t->double('contractual_rate')->nullable(); $t->double('eir_effective_annual')->nullable(); $t->string('origination_date')->nullable(); $t->string('interest_start_date')->nullable(); $t->string('moratorium_type')->nullable(); $t->integer('moratorium_months')->nullable(); $t->string('moratorium_from')->nullable(); $t->double('reference_rate_at_origination')->nullable(); $t->double('spread_over_prime')->nullable(); $t->double('markup')->nullable(); $t->timestamps(); });
        Schema::create('eir_amortisation', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period', 7); $t->double('opening_gross'); $t->double('interest_accrued'); $t->string('interest_basis')->default('GROSS'); $t->double('unwind_amount')->default(0); $t->double('cash_received')->default(0); $t->string('cash_source')->default('IMPORTED'); $t->double('modification_gain_loss')->default(0); $t->double('closing_gross')->default(0); $t->double('ecl_allowance')->default(0); $t->timestamps(); });
        Schema::create('gl_interest_postings', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('gl_account_code')->nullable(); $t->integer('period_year'); $t->integer('period_month'); $t->double('interest_income_posted'); $t->timestamps(); });
        // The expected leg reads the loan book: the prior month-end balance and
        // the rate in force for the month (spec 7.7).
        $this->createLoanBookSchema();
        // The tolerance band and the day count are governed settings, not constants.
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
    }

    /** A facility with no integral fees: the solved EIR is the contractual rate compounded. */
    private function contract(string $id, float $drawn, float $rate, string $portfolio = 'MAIIC', array $overrides = []): void
    {
        DB::table('contract_eir')->insert(array_merge(['contract_id' => $id, 'portfolio' => $portfolio, 'drawn_amount' => $drawn,
            'contractual_rate' => $rate, 'eir_effective_annual' => pow(1 + $rate / 12, 12) - 1,
            'origination_date' => '2025-01-06',
            'created_at' => now(), 'updated_at' => now()], $overrides));
    }

    private function accrual(string $id, string $period, float $opening, float $interest): void
    {
        DB::table('eir_amortisation')->insert(['contract_id' => $id, 'reporting_period' => $period,
            'opening_gross' => $opening, 'interest_accrued' => $interest, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function posting(string $id, int $year, int $month, float $posted): void
    {
        DB::table('gl_interest_postings')->insert(['contract_id' => $id, 'period_year' => $year,
            'period_month' => $month, 'interest_income_posted' => $posted, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** One monthly Loan Book Report row: the balance the next month is charged on. */
    private function loanBook(string $id, string $period, float $balance, float $rate, ?float $disbursed = null): void
    {
        DB::table('loan_books')->insert(['contract_id' => $id, 'customer_name' => $id . ' Limited',
            'reporting_period' => $period, 'interest_rate' => $rate, 'carrying_amount' => $balance,
            'principal_balance' => $balance, 'disbursed' => $disbursed ?? $balance,
            'created_at' => now(), 'updated_at' => now()]);
    }

    private function approve(string $key, string $value, string $effectiveFrom, string $reason): void
    {
        $governance = new GovernanceService();
        $proposal = $governance->propose($key, $value, $effectiveFrom, $reason, 10);
        $governance->approve($proposal->id, 20);
    }

    /* --------------------------------------------------------------------- */
    /*  The bridge                                                           */
    /* --------------------------------------------------------------------- */

    /**
     * The ledger charges the proven convention on the loan book's balance; the
     * engine amortises a lower one. Every term is named and nothing is left
     * over.
     */
    public function test_the_effects_reconcile_the_variance_exactly(): void
    {
        $this->contract('C-1', 1_000_000, 0.24);
        $this->loanBook('C-1', '2025-09', 1_000_000, 24.00);
        $this->loanBook('C-1', '2025-10', 1_000_000, 24.00);
        // 1,000,000 x 24 percent x 31 / 365.
        $this->posting('C-1', 2025, 10, 20_383.56);
        // The engine has amortised the same facility down to 700,000.
        $this->accrual('C-1', '2025-10', 700_000, 700_000 * 0.24 / 12);

        $row = (new EirGlReconciliationService())->forPeriod('2025-10')['rows'][0];

        $this->assertSame(20_383.56, $row['expected_interest']);
        $this->assertSame('WITHIN_TOLERANCE', $row['cause']);
        // The ledger agrees with the convention, so the whole variance is the
        // difference between the two balances plus the EIR convention.
        $this->assertEqualsWithDelta(0, $row['base_effect'], 0.01);
        $this->assertEqualsWithDelta(14_268.49 - 20_383.56, $row['carrying_amount_effect'], 0.01);
        $this->assertEqualsWithDelta(14_000 - 14_268.49, $row['rate_effect'], 0.01);
        $this->assertEqualsWithDelta(0, $row['impairment_effect'], 0.01);
        $this->assertEqualsWithDelta(0, $row['unexplained'], 0.01);
        $this->assertEqualsWithDelta(
            $row['variance'],
            $row['base_effect'] + $row['carrying_amount_effect'] + $row['rate_effect'] + $row['impairment_effect'],
            0.01
        );
    }

    /**
     * With the same balance on both sides and the ledger on the convention,
     * only the yield uplift can move the figure. It is measured against the
     * contractual charge for the month, never against the annual rate divided
     * by twelve.
     */
    public function test_a_fee_bearing_eir_shows_the_uplift_as_rate_effect(): void
    {
        DB::table('contract_eir')->insert(['contract_id' => 'C-2', 'portfolio' => 'MAIIC', 'drawn_amount' => 1_000_000,
            'contractual_rate' => 0.24, 'eir_effective_annual' => pow(1 + 0.03, 12) - 1, // 3% monthly, fees included
            'origination_date' => '2025-01-06', 'created_at' => now(), 'updated_at' => now()]);
        $this->loanBook('C-2', '2025-09', 1_000_000, 24.00);
        $this->loanBook('C-2', '2025-10', 1_000_000, 24.00);
        $this->posting('C-2', 2025, 10, 20_383.56);
        $this->accrual('C-2', '2025-10', 1_000_000, 1_000_000 * 0.03);

        $row = (new EirGlReconciliationService())->forPeriod('2025-10')['rows'][0];

        $this->assertEqualsWithDelta(0, $row['base_effect'], 0.01);
        $this->assertEqualsWithDelta(0, $row['carrying_amount_effect'], 0.01);
        $this->assertEqualsWithDelta(30_000 - 20_383.56, $row['rate_effect'], 0.01);
        $this->assertEqualsWithDelta(30_000 - 20_383.56, $row['variance'], 0.01);
        $this->assertEqualsWithDelta(0, $row['unexplained'], 0.01);
    }

    /**
     * A ledger accruing on the declining balance rather than on original
     * principal decomposes with no residual, and the balance its posting
     * implies is reported so that a reader can see which basis it used.
     */
    public function test_a_ledger_that_amortises_still_decomposes_with_no_residual(): void
    {
        $this->contract('C-1', 1_000_000, 0.24);
        $this->loanBook('C-1', '2025-09', 700_000, 24.00);
        $this->loanBook('C-1', '2025-10', 700_000, 24.00);
        // The ledger accrues on the current balance, on the proven convention.
        $this->posting('C-1', 2025, 10, 14_268.49);
        $this->accrual('C-1', '2025-10', 700_000, 700_000 * 0.24 / 12);

        $row = (new EirGlReconciliationService())->forPeriod('2025-10')['rows'][0];

        // Within a kwacha of the balance: the posting itself is rounded to the
        // tambala, so the balance it implies cannot come back exactly.
        $this->assertEqualsWithDelta(700_000, $row['gl_implied_base'], 1.00);
        $this->assertSame('WITHIN_TOLERANCE', $row['cause']);
        $this->assertEqualsWithDelta(0, $row['base_effect'], 0.01);
        $this->assertEqualsWithDelta(0, $row['carrying_amount_effect'], 0.01);
        $this->assertEqualsWithDelta(0, $row['unexplained'], 0.01);
        $this->assertEqualsWithDelta(
            $row['variance'],
            $row['base_effect'] + $row['carrying_amount_effect'] + $row['rate_effect'] + $row['impairment_effect'],
            0.01
        );
    }

    /**
     * Stage 3 accrues on the amortised cost net of the loss allowance, so the
     * shortfall against a gross accrual is a real measurement difference and
     * must be named rather than left sitting in the residual.
     */
    public function test_stage_three_net_accrual_reports_as_impairment_effect(): void
    {
        $this->contract('C-3', 1_000_000, 0.24);
        $this->loanBook('C-3', '2025-09', 1_000_000, 24.00);
        $this->loanBook('C-3', '2025-10', 1_000_000, 24.00);
        $this->posting('C-3', 2025, 10, 20_383.56);
        // Accrued on 800,000 net of a 200,000 allowance, against a gross opening.
        $this->accrual('C-3', '2025-10', 1_000_000, 800_000 * 0.24 / 12);

        $row = (new EirGlReconciliationService())->forPeriod('2025-10')['rows'][0];

        $this->assertEqualsWithDelta(-200_000 * 0.24 / 12, $row['impairment_effect'], 0.01);
        $this->assertEqualsWithDelta(0, $row['unexplained'], 0.01);
        $this->assertEqualsWithDelta(
            $row['variance'],
            $row['base_effect'] + $row['carrying_amount_effect'] + $row['rate_effect'] + $row['impairment_effect'],
            0.01
        );
    }

    public function test_postings_without_a_counterpart_stay_out_of_the_bridge(): void
    {
        $this->contract('C-1', 1_000_000, 0.24);
        $this->posting('C-1', 2025, 10, 20_000);
        $this->accrual('C-1', '2025-10', 1_000_000, 20_000);
        $this->contract('C-GAP', 500_000, 0.24);
        $this->posting('C-GAP', 2025, 10, 10_000); // Never calculated.

        $result = (new EirGlReconciliationService())->forPeriod('2025-10');
        $bridge = $result['bridge'];

        $this->assertSame(1, $result['summary']['not_calculated']);
        $this->assertEqualsWithDelta(30_000, $bridge['gl_total'], 0.01);
        $this->assertEqualsWithDelta(10_000, $bridge['gl_without_counterpart'], 0.01);
        $this->assertEqualsWithDelta(20_000, $bridge['gl_matched'], 0.01);
        // The uncovered posting must not appear as a measurement difference.
        $this->assertEqualsWithDelta(0, $bridge['net_variance'], 0.01);
        $this->assertEqualsWithDelta(
            $bridge['gl_matched'] + $bridge['base_effect'] + $bridge['carrying_amount_effect']
                + $bridge['rate_effect'] + $bridge['impairment_effect'] + $bridge['unexplained'],
            $bridge['eir_total'], 0.01
        );
    }

    public function test_small_differences_fall_inside_the_tolerance_band(): void
    {
        $this->contract('C-1', 1_000_000, 0.24);
        $this->posting('C-1', 2025, 10, 20_000);
        $this->accrual('C-1', '2025-10', 1_000_000, 20_050); // 0.25%

        $rows = (new EirGlReconciliationService())->forPeriod('2025-10')['rows'];

        $this->assertSame('WITHIN_TOLERANCE', $rows[0]['status']);
    }

    public function test_periods_are_listed_newest_first_and_default_to_the_latest(): void
    {
        $this->contract('C-1', 1_000_000, 0.24);
        $this->posting('C-1', 2025, 9, 20_000);
        $this->posting('C-1', 2025, 10, 20_000);
        $service = new EirGlReconciliationService();

        $this->assertSame(['2025-10', '2025-09'], $service->availablePeriods());
        $this->assertSame('2025-10', $service->forPeriod()['period']);
    }

    public function test_portfolio_filter_narrows_the_bridge(): void
    {
        $this->contract('C-1', 1_000_000, 0.24, 'MAIIC');
        $this->posting('C-1', 2025, 10, 20_000);
        $this->accrual('C-1', '2025-10', 1_000_000, 20_000);
        $this->contract('C-2', 1_000_000, 0.12, 'FInES');
        $this->posting('C-2', 2025, 10, 10_000);
        $this->accrual('C-2', '2025-10', 1_000_000, 10_000);

        $result = (new EirGlReconciliationService())->forPeriod('2025-10', 'FInES');

        $this->assertCount(1, $result['rows']);
        $this->assertEqualsWithDelta(10_000, $result['bridge']['gl_total'], 0.01);
    }

    /* --------------------------------------------------------------------- */
    /*  The governed settings                                                */
    /* --------------------------------------------------------------------- */

    /**
     * The band comes from the Governance Centre and is read for the period
     * being reconciled: tightening it from November leaves October's verdict
     * exactly as it was, because October was governed by the old band.
     */
    public function test_the_tolerance_is_the_governed_setting_in_force_for_the_period(): void
    {
        $this->contract('C-1', 1_000_000, 0.24);
        $this->posting('C-1', 2025, 10, 10_000);
        $this->accrual('C-1', '2025-10', 1_000_000, 10_080); // 0.8 percent over
        $this->posting('C-1', 2025, 11, 10_000);
        $this->accrual('C-1', '2025-11', 1_000_000, 10_080);

        $this->approve('recon_tolerance', '0.5 percent of the posted amount, floor MWK 1', '2025-11-01',
            'Auditor asked for a tighter band from November.');

        $service = new EirGlReconciliationService();
        $october = $service->forPeriod('2025-10');
        $november = $service->forPeriod('2025-11');

        $this->assertSame('WITHIN_TOLERANCE', $october['rows'][0]['status']);
        $this->assertSame(1.0, $october['summary']['tolerance_percent']);
        $this->assertSame('VARIANCE', $november['rows'][0]['status']);
        $this->assertSame(0.5, $november['summary']['tolerance_percent']);
    }

    /**
     * The day count behind the expected figure is governed too, and a change to
     * it moves only the months from its effective date.
     */
    public function test_the_day_count_change_moves_the_expected_interest_from_its_effective_date(): void
    {
        $this->jvdAgro();
        $this->posting('JVD-AGRO', 2026, 4, 4_692_858.90);
        $this->posting('JVD-AGRO', 2026, 5, 4_889_144.85);
        $this->approve('day_count', '30/360', '2026-05-01', 'Board approved the 30/360 basis with effect from May.');

        $service = new EirGlReconciliationService();
        $april = $service->forPeriod('2026-04');
        $may = $service->forPeriod('2026-05');

        $this->assertSame('ACT/365', $april['summary']['day_count']);
        $this->assertSame(4_692_859.03, $april['rows'][0]['expected_interest']);
        $this->assertSame('WITHIN_TOLERANCE', $april['rows'][0]['cause']);
        $this->assertSame('30/360', $may['summary']['day_count']);
        $this->assertSame(4_797_144.79, $may['rows'][0]['expected_interest']);
    }

    public function test_reconciliation_stops_when_no_tolerance_is_approved_for_the_period(): void
    {
        $this->contract('C-1', 1_000_000, 0.24);
        $this->posting('C-1', 2024, 6, 10_000);
        $this->accrual('C-1', '2024-06', 1_000_000, 10_000);

        $this->expectException(GovernanceSettingMissingException::class);
        (new EirGlReconciliationService())->forPeriod('2024-06');
    }

    /* --------------------------------------------------------------------- */
    /*  A named cause for every difference (spec 7.7, decision D20)          */
    /* --------------------------------------------------------------------- */

    /**
     * The acceptance test of decision D20: on a clean month the engine's
     * contractual interest equals E-Banker's posting. JVD Agro, April 2026:
     * posted 4,692,858.90 against 4,692,859.03 expected.
     */
    public function test_a_clean_month_agrees_with_the_ledger_to_the_tambala(): void
    {
        $this->jvdAgro();
        $this->posting('JVD-AGRO', 2026, 4, 4_692_858.90);

        $row = (new EirGlReconciliationService())->forPeriod('2026-04')['rows'][0];

        $this->assertSame(4_692_859.03, $row['expected_interest']);
        $this->assertSame('WITHIN_TOLERANCE', $row['cause']);
        $this->assertEqualsWithDelta(0.13, $row['expected_difference'], 0.01);
        $this->assertSame('JVD-AGRO Limited', $row['customer_name']);
        $this->assertSame(570_964_515.32, $row['expected_opening_balance']);
        $this->assertSame(30, $row['expected_days']);
    }

    /** The month the money was paid out: only part of the month is charged. */
    public function test_a_late_disbursement_is_named(): void
    {
        $this->contract('EBENEZER-MIDIAN', 314_900_900.00, 0.31, 'MAIIC',
            ['interest_start_date' => '2025-08-08', 'origination_date' => '2025-08-08']);
        // The ledger charged a whole month instead of the 24 days from the drawdown.
        $this->posting('EBENEZER-MIDIAN', 2025, 8, 8_290_952.46);

        $row = (new EirGlReconciliationService())->forPeriod('2025-08')['rows'][0];

        $this->assertSame('LATE_DISBURSEMENT', $row['cause']);
        $this->assertSame(6_418_801.91, $row['expected_interest']);
        $this->assertSame(24, $row['expected_days']);
        $this->assertTrue($row['first_disbursement_month']);
        $this->assertStringContainsString('2025-08-08', $row['cause_detail']);
    }

    /**
     * Promenade Medical Centre: nothing posted in November, December or
     * January, and four months posted at once in February.
     */
    public function test_a_catch_up_posting_is_named(): void
    {
        $this->contract('PROMENADE', 100_000_000, 0.24, 'MAIIC', ['origination_date' => '2025-06-02']);
        foreach (['2025-10', '2025-11', '2025-12', '2026-01', '2026-02'] as $period) {
            $this->loanBook('PROMENADE', $period, 100_000_000, 24.00);
        }
        $this->posting('PROMENADE', 2026, 2, 7_890_410.95);

        $row = (new EirGlReconciliationService())->forPeriod('2026-02')['rows'][0];

        $this->assertSame('CATCH_UP_POSTING', $row['cause']);
        $this->assertSame(1_841_095.89, $row['expected_interest']);
        $this->assertStringContainsString('2025-11, 2025-12, 2026-01', $row['cause_detail']);
    }

    /** Money drawn inside the month: the month-end balance cannot reproduce it. */
    public function test_a_mid_month_tranche_is_named(): void
    {
        $this->contract('MILELE', 300_000_000, 0.31, 'MAIIC', ['origination_date' => '2025-04-09']);
        $this->loanBook('MILELE', '2026-05', 200_000_000, 31.00, 200_000_000);
        $this->loanBook('MILELE', '2026-06', 300_000_000, 31.00, 300_000_000);
        $this->posting('MILELE', 2026, 6, 6_200_000.00);

        $row = (new EirGlReconciliationService())->forPeriod('2026-06')['rows'][0];

        $this->assertSame('MID_MONTH_TRANCHE', $row['cause']);
        $this->assertSame(5_095_890.41, $row['expected_interest']);
        $this->assertStringContainsString('100,000,000.00', $row['cause_detail']);
    }

    /** The ledger charged a rate the loan book does not carry, but the master does. */
    public function test_a_rate_mismatch_is_named(): void
    {
        $this->contract('JAT-GROUP', 100_000_000, 0.31, 'MAIIC', ['origination_date' => '2025-07-01']);
        $this->loanBook('JAT-GROUP', '2026-05', 100_000_000, 25.30);
        $this->loanBook('JAT-GROUP', '2026-06', 100_000_000, 25.30);
        // 100,000,000 x 31 percent x 30 / 365, the contract master's rate.
        $this->posting('JAT-GROUP', 2026, 6, 2_547_945.21);

        $row = (new EirGlReconciliationService())->forPeriod('2026-06')['rows'][0];

        $this->assertSame('RATE_MISMATCH', $row['cause']);
        $this->assertSame(2_079_452.05, $row['expected_interest']);
        $this->assertSame(0.253, $row['expected_rate']);
        $this->assertStringContainsString('31.00 percent', $row['cause_detail']);
        $this->assertStringContainsString('the contract master', $row['cause_detail']);
    }

    /** A live loan the ledger has nothing for is a row, not an absence. */
    public function test_a_live_loan_with_no_posting_is_named(): void
    {
        $this->contract('SILENT-LOAN', 100_000_000, 0.24, 'MAIIC');
        $this->loanBook('SILENT-LOAN', '2025-09', 100_000_000, 24.00);
        $this->loanBook('SILENT-LOAN', '2025-10', 100_000_000, 24.00);
        // Another account carries the only posting in the month.
        $this->contract('C-1', 1_000_000, 0.24);
        $this->posting('C-1', 2025, 10, 20_383.56);

        $result = (new EirGlReconciliationService())->forPeriod('2025-10');
        $silent = collect($result['rows'])->firstWhere('contract_id', 'SILENT-LOAN');

        $this->assertNotNull($silent);
        $this->assertSame('NO_POSTING', $silent['cause']);
        $this->assertFalse($silent['has_posting']);
        $this->assertSame(0.0, $silent['gl_posted']);
        $this->assertSame(2_038_356.16, $silent['expected_interest']);
        $this->assertSame(1, $result['summary']['no_posting_rows']);
        // Nothing posted adds nothing to the ledger total.
        $this->assertEqualsWithDelta(20_383.56, $result['bridge']['gl_total'], 0.01);
    }

    /** A missing input is named, and never filled with an assumption. */
    public function test_a_missing_input_is_named_as_a_data_gap(): void
    {
        $this->contract('NO-LOAN-BOOK', 100_000_000, 0.24);
        $this->posting('NO-LOAN-BOOK', 2025, 10, 20_000);

        $row = (new EirGlReconciliationService())->forPeriod('2025-10')['rows'][0];

        $this->assertSame('DATA_GAP', $row['cause']);
        $this->assertNull($row['expected_interest']);
        $this->assertStringContainsString('NO_OPENING_BALANCE', $row['cause_detail']);
        $this->assertStringContainsString('2025-09', $row['cause_detail']);
    }

    /**
     * A ledger that posted the annual rate divided by twelve in February is not
     * quietly accepted: no rate on record produces it, so the row is reported as
     * unexplained with the rate the posting implies.
     */
    public function test_a_difference_no_cause_explains_is_reported_as_unexplained(): void
    {
        $this->contract('TWELFTH-POSTER', 500_000_000, 0.31, 'MAIIC', ['origination_date' => '2025-06-10']);
        $this->loanBook('TWELFTH-POSTER', '2026-01', 500_000_000, 31.00);
        $this->loanBook('TWELFTH-POSTER', '2026-02', 500_000_000, 31.00);
        $this->posting('TWELFTH-POSTER', 2026, 2, round(500_000_000 * 0.31 / 12, 2));

        $result = (new EirGlReconciliationService())->forPeriod('2026-02');
        $row = $result['rows'][0];

        $this->assertSame('UNEXPLAINED', $row['cause']);
        $this->assertSame(11_890_410.96, $row['expected_interest']);
        $this->assertStringContainsString('33.68 percent', $row['cause_detail']);
        $this->assertSame(1, $result['summary']['expected_unexplained']);
    }

    /* --------------------------------------------------------------------- */
    /*  The GL postings list and the all-period totals                       */
    /* --------------------------------------------------------------------- */

    /**
     * The postings list is decorated by this same service, so the EIR Data
     * screen and the reconciliation screen cannot disagree.
     */
    public function test_postings_are_reconciled_one_by_one_for_the_postings_list(): void
    {
        $this->jvdAgro();
        $this->posting('JVD-AGRO', 2026, 4, 4_692_858.90);
        $this->accrual('JVD-AGRO', '2026-04', 570_964_515.32, 4_758_037.63);
        $postings = \App\Models\GlInterestPosting::all();

        $rows = (new EirGlReconciliationService())->forPostings($postings);

        $this->assertCount(1, $rows);
        $row = $rows[$postings->first()->id];
        $this->assertSame(4_692_859.03, $row['expected_interest']);
        $this->assertSame('WITHIN_TOLERANCE', $row['cause']);
        $this->assertSame(4_758_037.63, $row['eir_accrued']);
    }

    /**
     * The postings list spans periods, so one month with no approved
     * convention must not stop the page: that row says so instead.
     */
    public function test_a_posting_in_an_ungoverned_month_is_listed_with_its_reason(): void
    {
        $this->contract('C-1', 1_000_000, 0.24);
        $this->posting('C-1', 2024, 6, 10_000);
        $postings = \App\Models\GlInterestPosting::all();

        $row = (new EirGlReconciliationService())->forPostings($postings)[$postings->first()->id];

        $this->assertSame('NOT_GOVERNED', $row['status']);
        $this->assertSame('DATA_GAP', $row['cause']);
        $this->assertStringContainsString('recon_tolerance', $row['cause_detail']);
        $this->assertNull($row['expected_interest']);
        $this->assertSame(10_000.0, $row['gl_posted']);
    }

    public function test_the_all_period_totals_use_the_governed_band_of_each_period(): void
    {
        $this->contract('C-1', 1_000_000, 0.24);
        $this->posting('C-1', 2025, 10, 10_000);
        $this->accrual('C-1', '2025-10', 1_000_000, 10_080); // 0.8 percent over
        $this->posting('C-1', 2025, 11, 10_000);
        $this->accrual('C-1', '2025-11', 1_000_000, 10_080);
        $this->posting('C-GAP', 2025, 11, 5_000); // never calculated
        $this->approve('recon_tolerance', '0.5 percent of the posted amount, floor MWK 1', '2025-11-01',
            'Auditor asked for a tighter band from November.');

        $totals = (new EirGlReconciliationService())->overallSummary();

        $this->assertSame(3, $totals['posting_rows']);
        $this->assertSame(2, $totals['calculated_rows']);
        $this->assertSame(1, $totals['missing_rows']);
        // October passed under the old band; November fails under the new one.
        $this->assertSame(1, $totals['within_tolerance']);
        $this->assertSame(1, $totals['variance_rows']);
        $this->assertSame(0, $totals['ungoverned_rows']);
        $this->assertSame(25_000.0, $totals['gl_total']);
        $this->assertSame(5_000.0, $totals['missing_gl_total']);
        $this->assertSame(20_160.0, $totals['eir_total']);
        $this->assertSame(0.5, $totals['tolerance_percent']);
    }

    /** Rows in a period with no approved band are counted, never judged. */
    public function test_rows_in_an_ungoverned_period_are_counted_separately(): void
    {
        $this->contract('C-1', 1_000_000, 0.24);
        $this->posting('C-1', 2024, 6, 10_000);
        $this->accrual('C-1', '2024-06', 1_000_000, 10_000);

        $totals = (new EirGlReconciliationService())->overallSummary();

        $this->assertSame(1, $totals['ungoverned_rows']);
        $this->assertSame(0, $totals['within_tolerance']);
        $this->assertSame(0, $totals['variance_rows']);
    }

    /**
     * JVD Agro as the reconstruction found it: fixed 10 percent, and each
     * month's balance is the month before plus the interest charged.
     */
    private function jvdAgro(): void
    {
        $this->contract('JVD-AGRO', 570_964_515.32, 0.10, 'MAIIC', ['origination_date' => '2025-05-08']);
        $this->loanBook('JVD-AGRO', '2026-03', 570_964_515.32, 10.00);
        $this->loanBook('JVD-AGRO', '2026-04', 575_657_374.35, 10.00);
        $this->loanBook('JVD-AGRO', '2026-05', 580_546_519.17, 10.00);
    }
}
