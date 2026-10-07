<?php

namespace App\Http\Controllers;

use App\Http\Requests\Calc\RunAutoCorrelateRequest;
use App\Http\Requests\Calc\RunEclRequest;
use App\Jobs\RunAutoCorrelateJob;
use App\Jobs\RunEclJob;
use App\Models\AuditTrail;
use App\Services\Report\MartRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Calculators / Engines cockpit (SYSTEM_DESIGN 5-7).
 *
 * Every figure sent to a Calculators page is READ from the engine outputs the
 * services already wrote - the mart tables (via MartRepository), the parameter
 * registers (pd_lgd_inputs, ugd_parameters, lgd_parameters), the FLI registers
 * (fli_relationships / fli_fits / fli_suggestions), the SICR / EWS / stress
 * tables - or a clean empty-state when an engine has not yet run. Nothing is
 * computed or hardcoded here; the controller only shapes stored rows into
 * props. The two POST triggers dispatch QUEUED jobs (QUEUE_CONNECTION=database)
 * and return immediately.
 *
 * Owns only this controller, the Calculators pages, the two calc Jobs and
 * FormRequests, and the calc.* routes.
 */
class CalculatorController extends Controller
{
    public function __construct(private readonly MartRepository $marts)
    {
    }

    // -- Staging -------------------------------------------------------------

    public function staging(): Response
    {
        $run = $this->marts->latestRun();

        if (! $run) {
            return Inertia::render('Calculators/Staging', $this->emptyRun([
                'stageRows' => [], 'rbmRows' => [], 'dpdRows' => [],
                'notes' => $this->stagingNotes(),
            ]));
        }

        $runId = (int) $run->id;

        $stageRows = $this->marts->stageTotals($runId)->map(fn ($s) => [
            'stage'    => (int) $s->stage_final,
            'label'    => $s->label,
            'accounts' => (int) $s->account_count,
            'gross'    => (float) $s->gross_exposure,
            'ead'      => (float) $s->ead,
            'ecl'      => (float) $s->ecl,
            'coverage' => (float) $s->coverage_pct,
        ])->values();

        // RBM sub-class x stage is a genuine aggregate of the fact - it shows the
        // EXPLICIT governed RBM bands (Substandard / Doubtful / Loss) that replaced
        // the legacy blanket 'else -> Loss' default.
        $rbmRows = DB::table('ecl_results')
            ->where('ecl_run_id', $runId)
            ->groupBy('rbm_class', 'stage_final')
            ->orderBy('stage_final')
            ->orderByRaw('SUM(ecl_amount) DESC')
            ->get([
                'rbm_class',
                'stage_final',
                DB::raw('COUNT(*) as accounts'),
                DB::raw('SUM(ead) as ead'),
                DB::raw('SUM(ecl_amount) as ecl'),
            ])
            ->map(fn ($r) => [
                'rbmClass' => $r->rbm_class ?: 'Unclassified',
                'stage'    => (int) $r->stage_final,
                'accounts' => (int) $r->accounts,
                'ead'      => (float) $r->ead,
                'ecl'      => (float) $r->ecl,
            ])->values();

        $dpdRows = $this->marts->bucketSummary($runId)->map(fn ($b) => [
            'bucket'   => $b->dpd_bucket,
            'accounts' => (int) $b->account_count,
            'exposure' => (float) $b->exposure,
            'ecl'      => (float) $b->ecl,
            'coverage' => (float) $b->coverage_pct,
        ])->values();

        return Inertia::render('Calculators/Staging', [
            'hasRun'    => true,
            'run'       => $this->marts->runMeta($run),
            'stageRows' => $stageRows,
            'rbmRows'   => $rbmRows,
            'dpdRows'   => $dpdRows,
            'notes'     => $this->stagingNotes(),
        ]);
    }

    /** @return array<int, string> */
    private function stagingNotes(): array
    {
        return [
            'Staging is EXPLICIT: each facility is placed by its governed RBM class and DPD band - there is no legacy "else -> Loss" catch-all.',
            'A 30-day backstop forces any facility more than 30 DPD to at least Stage 2, independent of its RBM class (IFRS 9 5.5.11 rebuttable presumption).',
        ];
    }

    // -- PD / Transition Matrix ---------------------------------------------

