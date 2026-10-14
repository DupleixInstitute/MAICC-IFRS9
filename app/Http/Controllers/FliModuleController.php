<?php

namespace App\Http\Controllers;

use App\Services\AuditLoggerService;
use App\Services\Fli\FliCloseService;
use App\Services\Fli\FliImpactService;
use App\Services\Fli\FliModelRegistry;
use App\Services\Fli\FliMonthEnd;
use App\Services\Maintenance\CommandRunner;
use App\Services\Reports\EclBuildUpService;
use App\Support\Fli\FliPlainLanguage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Forward-Looking Information: the month-end Overview, the Impact Analysis
 * and the Model Versions (FLI module specification v1, sections 4 and 5).
 *
 * The Overview walks a month through its ten steps and runs the automated
 * ones in the background (the same runner as Maintenance, so a long ECL run
 * does not hold the browser); the approvals stay with people and are made on
 * the Overview or on their own screens. Viewing needs a login; running a
 * step, signing off and retiring need eir.govern.
 */
class FliModuleController extends Controller
{
    /** The steps the Overview may run, as artisan commands. */
    private const COMMANDS = [
        'fli-correlate' => ['label' => 'Refresh the data and fit the candidates', 'command' => 'fli:correlate {period}'],
        'fli-apply' => ['label' => 'Apply the forward-looking route', 'command' => 'fli:apply {period} --user={user}'],
        'fli-project' => ['label' => 'Project the scenarios year by year', 'command' => 'fli:month-end {period} --project --user={user}'],
        'ecl' => ['label' => 'Calculate the ECL on the post-FLI PD', 'command' => 'ifrs9:segment-ecl {period} --pd=pd_post_fli'],
        'fli-close' => ['label' => 'Record the month', 'command' => 'fli:month-end {period} --close --user={user}'],
        'fli-all' => ['label' => 'Run every remaining automated step', 'command' => 'fli:month-end {period} --all --user={user}'],
    ];

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:eir.govern')->only(['run', 'signOff', 'comment', 'retire', 'proposeExternal', 'approveExternal', 'rejectExternal']);
    }

    private function period(Request $request): string
    {
        $p = (string) $request->query('period', '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p) ? $p : (string) (DB::table('loan_books')->whereNotNull('pd_prefli')->max('reporting_period') ?? DB::table('loan_books')->max('reporting_period') ?? now()->format('Y-m'));
    }

    private function periods(): array
    {
        return DB::table('loan_books')->distinct()->orderByDesc('reporting_period')->limit(36)->pluck('reporting_period')->all();
    }

    // ------------------------------------------------------------------ Overview

    public function overview(Request $request, FliMonthEnd $monthEnd, FliCloseService $close, CommandRunner $runner)
    {
        $period = $this->period($request);
        $steps = $monthEnd->steps($period);
        $ym = str_replace('-', '', $period);
        $plain = new FliPlainLanguage();
        $userId = (int) $request->user()->id;

        // what waits for a second person this month
        $pending = [];
        foreach (DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')->leftJoin('users as u', 'u.id', '=', 'f.proposed_by')
            ->where('f.reporting_period', $ym)->where('f.approval_status', 'PROPOSED')->get(['f.id', 'f.r_squared', 'f.proposed_by', 'u.name as proposer', 'r.statistic_code', 'r.proxy_code', 'r.lag_months']) as $f) {
            $pending[] = ['kind' => 'fit', 'id' => (int) $f->id, 'title' => 'Fit ' . $f->id . ': ' . $plain->driver($f->statistic_code) . ' and ' . $plain->proxy($f->proxy_code)['name'] . ', lag ' . $f->lag_months . ' months, R-squared ' . number_format((float) $f->r_squared, 3),
                'proposer' => $f->proposer, 'own' => (int) $f->proposed_by === $userId, 'route' => 'fli-adjustments.approve', 'note' => 'Approving publishes it as the model version in force from ' . $period . '.'];
        }
        foreach (DB::table('governed_scenario_sets as s')->leftJoin('users as u', 'u.id', '=', 's.proposed_by')->where('s.reporting_period', $period)->where('s.status', 'PROPOSED')->get(['s.id', 's.name', 's.version', 's.proposed_by', 'u.name as proposer']) as $s) {
            $pending[] = ['kind' => 'set', 'id' => (int) $s->id, 'title' => "Scenario set {$s->name}, version {$s->version}", 'proposer' => $s->proposer, 'own' => (int) $s->proposed_by === $userId, 'route' => 'scenario-sets.approve', 'note' => 'See its effect on Impact Analysis before approving.'];
        }
        foreach (DB::table('fli_overlays as o')->leftJoin('users as u', 'u.id', '=', 'o.proposed_by')->where('o.reporting_period', $period)->where('o.status', 'PROPOSED')->get(['o.id', 'o.scope', 'o.scope_value', 'o.adjustment', 'o.proposed_by', 'u.name as proposer']) as $o) {
            $pending[] = ['kind' => 'overlay', 'id' => (int) $o->id, 'title' => "Overlay {$o->id} on the {$o->scope}" . ($o->scope_value ? " {$o->scope_value}" : '') . ', ' . round((float) $o->adjustment * 100, 2) . ' percent on the PD', 'proposer' => $o->proposer, 'own' => (int) $o->proposed_by === $userId, 'route' => 'fli-overlays.approve', 'note' => null];
        }
        foreach (DB::table('fli_model_versions as v')->leftJoin('users as u', 'u.id', '=', 'v.proposed_by')->where('v.status', 'PROPOSED')->get(['v.id', 'v.version', 'v.statistic_code', 'v.proxy_code', 'v.lag_months', 'v.effective_from', 'v.proposed_by', 'u.name as proposer']) as $v) {
            $pending[] = ['kind' => 'external', 'id' => (int) $v->id, 'title' => "External model, version {$v->version}: {$v->statistic_code} to {$v->proxy_code}, lag {$v->lag_months} months, from {$v->effective_from}", 'proposer' => $v->proposer, 'own' => (int) $v->proposed_by === $userId, 'route' => 'fli-models.approve-external', 'note' => null];
        }

        $snap = $close->latest($period);
        $modelStep = collect($steps)->firstWhere('key', 'model') ?? [];
        // the steps run from here, and the forward-looking ones run from Maintenance
        $recent = array_values(array_filter($runner->recent(40), fn ($r) => ($r['group'] ?? null) === 'fli' || str_starts_with((string) ($r['label'] ?? ''), 'Forward-looking')));

        return Inertia::render('FLI/Overview', [
            'period' => $period, 'periods' => $this->periods(), 'steps' => $steps, 'next' => $monthEnd->next($steps), 'pending' => $pending,
            'snapshot' => $snap, 'tolerance' => $close->tolerance($period), 'recent' => array_slice($recent, 0, 15), 'running' => $runner->running(),
            // no model in force (fli_without_model) and a month-end stopped part-way: each its own banner
            'withoutModel' => ! empty($modelStep['without_model']) ? $modelStep['status'] : null,
            'currency' => \App\Services\Fli\MonthEndCurrency::state($period),
            'commands' => array_map(fn ($c) => $c['label'], self::COMMANDS),
            'canGovern' => (bool) $request->user()?->can('eir.govern'), 'userId' => $userId,
        ]);
    }

    /** Launch one automated step in the background. */
    public function run(Request $request, CommandRunner $runner): JsonResponse
    {
        $key = (string) $request->input('key');
        $period = (string) $request->input('period');
        if (! isset(self::COMMANDS[$key])) {
            return response()->json(['ok' => false, 'message' => 'Unknown step.'], 422);
        }
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            return response()->json(['ok' => false, 'message' => 'Choose a month, YYYY-MM.'], 422);
        }
        if (! DB::table('loan_books')->where('reporting_period', $period)->exists()) {
            return response()->json(['ok' => false, 'message' => \App\Http\Controllers\MaintenanceController::noLoanBookMessage($period, self::COMMANDS[$key]['label'])], 422);
        }
        if ($busy = $runner->running()) {
            return response()->json(['ok' => false, 'message' => "\"{$busy['label']}\" is still running. Wait for it to finish."], 409);
        }
        $command = strtr(self::COMMANDS[$key]['command'], ['{period}' => $period, '{user}' => (string) (int) $request->user()->id]);
        $label = self::COMMANDS[$key]['label'] . " ({$period})";
        $id = $runner->launch($command, $label, ['danger' => 'caution', 'group' => 'fli', 'key' => $key]);
        AuditLoggerService::log('FLI Step Launched', 'fli', null, ['reporting_period' => $period, 'new_values' => ['command' => $command, 'run_id' => $id], 'meta' => ['label' => $label]]);

        return response()->json(['ok' => true, 'id' => $id, 'command' => 'php artisan ' . $command]);
    }

    public function output(string $id, CommandRunner $runner): JsonResponse
    {
        $status = $runner->status($id);

        return $status === null ? response()->json(['ok' => false, 'message' => 'Run not found.'], 404) : response()->json(['ok' => true, 'run' => $status]);
    }

    public function signOff(Request $request, int $snapshot, FliCloseService $close)
    {
        $data = $request->validate(['commentary' => ['nullable', 'string', 'max:4000']]);

        return $this->act(fn () => $close->signOff($snapshot, (int) $request->user()->id, $data['commentary'] ?? null), 'The month is signed off.');
    }

    public function comment(Request $request, int $snapshot, FliCloseService $close)
    {
        $data = $request->validate(['commentary' => ['required', 'string', 'max:4000']]);

        return $this->act(fn () => $close->comment($snapshot, (int) $request->user()->id, $data['commentary']), 'Commentary saved.');
    }

    // ------------------------------------------------------------------ Impact Analysis

    public function impact(Request $request, FliCloseService $close, FliImpactService $impact, EclBuildUpService $buildUp)
    {
        $period = $this->period($request);
        $snap = $close->latest($period);
        $prior = DB::table('fli_run_snapshots')->where('reporting_period', '<', $period)->whereIn('status', ['RECORDED', 'SIGNED_OFF'])->orderByDesc('reporting_period')->orderByDesc('id')->first();
        $sets = DB::table('governed_scenario_sets')->where('reporting_period', $period)->orderByDesc('version')->get(['id', 'name', 'version', 'status']);
        $compareId = (int) $request->query('set', $sets->firstWhere('status', 'PROPOSED')->id ?? ($sets->first()->id ?? 0));
        $whatIf = null;
        $whatIfError = null;
        if ($compareId) {
            try {
                $whatIf = $impact->simulate($period, $compareId);
            } catch (Throwable $e) {
                $whatIfError = $e->getMessage();
            }
        }
        // the loans the forward-looking adjustment moved most, for the drill-down
        $names = DB::table('loan_portfolios')->pluck('name', 'id')->all();
        $loans = $buildUp->loans($period)->map(function ($l) use ($names) {
            $x = EclBuildUpService::lineage($l);

            return ['id' => $l->id, 'contract_id' => $l->contract_id, 'customer' => $l->customer_name, 'portfolio' => $names[$l->loan_portfolio_id] ?? '-', 'stage' => $x['stage'],
                'pd_pre' => $x['pd_pre'], 'pd_post' => $x['pd_post'], 'ecl_pre' => $x['ecl_pre'], 'ecl' => $x['ecl'], 'fli_effect' => $x['fli_effect']];
        })->filter(fn ($r) => $r['fli_effect'] !== null)->sortByDesc(fn ($r) => abs((float) $r['fli_effect']))->values()->take(300);

        return Inertia::render('FLI/Impact', [
            'period' => $period, 'periods' => $this->periods(), 'snapshot' => $snap,
            'prior' => $prior ? ['period' => $prior->reporting_period, 'booked' => (float) $prior->booked_ecl, 'pre_fli' => (float) $prior->pre_fli_ecl, 'model_effect' => (float) $prior->model_effect, 'overlay_effect' => (float) $prior->overlay_effect] : null,
            'portfolios' => $names, 'sets' => $sets, 'compareId' => $compareId, 'whatIf' => $whatIf, 'whatIfError' => $whatIfError, 'loans' => $loans,
        ]);
    }

    // ------------------------------------------------------------------ Model Versions

    public function models(Request $request, FliModelRegistry $registry)
    {
        $period = $this->period($request);
        $plain = new FliPlainLanguage();
        $governed = $registry->monthsGoverned();
        $inForce = $registry->inForce($period);
        $versions = $registry->versions()->map(fn ($v) => (array) $v + ['driver_name' => $plain->driver($v->statistic_code), 'proxy_name' => $plain->proxy($v->proxy_code)['name'],
            'months' => $governed[$v->id] ?? [], 'in_force' => $inForce && (int) $inForce->id === (int) $v->id])->values();

        return Inertia::render('FLI/ModelVersions', [
            'period' => $period, 'periods' => $this->periods(), 'versions' => $versions, 'inForce' => $inForce,
            'cadence' => $this->setting('fli_model_cadence', $period), 'canGovern' => (bool) $request->user()?->can('eir.govern'), 'userId' => (int) $request->user()->id,
        ]);
    }

    public function retire(Request $request, int $version, FliModelRegistry $registry)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->act(fn () => $registry->retire($version, (int) $request->user()->id, $data['reason']), 'The version is retired; the months after it fall back to the version before it.');
    }

    public function approveExternal(Request $request, int $version, FliModelRegistry $registry)
    {
        return $this->act(fn () => $registry->approveExternal($version, (int) $request->user()->id), 'The external model is published from its effective month.');
    }

    public function rejectExternal(Request $request, int $version, FliModelRegistry $registry)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->act(fn () => $registry->rejectExternal($version, (int) $request->user()->id, $data['reason']), 'The external model is rejected.');
    }

    private function setting(string $key, string $period): ?string
    {
        try {
            return app(\App\Services\Eir\GovernanceService::class)->get($key, \Carbon\CarbonImmutable::parse($period . '-01')->endOfMonth());
        } catch (Throwable) {
            return null;
        }
    }

    private function act(callable $fn, string $ok)
    {
        try {
            $fn();
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $ok);
    }
}
