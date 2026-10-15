p = r'c:\xampp\htdocs\MAICC-IFRS9\app\Http\Controllers\DashboardController.php'
s = open(p, encoding='utf-8').read()


def rep(old, new):
    global s
    assert s.count(old) == 1, old[:70]
    s = s.replace(old, new)


rep("use App\\Models\\LoanBook;\n", "use App\\Models\\Import;\nuse App\\Models\\LoanBook;\n")

rep("""    $portfolios = LoanPortfolio::orderBy('name')->get(['id', 'name']);

    if ($periods->isEmpty()) {""", """    $portfolios = LoanPortfolio::orderBy('name')->get(['id', 'name']);

    // The operations row (month-end status, latest loan book, recent
    // imports) shows even before any ECL has been calculated.
    $latestLoanBookPeriod = LoanBook::max('reporting_period');
    $recentImports = Import::orderByDesc('id')->limit(5)
        ->get(['id', 'name', 'status', 'records', 'created_at', 'completed_at']);

    if ($periods->isEmpty()) {""")

rep("""            'eclTrends' => [],
            'error' => 'No data available. Please upload or calculate ECL first.',""", """            'eclTrends' => [],
            'monthEnd' => $latestLoanBookPeriod ? $this->monthEndStatus($latestLoanBookPeriod) : null,
            'loanBookSnapshot' => $latestLoanBookPeriod ? $this->loanBookSnapshot($latestLoanBookPeriod, null) : null,
            'recentImports' => $recentImports,
            'error' => 'No ECL has been calculated yet. Load the loan book, apply PD and LGD, then run the ECL calculation.',""")

rep("""    // --- Loan book: EAD by stage in ONE grouped query --------------------
    $eadByStage = $loanBookScope()
        ->selectRaw('ifrs9stage_post_qualitative as stage, SUM(carrying_amount) as amount')
        ->groupBy('ifrs9stage_post_qualitative')
        ->pluck('amount', 'stage');
""", """    // --- Loan book: EAD and loan count by stage in ONE grouped query ------
    $bookByStage = $loanBookScope()
        ->selectRaw('ifrs9stage_post_qualitative as stage, SUM(carrying_amount) as amount, COUNT(*) as loans')
        ->groupBy('ifrs9stage_post_qualitative')
        ->get()
        ->keyBy('stage');
    $eadByStage = $bookByStage->map(fn ($r) => (float) $r->amount);
    $loansByStage = [(int) ($bookByStage[1]->loans ?? 0), (int) ($bookByStage[2]->loans ?? 0), (int) ($bookByStage[3]->loans ?? 0)];
""")

rep("""    $lgdPercentage = round((float) ($eclByStage[3]->avg_lgd ?? 0) * 100, 2);
""", """    $lgdPercentage = round((float) ($eclByStage[3]->avg_lgd ?? 0) * 100, 2);
    $lgdPercentages = array_map(fn ($st) => round((float) ($eclByStage[$st]->avg_lgd ?? 0) * 100, 2), [1, 2, 3]);
""")

rep("""            ->selectRaw('ifrs9stage_post_qualitative as stage, SUM(carrying_amount) as amount')
            ->groupBy('ifrs9stage_post_qualitative')
            ->pluck('amount', 'stage');

        $cS1""", """            ->selectRaw('ifrs9stage_post_qualitative as stage, SUM(carrying_amount) as amount, COUNT(*) as loans')
            ->groupBy('ifrs9stage_post_qualitative')
            ->get()
            ->keyBy('stage');
        $cLoans = (int) $compareEad->sum('loans');
        $compareEad = $compareEad->map(fn ($r) => (float) $r->amount);

        $cS1""")

rep("""            'paid_amount' => $cGross - $cEcl,
            'weighted_pd'""", """            'paid_amount' => $cGross - $cEcl,
            'net_carrying_amount' => $cGross - $cEcl,
            'total_loans' => $cLoans,
            'weighted_pd'""")

rep("""    // --- Coverage trend: ONE grouped query, optional from/to range --------
    // Default range: January of the selected period's year through the
    // latest available period, so the chart tracks the reporting year as
    // data grows. 'all' shows every period from the first. Only periods
    // that really exist are plotted; nothing is zero-filled.
    $trendFrom = $request->input('trend_from');
    $trendTo = $request->input('trend_to');

    if ($trendFrom === 'all') {
        $lowerBound = null;
    } elseif ($periods->contains($trendFrom)) {
        $lowerBound = $trendFrom;
    } else {
        $trendFrom = null;
        $lowerBound = substr($selectedPeriod, 0, 4) . '-01';
    }

    $trendPeriods = $periods
        ->when($lowerBound !== null, fn ($c) => $c->filter(fn ($p) => $p >= $lowerBound))
        ->when($periods->contains($trendTo), fn ($c) => $c->filter(fn ($p) => $p <= $trendTo))
        ->values();
""", """    // --- ECL and coverage trend: ONE grouped query, optional from/to range -
    // Default range: the 12 months up to the selected period. 'all' shows
    // every period from the first. Without an explicit end the trend stops
    // at the selected period. Only periods that really exist are plotted;
    // nothing is zero-filled.
    $trendFrom = $request->input('trend_from');
    $trendTo = $request->input('trend_to');

    if ($trendFrom === 'all') {
        $lowerBound = null;
    } elseif ($periods->contains($trendFrom)) {
        $lowerBound = $trendFrom;
    } else {
        $trendFrom = null;
        $lowerBound = Carbon::createFromFormat('Y-m-d', $selectedPeriod . '-01')->subMonths(11)->format('Y-m');
    }
    $upperBound = $periods->contains($trendTo) ? $trendTo : $selectedPeriod;

    $trendPeriods = $periods
        ->when($lowerBound !== null, fn ($c) => $c->filter(fn ($p) => $p >= $lowerBound))
        ->filter(fn ($p) => $p <= $upperBound)
        ->values();
""")