    public function pd(): Response
    {
        $run    = $this->marts->latestRun();
        $period = $run->reporting_period ?? DB::table('pd_lgd_inputs')->max('reporting_period');

        // ASS6 averages per BU (the governed default basis). Stored as percent
        // units; normalised here to fractions so the page formats them uniformly.
        $avgRows = DB::table('pd_lgd_inputs as i')
            ->leftJoin('business_units as b', 'b.id', '=', 'i.business_unit_id')
            ->where('i.year_offset', 'avg')
            ->when($period, fn ($q) => $q->where('i.reporting_period', $period))
            ->get(['b.code', 'b.name', 'i.business_unit_id', 'i.metric', 'i.value']);

        $buInputs = $avgRows
            ->groupBy('business_unit_id')
            ->map(function ($rows) {
                $first = $rows->first();
                $m = $rows->keyBy('metric');
                $get = fn ($k) => isset($m[$k]) ? (float) $m[$k]->value / 100.0 : null;

                return [
                    'bu'           => $first->code ?: ('BU '.$first->business_unit_id),
                    'buName'       => $first->name ?: ('BU '.$first->business_unit_id),
                    'def1to2'      => $get('def_rate_stage1to2'),
                    'def1to3'      => $get('def_rate_stage1to3'),
                    'def2to3'      => $get('def_rate_stage2to3'),
                    'recoveryRate' => $get('recovery_rate'),
                    'cureRate'     => $get('cure_rate'),
                ];
            })->values();

        // Marginal default-rate curve (year 1..5) that feeds the power-survival
        // lifetime PD option - shown per BU for def_rate_stage1to3.
        $lifetimeSeries = DB::table('pd_lgd_inputs as i')
            ->leftJoin('business_units as b', 'b.id', '=', 'i.business_unit_id')
            ->where('i.metric', 'def_rate_stage1to3')
            ->whereIn('i.year_offset', ['1', '2', '3', '4', '5'])
            ->when($period, fn ($q) => $q->where('i.reporting_period', $period))
            ->orderBy('i.business_unit_id')
            ->orderByRaw('CAST(i.year_offset AS UNSIGNED)')
            ->get(['b.code', 'i.business_unit_id', 'i.year_offset', 'i.value'])
            ->groupBy('business_unit_id')
            ->map(fn ($rows) => [
                'bu'    => $rows->first()->code ?: ('BU '.$rows->first()->business_unit_id),
                'years' => $rows->map(fn ($r) => [
                    'year' => (int) $r->year_offset,
                    'rate' => (float) $r->value / 100.0,
                ])->values(),
            ])->values();

        // The applied PD basis actually used in the latest run (governed 12m
        // default vs the lifetime option) - a real distribution from the fact.
        $pdTypeDist = [];
        if ($run) {
            $pdTypeDist = DB::table('ecl_results')
                ->where('ecl_run_id', (int) $run->id)
                ->groupBy('pd_type_used')
                ->orderByRaw('COUNT(*) DESC')
                ->get(['pd_type_used', DB::raw('COUNT(*) as accounts')])
                ->map(fn ($r) => [
                    'type'     => $r->pd_type_used ?: 'n/a (Stage 3 - PD=1)',
                    'accounts' => (int) $r->accounts,
                ])->values();
        }

        return Inertia::render('Calculators/Pd', [
            'hasInputs'      => $buInputs->isNotEmpty(),
            'period'         => $period,
            'periodLabel'    => $this->marts->periodLabel($period),
            'buInputs'       => $buInputs,
            'lifetimeSeries' => $lifetimeSeries,
            'pdTypeDist'     => $pdTypeDist,
            // transition_matrices / pd_derivation_runs are governed but not yet
            // materialised for this period; read them honestly (empty-state).
            'hasMatrix'      => DB::table('transition_matrices')->when($period, fn ($q) => $q->where('reporting_period', $period))->exists(),
            'matrixRows'     => DB::table('transition_matrices')
                ->when($period, fn ($q) => $q->where('reporting_period', $period))
                ->orderBy('from_stage')->orderBy('to_stage')
                ->get(['business_unit_id', 'method', 'from_stage', 'to_stage', 'probability', 'window_len']),
            'derivationRuns' => DB::table('pd_derivation_runs')
                ->when($period, fn ($q) => $q->where('reporting_period', $period))
                ->orderByDesc('run_at')
                ->get(['business_unit_id', 'method', 'window_len', 'matrix_source', 'status', 'run_at']),
            'notes'          => [
                'Governed default: the 12-month PD is the ASS6 stage-1 default rate; Stage 2 uses the lifetime PD.',
                'Lifetime option: marginal default rates are chained by a power-survival curve (S(t) = (1 - q)^t) into a cumulative lifetime PD.',
            ],
        ]);
    }

    // -- LGD / Collateral ----------------------------------------------------

