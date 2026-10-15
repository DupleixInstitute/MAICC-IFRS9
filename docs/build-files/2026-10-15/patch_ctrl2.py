p = 'C:/xampp/htdocs/MAICC-IFRS9/app/Http/Controllers/DashboardController.php'
s = open(p, encoding='utf-8').read()

def rep(old, new):
    global s
    assert s.count(old) == 1, (s.count(old), old[:90])
    s = s.replace(old, new)

i = s.index("    $trendFrom = $request->input('trend_from');\n    $trendTo = $request->input('trend_to');")
j = s.index("    $summary = [\n        'carrying_amount' => $grossCarryingAmount,")
s = s[:i] + """    [$trendPeriods, $trendFrom, $trendTo] = $this->range($periods, $selectedPeriod, $request->input('trend_from'), $request->input('trend_to'));
    $eclTrends = $this->trendRows($trendPeriods, $portfolioId);

""" + s[j:]

rep("""        'trendFrom' => ($trendFrom === 'all' || $periods->contains($trendFrom)) ? $trendFrom : null,
        'trendTo' => $periods->contains($trendTo) ? $trendTo : null,
        'eclTrends' => $eclTrends,
        'eclMixTrend' => $this->eclMixTrend($eclTrends, $portfolioId),
        'scenarioEcl' => $this->scenarioEcl($selectedPeriod, $portfolioId, $totalECLAllowance),
        'defaultRates' => $this->defaultRates($eclTrends->pluck('period')->all(), $portfolioId),
    ];""", """        'trendFrom' => $trendFrom,
        'trendTo' => $trendTo,
        'eclTrends' => $eclTrends,
        // Each of the three charts below the summary has its own from / to
        // range (pf_, st_, dr_), defaulting to the trend's default (the twelve
        // months to the reporting period). They are closures so that a
        // partial reload of one chart (Inertia `only`) computes that chart
        // alone.
        'eclMixTrend' => function () use ($request, $periods, $selectedPeriod, $portfolioId) {
            [$in, $from, $to] = $this->range($periods, $selectedPeriod, $request->input('pf_from'), $request->input('pf_to'));

            return $this->eclMixTrend($this->trendRows($in, $portfolioId), $portfolioId) + ['range' => ['from' => $from, 'to' => $to]];
        },
        'scenarioEcl' => function () use ($request, $periods, $selectedPeriod, $portfolioId) {
            [$in, $from, $to] = $this->range($periods, $selectedPeriod, $request->input('st_from'), $request->input('st_to'));

            return $this->scenarioTrend($in, $this->trendRows($in, $portfolioId), $portfolioId) + ['range' => ['from' => $from, 'to' => $to]];
        },
        'defaultRates' => function () use ($request, $periods, $selectedPeriod, $portfolioId) {
            [$in, $from, $to] = $this->range($periods, $selectedPeriod, $request->input('dr_from'), $request->input('dr_to'));

            return $this->defaultRates($in->all(), $portfolioId) + ['range' => ['from' => $from, 'to' => $to]];
        },
    ];""")

rep("""/**
 * ECL by portfolio over the trend range,""", """/**
 * The periods of a from / to range, oldest first. Default: the twelve months
 * up to the reporting period; 'all' runs from the first period; without an
 * explicit end the range stops at the reporting period. Only periods with a
 * calculated ECL are in it; nothing is zero-filled. Returns the periods and
 * the from / to as the page should echo them (null for a default).
 *
 * @return array{0:\\Illuminate\\Support\\Collection,1:?string,2:?string}
 */
private function range($periods, string $selectedPeriod, $from, $to): array
{
    if ($from === 'all') {
        $lower = null;
    } elseif ($periods->contains($from)) {
        $lower = $from;
    } else {
        $from = null;
        $lower = Carbon::createFromFormat('Y-m-d', $selectedPeriod . '-01')->subMonths(11)->format('Y-m');
    }
    $to = $periods->contains($to) ? $to : null;
    $upper = $to ?? $selectedPeriod;
    $in = $periods
        ->when($lower !== null, fn ($c) => $c->filter(fn ($p) => $p >= $lower))
        ->filter(fn ($p) => $p <= $upper)
        ->sort()->values();

    return [$in, $from, $to];
}

/**
 * ECL, EAD and ECL by stage per period from the saved ECL runs (one grouped
 * query), for the trend and for the charts that must tie to it.
 */
private function trendRows($periods, ?int $portfolioId)
{
    return tap(
        ExpectedCreditLoss::whereIn('reporting_period', collect($periods)->all()),
        fn ($q) => $portfolioId
            ? $q->where('ecl_calculation_level', 'portfolio')->where('ecl_calculation_id', $portfolioId)
            : null
    )
        ->selectRaw('reporting_period, SUM(total_ead) as total_ead, SUM(total_ecl) as total_ecl,
            SUM(CASE WHEN ifrs9_stage = 1 THEN total_ecl ELSE 0 END) as ecl_s1,
            SUM(CASE WHEN ifrs9_stage = 2 THEN total_ecl ELSE 0 END) as ecl_s2,
            SUM(CASE WHEN ifrs9_stage = 3 THEN total_ecl ELSE 0 END) as ecl_s3')
        ->groupBy('reporting_period')
        ->orderBy('reporting_period')
        ->get()
        ->map(fn ($row) => [
            'period' => $row->reporting_period,
            'total_ead' => (float) $row->total_ead,
            'total_ecl' => (float) $row->total_ecl,
            'ecl_by_stage' => [(float) $row->ecl_s1, (float) $row->ecl_s2, (float) $row->ecl_s3],
            'ecl_percentage' => $row->total_ead > 0
                ? round(($row->total_ecl / $row->total_ead) * 100, 2)
                : 0,
        ])->values();
}

/**
 * The scenario ECL of every period of a range that has one (scenarioEcl),
 * and the periods that have none; gaps are never filled.
 */
private function scenarioTrend($periods, $trendRows, ?int $portfolioId): array
{
    $totals = collect($trendRows)->pluck('total_ecl', 'period');
    $byPeriod = [];
    $missing = [];
    $notes = [];
    foreach ($periods as $p) {
        $r = $this->scenarioEcl($p, $portfolioId, (float) ($totals[$p] ?? 0));
        if ($r['source'] === null) {
            $missing[] = $p;
            continue;
        }
        $byPeriod[$p] = $r;
        if ($r['note']) {
            $notes[$r['note']] = true;
        }
    }

    return ['periods' => array_keys($byPeriod), 'missing' => $missing, 'range_periods' => collect($periods)->values()->all(),
        'by_period' => $byPeriod, 'notes' => array_keys($notes)];
}

/**
 * ECL by portfolio over the trend range,""")

open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
