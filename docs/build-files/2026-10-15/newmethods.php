/**
 * ECL by portfolio over the trend range, one series per portfolio (or, with
 * a portfolio picked, per product group of its loans), the six largest in
 * the last month of the range and the rest as one "Other" line. All
 * portfolios: the same expected_credit_loss rows the trend sums, keyed by
 * the run each row belongs to, so every month adds back to the trend's total
 * ECL exactly. One portfolio: its loans' booked ECL (loan_books.ecl_value,
 * the "Where the ECL sits" source) by product group, checked against the
 * trend month by month and flagged where it differs.
 *
 * @param  \Illuminate\Support\Collection  $eclTrends
 */
private function eclMixTrend($eclTrends, ?int $portfolioId): array
{
    $periods = collect($eclTrends)->pluck('period')->values()->all();
    $dimension = $portfolioId ? 'product' : 'portfolio';
    if ($periods === []) {
        return ['dimension' => $dimension, 'periods' => [], 'series' => [], 'totals' => [], 'ties' => true, 'differs' => []];
    }

    $acc = [];   // key => [label, values by period]
    if (! $portfolioId) {
        $names = LoanPortfolio::pluck('name', 'id');
        $rows = ExpectedCreditLoss::whereIn('reporting_period', $periods)
            ->selectRaw('reporting_period, ecl_calculation_level as lvl, ecl_calculation_id as calc_id, SUM(total_ecl) as ecl')
            ->groupBy('reporting_period', 'ecl_calculation_level', 'ecl_calculation_id')
            ->get();
        foreach ($rows as $r) {
            $isPortfolio = $r->lvl === 'portfolio' && $r->calc_id !== null;
            $key = $isPortfolio ? 'p' . $r->calc_id : 'run:' . $r->lvl . ':' . $r->calc_id;
            $label = $isPortfolio
                ? ($names[$r->calc_id] ?? 'Portfolio ' . $r->calc_id)
                : ucfirst((string) ($r->lvl ?: 'unlabelled')) . '-level run' . ($r->calc_id !== null ? ' ' . $r->calc_id : '');
            $acc[$key] ??= ['label' => $label, 'values' => []];
            $acc[$key]['values'][$r->reporting_period] = ($acc[$key]['values'][$r->reporting_period] ?? 0.0) + (float) $r->ecl;
        }
    } else {
        $group = "CASE WHEN TRIM(COALESCE(product_group, '')) = '' THEN 'Not recorded' ELSE product_group END";
        $rows = LoanBook::whereIn('reporting_period', $periods)->where('loan_portfolio_id', $portfolioId)
            ->selectRaw("reporting_period, {$group} as grp, SUM(COALESCE(ecl_value, 0)) as ecl")
            ->groupBy('reporting_period', DB::raw($group))
            ->get();
        foreach ($rows as $r) {
            $acc[$r->grp] ??= ['label' => $r->grp, 'values' => []];
            $acc[$r->grp]['values'][$r->reporting_period] = ($acc[$r->grp]['values'][$r->reporting_period] ?? 0.0) + (float) $r->ecl;
        }
    }

    // rank by the ECL of the last month of the range, then by the range's total
    $last = end($periods);
    $ranked = array_values($acc);
    usort($ranked, fn ($a, $b) => ($b['values'][$last] ?? 0) <=> ($a['values'][$last] ?? 0)
        ?: array_sum($b['values']) <=> array_sum($a['values']));
    $top = \App\Services\Reports\EclBuildUpService::TOP;
    if (count($ranked) > $top + 1) {
        $rest = array_slice($ranked, $top);
        $ranked = array_slice($ranked, 0, $top);
        $other = ['label' => 'Other (' . count($rest) . ')', 'other' => true, 'values' => []];
        foreach ($rest as $r) {
            foreach ($r['values'] as $p => $v) {
                $other['values'][$p] = ($other['values'][$p] ?? 0.0) + $v;
            }
        }
        $ranked[] = $other;
    }

    $series = array_map(fn ($r) => ['label' => $r['label'], 'other' => $r['other'] ?? false,
        'values' => array_map(fn ($p) => round((float) ($r['values'][$p] ?? 0), 2), $periods)], $ranked);
    $totals = array_map(fn ($i) => round(array_sum(array_map(fn ($s) => $s['values'][$i], $series)), 2), array_keys($periods));
    $differs = [];
    foreach (collect($eclTrends)->values() as $i => $t) {
        if (abs($totals[$i] - (float) $t['total_ecl']) > max(1.0, 1e-9 * abs((float) $t['total_ecl']))) {
            $differs[] = ['period' => $t['period'], 'series_total' => $totals[$i], 'trend_total' => round((float) $t['total_ecl'], 2)];
        }
    }

    return ['dimension' => $dimension, 'periods' => $periods, 'series' => $series, 'totals' => $totals, 'ties' => $differs === [], 'differs' => $differs];
}