    public function lgd(): Response
    {
        $run    = $this->marts->latestRun();
        $period = $run->reporting_period ?? DB::table('pd_lgd_inputs')->max('reporting_period');

        // Recovery / cure per BU from the ASS6 register (percent units -> fractions).
        $recoveryCure = DB::table('pd_lgd_inputs as i')
            ->leftJoin('business_units as b', 'b.id', '=', 'i.business_unit_id')
            ->where('i.year_offset', 'avg')
            ->whereIn('i.metric', ['recovery_rate', 'cure_rate'])
            ->when($period, fn ($q) => $q->where('i.reporting_period', $period))
            ->get(['b.code', 'b.name', 'i.business_unit_id', 'i.metric', 'i.value'])
            ->groupBy('business_unit_id')
            ->map(function ($rows) {
                $first = $rows->first();
                $m = $rows->keyBy('metric');
                $recovery = isset($m['recovery_rate']) ? (float) $m['recovery_rate']->value / 100.0 : null;

                return [
                    'bu'           => $first->code ?: ('BU '.$first->business_unit_id),
                    'buName'       => $first->name ?: ('BU '.$first->business_unit_id),
                    'recoveryRate' => $recovery,
                    'cureRate'     => isset($m['cure_rate']) ? (float) $m['cure_rate']->value / 100.0 : null,
                    'lgd'          => $recovery !== null ? 1.0 - $recovery : null,
                ];
            })->values();

        // lgd_parameters is the governed LGD register (may be empty for this
        // period). Read honestly.
        $lgdParams = DB::table('lgd_parameters as l')
            ->leftJoin('business_units as b', 'b.id', '=', 'l.business_unit_id')
            ->when($period, fn ($q) => $q->where('l.reporting_period', $period))
            ->get(['b.code', 'l.business_unit_id', 'l.recovery_rate', 'l.cure_rate', 'l.method'])
            ->map(fn ($r) => [
                'bu'           => $r->code ?: ('BU '.$r->business_unit_id),
                'recoveryRate' => (float) $r->recovery_rate,
                'cureRate'     => (float) $r->cure_rate,
                'method'       => $r->method,
            ])->values();

        // Applied LGD (fractions) per stage from the latest run.
        $lgdApplied = [];
        if ($run) {
            $lgdApplied = DB::table('ecl_results')
                ->where('ecl_run_id', (int) $run->id)
                ->groupBy('stage_final')
                ->orderBy('stage_final')
                ->get([
                    'stage_final',
                    DB::raw('COUNT(*) as accounts'),
                    DB::raw('AVG(recovery_rate) as recovery_rate'),
                    DB::raw('AVG(cure_rate) as cure_rate'),
                    DB::raw('AVG(lgd) as lgd'),
                ])
                ->map(fn ($r) => [
                    'stage'        => (int) $r->stage_final,
                    'accounts'     => (int) $r->accounts,
                    'recoveryRate' => (float) $r->recovery_rate,
                    'cureRate'     => (float) $r->cure_rate,
                    'lgd'          => (float) $r->lgd,
                ])->values();
        }

        // Collateral coverage comes from the discounted collateral register
        // (collateral_allocations). The per-facility ecl_results collateral
        // column is customer-pooled and repeated, so it is NOT summed here.
        $collateralAgg = DB::table('collateral_allocations')
            ->when($period, fn ($q) => $q->where('reporting_period', $period))
            ->first([
                DB::raw('COUNT(*) as facilities'),
                DB::raw('SUM(allocated_value) as allocated'),
                DB::raw('SUM(discounted_collateral) as discounted'),
            ]);
        $hasCollateral = $collateralAgg && (int) $collateralAgg->facilities > 0;

        return Inertia::render('Calculators/Lgd', [
            'hasRun'        => (bool) $run,
            'run'           => $this->marts->runMeta($run),
            'period'        => $period,
            'periodLabel'   => $this->marts->periodLabel($period),
            'recoveryCure'  => $recoveryCure,
            'lgdParams'     => $lgdParams,
            'hasLgdParams'  => $lgdParams->isNotEmpty(),
            'lgdApplied'    => $lgdApplied,
            'hasCollateral' => $hasCollateral,
            'collateral'    => $hasCollateral ? [
                'facilities' => (int) $collateralAgg->facilities,
                'allocated'  => (float) $collateralAgg->allocated,
                'discounted' => (float) $collateralAgg->discounted,
            ] : null,
            'notes'         => [
                'LGD = 1 - recovery rate; the cure rate credits Stage 2/3 facilities expected to return to performing.',
                'Stage 3 (defaulted) facilities credit collateral only via the net-EAD path - no further cash-flow recovery is assumed.',
            ],
        ]);
    }

    // -- EAD -----------------------------------------------------------------

