import sys
p = r'c:\xampp\htdocs\MAICC-IFRS9\app\Services\Fli\FliRouteService.php'
s = open(p, encoding='utf-8').read()

def rep(old, new):
    global s
    if old not in s:
        sys.exit('NOT FOUND: ' + old[:80])
    s = s.replace(old, new, 1)

rep("""    private OverlayService $overlays;

    public function __construct(private GovernanceService $governance, private TransmissionMethodCatalogue $methods, private ScenarioSetService $sets, ?OverlayService $overlays = null)
    {
        $this->overlays = $overlays ?? new OverlayService($sets);
    }""", """    private OverlayService $overlays;

    private FliModelRegistry $registry;

    public function __construct(private GovernanceService $governance, private TransmissionMethodCatalogue $methods, private ScenarioSetService $sets, ?OverlayService $overlays = null, ?FliModelRegistry $registry = null)
    {
        $this->overlays = $overlays ?? new OverlayService($sets);
        $this->registry = $registry ?? new FliModelRegistry();
    }""")

rep("""        AuditLoggerService::log('FLI Fit Approved', 'fli_fits', $fitId, ['meta' => ['approved_by' => $approverId, 'label' => $label]]);
    }

    /** The approved fit for a period, with its relationship. */
    public function approvedFit(string $period): ?object
    {
        $ym = str_replace('-', '', $period);

        return DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')
            ->where('f.reporting_period', $ym)->where('f.approval_status', 'APPROVED')->orderByDesc('f.approved_at')
            ->first(['f.id', 'f.slope', 'f.intercept', 'f.correlation_r', 'f.r_squared', 'f.n_obs', 'r.statistic_code', 'r.proxy_code', 'r.lag_months']);
    }""", """        AuditLoggerService::log('FLI Fit Approved', 'fli_fits', $fitId, ['meta' => ['approved_by' => $approverId, 'label' => $label]]);
        // the approved fit becomes the model version in force from its month (FLI module D-F2)
        $this->registry->publishFromFit($fitId, $approverId, $label);
    }

    /**
     * The model in force for a period (FLI module D-F1, D-F2): the latest
     * published model version effective on or before it, with the fit it was
     * published from. The coefficients are the version's own, which never
     * change after publication. A month with no version in force has no model.
     */
    public function approvedFit(string $period): ?object
    {
        $v = $this->registry->inForce($period);
        if ($v === null) {
            return null;
        }
        $r = $v->fli_fit_id === null ? null : DB::table('fli_fits')->where('id', $v->fli_fit_id)->value('correlation_r');

        return (object) ['id' => $v->fli_fit_id === null ? null : (int) $v->fli_fit_id, 'model_version_id' => (int) $v->id, 'version' => (int) $v->version,
            'effective_from' => $v->effective_from, 'calibrated_period' => $v->calibrated_period,
            'slope' => $v->slope, 'intercept' => $v->intercept, 'correlation_r' => $r, 'r_squared' => $v->r_squared, 'n_obs' => $v->n_obs,
            'statistic_code' => $v->statistic_code, 'proxy_code' => $v->proxy_code, 'lag_months' => (int) $v->lag_months];
    }

    /**
     * What the route would do for a period, without writing anything: the
     * inputs in force and the adjustment of each scenario. The Apply to Loan
     * Book screen shows it before anyone presses apply.
     *
     * @return array{route:string,method:string,weighting:string,fit:?object,set:?object,scenarios:array,base:?array,overlays:list<array>,problem:?string}
     */
    public function preview(string $period): array
    {
        $asOf = CarbonImmutable::parse($period . '-01')->endOfMonth();
        $route = $this->setting('fli_adjustment_route', 'Regression', $asOf);
        $weighting = $this->setting('scenario_weighting_method', 'Weight the ECL across scenarios', $asOf);
        $set = DB::table('governed_scenario_sets')->where('reporting_period', $period)->whereIn('status', ['APPROVED', 'LOCKED'])->orderByDesc('version')->first();
        $fit = str_starts_with($route, 'Regression') ? $this->approvedFit($period) : null;
        $scenarios = [];
        $base = null;
        $problem = null;
        if ($fit !== null && $set !== null) {
            try {
                [$base, $scenarios] = $this->regressionAdjustments($period, $fit, $this->sets->paths((int) $set->id));
            } catch (Throwable $e) {
                $problem = $e->getMessage();
            }
        }
        $overlays = str_contains($route, 'overlay') ? $this->overlays->inForce($period) : collect();

        return ['route' => $route, 'method' => $this->methods->inForce(), 'weighting' => $weighting, 'fit' => $fit, 'set' => $set, 'scenarios' => $scenarios, 'base' => $base,
            'overlays' => $overlays->map(fn ($o) => ['id' => (int) $o->id, 'scope' => $o->scope, 'scope_value' => $o->scope_value, 'adjustment' => (float) $o->adjustment])->values()->all(), 'problem' => $problem];
    }

    /**
     * The adjustment of each scenario in each forecast year (FLI module D-F4):
     * year y is the twelve months that end 12(y + 1) months after the period,
     * read under the model's lag exactly as the route reads year 0, so year 0
     * here is the adjustment the route books. Years beyond the scenario path
     * hold the path's last year; the caller reverts them to the long-run PD.
     *
     * @return array<string,list<array{year:int,driver:?float,driver_period:string,source:string,predicted_proxy:?float,adjustment:?float}>>
     */
    public function adjustmentsByYear(string $period, object $fit, array $paths, int $years): array
    {
        $p = AsAtSeries::ym($period);
        $code = (string) $fit->statistic_code;
        $lag = (int) $fit->lag_months;
        $actuals = AsAtSeries::macro(null, $p, ['actual'], [$code])[$code] ?? [];
        $basePeriod = SeriesAligner::shiftPeriod($p, $lag);
        if (! array_key_exists($basePeriod, $actuals)) {
            throw new RuntimeException("No {$code} actual for {$basePeriod} is known at the end of {$period}; the model in force cannot be projected.");
        }
        $predBase = (float) $fit->slope * (float) $actuals[$basePeriod] + (float) $fit->intercept;
        if (abs($predBase) <= 1e-12) {
            throw new RuntimeException("The model in force predicts a base of zero for {$period}; no ratio to the base exists.");
        }
        $out = [];
        foreach ($paths['scenarios'] as $name => $sc) {
            $keys = array_keys(array_filter($sc['path'][$code] ?? [], fn ($v) => $v !== null));
            $last = $keys === [] ? null : max($keys);
            for ($y = 0; $y < $years; $y++) {
                $window = SeriesAligner::shiftPeriod($p, $lag - 12 * ($y + 1));
                $ahead = AsAtSeries::monthsBetween($p, $window);
                if ($ahead <= 0) {
                    $driver = $actuals[$window] ?? null;
                    $source = "actual {$window}";
                } else {
                    $offset = intdiv($ahead - 1, 12);
                    $use = $last === null ? null : min($offset, $last);
                    $driver = $use === null ? null : ($sc['path'][$code][$use] ?? null);
                    $source = $last !== null && $offset > $last ? "scenario path, year {$last} held" : "scenario path, forecast year {$offset}";
                }
                $pred = $driver === null ? null : (float) $fit->slope * (float) $driver + (float) $fit->intercept;
                $out[$name][] = ['year' => $y, 'driver' => $driver === null ? null : round((float) $driver, 6), 'driver_period' => $window, 'source' => $source,
                    'predicted_proxy' => $pred === null ? null : round($pred, 6), 'adjustment' => $pred === null ? null : round($pred / $predBase - 1, 6)];
            }
        }

        return $out;
    }""")

