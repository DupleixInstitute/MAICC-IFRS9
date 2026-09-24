<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\ContractualInterest;
use App\Services\Eir\ContractualInterestService;
use App\Services\Eir\GovernanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The interest posting convention of spec v3 section 7.7, against MAIIC's own
 * numbers.
 *
 * Every figure in the first seven tests came from rebuilding MAIIC's monthly
 * loan books against their general ledger. The rate is the one the account was
 * charged and the opening balance is the outstanding balance at the end of the
 * month before; the monthly Loan Book Reports that hold it are client data and
 * are not in the repository, so each fixture states the balance to the cent.
 * What is being proven is the convention: the ledger's own posting comes back
 * out of it.
 *
 * On five of the seven the engine and the ledger agree to within 15 tambala on
 * sums of hundreds of millions of kwacha, which is E-Banker's own rounding.
 * On the two first-disbursement months the engine reproduces the reconstructed
 * figure exactly.
 */
class ContractualInterestServiceTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('contract_eir', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id')->unique();
            $t->string('portfolio')->nullable();
            $t->double('drawn_amount')->default(0);
            $t->double('contractual_rate')->nullable();
            $t->double('eir_effective_annual')->nullable();
            $t->string('origination_date')->nullable();
            $t->string('interest_start_date')->nullable();
            $t->string('moratorium_type')->nullable();
            $t->integer('moratorium_months')->nullable();
            $t->string('moratorium_from')->nullable();
            $t->double('reference_rate_at_origination')->nullable();
            $t->double('spread_over_prime')->nullable();
            $t->double('markup')->nullable();
            $t->timestamps();
        });
        $this->createLoanBookSchema();
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
    }

    /* --------------------------------------------------------------------- */
    /*  The five clean months: prior balance x rate x days in the month / 365 */
    /* --------------------------------------------------------------------- */

    /**
     * JVD Agro is a fixed 10 percent facility on a capitalising moratorium. The
     * rate is not assumed: it solves to 10.000 percent from the two ledger
     * postings alone, given that April's interest is added to the balance May
     * opens on.
     */
    public function test_jvd_agro_april_2026_reproduces_the_ledger(): void
    {
        $this->jvdAgro();

        $result = (new ContractualInterestService())->forPeriod('JVD-AGRO', '2026-04');

        $this->assertTrue($result->available, (string) $result->message);
        $this->assertSame(4692859.03, $result->interest);
        // The ledger posted 4,692,858.90.
        $this->assertEqualsWithDelta(4692858.90, $result->interest, 0.15);
        $this->assertSame(30, $result->daysCharged);
        $this->assertSame('ACT/365', $result->dayCount);
        $this->assertSame('LOAN_BOOK_PRIOR_MONTH', $result->openingBalanceSource);
        $this->assertSame(570964515.32, round($result->openingBalance, 2));
    }

    public function test_jvd_agro_may_2026_reproduces_the_ledger(): void
    {
        $this->jvdAgro();

        $result = (new ContractualInterestService())->forPeriod('JVD-AGRO', '2026-05');

        $this->assertSame(4889144.82, $result->interest);
        // The ledger posted 4,889,144.85.
        $this->assertEqualsWithDelta(4889144.85, $result->interest, 0.15);
        $this->assertSame(31, $result->daysCharged);
        // The balance May opens on is April's balance plus exactly April's
        // interest: the capitalising moratorium, in MAIIC's own loan books.
        $this->assertSame(570964515.32 + 4692859.03, round($result->openingBalance, 2));
    }

    public function test_lake_malawi_aquaculture_june_2026_reproduces_the_ledger(): void
    {
        $this->contract('LAKE-MALAWI-AQUA', ['contractual_rate' => 0.31, 'origination_date' => '2024-11-20']);
        $this->loanBook('LAKE-MALAWI-AQUA', '2026-05', 343919679.32, 31.00);
        $this->loanBook('LAKE-MALAWI-AQUA', '2026-06', 352682564.30, 31.00);

        $result = (new ContractualInterestService())->forPeriod('LAKE-MALAWI-AQUA', '2026-06');

        $this->assertSame(8762884.98, $result->interest);
        // The ledger posted 8,762,885.10.
        $this->assertEqualsWithDelta(8762885.10, $result->interest, 0.15);
        $this->assertSame(30, $result->daysCharged);
    }

    public function test_microloan_foundation_july_2026_reproduces_the_ledger(): void
    {
        $this->contract('MICROLOAN-FOUNDATION', [
            'contractual_rate' => 0.10, 'drawn_amount' => 400000000.00, 'interest_start_date' => '2025-12-18',
        ]);
        $this->loanBook('MICROLOAN-FOUNDATION', '2026-06', 421861817.00, 10.00);
        $this->loanBook('MICROLOAN-FOUNDATION', '2026-07', 425444752.98, 10.00);

        $result = (new ContractualInterestService())->forPeriod('MICROLOAN-FOUNDATION', '2026-07');

        // Posted and expected agree to the tambala on this one.
        $this->assertSame(3582935.98, $result->interest);
        $this->assertEqualsWithDelta(3582935.98, $result->interest, 0.15);
        $this->assertSame(31, $result->daysCharged);
    }

    public function test_malawi_police_sacco_may_2026_reproduces_the_ledger(): void
    {
        $this->contract('POLICE-SACCO', ['contractual_rate' => 0.31, 'origination_date' => '2025-02-14']);
        $this->loanBook('POLICE-SACCO', '2026-04', 3243071217.13, 31.00);
        $this->loanBook('POLICE-SACCO', '2026-05', 3328457283.97, 31.00);

        $result = (new ContractualInterestService())->forPeriod('POLICE-SACCO', '2026-05');

        $this->assertSame(85386066.84, $result->interest);
        // The ledger posted 85,386,066.75.
        $this->assertEqualsWithDelta(85386066.75, $result->interest, 0.15);
    }

    /* --------------------------------------------------------------------- */
    /*  The two first-disbursement months: days from the drawdown, inclusive  */
    /* --------------------------------------------------------------------- */

    /**
     * Ebenezer Midian: 314,900,900.00 drawn on 8 August 2025 at 31.00 percent.
     * 8 August to 31 August inclusive is 24 days, not 23, and that is what ties
     * the figure to the ledger.
     */
    public function test_ebenezer_midian_is_charged_twenty_four_days_of_august(): void
    {
        $this->contract('EBENEZER-MIDIAN', [
            'contractual_rate' => 0.31, 'drawn_amount' => 314900900.00, 'interest_start_date' => '2025-08-08',
        ]);

        $result = (new ContractualInterestService())->forPeriod('EBENEZER-MIDIAN', '2025-08');

        $this->assertSame(6418801.91, $result->interest);
        $this->assertSame(24, $result->daysCharged);
        $this->assertSame(31, $result->daysInPeriod);
        $this->assertTrue($result->firstDisbursementMonth);
        $this->assertSame('DISBURSEMENT', $result->openingBalanceSource);
        $this->assertSame('CONTRACT_MASTER', $result->rateSource);
        // The ledger posted 6,418,801.92: one tambala of E-Banker's rounding.
        $this->assertEqualsWithDelta(6418801.92, $result->interest, 0.02);
    }

    /**
     * Microloan Foundation: 400,000,000.00 drawn on 18 December 2025 at 10.00
     * percent, which is 14 days of December.
     */
    public function test_microloan_foundation_is_charged_fourteen_days_of_december(): void
    {
        $this->contract('MICROLOAN-FOUNDATION', [
            'contractual_rate' => 0.10, 'drawn_amount' => 400000000.00, 'interest_start_date' => '2025-12-18',
        ]);

        $result = (new ContractualInterestService())->forPeriod('MICROLOAN-FOUNDATION', '2025-12');

        $this->assertSame(1534246.58, $result->interest);
        $this->assertSame(14, $result->daysCharged);
        $this->assertTrue($result->firstDisbursementMonth);
        // The ledger posted 1,534,246.56.
        $this->assertEqualsWithDelta(1534246.56, $result->interest, 0.03);
    }

    /* --------------------------------------------------------------------- */
    /*  Why the convention matters, and the governed settings behind it       */
    /* --------------------------------------------------------------------- */

    /**
     * The reason the engine does not divide the annual rate by twelve. On the
     * same balance in February the twelfth overstates the charge by 8.63
     * percent, and a year of that does not reconcile to anything.
     */
    public function test_dividing_the_annual_rate_by_twelve_is_wrong_in_february(): void
    {
        $this->contract('FEBRUARY-TEST', ['contractual_rate' => 0.31, 'origination_date' => '2025-06-10']);
        $this->loanBook('FEBRUARY-TEST', '2026-01', 500000000.00, 31.00);
        $this->loanBook('FEBRUARY-TEST', '2026-02', 500000000.00, 31.00);

        $result = (new ContractualInterestService())->forPeriod('FEBRUARY-TEST', '2026-02');
        $twelfth = round(500000000.00 * 0.31 / 12, 2);

        $this->assertSame(28, $result->daysCharged);
        $this->assertSame(11890410.96, $result->interest);
        $this->assertSame(12916666.67, $twelfth);
        $this->assertEqualsWithDelta(1026255.71, $twelfth - $result->interest, 0.01);
        $this->assertEqualsWithDelta(8.63, ($twelfth - $result->interest) / $result->interest * 100, 0.01);
    }

    /**
     * A moratorium of type Both defers interest and capital, and the deferred
     * interest joins the balance every month. With no loan book for the months
     * in between, the engine carries the balance forward itself rather than
     * stopping, because the compounding is the governed convention.
     */
    public function test_a_capitalising_moratorium_compounds_monthly(): void
    {
        $this->contract('MORATORIUM-BOTH', [
            'contractual_rate' => 0.12, 'drawn_amount' => 100000000.00, 'interest_start_date' => '2025-08-08',
            'moratorium_type' => 'BOTH', 'moratorium_months' => 6,
        ]);
        $service = new ContractualInterestService();

        $august = $service->forPeriod('MORATORIUM-BOTH', '2025-08');
        $september = $service->forPeriod('MORATORIUM-BOTH', '2025-09');
        $october = $service->forPeriod('MORATORIUM-BOTH', '2025-10');

        $this->assertSame(789041.10, $august->interest);
        // September opens on August's balance plus exactly August's interest.
        $this->assertSame(100789041.10, round($september->openingBalance, 2));
        $this->assertSame('CAPITALISED_MORATORIUM', $september->openingBalanceSource);
        $this->assertTrue($september->capitalisingMoratoriumMonth);
        $this->assertSame(994083.69, $september->interest);
        $this->assertSame(101783124.79, round($october->openingBalance, 2));
        $this->assertSame(1037351.30, $october->interest);
    }

    /**
     * The alternative day count is a governed option, not a code change. Under
     * 30/360 every month is thirty days over 360, which is the annual rate
     * divided by twelve: the very figure ACT/365 refuses.
     */
    public function test_the_thirty_three_sixty_option_charges_a_thirty_day_month(): void
    {
        $this->jvdAgro();
        $this->approve('day_count', '30/360', '2026-01-01', 'MAIIC elected the 30/360 basis for the 2026 cycle.');

        $result = (new ContractualInterestService())->forPeriod('JVD-AGRO', '2026-04');

        $this->assertSame('30/360', $result->dayCount);
        $this->assertSame(30, $result->daysCharged);
        $this->assertSame(360, $result->denominator);
        $this->assertSame(4758037.63, $result->interest);
        $this->assertSame(round(570964515.32 * 0.10 / 12, 2), $result->interest);
    }

    /** A first part month on 30/360 runs from the drawdown day to the thirtieth. */
    public function test_a_first_disbursement_month_on_thirty_three_sixty_counts_to_the_thirtieth(): void
    {
        $this->contract('EBENEZER-MIDIAN', [
            'contractual_rate' => 0.31, 'drawn_amount' => 314900900.00, 'interest_start_date' => '2025-08-08',
        ]);
        $this->approve('day_count', '30/360', '2025-01-02', 'Testing the alternative basis before the first run.');

        $result = (new ContractualInterestService())->forPeriod('EBENEZER-MIDIAN', '2025-08');

        $this->assertSame(23, $result->daysCharged);
        $this->assertSame(6236787.27, $result->interest);
    }

    /**
     * A settings change applies from its effective date forward and never
     * restates a month already reported: April keeps the basis it was
     * calculated on, May moves.
     */
    public function test_a_day_count_change_applies_from_its_effective_date_only(): void
    {
        $this->jvdAgro();
        $this->approve('day_count', '30/360', '2026-05-01', 'Board approved the 30/360 basis with effect from May.');
        $service = new ContractualInterestService();

        $april = $service->forPeriod('JVD-AGRO', '2026-04');
        $may = $service->forPeriod('JVD-AGRO', '2026-05');

        $this->assertSame('ACT/365', $april->dayCount);
        $this->assertSame(4692859.03, $april->interest);
        $this->assertSame('30/360', $may->dayCount);
        $this->assertSame(4797144.79, $may->interest);
    }

    /** The rate for a month comes from that month's loan book where it has one. */
    public function test_the_rate_comes_from_the_loan_book_for_the_month(): void
    {
        $this->contract('RATE-SOURCE', ['contractual_rate' => 0.2510, 'origination_date' => '2025-03-01']);
        $this->loanBook('RATE-SOURCE', '2026-03', 200000000.00, 25.10);
        $this->loanBook('RATE-SOURCE', '2026-04', 200000000.00, 31.00);

        $result = (new ContractualInterestService())->forPeriod('RATE-SOURCE', '2026-04');

        $this->assertSame(0.31, $result->rate);
        $this->assertSame('LOAN_BOOK_PERIOD', $result->rateSource);
        $this->assertSame(round(200000000.00 * 0.31 * 30 / 365, 2), $result->interest);
    }

    /* --------------------------------------------------------------------- */
    /*  Failing closed: a missing input produces a named reason, never a guess */
    /* --------------------------------------------------------------------- */

    public function test_a_missing_prior_balance_fails_closed_with_its_reason(): void
    {
        $this->contract('NO-BALANCE', ['contractual_rate' => 0.10, 'origination_date' => '2025-01-06']);
        $this->loanBook('NO-BALANCE', '2026-04', 100000000.00, 10.00);

        $result = (new ContractualInterestService())->forPeriod('NO-BALANCE', '2026-04');

        $this->assertFalse($result->available);
        $this->assertSame(ContractualInterest::NO_OPENING_BALANCE, $result->reason);
        $this->assertNull($result->interest);
        $this->assertStringContainsString('2026-03', $result->message);
    }

    public function test_a_missing_rate_fails_closed_with_its_reason(): void
    {
        $this->contract('NO-RATE', ['contractual_rate' => null, 'origination_date' => '2025-01-06']);
        $this->loanBook('NO-RATE', '2026-03', 100000000.00, 0);
        $this->loanBook('NO-RATE', '2026-04', 100000000.00, 0);

        $result = (new ContractualInterestService())->forPeriod('NO-RATE', '2026-04');

        $this->assertFalse($result->available);
        $this->assertSame(ContractualInterest::NO_RATE, $result->reason);
    }

    public function test_a_month_before_the_first_disbursement_is_not_charged(): void
    {
        $this->contract('NOT-YET-DRAWN', [
            'contractual_rate' => 0.31, 'drawn_amount' => 50000000.00, 'interest_start_date' => '2026-03-10',
        ]);

        $result = (new ContractualInterestService())->forPeriod('NOT-YET-DRAWN', '2026-02');

        $this->assertFalse($result->available);
        $this->assertSame(ContractualInterest::BEFORE_FIRST_DISBURSEMENT, $result->reason);
        $this->assertStringContainsString('2026-03-10', $result->message);
    }

    public function test_an_account_with_no_contract_master_fails_closed(): void
    {
        $this->loanBook('NO-CONTRACT', '2026-03', 100000000.00, 31.00);

        $result = (new ContractualInterestService())->forPeriod('NO-CONTRACT', '2026-04');

        $this->assertFalse($result->available);
        $this->assertSame(ContractualInterest::NO_CONTRACT, $result->reason);
    }

    public function test_a_first_disbursement_month_with_no_amount_drawn_fails_closed(): void
    {
        $this->contract('NO-AMOUNT', [
            'contractual_rate' => 0.31, 'drawn_amount' => 0, 'interest_start_date' => '2026-03-10',
        ]);

        $result = (new ContractualInterestService())->forPeriod('NO-AMOUNT', '2026-03');

        $this->assertFalse($result->available);
        $this->assertSame(ContractualInterest::NO_DRAWN_AMOUNT, $result->reason);
    }

    public function test_an_unreadable_period_is_refused_rather_than_guessed(): void
    {
        $this->contract('JVD-AGRO', ['contractual_rate' => 0.10]);

        $result = (new ContractualInterestService())->forPeriod('JVD-AGRO', 'April 2026');

        $this->assertFalse($result->available);
        $this->assertSame(ContractualInterest::PERIOD_NOT_READABLE, $result->reason);
    }

    /* --------------------------------------------------------------------- */
    /*  Fixtures                                                             */
    /* --------------------------------------------------------------------- */

    /**
     * JVD Agro as the reconstruction found it: fixed 10 percent, and each
     * month's balance is the month before plus the interest charged.
     */
    private function jvdAgro(): void
    {
        $this->contract('JVD-AGRO', ['contractual_rate' => 0.10, 'origination_date' => '2025-05-08',
            'moratorium_type' => 'BOTH', 'moratorium_months' => 18]);
        $this->loanBook('JVD-AGRO', '2026-03', 570964515.32, 10.00);
        $this->loanBook('JVD-AGRO', '2026-04', 575657374.35, 10.00);
        $this->loanBook('JVD-AGRO', '2026-05', 580546519.17, 10.00);
    }

    private function contract(string $contractId, array $overrides = []): void
    {
        DB::table('contract_eir')->insert(array_merge([
            'contract_id' => $contractId,
            'portfolio' => 'MAIIC',
            'drawn_amount' => 0,
            'contractual_rate' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function loanBook(string $contractId, string $period, float $balance, float $rate, array $overrides = []): void
    {
        DB::table('loan_books')->insert(array_merge([
            'contract_id' => $contractId,
            'customer_name' => $contractId . ' Limited',
            'reporting_period' => $period,
            'interest_rate' => $rate,
            'carrying_amount' => $balance,
            'principal_balance' => $balance,
            'disbursed' => $balance,
            'create_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /** Approve a governance change, maker and checker, as the screen would. */
    private function approve(string $key, string $value, string $effectiveFrom, string $reason): void
    {
        $governance = new GovernanceService();
        $proposal = $governance->propose($key, $value, $effectiveFrom, $reason, 10);
        $governance->approve($proposal->id, 20);
    }
}