/**
 * The ECL of the period under each scenario of the set in force, by stage,
 * with the probability-weighted figure. First source: the forward-looking
 * month-end record (fli_run_snapshots and its loan rows in
 * ecl_scenario_results, FliCloseService::record), which re-bases each loan's
 * booked ECL to each scenario's stage PD; its weighted figure reconciles to
 * the allowance. Else the saved stress runs of the approved set
 * (stress_scenarios, StressTestService::runSet), each scenario's PD
 * multiplier through the loan-level stress engine. Nothing is estimated:
 * with neither for the period and portfolio, the chart says so.
 */
private function scenarioEcl(string $period, ?int $portfolioId, float $totalEcl): array
{
    $empty = fn (string $note) => ['period' => $period, 'source' => null, 'series' => [], 'loans' => [], 'set' => null, 'record' => null, 'ties' => null, 'note' => $note];
    $setInfo = function ($set) {
        if (! $set) {
            return null;
        }
        $approver = $set->approver_label ?: ($set->approved_by ? optional(User::find($set->approved_by))->name : null);

        return ['id' => (int) $set->id, 'name' => $set->name, 'version' => (int) $set->version, 'status' => $set->status, 'approver' => $approver,
            'bootstrap' => $approver !== null && str_contains($approver, \App\Services\Scenario\ScenarioSetService::BOOTSTRAP_LABEL)];
    };
    $withWeighted = function (array $series) {
        $weighted = [0.0, 0.0, 0.0, 0.0];
        foreach ($series as $s) {
            foreach ($s['values'] as $i => $v) {
                $weighted[$i] += $v * $s['weight'] / 100;
            }
        }
        $series[] = ['name' => 'Probability-weighted', 'weight' => null, 'is_base' => false, 'weighted' => true, 'values' => array_map(fn ($v) => round($v, 2), $weighted)];

        return $series;
    };

    // 1. the forward-looking month-end record
    $snap = DB::table('fli_run_snapshots')->where('reporting_period', $period)->whereIn('status', ['RECORDED', 'SIGNED_OFF'])->orderByDesc('id')->first();
    if ($snap) {
        $rows = DB::table('ecl_scenario_results')->where('snapshot_id', $snap->id)
            ->when($portfolioId, fn ($q) => $q->where('loan_portfolio_id', $portfolioId))
            ->selectRaw('stage, scenario, MAX(weight) as weight, SUM(ecl) as ecl, COUNT(*) as loans')
            ->groupBy('stage', 'scenario')->get();
        if ($rows->isEmpty()) {
            return $empty('The forward-looking month-end record of ' . Carbon::createFromFormat('Y-m', $period)->format('M Y') . ' holds no loan of this portfolio.');
        }
        $meta = collect(json_decode((string) $snap->per_scenario, true) ?: [])->keyBy('scenario');
        $series = [];
        foreach ($rows->groupBy('scenario') as $name => $g) {
            $byStage = $g->keyBy(fn ($r) => (string) $r->stage);
            $values = array_map(fn ($st) => round((float) ($byStage[$st]->ecl ?? 0), 2), ['1', '2', '3']);
            $values[] = round((float) $g->sum('ecl'), 2);   // a loan without a stage counts in the total only
            $series[] = ['name' => $name, 'weight' => (float) ($meta[$name]['weight'] ?? $g->max('weight')), 'is_base' => (bool) ($meta[$name]['is_base'] ?? false), 'weighted' => false, 'values' => $values];
        }
        $order = $meta->keys()->flip();
        usort($series, fn ($a, $b) => ($order[$a['name']] ?? 99) <=> ($order[$b['name']] ?? 99));
        $first = $rows->where('scenario', $series[0]['name']);
        $loans = array_map(fn ($st) => (int) ($first->first(fn ($r) => (string) $r->stage === $st)->loans ?? 0), ['1', '2', '3']);
        $loans[] = (int) $first->sum('loans');
        $series = $withWeighted($series);
        $weightedTotal = end($series)['values'][3];

        return ['period' => $period, 'source' => 'month_end', 'series' => $series, 'loans' => $loans,
            'set' => $setInfo($snap->set_id ? DB::table('governed_scenario_sets')->where('id', $snap->set_id)->first() : null),
            'record' => ['id' => (int) $snap->id, 'status' => $snap->status, 'reconciles' => (bool) $snap->reconciles, 'recorded_at' => $snap->created_at],
            'ties' => abs($weightedTotal - $totalEcl) <= max(1.0, 1e-6 * abs($totalEcl)), 'note' => null];
    }

    // 2. the saved stress runs of the approved set ("Set 8 v1: Base")
    $saved = DB::table('stress_scenarios')->where('reporting_period', $period)
        ->where(fn ($q) => $portfolioId ? $q->where('loan_portfolio_id', $portfolioId) : $q->whereNull('loan_portfolio_id'))
        ->where('scenario_name', 'like', 'Set %')->orderBy('id')->get()
        ->map(function ($r) {
            if (! preg_match('/^Set (\d+) v(\d+): (.+)$/', (string) $r->scenario_name, $m)) {
                return null;
            }
            $r->set_id = (int) $m[1];
            $r->scenario = $m[3];

            return $r;
        })->filter();
    if ($saved->isNotEmpty()) {
        $setId = $saved->max('set_id');
        $set = DB::table('governed_scenario_sets')->where('id', $setId)->first();
        $scenarios = DB::table('governed_scenarios')->where('set_id', $setId)->orderBy('order_position')->get()->keyBy('name');
        $series = [];
        $loans = null;
        foreach ($saved->where('set_id', $setId) as $r) {
            $snapR = json_decode((string) $r->result_snapshot, true) ?: [];
            $byStage = collect($snapR['by_stage'] ?? [])->keyBy(fn ($x) => (string) $x['stage']);
            $values = array_map(fn ($st) => round((float) ($byStage[$st]['stress_ecl'] ?? 0), 2), ['1', '2', '3']);
            $values[] = round((float) ($snapR['total_stress_ecl'] ?? array_sum($values)), 2);
            $loans ??= array_merge(array_map(fn ($st) => (int) ($byStage[$st]['accounts'] ?? 0), ['1', '2', '3']), [(int) $byStage->sum('accounts')]);
            $series[] = ['name' => $r->scenario, 'weight' => (float) ($scenarios[$r->scenario]->weight ?? 0), 'is_base' => (bool) ($scenarios[$r->scenario]->is_base ?? false), 'weighted' => false, 'values' => $values,
                'order' => (int) ($scenarios[$r->scenario]->order_position ?? 99)];
        }
        usort($series, fn ($a, $b) => $a['order'] <=> $b['order']);
        $series = array_map(fn ($s) => array_diff_key($s, ['order' => 1]), $series);
        // a weighted figure needs every scenario of the set and weights that add to 100
        $complete = count($series) === $scenarios->count() && abs(array_sum(array_column($series, 'weight')) - 100) < 0.01;
        if ($complete) {
            $series = $withWeighted($series);
        }

        return ['period' => $period, 'source' => 'stress_set', 'series' => $series, 'loans' => $loans ?? [],
            'set' => $setInfo($set), 'record' => null, 'ties' => null, 'note' => $complete ? null : 'The saved stress runs do not cover every scenario of the set, so no probability-weighted figure is shown.'];
    }

    return $empty('No stress run or forward-looking month-end record for ' . Carbon::createFromFormat('Y-m', $period)->format('M Y') . ($portfolioId ? ' for this portfolio' : '') . '.');
}