    public function ead(): Response
    {
        $run = $this->marts->latestRun();

        $ugd = DB::table('ugd_parameters')
            ->orderByDesc('effective_from')
            ->first(['ugd_stage1', 'ugd_stage2', 'ugd_stage3', 'effective_from']);

        $eadRows = collect();
        $totals  = null;
        if ($run) {
            $eadRows = DB::table('ecl_results')
                ->where('ecl_run_id', (int) $run->id)
                ->groupBy('stage_final')
                ->orderBy('stage_final')
                ->get([
                    'stage_final',
                    DB::raw('COUNT(*) as accounts'),
                    DB::raw('SUM(ead) as ead'),
                    DB::raw('SUM(undrawn_amount) as undrawn'),
                    DB::raw('SUM(undrawn_amount * ugd) as undrawn_ead'),
                    DB::raw('AVG(ugd) as avg_ugd'),
                ])
                ->map(fn ($r) => [
                    'stage'      => (int) $r->stage_final,
                    'accounts'   => (int) $r->accounts,
                    'drawn'      => (float) $r->ead - (float) $r->undrawn_ead, // on-balance
                    'undrawn'    => (float) $r->undrawn,
                    'undrawnEad' => (float) $r->undrawn_ead,                   // undrawn x UGD
                    'ugd'        => (float) $r->avg_ugd,
                    'ead'        => (float) $r->ead,
                ])->values();

            $totals = [
                'drawn'      => $eadRows->sum('drawn'),
                'undrawn'    => $eadRows->sum('undrawn'),
                'undrawnEad' => $eadRows->sum('undrawnEad'),
                'ead'        => $eadRows->sum('ead'),
                'accounts'   => $eadRows->sum('accounts'),
            ];
        }

        return Inertia::render('Calculators/Ead', [
            'hasRun'  => (bool) $run,
            'run'     => $this->marts->runMeta($run),
            'ugd'     => $ugd ? [
                'stage1'        => (float) $ugd->ugd_stage1,
                'stage2'        => (float) $ugd->ugd_stage2,
                'stage3'        => (float) $ugd->ugd_stage3,
                'effectiveFrom' => $ugd->effective_from,
            ] : null,
            'eadRows' => $eadRows,
            'totals'  => $totals,
            'notes'   => [
                'EAD = drawn (on-balance) exposure + undrawn commitment x UGD, where UGD is the governed usage-given-default factor per stage.',
                'UGD rises by stage (Stage 1 -> 2 -> 3) to reflect increasing drawdown as credit risk deteriorates.',
            ],
        ]);
    }

    // -- FLI / Correlation Finder cockpit ------------------------------------

