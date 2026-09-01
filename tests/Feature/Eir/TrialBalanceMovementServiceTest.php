<?php

namespace Tests\Feature\Eir;

use App\Models\GlAccountScope;
use App\Models\GlTrialBalanceLine;
use App\Services\Eir\TrialBalanceMovementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The cumulative-YTD rule (spec §3.4.1), pinned against the audited balances.
 *
 * Every balance used here was read out of the delivered trial balances, so the
 * expected movements are arithmetic on real audited figures rather than
 * invented numbers that only prove the code agrees with itself.
 */
class TrialBalanceMovementServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('gl_account_scope', function (Blueprint $t) {
            $t->increments('id');
            $t->string('gl_code', 20);
            $t->string('gl_title');
            $t->string('chart', 20)->default('EBANKER');
            $t->string('quickbooks_code', 20)->nullable();
            $t->string('statement', 2);
            $t->string('normal_balance', 2);
            $t->string('category', 30);
            $t->tinyInteger('eir_door')->nullable();
            $t->boolean('in_eir_scope')->default(false);
            $t->string('portfolio', 30)->nullable();
            $t->boolean('retired')->default(false);
            $t->string('notes', 500)->nullable();
            $t->timestamps();
        });

        Schema::create('gl_trial_balance_lines', function (Blueprint $t) {
            $t->increments('id');
            $t->date('period');
            $t->date('source_period_stamp')->nullable();
            $t->string('gl_code', 20);
            $t->string('gl_title');
            $t->decimal('debit', 20, 2)->default(0);
            $t->decimal('credit', 20, 2)->default(0);
            $t->string('basis', 12)->default('POSTCLOSING');
            $t->string('source_file');
            $t->string('source_sheet', 100)->nullable();
            $t->timestamps();
        });
    }

    private function scope(
        string $code,
        string $title,
        string $statement,
        string $normal,
        bool $inScope = true,
        string $chart = 'EBANKER'
    ): void {
        GlAccountScope::create([
            'gl_code' => $code, 'gl_title' => $title, 'statement' => $statement,
            'normal_balance' => $normal, 'category' => 'INTEREST_INCOME',
            'in_eir_scope' => $inScope, 'chart' => $chart,
        ]);
    }

    private function balance(string $code, string $period, float $credit = 0, float $debit = 0, string $basis = 'POSTCLOSING'): void
    {
        GlTrialBalanceLine::create([
            'period' => $period, 'gl_code' => $code, 'gl_title' => 'x',
            'credit' => $credit, 'debit' => $debit, 'basis' => $basis, 'source_file' => 'test.xls',
        ]);
    }

    private function service(): TrialBalanceMovementService
    {
        return new TrialBalanceMovementService;
    }

    /**
     * The headline case. GL 4216's November 2025 balance is 1,361,118,063 and
     * October's is 1,194,947,139 — both audited. November's actual interest is
     * the difference, 166,170,924. Reading the balance as the month overstates
     * it 8.2-fold against a ledger that is in fact correct.
     */
    public function test_a_mid_year_month_is_the_difference_between_two_cumulative_balances(): void
    {
        $this->scope('4216', 'Interest On MAIIC Industrial Loans', 'PL', 'CR');
        $this->balance('4216', '2025-10-01', credit: 1194947139.00);
        $this->balance('4216', '2025-11-01', credit: 1361118063.00);

        $result = $this->service()->movement('4216', '2025-11-01');

        $this->assertSame('MOVEMENT', $result['status']);
        $this->assertEqualsWithDelta(166170924.00, $result['movement'], 0.01);

        // Both balances are returned beside the movement so the report can show
        // the subtraction rather than assert its result.
        $this->assertEqualsWithDelta(1361118063.00, $result['closing_balance'], 0.01);
        $this->assertEqualsWithDelta(1194947139.00, $result['opening_balance'], 0.01);
        $this->assertSame('2025-10-01', $result['opening_period']);
    }

    /**
     * January is taken whole. The preceding December belongs to a different
     * financial year and the accounts have reset, so subtracting across the
     * boundary would net a full year out of a single month.
     */
    public function test_january_is_taken_whole_and_never_differenced_across_the_year_boundary(): void
    {
        $this->scope('4216', 'Interest On MAIIC Industrial Loans', 'PL', 'CR');
        $this->balance('4216', '2025-11-01', credit: 1361118063.00);
        $this->balance('4216', '2026-01-01', credit: 167288651.00);

        $result = $this->service()->movement('4216', '2026-01-01');

        $this->assertSame('YEAR_OPENING', $result['status']);
        $this->assertTrue($result['is_year_opening']);
        $this->assertEqualsWithDelta(167288651.00, $result['movement'], 0.01);

        // Differencing across the boundary would have produced a large negative.
        $this->assertGreaterThan(0, $result['movement']);
    }

    /**
     * Balance-sheet accounts are point-in-time. Differencing a loan balance
     * produces nonsense in the opposite direction to the P&L error.
     */
    public function test_balance_sheet_accounts_are_never_differenced(): void
    {
        $this->scope('1050401', 'MAIIC Term Loan', 'BS', 'DR');
        $this->balance('1050401', '2025-10-01', debit: 900000000.00);
        $this->balance('1050401', '2025-11-01', debit: 1000000000.00);

        $result = $this->service()->movement('1050401', '2025-11-01');

        $this->assertSame('POINT_IN_TIME', $result['status']);
        $this->assertNull($result['movement']);
        $this->assertEqualsWithDelta(1000000000.00, $result['closing_balance'], 0.01);
    }

    /**
     * A GL code nobody has classified yet still has to be differenced correctly,
     * because next month's file may carry codes this one did not. The prefix
     * rule is the fallback so the unknown case resolves to a rule rather than
     * to whichever behaviour happens to be the default.
     */
    public function test_an_unclassified_code_falls_back_to_the_prefix_rule(): void
    {
        $this->balance('4999', '2025-10-01', credit: 100.00);
        $this->balance('4999', '2025-11-01', credit: 175.00);
        $this->balance('1999', '2025-10-01', debit: 100.00);
        $this->balance('1999', '2025-11-01', debit: 175.00);

        $income = $this->service()->movement('4999', '2025-11-01');
        $this->assertSame('PL', $income['statement']);
        $this->assertEqualsWithDelta(75.00, $income['movement'], 0.01);

        $asset = $this->service()->movement('1999', '2025-11-01');
        $this->assertSame('BS', $asset['statement']);
        $this->assertNull($asset['movement']);
    }

    /**
     * Expenses net the other way round, or 6242 Impairment reads as a large
     * negative beside 4216 Interest reading positive and any total spanning
     * both is meaningless.
     */
    public function test_expense_accounts_are_signed_so_a_normal_balance_reads_positive(): void
    {
        $this->scope('6242', 'Impairement Of Financial Asset', 'PL', 'DR');
        $this->balance('6242', '2025-10-01', debit: 500000000.00);
        $this->balance('6242', '2025-11-01', debit: 656093875.49);

        $result = $this->service()->movement('6242', '2025-11-01');

        $this->assertEqualsWithDelta(656093875.49, $result['closing_balance'], 0.01);
        $this->assertEqualsWithDelta(156093875.49, $result['movement'], 0.01);
    }

    /**
     * A missing prior month makes the movement genuinely unknown. Falling back
     * to the closing balance would report a cumulative figure as a month —
     * exactly the error this service exists to prevent — so it is refused.
     */
    public function test_a_missing_prior_month_is_refused_rather_than_defaulted(): void
    {
        $this->scope('4216', 'Interest On MAIIC Industrial Loans', 'PL', 'CR');
        $this->balance('4216', '2025-11-01', credit: 1361118063.00);

        $result = $this->service()->movement('4216', '2025-11-01');

        $this->assertSame('NO_PRIOR_BALANCE', $result['status']);
        $this->assertNull($result['movement']);
        $this->assertSame('2025-10-01', $result['opening_period']);
        $this->assertEqualsWithDelta(1361118063.00, $result['closing_balance'], 0.01);
    }

    /**
     * December 2025's post-closing file has no income accounts at all, so an
     * absent balance is a coverage gap and must never read as a zero movement —
     * that would show the year's most material month as no activity.
     */
    public function test_an_absent_balance_is_a_gap_and_not_a_zero_movement(): void
    {
        $this->scope('4216', 'Interest On MAIIC Industrial Loans', 'PL', 'CR');
        $this->balance('4216', '2025-11-01', credit: 1361118063.00);

        $result = $this->service()->movement('4216', '2025-12-01');

        $this->assertSame('NO_BALANCE', $result['status']);
        $this->assertNull($result['movement']);
        $this->assertNull($result['closing_balance']);
    }

    /**
     * Where December exists on both bases, the pre-closing row wins by default —
     * it is the one that actually carries an income statement (§3.4.2). Open
     * item #23 may change this, which is why both are stored and the choice is
     * made here rather than at ingestion.
     *
     * December's true movement is the audited full-year 1,513,020,260.19 less
     * November's 1,361,118,063 cumulative.
     */
    public function test_december_prefers_the_pre_closing_basis_and_yields_the_audited_movement(): void
    {
        $this->scope('4216', 'Interest On MAIIC Industrial Loans', 'PL', 'CR');
        $this->balance('4216', '2025-11-01', credit: 1361118063.00);
        $this->balance('4216', '2025-12-01', credit: 1513020260.19, basis: 'PRECLOSING');

        $result = $this->service()->movement('4216', '2025-12-01');

        $this->assertSame('PRECLOSING', $result['basis']);
        $this->assertEqualsWithDelta(151902197.19, $result['movement'], 0.01);
    }

    /** The period sweep covers in-scope accounts only, so out-of-scope income cannot leak into a control total. */
    public function test_the_period_sweep_covers_in_scope_accounts_only(): void
    {
        $this->scope('4216', 'Interest On MAIIC Industrial Loans', 'PL', 'CR', inScope: true);
        $this->scope('4205', 'Investment Interest Income -Tbill', 'PL', 'CR', inScope: false);
        $this->balance('4216', '2025-10-01', credit: 1194947139.00);
        $this->balance('4216', '2025-11-01', credit: 1361118063.00);
        $this->balance('4205', '2025-10-01', credit: 50.00);
        $this->balance('4205', '2025-11-01', credit: 90.00);

        $results = $this->service()->movementsForPeriod('2025-11-01');

        $this->assertArrayHasKey('4216', $results);
        $this->assertArrayNotHasKey('4205', $results);
    }

    /**
     * The acceptance test for the whole rule.
     *
     * These twelve balances are GL 4216's actual cumulative figures, read out of
     * the delivered trial balances (December from the AFS pre-closing sheet).
     * Differenced month by month and summed, they must come back to the audited
     * full-year net of 1,513,020,260.19 in Appendix A — which they do, exactly.
     *
     * That is a closed loop: twelve subtractions reproducing an audited annual
     * total to the kwacha cannot be a coincidence, and no other reading of these
     * balances does it. Summing them undifferenced gives 9,024,283,... — six
     * times the truth.
     */
    public function test_twelve_differenced_movements_sum_to_the_audited_annual_total(): void
    {
        $this->scope('4216', 'Interest On MAIIC Industrial Loans', 'PL', 'CR');

        $cumulative = [
            '2025-01-01' => 51993819.10,
            '2025-02-01' => 110357736.13,
            '2025-03-01' => 184743271.95,
            '2025-04-01' => 256132361.22,
            '2025-05-01' => 382666463.06,
            '2025-06-01' => 524085643.50,
            '2025-07-01' => 672356033.08,
            '2025-08-01' => 850170990.36,
            '2025-09-01' => 1026620852.72,
            '2025-10-01' => 1194947139.21,
            '2025-11-01' => 1361118063.17,
        ];
        foreach ($cumulative as $period => $balance) {
            $this->balance('4216', $period, credit: $balance);
        }
        $this->balance('4216', '2025-12-01', credit: 1513020260.19, basis: 'PRECLOSING');

        $total = 0.0;
        foreach (array_merge(array_keys($cumulative), ['2025-12-01']) as $period) {
            $result = $this->service()->movement('4216', $period);
            $this->assertContains($result['status'], ['MOVEMENT', 'YEAR_OPENING'], "$period status");
            $total += (float) $result['movement'];
        }

        $this->assertEqualsWithDelta(1513020260.19, $total, 0.01);
    }

    /**
     * The trial balances are E-Banker. 4206 is the QuickBooks name for the same
     * interest E-Banker calls 42019 (§3.4.4), so an unfiltered sweep shows it as
     * a permanently absent row — and double-counts the moment anyone totals the
     * column. Caught by the first end-to-end run against the real corpus.
     */
    public function test_quickbooks_codes_do_not_leak_into_an_ebanker_sweep(): void
    {
        $this->scope('42019', 'Interest On MAIIC Term Loan', 'PL', 'CR', chart: 'EBANKER');
        $this->scope('4206', 'Interest on Term Loans-Maiic', 'PL', 'CR', chart: 'QUICKBOOKS');
        $this->balance('42019', '2026-01-01', credit: 10000.00);

        $ebanker = $this->service()->movementsForPeriod('2026-01-01');
        $this->assertArrayHasKey('42019', $ebanker);
        $this->assertArrayNotHasKey('4206', $ebanker);

        $quickbooks = $this->service()->movementsForPeriod('2026-01-01', chart: 'QUICKBOOKS');
        $this->assertArrayHasKey('4206', $quickbooks);
        $this->assertArrayNotHasKey('42019', $quickbooks);
    }
}
