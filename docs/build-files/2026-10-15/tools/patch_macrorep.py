P = 'C:/xampp/htdocs/MAICC-IFRS9/app/Http/Controllers/Reports/Ifrs9ReportsController.php'
s = open(P, encoding='utf-8').read()
a = s.index('    public function macroScenarioPart(Request $request)')
b = s.index('    /* ===================================================================== */\n    /*  RBM Prudential')
new = r'''    public function macroScenarioPart(Request $request)
    {
        $period = $this->period($request);
        $snap = $this->fliSnapshot($period);
        $set = $this->fliSet($period, $snap);

        // the latest observation of each variable the bridge held for the month (what the FLI read)
        $macro = [];
        if ($period && Schema::hasTable('macro_series')) {
            $ym = str_replace('-', '', $period);
            $latest = DB::table('macro_series')->where('observation_period', '<=', $ym)
                ->selectRaw('statistic_code, MAX(observation_period) p')->groupBy('statistic_code');
            $names = DB::table('macro_statistics')->pluck('statistic_name', 'statistic_code');
            $macro = DB::table('macro_series as m')
                ->joinSub($latest, 'l', fn ($j) => $j->on('l.statistic_code', '=', 'm.statistic_code')->on('l.p', '=', 'm.observation_period'))
                ->orderBy('m.statistic_code')->get(['m.statistic_code', 'm.statistic_name', 'm.observation_period', 'm.value', 'm.value_type', 'm.source'])
                ->map(fn ($r) => [$names[$r->statistic_code] ?? $r->statistic_name ?? $r->statistic_code, $r->statistic_code,
                    substr($r->observation_period, 0, 4) . '-' . substr($r->observation_period, 4, 2), ucfirst($r->value_type),
                    $this->num($r->value, 4), $r->source ?: '-'])->all();
        }

        $scenarios = [];
        $shocks = [];
        if ($set) {
            $sc = DB::table('governed_scenarios')->where('set_id', $set->id)->orderBy('order_position')->orderBy('id')->get();
            $scenarios = $sc->map(fn ($r) => [$r->name . ($r->is_base ? ' (base)' : ''), $this->pct($r->weight / 100),
                $r->anchored_to ?: '-', $r->pd_multiplier !== null ? $this->num($r->pd_multiplier, 4) : '-'])->all();
            $byId = $sc->pluck('name', 'id');
            $shocks = DB::table('scenario_shocks')->whereIn('scenario_id', $byId->keys())->orderBy('scenario_id')->orderBy('statistic_code')->orderBy('year_offset')->get()
                ->map(fn ($r) => [$byId[$r->scenario_id], $r->statistic_code, 'Year ' . ($r->year_offset + 1),
                    ['pct' => 'Percent change', 'abs' => 'Add', 'replace' => 'Replace', 'mult' => 'Multiply'][$r->kind] ?? $r->kind,
                    $this->num($r->value, 4)])->all();
        }
        $setLabel = $set ? $set->name . ' v' . $set->version . ' (' . strtolower($set->status) . ')' : null;

        return $this->respond(['key' => 'macro-scenario', 'period' => $period,
            'subtitle' => 'The macro-economic data and the governed scenario set the forward-looking adjustment used for ' . ($period ?: 'the month'),
            'kpis' => array_merge($this->totalsKpis($period), [
                ['label' => 'Scenario set', 'value' => $setLabel ?? 'None approved', 'tone' => $set ? 'emerald' : 'amber'],
                ['label' => 'Macro vintage', 'value' => $snap->macro_vintage ?? '-', 'tone' => 'blue'],
            ]),
            'sections' => [
                ['heading' => 'Macro-Economic Data Held for the Month',
                 'columns' => ['Variable', 'Code', 'Latest period', 'Type', 'Value', 'Source'], 'align' => ['l', 'l', 'l', 'l', 'r', 'l'],
                 'rows' => $macro ?: [['No macro-economic data held up to this month', '-', '-', '-', '-', '-']]],
                ['heading' => 'Economic Scenarios & Weights' . ($setLabel ? ' - ' . $setLabel : ''),
                 'columns' => ['Scenario', 'Weight', 'Anchored to', 'PD multiplier'], 'align' => ['l', 'r', 'l', 'r'],
                 'rows' => $scenarios ?: [['No approved scenario set for this month', '-', '-', '-']]],
                ['heading' => 'Scenario Shocks',
                 'columns' => ['Scenario', 'Variable', 'Forecast year', 'Shock', 'Value'], 'align' => ['l', 'l', 'l', 'l', 'r'],
                 'rows' => $shocks ?: [['No shocks recorded on the set', '-', '-', '-', '-']]],
            ]]);
    }

    public function scenarioEcl(Request $request)
    {
        $period = $this->period($request);
        $snap = $this->fliSnapshot($period);
        $set = $this->fliSet($period, $snap);

        $rows = [];
        foreach (json_decode($snap->per_scenario ?? '[]', true) ?: [] as $r) {
            $rows[] = [($r['scenario'] ?? '-') . (! empty($r['is_base']) ? ' (base)' : ''), $this->pct(($r['weight'] ?? 0) / 100),
                $this->money($r['ecl'] ?? 0), $this->money($r['contribution'] ?? 0)];
        }
        if ($snap) {
            $rows[] = ['Probability-weighted', $this->pct(1), '', $this->money($snap->weighted_ecl)];
        }

        $kpis = $snap ? [
            ['label' => 'Booked ECL', 'value' => $this->money($snap->booked_ecl), 'tone' => 'rose'],
            ['label' => 'Probability-Weighted ECL', 'value' => $this->money($snap->weighted_ecl), 'tone' => 'amber'],
            ['label' => 'Difference', 'value' => $this->money($snap->difference), 'tone' => $snap->reconciles ? 'emerald' : 'rose'],
            ['label' => 'Close status', 'value' => $snap->status === 'SIGNED_OFF' ? 'Signed off' : 'Recorded, awaiting sign-off', 'tone' => 'blue'],
        ] : [
            ['label' => 'FLI close', 'value' => 'Not recorded for this month', 'tone' => 'amber'],
        ];

        return $this->respond(['key' => 'scenario-ecl', 'period' => $period,
            'subtitle' => 'ECL under each governed scenario and the probability-weighted result, from the FLI month-end close'
                . ($set ? ' (' . $set->name . ' v' . $set->version . ')' : ''),
            'kpis' => $kpis,
            'sections' => [[
                'heading' => 'ECL by Scenario',
                'columns' => ['Scenario', 'Weight', 'ECL under the scenario', 'Weighted contribution'],
                'align' => ['l', 'r', 'r', 'r'],
                'rows' => $rows ?: [['Run the FLI month-end close for this month (FLI Month-End, Overview)', '-', '-', '-']],
            ]]]);
    }

    /** The FLI month-end close of the month that stands (recorded or signed off), or null. */
    private function fliSnapshot(?string $period): ?object
    {
        if (! $period || ! Schema::hasTable('fli_run_snapshots')) {
            return null;
        }

        return DB::table('fli_run_snapshots')->where('reporting_period', $period)->whereIn('status', ['RECORDED', 'SIGNED_OFF'])->orderByDesc('id')->first();
    }

    /** The scenario set the close used, else the approved set of the month. */
    private function fliSet(?string $period, ?object $snap): ?object
    {
        if (! $period || ! Schema::hasTable('governed_scenario_sets')) {
            return null;
        }
        if ($snap && $snap->set_id) {
            return DB::table('governed_scenario_sets')->where('id', $snap->set_id)->first();
        }

        return DB::table('governed_scenario_sets')->where('reporting_period', $period)->whereIn('status', ['APPROVED', 'LOCKED'])->orderByDesc('version')->first();
    }

'''
s = s[:a] + new + s[b:]
open(P, 'w', encoding='utf-8').write(s)
print('ok')