rep("""        ->selectRaw('reporting_period, SUM(total_ead) as total_ead, SUM(total_ecl) as total_ecl')""", """        ->selectRaw('reporting_period, SUM(total_ead) as total_ead, SUM(total_ecl) as total_ecl,
            SUM(CASE WHEN ifrs9_stage = 1 THEN total_ecl ELSE 0 END) as ecl_s1,
            SUM(CASE WHEN ifrs9_stage = 2 THEN total_ecl ELSE 0 END) as ecl_s2,
            SUM(CASE WHEN ifrs9_stage = 3 THEN total_ecl ELSE 0 END) as ecl_s3')""")

rep("""        'period' => $row->reporting_period,
        'ecl_percentage' => $row->total_ead > 0""", """        'period' => $row->reporting_period,
        'total_ead' => (float) $row->total_ead,
        'total_ecl' => (float) $row->total_ecl,
        'ecl_by_stage' => [(float) $row->ecl_s1, (float) $row->ecl_s2, (float) $row->ecl_s3],
        'ecl_percentage' => $row->total_ead > 0""")

rep("""        'paid_amount' => $paidAmount,
        'stage_3_percentage'""", """        'paid_amount' => $paidAmount,
        'net_carrying_amount' => $paidAmount,
        'total_loans' => array_sum($loansByStage),
        'loans_by_stage' => $loansByStage,
        'lgd_percentages' => $lgdPercentages,
        'stage_3_percentage'""")

rep("""        'eclTrends' => $eclTrends,
    ]);
}
""", """        'eclTrends' => $eclTrends,
        'monthEnd' => $this->monthEndStatus($selectedPeriod),
        'loanBookSnapshot' => $latestLoanBookPeriod ? $this->loanBookSnapshot($latestLoanBookPeriod, $portfolioId) : null,
        'recentImports' => $recentImports,
    ]);
}

/**
 * Where the month-end run stands for one period: loan book loaded, PD
 * applied, LGD applied, ECL calculated. reporting_periods.period is a
 * date (Y-m-01); loan_books.reporting_period is Y-m.
 */
private function monthEndStatus(string $period): array
{
    $rp = ReportingPeriods::whereDate('period', $period . '-01')->first();

    return [
        'period' => $period,
        'loan_book_rows' => (int) LoanBook::where('reporting_period', $period)->count(),
        'pd_applied' => (bool) ($rp?->pd_id),
        'pd_source' => $rp?->pd_calculation_source,
        'lgd_applied' => (bool) ($rp?->lgd_id),
        'lgd_source' => $rp?->lgd_calculation_source,
        'ecl_calculated' => (bool) ($rp?->ecl_calculated),
    ];
}

/**
 * Carrying amount and loan count by stage for the latest loaded loan book,
 * under the dashboard's portfolio filter.
 */
private function loanBookSnapshot(string $period, ?int $portfolioId): array
{
    $rows = LoanBook::where('reporting_period', $period)
        ->when($portfolioId, fn ($q) => $q->where('loan_portfolio_id', $portfolioId))
        ->selectRaw('ifrs9stage_post_qualitative as stage, COUNT(*) as loans, SUM(carrying_amount) as balance')
        ->groupBy('ifrs9stage_post_qualitative')
        ->get()
        ->keyBy('stage');

    $balance = [];
    $loans = [];
    foreach ([1, 2, 3] as $st) {
        $balance[] = (float) ($rows[$st]->balance ?? 0);
        $loans[] = (int) ($rows[$st]->loans ?? 0);
    }

    return [
        'period' => $period,
        'balance_by_stage' => $balance,
        'loans_by_stage' => $loans,
        'total_balance' => array_sum($balance),
        'total_loans' => array_sum($loans),
    ];
}
""")

rep("""        'paid_amount' => 0,
        'stage_3_percentage' => 0,""", """        'paid_amount' => 0,
        'net_carrying_amount' => 0,
        'total_loans' => 0,
        'loans_by_stage' => [0, 0, 0],
        'lgd_percentages' => [0, 0, 0],
        'stage_3_percentage' => 0,""")

open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
