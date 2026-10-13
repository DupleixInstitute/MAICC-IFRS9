<?php

namespace App\Http\Controllers\FLI;

use App\Http\Controllers\Controller;
use App\Services\Fli\FliCloseService;
use App\Services\Fli\FliModelRegistry;
use App\Services\Fli\FliRouteService;
use App\Support\Fli\FliPlainLanguage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * External Calculations and History (FLI module specification v1).
 *
 * External Calculations is where a forward-looking model calibrated outside
 * the system (by a consultant, or in a validation workbook) is entered: the
 * driver, the credit-loss measure, the lag, the slope and the intercept, the
 * month it was calibrated on and the month it governs from, with the
 * evidence. One person proposes it and a second approves it; it is then a
 * model version like one published from a fit, and the forward-looking route
 * applies it to every loan. The page also shows what the route would do for
 * the month under the version in force, before anyone applies it.
 *
 * History is the record of every month: what it ran under (model version,
 * scenario set, overlays, vintage), the ECL before and after FLI, the
 * weighted ECL against the booked one, the sign-off, and the approvals that
 * led to it.
 *
 * The older one-slope screens wrote fli_adj straight onto the loans outside
 * maker-checker (system audit of 9 October 2026, finding M6); that path is
 * replaced by this one, which reaches the loans only through the governed
 * route.
 */
class ExternalCalculationsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:eir.govern')->only(['propose']);
    }

    private function period(Request $request): string
    {
        $p = (string) $request->query('period', '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p) ? $p : (string) (DB::table('loan_books')->whereNotNull('pd_prefli')->max('reporting_period') ?? now()->format('Y-m'));
    }

    public function index(Request $request, FliRouteService $route)
    {
        $period = $this->period($request);
        $plain = new FliPlainLanguage();
        $versions = DB::table('fli_model_versions as v')->leftJoin('users as p', 'p.id', '=', 'v.proposed_by')->leftJoin('users as a', 'a.id', '=', 'v.published_by')
            ->where('v.source', 'external')->orderByDesc('v.id')
            ->get(['v.*', 'p.name as proposer', 'a.name as approver'])
            ->map(fn ($v) => (array) $v + ['driver_name' => $plain->driver($v->statistic_code), 'proxy_name' => $plain->proxy($v->proxy_code)['name']]);
        $preview = $route->preview($period);

        return Inertia::render('FLI/ExternalCalculations/Index', [
            'period' => $period,
            'periods' => DB::table('loan_books')->distinct()->orderByDesc('reporting_period')->limit(36)->pluck('reporting_period'),
            'versions' => $versions->values(),
            'preview' => $preview,
            'drivers' => DB::table('macro_series')->distinct()->orderBy('statistic_code')->pluck('statistic_code')->map(fn ($c) => ['code' => $c, 'name' => $plain->driver($c)])->values(),
            'proxies' => DB::table('credit_loss_series')->distinct()->orderBy('proxy_code')->pluck('proxy_code')->map(fn ($c) => ['code' => $c, 'name' => $plain->proxy($c)['name']])->values(),
            'canGovern' => (bool) $request->user()?->can('eir.govern'),
            'userId' => (int) $request->user()->id,
        ]);
    }

    public function propose(Request $request, FliModelRegistry $registry)
    {
        $data = $request->validate([
            'statistic_code' => ['required', 'string', 'max:32'],
            'proxy_code' => ['required', 'string', 'max:32'],
            'lag_months' => ['required', 'integer', 'min:0', 'max:36'],
            'slope' => ['required', 'numeric'],
            'intercept' => ['required', 'numeric'],
            'r_squared' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'n_obs' => ['nullable', 'integer', 'min:1'],
            'calibrated_period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'effective_from' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'evidence' => ['required', 'string', 'max:4000'],
        ]);
        try {
            $id = $registry->proposeExternal($data, (int) $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "External model proposed (version record {$id}); a second person approves it before it governs any month.");
    }

    public function list(Request $request, FliCloseService $close)
    {
        $snapshots = DB::table('fli_run_snapshots as s')->leftJoin('users as c', 'c.id', '=', 's.created_by')->leftJoin('users as o', 'o.id', '=', 's.signed_off_by')
            ->leftJoin('fli_model_versions as v', 'v.id', '=', 's.model_version_id')->leftJoin('governed_scenario_sets as g', 'g.id', '=', 's.set_id')
            ->orderByDesc('s.reporting_period')->orderByDesc('s.id')
            ->get(['s.id', 's.reporting_period', 's.status', 's.loans', 's.pre_fli_ecl', 's.model_effect', 's.overlay_effect', 's.booked_ecl', 's.weighted_ecl', 's.difference', 's.reconciles',
                's.movement_pct', 's.needs_commentary', 's.commentary', 's.created_at', 's.signed_off_at', 's.approver_label', 'c.name as recorder', 'o.name as signer',
                'v.version as model_version', 'v.statistic_code', 'v.proxy_code', 'v.lag_months', 'g.name as set_name', 'g.version as set_version', 's.set_id']);
        $events = DB::table('audit_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->where(fn ($q) => $q->where('l.action', 'like', 'FLI %')->orWhere('l.action', 'like', 'Scenario Set %'))
            ->orderByDesc('l.id')->limit(300)->get(['l.id', 'l.action', 'l.entity_type', 'l.entity_id', 'l.reporting_period', 'l.created_at', 'u.name as user', 'l.meta']);
        $events = $events->map(function ($e) {
            $meta = json_decode((string) $e->meta, true) ?: [];
            $by = $e->user ?? (isset($meta['user']) ? DB::table('users')->where('id', $meta['user'])->value('name') : null) ?? (isset($meta['approved_by']) ? DB::table('users')->where('id', $meta['approved_by'])->value('name') : null);

            return ['id' => $e->id, 'action' => $e->action, 'entity' => $e->entity_type, 'entity_id' => $e->entity_id, 'period' => $e->reporting_period, 'at' => (string) $e->created_at, 'by' => $by, 'label' => $meta['label'] ?? null];
        });

        return Inertia::render('FLI/ExternalCalculations/List', [
            'snapshots' => $snapshots,
            'events' => $events->values(),
            'actions' => $events->pluck('action')->unique()->sort()->values(),
        ]);
    }
}
