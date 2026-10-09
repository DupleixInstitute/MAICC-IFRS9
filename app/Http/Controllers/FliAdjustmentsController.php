<?php

namespace App\Http\Controllers;

use App\Services\Fli\FliRouteService;
use App\Services\Fli\TransmissionMethodCatalogue;
use App\Support\Fli\FliPlainLanguage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Financial Modelling, Forward-Looking Model, FLI Adjustments (spec v4
 * sections 14.6 to 14.8): the chain's applied fits of the period, the one
 * approved for the route, the method cards with their live preconditions,
 * and the route's last result on the loans; propose, approve, apply.
 */
class FliAdjustmentsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:eir.govern')->only(['propose', 'approve', 'apply']);
    }

    public function index(Request $request, FliRouteService $route, TransmissionMethodCatalogue $methods)
    {
        $period = (string) $request->query('period', DB::table('loan_books')->whereNotNull('pd_prefli')->max('reporting_period') ?? DB::table('loan_books')->max('reporting_period') ?? now()->format('Y-m'));
        $ym = str_replace('-', '', $period);
        $fits = DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')->leftJoin('users as p', 'p.id', '=', 'f.proposed_by')->leftJoin('users as a', 'a.id', '=', 'f.approved_by')
            ->where('f.reporting_period', $ym)->orderByRaw("f.verdict = 'applied' desc")->orderByDesc('f.r_squared')->limit(500)
            ->get(['f.id', 'r.statistic_code', 'r.proxy_code', 'r.lag_months', 'f.slope', 'f.intercept', 'f.correlation_r', 'f.r_squared', 'f.p_value', 'f.n_obs', 'f.sign_ok', 'f.verdict', 'f.declined_reason', 'f.approval_status', 'f.approval_note', 'p.name as proposer', 'a.name as approver', 'f.approver_label', 'f.approved_at']);
        $loans = DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('pd_post_fli')
            ->selectRaw('count(*) n, round(avg(pd_prefli), 6) pre, round(avg(pd_post_fli), 6) post, round(avg(fli_adj), 6) adj, max(fli_route) route, max(fli_method) method, max(fli_fit_id) fit, max(fli_set_id) set_id, sum(fli_adj <> 0) adjusted')->first();
        // the codes in plain words for the screen; the approved fit pinned with who approved it and when
        $plain = new FliPlainLanguage();
        $fits = $fits->map(fn ($f) => (array) $f + ['driver_name' => $plain->driver($f->statistic_code), 'proxy_name' => $plain->proxy($f->proxy_code)['name'], 'declined_plain' => $plain->declined($f->declined_reason)])->values();
        $approved = $route->approvedFit($period);
        $approvedCard = null;
        if ($approved) {
            $who = DB::table('fli_fits as f')->leftJoin('users as a', 'a.id', '=', 'f.approved_by')->leftJoin('users as p', 'p.id', '=', 'f.proposed_by')->leftJoin('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')
                ->where('f.id', $approved->id)->first(['a.name as approver', 'p.name as proposer', 'f.approver_label', 'f.approved_at', 'f.p_value', 'r.expected_sign']);
            $approvedCard = (array) $approved + ['driver_name' => $plain->driver($approved->statistic_code), 'proxy_name' => $plain->proxy($approved->proxy_code)['name'],
                'approver' => $who->approver ?? null, 'proposer' => $who->proposer ?? null, 'approver_label' => $who->approver_label ?? null, 'approved_at' => $who->approved_at ?? null, 'p_value' => $who->p_value ?? null, 'expected_sign' => $who->expected_sign ?? null];
        }
        $last = DB::table('audit_logs')->where('action', 'FLI Route Applied')->where('reporting_period', $period)->orderByDesc('id')->first();

        return Inertia::render('FLI/Adjustments', [
            'period' => $period, 'fits' => $fits, 'approved' => $approvedCard, 'cards' => $methods->cards($period), 'methodInForce' => $methods->inForce(),
            'loans' => $loans, 'lastRun' => $last ? json_decode($last->new_values, true) : null,
            'periods' => DB::table('fli_fits')->distinct()->orderByDesc('reporting_period')->pluck('reporting_period')->map(fn ($p) => substr($p, 0, 4) . '-' . substr($p, 4, 2)),
            'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false),
        ]);
    }

    public function propose(Request $request, int $fit, FliRouteService $route)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        return $this->act(fn () => $route->proposeFit($fit, $request->user()->id, $data['note'] ?? null), "Fit {$fit} proposed for the route; a second person approves it.");
    }

    public function approve(Request $request, int $fit, FliRouteService $route)
    {
        return $this->act(fn () => $route->approveFit($fit, $request->user()->id), "Fit {$fit} approved for the route.");
    }

    public function apply(Request $request, FliRouteService $route)
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        try {
            $r = $route->apply($data['period'], $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Route applied for {$data['period']}: {$r['adjusted']} loans adjusted, {$r['held']} held" . ($r['note'] ? ' (' . $r['note'] . ')' : '') . '.');
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
