p = r'C:\xampp\htdocs\MAICC-IFRS9\app\Services\Fli\FliMonthEnd.php'
s = open(p, encoding='utf-8').read()


def rep(old, new):
    global s
    assert old in s, old[:80]
    s = s.replace(old, new)


rep("""    public function __construct(private FliModelRegistry $registry, private FliCloseService $close, private FliProjectionService $projection, private GovernanceService $governance)
    {
    }""", """    private FliWithoutModel $withoutModel;

    public function __construct(private FliModelRegistry $registry, private FliCloseService $close, private FliProjectionService $projection, private GovernanceService $governance, ?FliWithoutModel $withoutModel = null)
    {
        $this->withoutModel = $withoutModel ?? new FliWithoutModel($governance, $registry);
    }""")

rep("""        // 4. the model version in force
        $version = $this->registry->inForce($period);
        $cadence = $this->setting('fli_model_cadence', $period) ?? '';
        $age = $version ? AsAtSeries::monthsBetween(AsAtSeries::ym($version->effective_from), $ym) : null;
        $monthly = str_starts_with($cadence, 'Every month');
        $vState = $version === null ? 'blocked' : (($monthly && $version->effective_from !== $period) ? 'blocked' : ($age !== null && $age >= 12 ? 'warn' : 'done'));
        $steps[] = $this->step('model', 'Model version in force', $vState,
            $version === null ? 'No model version is in force: run the correlation sweep, then propose and approve a fit on Regression; approval publishes it.'
                : "Version""", """        // 4. the model version in force; with none, the governed setting fli_without_model decides
        // whether the month-end proceeds with the PD held or stops (D-F1, note of 14 October 2026)
        $decision = $this->withoutModel->decide($period);
        $version = $decision['model'];
        $noModel = $version === null && $decision['proceed'];
        $cadence = $this->setting('fli_model_cadence', $period) ?? '';
        $age = $version ? AsAtSeries::monthsBetween(AsAtSeries::ym($version->effective_from), $ym) : null;
        $monthly = str_starts_with($cadence, 'Every month');
        $vState = $version === null ? ($noModel ? 'warn' : 'blocked') : (($monthly && $version->effective_from !== $period) ? 'blocked' : ($age !== null && $age >= 12 ? 'warn' : 'done'));
        $steps[] = $this->step('model', 'Model version in force', $vState,
            $version === null ? $decision['message']
                : "Version""")

rep("""            'fli-models.index', null, ['version_id' => $version?->id]);""",
    """            'fli-models.index', null, ['version_id' => $version?->id, 'without_model' => $noModel, 'without_model_setting' => $decision['setting']]);""")

rep("""        $applied = DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('pd_prefli')
            ->selectRaw('count(*) n, sum(fli_set_id = ?) on_set, sum(fli_model_version_id = ?) on_version, sum(pd_post_fli is not null) post', [(int) ($approved->id ?? 0), (int) ($version->id ?? 0)])->first();""",
    """        // with no model in force (and the setting letting the month proceed) the loans carry no version
        $onVersion = $version ? 'sum(case when fli_model_version_id = ? then 1 else 0 end)' : 'sum(case when fli_model_version_id is null then 1 else 0 end)';
        $applied = DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('pd_prefli')
            ->selectRaw("count(*) n, sum(case when fli_set_id = ? then 1 else 0 end) on_set, {$onVersion} on_version, sum(case when pd_post_fli is not null then 1 else 0 end) post", $version ? [(int) ($approved->id ?? 0), (int) $version->id] : [(int) ($approved->id ?? 0)])->first();""")

rep("""        $applyDone = $approved && $version && $applied->n > 0""", """        $modelOk = $version !== null || $noModel;
        $applyDone = $approved && $modelOk && $applied->n > 0""")

rep("""        $steps[] = $this->step('apply', 'Forward-looking route applied', $applyDone ? 'done' : (($approved && $version) ? 'todo' : 'blocked'),
            $applyDone ? number_format((int) $applied->n) . ' loans carry the post-FLI PD under version ' . $version->version . ' and set ' . $approved->id . ($appliedAt ? ", applied {$appliedAt}" : '')
                : (($approved && $version) ? ((int) $applied->post < (int) $applied->n ? number_format((int) $applied->n - (int) $applied->post) . ' loans have no post-FLI PD (a new PD run clears it): apply the route.' : number_format((int) ($applied->on_set ?? 0)) . ' of ' . number_format((int) $applied->n) . ' loans are on the set and version in force: apply the route.') : 'Needs the model version and the scenario set first.'),""",
    """        $under = $version ? 'version ' . $version->version : 'no model version (' . FliWithoutModel::NOTE . ': the PD holds)';
        $steps[] = $this->step('apply', 'Forward-looking route applied', $applyDone ? 'done' : (($approved && $modelOk) ? 'todo' : 'blocked'),
            $applyDone ? number_format((int) $applied->n) . ' loans carry the post-FLI PD under ' . $under . ' and set ' . $approved->id . ($appliedAt ? ", applied {$appliedAt}" : '')
                : (($approved && $modelOk) ? ((int) $applied->post < (int) $applied->n ? number_format((int) $applied->n - (int) $applied->post) . ' loans have no post-FLI PD (a new PD run clears it): apply the route.' : number_format((int) ($applied->on_set ?? 0)) . ' of ' . number_format((int) $applied->n) . ' loans are on the set and ' . ($version ? 'the version in force' : 'the route with no model version') . ': apply the route.') : ($approved ? (string) $decision['message'] : 'Needs the model version and the scenario set first.')),""")

rep("""        $steps[] = $this->step('project', 'Scenario projection (term structure)', $projCurrent ? 'done' : ($applyDone ? 'optional' : 'blocked'),
            $proj ? 'Projected ' . $proj->completed_at . ($projCurrent ? '' : ', before the route last ran: project again') : 'Not projected yet: the year-by-year PD of each portfolio under each scenario.',
            'forecasting.manual', 'fli-project');""",
    """        if ($noModel) {
            // nothing to project: the PD holds in every scenario (fli_without_model, Proceed)
            $steps[] = $this->step('project', 'Scenario projection (term structure)', 'optional', ucfirst(FliWithoutModel::PROJECTION_SKIPPED) . '.', 'forecasting.manual', null);
        } else {
            $steps[] = $this->step('project', 'Scenario projection (term structure)', $projCurrent ? 'done' : ($applyDone ? 'optional' : 'blocked'),
                $proj ? 'Projected ' . $proj->completed_at . ($projCurrent ? '' : ', before the route last ran: project again') : 'Not projected yet: the year-by-year PD of each portfolio under each scenario.',
                'forecasting.manual', 'fli-project');
        }""")

rep("""($snap['needs_commentary'] ? '; the movement needs a commentary' : '') . ': a second person signs it off.'),""",
    """($snap['needs_commentary'] ? '; the movement needs a commentary' : '')
                        . (! empty($snap['inputs']['without_model']) ? '; ' . FliWithoutModel::NOTE . ', so the sign-off needs a commentary saying so' : '') . ': a second person signs it off.'),""")

open(p, 'w', encoding='utf-8').write(s)
print('ok')
