<?php

namespace App\Http\Controllers;

use App\Services\Fli\FliProjectionService;
use App\Services\Fli\FliRouteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Adjusted Forecast (FLI module specification v1, decision D-F4): the
 * forecast of the credit-loss measure and of the PD that the model version
 * in force gives under each scenario, year by year.
 *
 * The screen used to take a typed intercept, slope and macro path and keep
 * the result in the session. It now reads the approved model and the
 * approved scenario set of the month, so the forecast it shows is the one
 * the ECL is built on: year 0 is the adjustment the route books, the later
 * years run to the governed forecast horizon and revert to the long-run PD
 * after it, and each portfolio's PD term structure (conditional, survival,
 * marginal, cumulative) is stored for the month.
 */
class ManualForecastController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:eir.govern')->only(['process']);
    }

    public function show(Request $request, FliProjectionService $projection, FliRouteService $route)
    {
        $p = (string) $request->query('period', '');
        $period = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p) ? $p : (string) (DB::table('loan_books')->whereNotNull('pd_prefli')->max('reporting_period') ?? now()->format('Y-m'));
        $horizon = null;
        $error = null;
        try {
            $horizon = $projection->horizon($period);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
        $model = $route->approvedFit($period);
        $set = DB::table('governed_scenario_sets')->where('reporting_period', $period)->whereIn('status', ['APPROVED', 'LOCKED'])->orderByDesc('version')->first(['id', 'name', 'version', 'status']);
        $applied = DB::table('audit_logs')->where('action', 'FLI Route Applied')->where('reporting_period', $period)->max('created_at');
        $result = $projection->latest($period);

        return Inertia::render('FLI/ForecastMacroData/Manual', [
            'period' => $period,
            'periods' => DB::table('loan_books')->distinct()->orderByDesc('reporting_period')->limit(36)->pluck('reporting_period'),
            'model' => $model, 'set' => $set, 'horizon' => $horizon, 'error' => $error, 'result' => $result,
            'stale' => $result && $applied && $result['created_at'] < $applied,
            'canGovern' => (bool) $request->user()?->can('eir.govern'),
        ]);
    }

    public function process(Request $request, FliProjectionService $projection)
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        try {
            $r = $projection->run($data['period'], (int) $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('forecasting.manual', ['period' => $data['period']])->with('success', 'Projected ' . count($r['segments']) . ' portfolios under ' . count($r['adjustments']) . ' scenarios.');
    }
}
