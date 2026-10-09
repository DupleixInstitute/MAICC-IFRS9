import io, os
os.chdir(r'C:\xampp\htdocs\MAICC-IFRS9')

def sub(path, pairs):
    s = io.open(path, encoding='utf-8').read()
    for old, new in pairs:
        assert old in s, (path, old[:70])
        s = s.replace(old, new, 1)
    io.open(path, 'w', encoding='utf-8', newline='\n').write(s)

sub('app/Services/Eir/EirRevenueService.php', [
# C6: the allowance the net basis nets is the opening allowance the engine wrote
("""                $allowance = max(0.0, (float) ($loan->expected_loss_provision ?? 0));""",
"""                $allowance = $this->openingAllowance($contractId, $period, $loan);"""),
("""    private function loanSnapshot(string $contractId, string $period): ?object
    {""",
"""    /**
     * The loss allowance the net basis nets off (IFRS 9 5.4.1(b)): the
     * allowance in the books at the start of the month, which is the ECL the
     * engine wrote on the prior month's loan-book row. The current month's
     * ECL stands in when the prior month has none; the legacy
     * expected_loss_provision column, which only a legacy importer fills, is
     * the last resort. System audit of 9 October 2026, finding C6: the engine
     * read only that column, nothing in the governed chain wrote it, and
     * Stage 3 interest accrued on gross while the setting said net.
     */
    private function openingAllowance(string $contractId, string $period, object $loan): float
    {
        $prior = CarbonImmutable::createFromFormat('Y-m-d', $period . '-01')->subMonth()->format('Y-m');
        $previous = $this->loanSnapshot($contractId, $prior);
        foreach ([$previous->ecl_value ?? null, $loan->ecl_value ?? null, $loan->expected_loss_provision ?? null] as $candidate) {
            if ($candidate !== null) {
                return max(0.0, (float) $candidate);
            }
        }

        return 0.0;
    }

    private function loanSnapshot(string $contractId, string $period): ?object
    {"""),
# C5: the schedule stands in only outside the feed's coverage
("""        $window = DB::table('eir_actual_transactions')->where('contract_id', $contractId)
            ->selectRaw('MIN(transaction_date) as first_txn, MAX(transaction_date) as last_txn')->first();
        $covered = $window && $window->first_txn && $window->last_txn
            && $start->toDateString() <= substr((string) $window->last_txn, 0, 10)
            && $end->toDateString() >= substr((string) $window->first_txn, 0, 10);
""",
"""        // The ledger covers a month when the feed's postings run past its
        // start and the contract existed by its end; a covered month with no
        // receipt is zero cash, not the schedule. System audit of 9 October
        // 2026, finding C5: the window was the contract's own first and last
        // posting, so every month after a borrower's last receipt fell back to
        // the schedule and the roll-forward retired a loan nobody was paying.
        static $feedLast = null;
        $feedLast = $feedLast ?? (DB::table('eir_actual_transactions')->max('transaction_date') ?? '');
        $window = DB::table('eir_actual_transactions')->where('contract_id', $contractId)
            ->selectRaw('MIN(transaction_date) as first_txn')->first();
        $contractStart = $window && $window->first_txn ? substr((string) $window->first_txn, 0, 10) : null;
        $covered = $feedLast !== '' && $contractStart !== null
            && $start->toDateString() <= substr((string) $feedLast, 0, 10)
            && $end->toDateString() >= $contractStart;
"""),
])

# Bootstrap: the pre-FLI ECL for every period before the revenue step (C6, M17)
sub('app/Console/Commands/Bootstrap.php', [
("""        $before = DB::table('eir_amortisation')->count();
        $errors = [];
        foreach ($periods as $p) {
            $code = Artisan::call('eir:run-revenue', ['period' => $p, '--user' => $user]);""",
"""        // 4, 5, 7 for every period, pre-FLI: the PD, the LGD and the ECL of each
        // month, so the revenue step that follows nets the opening allowance on
        // Stage 3 (audit C6) and the chain runs from the first full month, not
        // the last (spec 6.10, audit M17). A month without a twelve-month window
        // or a Stage 3 cohort is noted and left without an allowance.
        $portfolio = (int) DB::table('loan_portfolios')->orderBy('id')->value('id');
        $eclPeriods = []; $eclSkipped = [];
        foreach ($periods as $p) {
            try {
                app(\\App\\Services\\Pd\\PdEngineService::class)->run($p, $portfolio, 12, 'bootstrap', $user);
                app(\\App\\Services\\Lgd\\LgdEngineService::class)->run($p, $portfolio, 12, $user, LoanBookBuildService::BOOTSTRAP_LABEL);
                Artisan::call('ifrs9:recalculate-ecl', ['period' => $p, '--level' => 'portfolio', '--portfolio' => $portfolio, '--pd' => 'pd_prefli']);
                $eclPeriods[] = $p;
            } catch (Throwable $e) {
                $eclSkipped[$p] = substr($e->getMessage(), 0, 60);
            }
        }
        $this->note('6.7 ECL pre-FLI by period', count($eclPeriods) . ' periods with a PD, an LGD and an ECL' . ($eclPeriods !== [] ? " ({$eclPeriods[0]}..{$eclPeriods[count($eclPeriods) - 1]})" : '') . '; ' . count($eclSkipped) . ' without' . ($eclSkipped !== [] ? ': ' . implode(' | ', array_slice(array_map(fn ($k, $v) => "{$k} {$v}", array_keys($eclSkipped), $eclSkipped), 0, 2)) : ''));

        $before = DB::table('eir_amortisation')->count();
        $errors = [];
        foreach ($periods as $p) {
            $code = Artisan::call('eir:run-revenue', ['period' => $p, '--user' => $user]);"""),
("""        // 4 PD: the transition matrix over the last twelve staged months, the Stage 3 probabilities to the loan book
        $portfolio = (int) DB::table('loan_portfolios')->orderBy('id')->value('id');
        try {""",
"""        // 4 PD: the transition matrix over the last twelve staged months, the Stage 3 probabilities to the loan book
        try {"""),
])
print('ok')