rep("""                $row = ['pd_post_fli' => round($post, 8), 'fli_adj' => $pre > 0 ? round($post / $pre - 1, 8) : 0,
                    'fli_route' => $route, 'fli_method' => $method, 'fli_fit_id' => $fit?->id, 'fli_set_id' => $set?->id, 'fli_by_scenario' => json_encode($by)];""", """                $row = ['pd_post_fli' => round($post, 8), 'fli_adj' => $pre > 0 ? round($post / $pre - 1, 8) : 0,
                    'fli_route' => $route, 'fli_method' => $method, 'fli_fit_id' => $fit?->id, 'fli_set_id' => $set?->id, 'fli_by_scenario' => json_encode($by)];
                if ($hasVersion) {
                    $row['fli_model_version_id'] = $fit?->model_version_id;
                }""")

rep("""        $hasOverlayIds = DB::getSchemaBuilder()->hasColumn('loan_books', 'fli_overlay_ids');
        $adjusted = 0; $held = 0;
        DB::transaction(function () use ($loans, $scenarios, $overlays, $method, $route, $fit, $set, $hasOverlayIds, &$adjusted, &$held) {""", """        $hasOverlayIds = DB::getSchemaBuilder()->hasColumn('loan_books', 'fli_overlay_ids');
        $hasVersion = DB::getSchemaBuilder()->hasColumn('loan_books', 'fli_model_version_id');
        $adjusted = 0; $held = 0;
        DB::transaction(function () use ($loans, $scenarios, $overlays, $method, $route, $fit, $set, $hasOverlayIds, $hasVersion, &$adjusted, &$held) {""")

rep("""'weighting' => $weighting, 'fit' => $fit?->id, 'fit_relationship'""", """'weighting' => $weighting, 'fit' => $fit?->id, 'model_version' => $fit?->version, 'model_version_id' => $fit?->model_version_id, 'fit_relationship'""")

open(p, 'w', encoding='utf-8').write(s)
print('ok')
