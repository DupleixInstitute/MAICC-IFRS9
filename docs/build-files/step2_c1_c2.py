import io, os
os.chdir(r'C:\xampp\htdocs\MAICC-IFRS9')

def sub(path, pairs):
    s = io.open(path, encoding='utf-8').read()
    for old, new in pairs:
        assert old in s, (path, old[:70])
        s = s.replace(old, new, 1)
    io.open(path, 'w', encoding='utf-8', newline='\n').write(s)

# 1. POWER on sqlite, so the engines' SQL runs the same under the tests
sub('app/Providers/AppServiceProvider.php', [
("""    public function boot()
    {
        Schema::defaultStringLength(199);
""",
"""    public function boot()
    {
        Schema::defaultStringLength(199);

        // The engines use POWER() in SQL (lifetime PD from the 12-month PD over
        // the remaining tenor). MySQL has it; sqlite, which the tests run on,
        // does not, so it is registered on every sqlite connection.
        \\Illuminate\\Support\\Facades\\Event::listen(\\Illuminate\\Database\\Events\\ConnectionEstablished::class, function ($event) {
            if ($event->connection->getDriverName() === 'sqlite') {
                $event->connection->getPdo()->sqliteCreateFunction('POWER', fn ($base, $exp) => pow((float) $base, (float) $exp), 2);
            }
        });
"""),
])

# 2. ECL: the PD by stage. remaining_tenor is in MONTHS everywhere (finding C2).
sub('app/Http/Controllers/ExpectedCreditLossController.php', [
("""                $pdExpr = $validated['pd_type'] === 'pd_prefli'
                    ? 'COALESCE(pd_prefli, pd_post_fli)'
                    : 'pd_post_fli';
""",
"""                $pdExpr = $validated['pd_type'] === 'pd_prefli'
                    ? 'COALESCE(pd_prefli, pd_post_fli)'
                    : 'pd_post_fli';

                /*
                | The PD the allowance is measured on, by stage (IFRS 9 5.5.3 and
                | 5.5.5; system audit of 9 October 2026, finding C1). $pdExpr is the
                | 12-month PD. Stage 1 carries it over the shorter of twelve months
                | and the remaining life; Stage 2 carries the lifetime PD,
                | 1 - (1 - PD12)^(months/12), over the remaining tenor; Stage 3 is
                | 1. remaining_tenor is in months; a loan with no tenor is given
                | twelve months and is flagged on the row. The stage is the one the
                | staging engine measured, post qualitative.
                */
                $pd12 = "CASE WHEN {$pdExpr} > 1 THEN 1 WHEN {$pdExpr} < 0 THEN 0 ELSE {$pdExpr} END";
                $months = 'CASE WHEN remaining_tenor IS NULL OR remaining_tenor < 1 THEN 12 ELSE remaining_tenor END';
                $stageExpr = 'COALESCE(ifrs9stage_post_qualitative, calculated_ifrs9_stage, ifrs9stage_pre_qualitative)';
                $stagePdExpr = "CASE WHEN {$stageExpr} IN ('3', 3) THEN 1"
                    . " WHEN {$stageExpr} IN ('2', 2) THEN 1 - POWER(1 - ({$pd12}), ({$months}) / 12.0)"
                    . " ELSE 1 - POWER(1 - ({$pd12}), (CASE WHEN ({$months}) < 12 THEN ({$months}) ELSE 12 END) / 12.0) END";
"""),
("""                            lgd_value = IFNULL($lgdExpr, 0),
                            ecl_value = IFNULL($pdExpr, 0) * IFNULL($lgdExpr, 0)
                                * (IFNULL(carrying_amount, 0)
                                    + IFNULL(commitments, 0) * IFNULL(facility_utilisation_rate, 1))
                        WHERE $baseWhere""",
"""                            lgd_value = IFNULL($lgdExpr, 0),
                            ecl_value = IFNULL($stagePdExpr, 0) * IFNULL($lgdExpr, 0)
                                * (IFNULL(carrying_amount, 0)
                                    + IFNULL(commitments, 0) * IFNULL(facility_utilisation_rate, 1))
                        WHERE $baseWhere"""),
])

# 3. PD engine: lifetime over the remaining months, floor one month, not one year (M3)
sub('app/Services/Pd/PdEngineService.php', [
("""                // lifetime PD from the remaining tenor in years: 1 - (1 - annual PD)^years, at least one year
                foreach (DB::table('loan_books')->where('reporting_period', $period)->where('ifrs9stage_pre_qualitative', (string) $stage)->where('loan_portfolio_id', $portfolioId)->whereNotNull('remaining_tenor')->get(['id', 'remaining_tenor']) as $loan) {
                    $years = max(1.0, (float) $loan->remaining_tenor / 12);""",
"""                // lifetime PD over the remaining tenor (months): 1 - (1 - annual PD)^(months/12), at least one month
                foreach (DB::table('loan_books')->where('reporting_period', $period)->where('ifrs9stage_pre_qualitative', (string) $stage)->where('loan_portfolio_id', $portfolioId)->whereNotNull('remaining_tenor')->get(['id', 'remaining_tenor']) as $loan) {
                    $years = max(1.0, (float) $loan->remaining_tenor) / 12;"""),
])

# 4. Transition-matrix screen: the same formula, months
s = io.open('app/Http/Controllers/TransitionMatrixController.php', encoding='utf-8').read()
n = s.count("SET lifetime_pd = 1 - POWER((1 - ?), remaining_tenor)")
assert n == 2, n
s = s.replace("SET lifetime_pd = 1 - POWER((1 - ?), remaining_tenor)",
              "SET lifetime_pd = 1 - POWER((1 - ?), (CASE WHEN remaining_tenor < 1 THEN 1 ELSE remaining_tenor END) / 12.0)")
io.open('app/Http/Controllers/TransitionMatrixController.php', 'w', encoding='utf-8', newline='\n').write(s)

# 5. Time-phased engine: months are months
sub('app/Services/Ecl/TimePhasedEclService.php', [
("$months=$last?max(1,$asOf->diffInMonths(CarbonImmutable::parse($last)->endOfMonth())):(int)ceil((float)($loan->remaining_tenor??0)*12);",
 "$months=$last?max(1,$asOf->diffInMonths(CarbonImmutable::parse($last)->endOfMonth())):(int)ceil((float)($loan->remaining_tenor??0)); // remaining_tenor is in months"),
])

# 6. Discounting service: horizon in years from months
sub('app/Services/Ecl/EclDiscountingService.php', [
("""        $remaining = (float) ($loan->remaining_tenor ?? 0);
        if ($remaining <= 0 && ! empty($loan->due_date)) {""",
"""        $remaining = (float) ($loan->remaining_tenor ?? 0) / 12; // remaining_tenor is in months
        if ($remaining <= 0 && ! empty($loan->due_date)) {"""),
])

# 7. Legacy writers: months, like the governed build
sub('app/Jobs/ProcessLoanImportJob.php', [
("""            $decimalYears = $years + ($months / 12);
            $remainingTenor = round($decimalYears, 2);""",
"""            // months, the unit the governed build writes and every engine reads (audit C2)
            $remainingTenor = round($years * 12 + $months, 2);"""),
])
sub('app/Imports/LoanBooksImport.php', [
("                $remainingLife = $reportingEnd->floatDiffInYears($dueCarbon, false);",
 "                $remainingLife = round($reportingEnd->floatDiffInMonths($dueCarbon, false), 2); // months (audit C2)"),
])
print('ok')