/**
 * The observed default rate per month of the trend range: the share of the
 * balance in Stage 1 or 2 at the start of a window that was in default
 * (Stage 3) at its end or left the book unpaid. This is the PD engine's own
 * observation (PdSegmentationService::measure, writing nothing) over a
 * one-month window, and over the twelve-month window the PD reports use for
 * the observed one-year PD. The whole book always; with a portfolio picked,
 * that portfolio as well. A window with no transition gives no figure.
 *
 * @param  list<string>  $periods
 */
private function defaultRates(array $periods, ?int $portfolioId): array
{
    $svc = app(\App\Services\Pd\PdSegmentationService::class);
    $rows = [];
    $notes = [];
    $rate = function (?array $segment, string $default, array $m): ?array {
        if ($segment === null) {
            return null;
        }
        $bal = 0.0; $defBal = 0.0; $loans = 0; $defaults = 0;
        foreach ($segment['stages'] as $st => $c) {
            if ((string) $st === $default) {
                continue; // a loan already in default is not at risk of defaulting
            }
            $bal += $c['balance']; $defBal += $c['default_balance']; $loans += $c['loans']; $defaults += $c['default_loans'];
        }

        return ['rate' => $bal > 0 ? round($defBal / $bal * 100, 4) : null, 'loans' => $loans, 'defaults' => $defaults,
            'balance' => round($bal, 2), 'default_balance' => round($defBal, 2), 'window' => $m['window_start'] . ' to ' . $m['window_end'], 'months' => (int) $m['window_months']];
    };
    foreach ($periods as $p) {
        $row = ['period' => $p];
        foreach (['monthly' => 1, 'annual' => 12] as $k => $w) {
            try {
                $m = $svc->measure($p, 'portfolio', $w);
                $default = (string) ($m['profile']['default'] ?? '3');
                $row[$k] = ['book' => $rate($m['segments']['book'] ?? null, $default, $m),
                    'portfolio' => $portfolioId ? $rate($m['segments']['portfolio:' . $portfolioId] ?? null, $default, $m) : null];
            } catch (\RuntimeException $e) {
                $row[$k] = ['book' => null, 'portfolio' => null];
                $notes[$e->getMessage()] = true;
            }
        }
        $rows[] = $row;
    }

    return ['rows' => $rows, 'notes' => array_keys($notes)];
}
