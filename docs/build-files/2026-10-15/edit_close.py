p = r'C:\xampp\htdocs\MAICC-IFRS9\app\Services\Fli\FliCloseService.php'
s = open(p, encoding='utf-8', newline='').read()
nl = '\r\n' if '\r\n' in s else '\n'
s = s.replace('\r\n', '\n')
def rep(old, new):
    global s
    assert old in s, old[:80]
    s = s.replace(old, new)
rep("""        $versionIds = DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('fli_model_version_id')->distinct()->pluck('fli_model_version_id');
        $version = $versionIds->count() === 1 ? DB::table('fli_model_versions')->where('id', $versionIds->first())->first() : null;
""", """        $versionIds = DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('fli_model_version_id')->distinct()->pluck('fli_model_version_id');
        $version = $versionIds->count() === 1 ? DB::table('fli_model_versions')->where('id', $versionIds->first())->first() : null;
        // The route ran under the regression route with no model version on any loan: the PD held
        // (fli_without_model, D-F1 note of 14 October 2026). Under "Proceed" the month is recorded and
        // says so, and its sign-off needs a commentary; under "Refuse" (or with no approved value) the
        // record stops with the reason, as the month-end and the bootstrap do.
        $withoutModel = null;
        if ($versionIds->isEmpty() && $loans->contains(fn ($l) => str_starts_with((string) ($l->fli_route ?? ''), 'Regression'))) {
            $d = (new FliWithoutModel($this->governance, $this->registry))->decide($period);
            if (! $d['in_force'] && ! $d['proceed']) {
                throw new RuntimeException($d['message']);
            }
            $withoutModel = ['note' => FliWithoutModel::NOTE, 'setting' => $d['setting'],
                'detail' => 'The forward-looking route ran with no model version in force: each scenario of the set carries the PD before FLI, so the allowance has no forward-looking adjustment. Signing off needs a commentary saying so.'];
        }
""")
rep("""            'without_scenario_pd' => ['loans' => count(array_unique(array_map(fn ($m) => strstr($m, ' (', true), $pdMissing))), 'first' => array_slice($pdMissing, 0, 20)],
        ];""", """            'without_scenario_pd' => ['loans' => count(array_unique(array_map(fn ($m) => strstr($m, ' (', true), $pdMissing))), 'first' => array_slice($pdMissing, 0, 20)],
            'without_model' => $withoutModel,
        ];""")
rep("""        $commentary = trim((string) ($commentary ?? $s->commentary ?? ''));
        if ($s->needs_commentary && $commentary === '') {""", """        // a record made before a step it depends on ran again is not the month any more (14 October 2026)
        $currency = MonthEndCurrency::state((string) $s->reporting_period);
        if ($currency['record_id'] === (int) $s->id && $currency['record_stale'] !== null) {
            throw new RuntimeException($currency['record_stale'] . ' Run the remaining month-end steps and record the month again before signing it off.');
        }
        $commentary = trim((string) ($commentary ?? $s->commentary ?? ''));
        if (! empty((json_decode((string) ($s->inputs ?? ''), true) ?: [])['without_model']) && $commentary === '') {
            throw new RuntimeException(FliWithoutModel::NOTE . " for {$s->reporting_period}: the PD held with no forward-looking adjustment (fli_without_model). Say so in the commentary, with why no model version is in force, before signing off.");
        }
        if ($s->needs_commentary && $commentary === '') {""")
rep("""    /** The latest record of a month that is not superseded. */
    public function latest(string $period): ?array
    {
        $id = DB::table('fli_run_snapshots')->where('reporting_period', $period)->whereIn('status', ['RECORDED', 'SIGNED_OFF'])->orderByDesc('id')->value('id');

        return $id ? $this->find((int) $id) : null;
    }""", """    /**
     * The latest record of a month that is not superseded, with whether it is still current: a record
     * made before the staging, the PD, the LGD, the route or the ECL ran again carries 'stale' (the
     * reason) and is shown as not current (MonthEndCurrency, 14 October 2026).
     */
    public function latest(string $period): ?array
    {
        $id = DB::table('fli_run_snapshots')->where('reporting_period', $period)->whereIn('status', ['RECORDED', 'SIGNED_OFF'])->orderByDesc('id')->value('id');
        if (! $id) {
            return null;
        }
        $s = $this->find((int) $id);
        $currency = MonthEndCurrency::state($period);
        $s['stale'] = $currency['record_stale'];
        $s['without_model'] = ! empty($s['inputs']['without_model']);

        return $s;
    }""")
s = s.replace('\n', nl)
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
