p = 'C:/xampp/htdocs/MAICC-IFRS9/app/Services/Eir/RestructureCandidateService.php'
s = open(p, encoding='utf-8').read()


def rep(a, b):
    global s
    assert s.count(a) == 1, a[:80]
    s = s.replace(a, b)


rep("""        $candidates = array_merge($this->fromChartHistory(), $this->fromCopies());""",
    """        $candidates = array_merge($this->fromChartHistory(), $this->fromCopies(), $this->fromOpeningBalance());""")

rep(""" * The 28 September copy is dated 2024:""",
""" *  - The migration worksheet (CV_10, OPENING_BAL, pack 9 of 14 October 2026):
 *    the loans taken on at 31 July 2024, eight of them with a RESTRUCTURE_DATE
 *    and RESTRUCURE_AMT, the new maturity and tenor. These are MAIIC's own
 *    record of a restructure, so the suggestion is a modification, but they
 *    are still candidates until a person confirms them. Every one predates the
 *    take-on: the balance taken on (NEWDISBURS_AMT, the restructured amount)
 *    and the take-on EIR (decision D41, solved on the workbook's chain) already
 *    carry it, so a confirmed pre-take-on restructure is history and
 *    disclosure (the RBM return, paragraph 16(1)), never a new modification
 *    gain or loss after the take-on (RestructureRegisterService::changesAccounting).
 *    The worksheet holds neither the carrying amount before the restructure
 *    nor the old cash flows, so the 10 percent test cannot be re-performed
 *    from it; the candidate says so.
 *
 * The 28 September copy is dated 2024:""")

rep("""    /** @return list<array> accepted MODIFICATION_LOG edits to a loan's terms, one candidate per account and day */""",
r"""    /** @return list<array> the restructures the migration worksheet records (CV_10), all before the take-on */
    public function fromOpeningBalance(): array
    {
        $inScope = array_flip(array_map(fn ($c) => (string) $c, DB::table('loan_books')->distinct()->pluck('contract_id')->all()));
        $out = [];
        foreach ($this->zone->family(['CV_10']) as $row) {
            $p = $row['payload'];
            $account = trim((string) ($p['ACCNO'] ?? $row['account'] ?? ''));
            $date = InstalmentChartHistory::date((string) ($p['RESTRUCTURE_DATE'] ?? ''));
            $amount = abs(self::num($p['RESTRUCURE_AMT'] ?? 0));
            if ($account === '' || $date === null || $amount <= 0 || ! isset($inScope[ltrim($account, '0')])) {
                continue;
            }
            $maturity = InstalmentChartHistory::date((string) ($p['MATURITY_DATE'] ?? ''));
            $rate = self::num($p['INT_RATE'] ?? 0);
            $rate = $rate > 0 && $rate < 1 ? $rate * 100 : $rate; // the worksheet writes 0.33 for 33 percent
            $tenor = trim((string) ($p['TENOR_YRS'] ?? ''));
            $sanctioned = abs(self::num($p['SANCTION_AMOUNT'] ?? 0));
            $disbursed = abs(self::num($p['DISBURS_AMT'] ?? 0));
            $out[] = [
                'detection_key' => "OPENBAL|{$account}|{$date}", 'contract_id' => ltrim($account, '0'), 'account' => $account, 'event_date' => $date,
                'source' => 'OPENING_BAL',
                'source_reference' => sprintf('Migration worksheet OPENING_BAL (CV_10, row %s): restructured on %s for %s, maturity %s, tenor %s years', (string) ($p['OP_BAL_DET_ID'] ?? $row['source_key']),
                    $date, self::money($amount), $maturity ?? '-', $tenor === '' ? '-' : rtrim(rtrim($tenor, '0'), '.')),
                'suggested_class' => 'MODIFICATION',
                'suggestion_reason' => 'MAIIC\'s migration worksheet records this restructure, so it is suggested as a modification. It predates the take-on of ' . \App\Services\Ebanker\TakeonLandingService::TAKEON_DATE
                    . ': the balance taken on and the take-on EIR already reflect it, so once confirmed it is history and disclosure (the RBM return, paragraph 16(1)), not a modification gain or loss after the take-on. The worksheet holds neither the carrying amount before the restructure nor the old cash flows, so the 10 percent test cannot be re-performed from it.',
                'old_terms' => ['sanctioned' => round($sanctioned, 2), 'disbursed' => round($disbursed, 2), 'sanction_date' => InstalmentChartHistory::date((string) ($p['SANCTION_DATE'] ?? ''))],
                'new_terms' => ['restructured_amount' => round($amount, 2), 'maturity' => $maturity, 'tenor_years' => $tenor === '' ? null : (float) $tenor,
                    'balance_at_takeon' => round(abs(self::num($p['BALANCE_AMT'] ?? 0)), 2), 'taken_on_as' => round(abs(self::num($p['NEWDISBURS_AMT'] ?? 0)), 2)],
                'new_maturity' => $maturity, 'new_balance' => round($amount, 2), 'new_rate' => $rate > 0 ? round($rate, 5) : null, 'new_flows' => null,
            ];
        }

        return $out;
    }

    /** @return list<array> accepted MODIFICATION_LOG edits to a loan's terms, one candidate per account and day */""")

open(p, 'w', encoding='utf-8', newline='\n').write(s)

# the register: a pre-take-on event is history only
p = 'C:/xampp/htdocs/MAICC-IFRS9/app/Services/Eir/RestructureRegisterService.php'
s = open(p, encoding='utf-8').read()
rep("""    /** Whether a confirmed event of this class changes the gross carrying amount. */
    public function changesAccounting(object $e): bool
    {
        return match ((string) $e->classification) {""",
"""    /**
     * Whether a confirmed event of this class changes the gross carrying amount. An event on
     * or before the take-on of 31 July 2024 never does: the engine's roll-forward starts in
     * August 2024 from the balance taken on, and that balance and the take-on EIR (D41)
     * already carry a restructure made before it (the migration worksheet, CV_10). It is
     * history and disclosure (the RBM return, paragraph 16(1)), not a new gain or loss.
     */
    public function changesAccounting(object $e): bool
    {
        if (self::beforeTakeon($e->event_date ?? null)) {
            return false;
        }

        return match ((string) $e->classification) {""")
rep("""    private function isFloating(string $contractId): bool""",
"""    public static function beforeTakeon(?string $date): bool
    {
        return $date !== null && $date !== '' && substr($date, 0, 10) <= \\App\\Services\\Ebanker\\TakeonLandingService::TAKEON_DATE;
    }

    private function isFloating(string $contractId): bool""")
rep("""        $probe = (object) ['classification' => $class, 'contract_id' => $e->contract_id];""",
    """        $probe = (object) ['classification' => $class, 'contract_id' => $e->contract_id, 'event_date' => $e->event_date];""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