    public function fli(): Response
    {
        // Series code -> declared frequency (annual / quarterly / monthly), so the
        // cockpit can show each dataset's frequency and read n as annual-equivalent.
        $freqByCode = DB::table('macro_variables')->pluck('frequency', 'code')->all();

        // Each relationship with its most-recent fit (verdict shown honestly).
        $relationships = DB::table('fli_relationships as r')
            ->leftJoin('business_units as b', 'b.id', '=', 'r.business_unit_id')
            ->leftJoin('fli_fits as f', function ($join) {
                $join->on('f.fli_relationship_id', '=', 'r.id')
                    ->whereRaw('f.id = (SELECT MAX(f2.id) FROM fli_fits f2 WHERE f2.fli_relationship_id = r.id)');
            })
            ->orderBy('r.statistic_code')
            ->get([
                'r.id', 'r.statistic_code', 'r.proxy_code', 'b.code as bu_code',
                'r.expected_sign', 'r.sign_status', 'r.sign_source', 'r.sign_note',
                'r.r2_cutoff', 'r.method', 'r.lag_months', 'r.is_active',
                'f.slope', 'f.correlation_r', 'f.r_squared', 'f.p_value', 'f.n_obs', 'f.n_years',
                'f.sign_ok', 'f.verdict', 'f.declined_reason', 'f.reporting_period as fit_period',
            ])
            ->map(fn ($r) => [
                'id'             => (int) $r->id,
                'statistic'      => $r->statistic_code,
                'proxy'          => $r->proxy_code,
                'bu'             => $r->bu_code,
                'expectedSign'   => $r->expected_sign,
                'signStatus'     => $r->sign_status,
                'signSource'     => $r->sign_source,
                'signNote'       => $r->sign_note,
                'r2Cutoff'       => (float) $r->r2_cutoff,
                'method'         => $r->method,
                'lagMonths'      => (int) $r->lag_months,
                'isActive'       => (bool) $r->is_active,
                'hasFit'         => $r->verdict !== null,
                'slope'          => $r->slope !== null ? (float) $r->slope : null,
                'correlationR'   => $r->correlation_r !== null ? (float) $r->correlation_r : null,
                'rSquared'       => $r->r_squared !== null ? (float) $r->r_squared : null,
                'pValue'         => $r->p_value !== null ? (float) $r->p_value : null,
                'nObs'           => $r->n_obs !== null ? (int) $r->n_obs : null,
                'nYears'         => $r->n_years !== null ? (float) $r->n_years : null,
                'freqX'          => $freqByCode[$r->statistic_code] ?? null,
                'freqY'          => $freqByCode[$r->proxy_code] ?? null,
                'signOk'         => $r->sign_ok !== null ? (bool) $r->sign_ok : null,
                'verdict'        => $r->verdict,
                'declinedReason' => $r->declined_reason,
                'fitPeriod'      => $r->fit_period,
            ])->values();

        $verdicts = DB::table('fli_fits')
            ->groupBy('verdict')
            ->get(['verdict', DB::raw('COUNT(*) as n')])
            ->mapWithKeys(fn ($r) => [($r->verdict ?: 'pending') => (int) $r->n]);

        // Latest suggestion sweep, ranked by score with the human-readable reason
        // + the persisted pre-fit diagnostics (native Dickey-Fuller stationarity,
        // normality shape, recommended method/transform) for the expandable row.
        $latestRunId = DB::table('fli_suggestions')->max('run_id');
        $suggestions = DB::table('fli_suggestions')
            ->when($latestRunId, fn ($q) => $q->where('run_id', $latestRunId))
            ->orderByDesc('score')
            ->get(['statistic_code', 'proxy_code', 'lag_months', 'score', 'r_squared', 'sign_ok', 'verdict', 'reason', 'diagnostics'])
            ->map(fn ($r) => [
                'statistic'   => $r->statistic_code,
                'proxy'       => $r->proxy_code,
                'lagMonths'   => (int) $r->lag_months,
                'score'       => (float) $r->score,
                'rSquared'    => (float) $r->r_squared,
                'signOk'      => $r->sign_ok !== null ? (bool) $r->sign_ok : null,
                'verdict'     => $r->verdict,
                'reason'      => $r->reason,
                'diagnostics' => $r->diagnostics !== null ? json_decode((string) $r->diagnostics, true) : null,
            ])->values();

        // Sweep meta for the cockpit: iterations = ranked pairs x governed lags.
        try {
            \App\Support\Fli\GovernedValues::ensureDefaults(config('database.default'));
            $gov = new \App\Support\Fli\GovernedValues(now()->format('Ym'), config('database.default'));
            $lagCount = max(1, count($gov->lagGrid()));
            $maxYears = $gov->int('fli.max_history_years');
        } catch (\Throwable) {
            $lagCount = 5;
            $maxYears = 5;
        }
        $pairs = $suggestions->count();
        $sweep = [
            'run_id' => $latestRunId,
            'pairs' => $pairs,
            'lags' => $lagCount,
            'iterations' => $pairs * $lagCount,
            'max_history_years' => $maxYears,
        ];

        return Inertia::render('Calculators/Fli', [
            'relationships'   => $relationships,
            'suggestions'     => $suggestions,
            'sweep'           => $sweep,
            'verdicts'        => [
                'applied'  => (int) ($verdicts['applied'] ?? 0),
                'declined' => (int) ($verdicts['declined'] ?? 0),
                'pending'  => (int) ($verdicts['pending'] ?? 0),
                'total'    => (int) $verdicts->sum(),
            ],
            'flash'           => ['auto' => session('calc_fli_flash')],
            'queueConnection' => (string) config('queue.default'),
            'notes'           => [
                'The regression guardrail is fail-closed: a fit is only APPLIED when it passes sign, R-squared, p-value and minimum-observation gates - otherwise it is DECLINED with a logged reason.',
                'Suggestions are advisory only: they rank candidate macro correlations (with caveats) for a modeller to consider, and never auto-apply an overlay.',
            ],
        ]);
    }

    public function runAutoCorrelate(RunAutoCorrelateRequest $request): RedirectResponse
    {
        // RBAC (SYSTEM_DESIGN 10.2): running auto-correlate is a User+Admin action.
        Gate::authorize('run-auto-correlate');

        $period = $request->input('period'); // nullable; job defaults to latest

        // Seed the live progress feed so the cockpit's polling panel shows
        // "queued" immediately; the job/command advance it through
        // running -> fitting -> complete | failed.
        \App\Support\Fli\AutoCorrelateProgress::queued($period ?: null);

        RunAutoCorrelateJob::dispatch($period ?: null);

        AuditTrail::create([
            'actor_id' => $request->user()?->id,
            'action' => 'fli_auto_correlate_trigger',
            'entity' => 'fli_suggestion_run',
            'entity_id' => $period ?: null,
            'detail' => ['period' => $period ?: null],
            'created_at' => now(),
        ]);

        return redirect()
            ->route('calc.fli')
            ->with('calc_fli_flash', [
                'status' => 'queued',
                'period' => $period,
                'at'     => now()->toDateTimeString(),
            ]);
    }

