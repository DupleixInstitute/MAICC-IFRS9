<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\TrialBalanceImportService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Parser rules pinned against the delivered files and the audited control
 * totals in spec Appendix A.
 *
 * These numbers are MAIIC's audited figures, not ours, so a parser that
 * reproduces them has been verified rather than merely reviewed. Two separate
 * parsing attempts on 2026-08-19 produced wrong answers before the §3.5.5 rules
 * were established, which is why they are pinned here rather than trusted.
 *
 * The corpus lives outside the repo (client data, unanonymised - open item #15),
 * so every test skips rather than fails when it is absent. A red suite on a
 * machine that simply has not synced OneDrive teaches people to ignore red.
 */
class TrialBalanceImportServiceTest extends TestCase
{
    protected $seed = false;

    private const CORPUS_ENV = 'MAIIC_TB_CORPUS_PATH';

    private const DEFAULT_CORPUS = 'C:/Users/Hp/Downloads/MAIIC/Dupleix 2026/Dupleix 2026';

    private const DEFAULT_AFS = 'C:/Users/Hp/Downloads/MAIIC/AFS Final TB and Initial TB Mapped to E-Banker TB for MAIIC for December 2025.xlsx';

    private function corpusPath(string $file): string
    {
        return rtrim(env(self::CORPUS_ENV, self::DEFAULT_CORPUS), '/\\') . '/' . $file;
    }

    private function skipUnless(string $path): void
    {
        if (! is_file($path)) {
            $this->markTestSkipped('Client corpus not available at ' . $path . '. Set ' . self::CORPUS_ENV . '.');
        }
    }

    private function service(): TrialBalanceImportService
    {
        return new TrialBalanceImportService;
    }

    /** Every delivered month, with its audited period stamp and line count. */
    public static function deliveredMonths(): array
    {
        return [
            'Jan 2025' => ['Trial Balance_31 January 2025.xls', '2025-01-01', 168, 116353847465.56],
            'Feb 2025' => ['Trial Balance_28 February 2025.xls', '2025-02-01', 175, 120540016525.93],
            'Mar 2025' => ['Trial Balance_31 March 2025.xls', '2025-03-01', 178, 123837233584.63],
            'Apr 2025' => ['Trial Balance_30 April 2025.xls', '2025-04-01', 182, 124782932834.86],
            'May 2025' => ['Trial Balance_31 May 2025.xls', '2025-05-01', 181, 126383556669.06],
            'Jun 2025' => ['Trial Balance_30 June 2025.xls', '2025-06-01', 182, 127749807201.48],
            'Jul 2025' => ['Trial Balance_31 July 2025.xls', '2025-07-01', 185, 131160665726.90],
            'Aug 2025' => ['Trial Balance_31 August 2025.xls', '2025-08-01', 185, 132214844838.96],
            'Sep 2025' => ['Trial Balance_30 September 2025.xls', '2025-09-01', 185, 118719850964.87],
            'Oct 2025' => ['Trial Balance_31 October 2025.xls', '2025-10-01', 188, 120022445302.78],
            'Nov 2025' => ['Trial Balance_30 November 2025.xls', '2025-11-01', 190, 120485664455.66],
            'Dec 2025' => ['Trial Balance_31 December 2025.xls', '2025-12-01', 121, 117370478433.36],
            'Jan 2026' => ['Trial Balance_31 January 2026.xls', '2026-01-01', 168, 159006685297.49],
            'Feb 2026' => ['Trial Balance_28 February 2026.xls', '2026-02-01', 175, 161470300734.58],
            'Mar 2026' => ['Trial Balance_31 March 2026.xls', '2026-03-01', 179, 162593942090.98],
            'Apr 2026' => ['Trial Balance_30 April 2026.xls', '2026-04-01', 194, 164096681854.01],
            'May 2026' => ['Trial Balance_31 May 2026.xls', '2026-05-01', 193, 165752075296.63],
            'Jun 2026' => ['Trial Balance_30 June 2026.xls', '2026-06-01', 192, 161695130320.31],
            'Jul 2026' => ['Trial Balance_31 July 2026.xls', '2026-07-01', 194, 163317378178.63],
        ];
    }

    #[DataProvider('deliveredMonths')]
    public function test_every_delivered_month_parses_balances_and_ties_to_its_own_grand_total(
        string $file,
        string $expectedPeriod,
        int $expectedLines,
        float $expectedTotal
    ): void {
        $path = $this->corpusPath($file);
        $this->skipUnless($path);

        $result = $this->service()->parse($path);

        $this->assertSame($expectedPeriod, $result['period'], "$file period");
        $this->assertCount($expectedLines, $result['rows'], "$file GL line count");
        $this->assertEqualsWithDelta($expectedTotal, $result['debit'], 0.01, "$file debits");
        $this->assertEqualsWithDelta($expectedTotal, $result['credit'], 0.01, "$file credits");

        // The file's own assertion of its total. Ties here means no row was
        // missed AND the total row itself was not counted as data.
        $this->assertEqualsWithDelta($expectedTotal, (float) $result['grand_total'], 0.01, "$file grand total");
    }

    /**
     * The 234.74bn error, pinned.
     *
     * December's audited total is 117,370,478,433.36. Counting the file's own
     * `Grand Total :` row as a balance yields exactly twice that, which is the
     * figure that circulated on 2026-08-18 (§3.4.6). Asserting the parser is
     * NOT off by a factor of two states the failure mode plainly enough that
     * someone re-introducing it sees why the test exists.
     */
    public function test_the_grand_total_row_is_excluded_rather_than_counted_as_a_balance(): void
    {
        $path = $this->corpusPath('Trial Balance_31 December 2025.xls');
        $this->skipUnless($path);

        $result = $this->service()->parse($path);

        $this->assertEqualsWithDelta(117370478433.36, $result['debit'], 0.01);
        $this->assertGreaterThan(
            1.0,
            abs(234740956866.72 - $result['debit']),
            'Parsed total equals the doubled figure: the Grand Total row is being counted as a balance.'
        );

        foreach ($result['rows'] as $row) {
            $this->assertMatchesRegularExpression('/^\d+$/', $row['gl_code']);
        }
    }

    /**
     * The standalone December file is POST-closing: the P&L has already been
     * swept into 3200 Retained Earnings, so it carries 121 lines and not one
     * income account (§3.4.2). Ingesting it as December's income statement
     * reports the year's most material month as zero, silently.
     */
    public function test_the_standalone_december_file_carries_no_profit_and_loss_accounts(): void
    {
        $path = $this->corpusPath('Trial Balance_31 December 2025.xls');
        $this->skipUnless($path);

        $result = $this->service()->parse($path);

        $plCodes = array_filter(
            $result['rows'],
            fn ($row) => in_array($row['gl_code'][0], ['4', '5', '6'], true)
        );

        $this->assertCount(0, $plCodes, 'The post-closing December file must carry no 4xxx/5xxx/6xxx accounts.');
        $this->assertCount(121, $result['rows']);
    }

    /**
     * December's income statement comes from the AFS workbook instead (§3.4.2),
     * and its audited totals are Appendix A fixtures.
     */
    public function test_the_afs_pre_closing_december_sheet_carries_the_income_statement(): void
    {
        $path = env('MAIIC_AFS_BRIDGE_PATH', self::DEFAULT_AFS);
        $this->skipUnless($path);

        $result = $this->service()->parse($path, '2025-12-01', 'Final E-Banker TB Dec 2025');

        $this->assertCount(191, $result['rows']);
        $this->assertEqualsWithDelta(122650199563.49, $result['debit'], 0.01);
        $this->assertEqualsWithDelta(122650199563.49, $result['credit'], 0.01);

        $byCode = collect($result['rows'])->keyBy('gl_code');
        $net = fn (string $code) => (float) $byCode[$code]['credit'] - (float) $byCode[$code]['debit'];

        // Audited, and identical to the GL transaction-spool control totals in
        // Appendix A - two independent sources agreeing to the kwacha.
        $this->assertEqualsWithDelta(171128135.00, $net('4871'), 0.01, 'Legal fees');
        $this->assertEqualsWithDelta(311342792.00, $net('4873'), 0.01, 'Arrangement fees');
        $this->assertEqualsWithDelta(554447006.83, $net('4215'), 0.01, 'MAIIC agricultural interest');
        $this->assertEqualsWithDelta(1513020260.19, $net('4216'), 0.01, 'MAIIC industrial interest');
        $this->assertEqualsWithDelta(162692860.92, $net('4218'), 0.01, 'FInES agricultural interest');
        $this->assertEqualsWithDelta(285136670.80, $net('4219'), 0.01, 'FInES industrial interest');
    }

    /**
     * The sheet has no date stamp, so a caller who forgets the period must be
     * told rather than silently given a wrong one.
     */
    public function test_a_sheet_without_a_period_stamp_is_refused_when_no_period_is_supplied(): void
    {
        $path = env('MAIIC_AFS_BRIDGE_PATH', self::DEFAULT_AFS);
        $this->skipUnless($path);

        $this->expectExceptionMessageMatches('/No period stamp/');
        $this->service()->parse($path, null, 'Final E-Banker TB Dec 2025');
    }

    /**
     * Open item #22, pinned as observed fact rather than as interpretation.
     *
     * Every file named for the last day of a month carries an internal stamp of
     * the FIRST. Until MAIIC confirms in writing that this is a period label,
     * this test records what the files actually say, so if the convention turns
     * out to be different the failure lands here rather than in a variance
     * nobody can explain a month later.
     */
    public function test_every_file_stamps_the_first_of_the_month_it_is_named_for(): void
    {
        $any = false;
        foreach (self::deliveredMonths() as $label => [$file, $expectedPeriod]) {
            $path = $this->corpusPath($file);
            if (! is_file($path)) {
                continue;
            }
            $any = true;
            $result = $this->service()->parse($path);
            $this->assertSame($expectedPeriod, $result['source_period_stamp'], "$label stamp");
        }

        if (! $any) {
            $this->markTestSkipped('Client corpus not available.');
        }
    }
}