    /**
     * Live Auto-Correlate progress feed (polled by the FLI cockpit): the exact
     * (X x Y x lag) iteration under evaluation, running verdict tallies, the
     * best find so far, then the completion summary. Read-only JSON.
     */
    public function fliProgress(): \Illuminate\Http\JsonResponse
    {
        return response()->json(\App\Support\Fli\AutoCorrelateProgress::get() ?? ['state' => 'idle']);
    }

    /**
     * Approve the expected sign of a provisional FLI relationship (Admin). The
     * Auto-Correlate fits every discovered pair with a PROVISIONAL/either sign;
     * this sets the governed sign and marks it approved so the sign gate applies
     * on the next run. Audited (SYSTEM_DESIGN 10.2).
     */
    public function approveFliSign(\Illuminate\Http\Request $request, int $relationship): RedirectResponse
    {
        $data = $request->validate([
            'expected_sign' => ['required', 'string', 'in:positive,negative'],
        ]);

        $row = DB::table('fli_relationships')->where('id', $relationship)->first();
        if ($row === null) {
            return back()->with('error', 'Relationship not found.');
        }

        DB::table('fli_relationships')->where('id', $relationship)->update([
            'expected_sign' => $data['expected_sign'],
            'sign_status' => 'approved',
            'sign_note' => 'expected sign approved by '.($request->user()?->name ?? 'admin'),
            'sign_approved_by' => $request->user()?->id,
            'sign_approved_at' => now(),
            'updated_at' => now(),
        ]);

        AuditTrail::create([
            'actor_id' => $request->user()?->id,
            'action' => 'fli_sign_approve',
            'entity' => 'fli_relationship',
            'entity_id' => (string) $relationship,
            'detail' => [
                'statistic_code' => $row->statistic_code,
                'proxy_code' => $row->proxy_code,
                'expected_sign' => $data['expected_sign'],
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', "Expected sign approved ({$data['expected_sign']}) for {$row->statistic_code} x {$row->proxy_code}.");
    }

    // -- ECL Run -------------------------------------------------------------

    public function eclRun(): Response
    {
        $run = $this->marts->latestRun();

        // Per-run totals from the period mart (each run keeps its own mart rows).
        $runTotals = DB::table('ecl_period_summary')
            ->groupBy('ecl_run_id')
            ->get(['ecl_run_id', DB::raw('SUM(ecl) as ecl'), DB::raw('SUM(account_count) as accounts')])
            ->keyBy('ecl_run_id');

        $runs = DB::table('ecl_runs')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'reporting_period', 'status', 'started_at', 'finished_at', 'ecl_config_id', 'forecast_set_id'])
            ->map(fn ($r) => [
                'id'          => (int) $r->id,
                'period'      => $r->reporting_period,
                'periodLabel' => $this->marts->periodLabel($r->reporting_period),
                'status'      => $r->status,
                'startedAt'   => $r->started_at,
                'finishedAt'  => $r->finished_at,
                'totalEcl'    => isset($runTotals[$r->id]) ? (float) $runTotals[$r->id]->ecl : null,
                'accounts'    => isset($runTotals[$r->id]) ? (int) $runTotals[$r->id]->accounts : null,
                'configId'    => $r->ecl_config_id,
                'forecastSet' => $r->forecast_set_id,
            ])->values();

        // Reproducibility stamp for the latest run (analysis_runs, run_type=ecl).
        $stamp = null;
        if ($run) {
            $stamp = DB::table('analysis_runs')
                ->where('run_type', 'ecl')
                ->where('reporting_period', $run->reporting_period)
                ->orderByDesc('id')
                ->first(['inputs_hash', 'status', 'run_at']);
        }

        $stageRows = collect();
        $buRows    = collect();
        $recon     = null;
        if ($run) {
            $runId = (int) $run->id;
            $stageRows = $this->marts->stageTotals($runId)->map(fn ($s) => [
                'stage'    => (int) $s->stage_final,
                'label'    => $s->label,
                'accounts' => (int) $s->account_count,
                'ead'      => (float) $s->ead,
                'ecl'      => (float) $s->ecl,
                'coverage' => (float) $s->coverage_pct,
            ])->values();
            $buRows = $this->marts->businessUnitTotals($runId)->map(fn ($b) => [
                'bu'       => $b->bu_code,
                'name'     => $b->bu_name,
                'accounts' => (int) $b->account_count,
                'ead'      => (float) $b->ead,
                'ecl'      => (float) $b->ecl,
                'coverage' => (float) $b->coverage_pct,
            ])->values();
            $recon = $this->marts->reconciliation($runId); // goldenTarget (parity) + corrected
        }

        // Loaded periods available to run (YYYY-MM), for the Run-ECL selector.
        $periods = DB::table('facility_snapshots')
            ->select('reporting_period')->distinct()
            ->pluck('reporting_period')
            ->map(fn ($p) => strlen((string) $p) === 6
                ? ['value' => substr((string) $p, 0, 4).'-'.substr((string) $p, 4, 2), 'label' => $this->marts->periodLabel((string) $p)]
                : null)
            ->filter()->values();

        return Inertia::render('Calculators/EclRun', [
            'hasRun'          => (bool) $run,
            'run'             => $this->marts->runMeta($run),
            'runs'            => $runs,
            'stageRows'       => $stageRows,
            'buRows'          => $buRows,
            'reconciliation'  => $recon,
            'reproStamp'      => $stamp ? [
                'inputsHash' => $stamp->inputs_hash,
                'status'     => $stamp->status,
                'runAt'      => $stamp->run_at,
            ] : null,
            'periods'         => $periods,
            'flash'           => ['ecl' => session('calc_ecl_flash')],
            'queueConnection' => (string) config('queue.default'),
            'notes'           => [
                'Formula parity (11.9764 bn) - the engine reproduces the legacy per-facility result to the cent over the legacy inputs.',
                'Corrected run (12.3078 bn) - the methodologically-corrected engine on fdh_ifrs9; the +0.3314 bn bridge is categorised in the golden baseline.',
            ],
        ]);
    }

    public function runEcl(RunEclRequest $request): RedirectResponse
    {
        // RBAC (SYSTEM_DESIGN 10.2): triggering an ECL run is a User+Admin action.
        Gate::authorize('run-ecl');

        $period = (string) $request->validated()['period'];

        RunEclJob::dispatch($period, 3000, optional($request->user())->id);

        AuditTrail::create([
            'actor_id' => $request->user()?->id,
            'action' => 'ecl_run_trigger',
            'entity' => 'ecl_run',
            'entity_id' => $period,
            'detail' => ['period' => $period],
            'created_at' => now(),
        ]);

        return redirect()
            ->route('calc.ecl-run')
            ->with('calc_ecl_flash', [
                'status' => 'queued',
                'period' => $period,
                'at'     => now()->toDateTimeString(),
            ]);
    }

    // -- SICR ----------------------------------------------------------------

    public function sicr(): Response
    {
        $run    = $this->marts->latestRun();
        $period = $run->reporting_period ?? null;

        $items = DB::table('sicr_items')
            ->orderBy('target_stage')
            ->orderBy('group_code')
            ->get(['group_code', 'item_description', 'factor_type', 'target_stage', 'probation_months', 'is_active'])
            ->map(fn ($r) => [
                'groupCode'       => $r->group_code,
                'description'     => $r->item_description,
                'factorType'      => $r->factor_type,
                'targetStage'     => (int) $r->target_stage,
                'probationMonths' => (int) $r->probation_months,
                'isActive'        => (bool) $r->is_active,
            ])->values();

        $alerts = DB::table('sicr_alerts as a')
            ->leftJoin('customers as c', 'c.id', '=', 'a.customer_id')
            ->when($period, fn ($q) => $q->where('a.reporting_period', $period))
            ->orderByDesc('a.raised_at')
            ->limit(50)
            ->get(['a.facility_id', 'c.name as customer', 'a.target_stage', 'a.status', 'a.cure_status', 'a.raised_at'])
            ->map(fn ($r) => [
                'facilityId'  => $r->facility_id,
                'customer'    => $r->customer,
                'targetStage' => $r->target_stage !== null ? (int) $r->target_stage : null,
                'status'      => $r->status,
                'cureStatus'  => $r->cure_status,
                'raisedAt'    => $r->raised_at,
            ])->values();

        $overrides = DB::table('staging_overrides as o')
            ->when($period, fn ($q) => $q->where('o.reporting_period', $period))
            ->orderByDesc('o.created_at')
            ->limit(50)
            ->get(['o.facility_id', 'o.forced_stage', 'o.reason', 'o.approved_at', 'o.created_at'])
            ->map(fn ($r) => [
                'facilityId'  => $r->facility_id,
                'forcedStage' => (int) $r->forced_stage,
                'reason'      => $r->reason,
                'approvedAt'  => $r->approved_at,
                'createdAt'   => $r->created_at,
            ])->values();

        return Inertia::render('Calculators/Sicr', [
            'hasRun'       => (bool) $run,
            'run'          => $this->marts->runMeta($run),
            'items'        => $items,
            'alerts'       => $alerts,
            'hasAlerts'    => $alerts->isNotEmpty(),
            'overrides'    => $overrides,
            'hasOverrides' => $overrides->isNotEmpty(),
            'notes'        => [
                'Each SICR factor maps a qualitative trigger to a target stage (watchlist / covenant / forbearance -> Stage 2; distress / rescue / unlikely-to-pay -> Stage 3).',
                'A raised alert becomes a staging_override that forces the stage for the period; probation months govern how long a cured facility is held before it steps back down.',
            ],
        ]);
    }

    // -- EWS -----------------------------------------------------------------

    public function ews(): Response
    {
        $run = $this->marts->latestRun();

        $scorecard = collect();
        if ($run) {
            $scorecard = $this->marts->ewsScorecard((int) $run->id)->map(fn ($r) => [
                'indicator' => $r->indicator,
                'value'     => $r->value !== null ? (float) $r->value : null,
                'rag'       => $r->rag,
            ])->values();
        }

        return Inertia::render('Calculators/Ews', [
            'hasRun'    => (bool) $run && $scorecard->isNotEmpty(),
            'run'       => $this->marts->runMeta($run),
            'scorecard' => $scorecard,
            'notes'     => [
                'The early-warning scorecard rates governed portfolio indicators Red / Amber / Green against their thresholds.',
                'An indicator with no value (e.g. watchlist migration needs a prior period to compare) is shown as un-scored, never as zero.',
            ],
        ]);
    }

    // -- Stress Testing ------------------------------------------------------

    public function stress(): Response
    {
        $scenarios = DB::table('stress_scenarios')
            ->orderBy('id')
            ->get(['id', 'name', 'pd_scale', 'lgd_scale', 'macro_shock', 'status'])
            ->map(function ($r) {
                $shock = json_decode((string) $r->macro_shock, true) ?: [];

                return [
                    'id'       => (int) $r->id,
                    'name'     => $r->name,
                    'label'    => $shock['label'] ?? $r->name,
                    'pdScale'  => (float) $r->pd_scale,
                    'lgdScale' => (float) $r->lgd_scale,
                    'status'   => $r->status,
                ];
            })->values();

        // Runs with total stressed ECL + delta vs base (summed from stress_results).
        $resultTotals = DB::table('stress_results')
            ->groupBy('stress_run_id')
            ->get(['stress_run_id', DB::raw('SUM(ecl) as ecl'), DB::raw('SUM(delta_vs_base) as delta')])
            ->keyBy('stress_run_id');

        $runs = DB::table('stress_runs as sr')
            ->leftJoin('stress_scenarios as s', 's.id', '=', 'sr.stress_scenario_id')
            ->orderBy('sr.id')
            ->get(['sr.id', 'sr.reporting_period', 'sr.status', 'sr.run_at', 's.name as scenario', 's.pd_scale', 's.lgd_scale'])
            ->map(fn ($r) => [
                'id'          => (int) $r->id,
                'period'      => $r->reporting_period,
                'scenario'    => $r->scenario,
                'pdScale'     => (float) $r->pd_scale,
                'lgdScale'    => (float) $r->lgd_scale,
                'status'      => $r->status,
                'runAt'       => $r->run_at,
                'stressedEcl' => isset($resultTotals[$r->id]) ? (float) $resultTotals[$r->id]->ecl : null,
                'delta'       => isset($resultTotals[$r->id]) ? (float) $resultTotals[$r->id]->delta : null,
            ])->values();

        // Per-stage detail keyed by run for the base-vs-stressed table.
        $stageDetail = DB::table('stress_results')
            ->orderBy('stress_run_id')->orderBy('stage_final')
            ->get(['stress_run_id', 'stage_final', 'ecl', 'delta_vs_base'])
            ->map(fn ($r) => [
                'runId' => (int) $r->stress_run_id,
                'stage' => (int) $r->stage_final,
                'ecl'   => (float) $r->ecl,
                'delta' => (float) $r->delta_vs_base,
            ])->values();

        // Base ECL = the total of the 'base' scenario run.
        $baseRun = $runs->firstWhere('scenario', 'base');
        $baseEcl = $baseRun['stressedEcl'] ?? null;

        return Inertia::render('Calculators/StressTesting', [
            'hasRuns'     => $runs->isNotEmpty(),
            'scenarios'   => $scenarios,
            'runs'        => $runs,
            'stageDetail' => $stageDetail,
            'baseEcl'     => $baseEcl,
            'notes'       => [
                'Scenarios scale PD and LGD proportionally (adverse / severe) around the approved base; the base run reproduces the reported ECL.',
                'Delta vs base isolates the incremental ECL the stress adds, split by stage for the capital impact assessment.',
            ],
        ]);
    }

    // -- Shared --------------------------------------------------------------

    /**
     * Empty-run scaffold: the common "no completed ecl_run" prop shape, merged
     * with page-specific empty collections.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function emptyRun(array $extra = []): array
    {
        return array_merge([
            'hasRun' => false,
            'run'    => $this->marts->runMeta(null),
        ], $extra);
    }
}
