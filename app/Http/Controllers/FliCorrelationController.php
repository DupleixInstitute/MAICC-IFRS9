<?php

namespace App\Http\Controllers;

use App\Services\AuditLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Financial Modelling, Forward-Looking Model, Correlation Finder (spec v4
 * section 14.4). Until the system audit of 9 October 2026 (finding M16) the
 * finder ran only on the console. The screen shows, for a period, the
 * latest sweep's ranked suggestions (one per driver and proxy at its best
 * lag, with the guardrail's verdict and reason) and the regression engine's
 * fits on them (slope, R-squared, p-value, observations, applied or declined
 * with the reason), and runs the finder for a chosen period. A fit that
 * passes is proposed and approved on FLI Adjustments; nothing here touches a
 * PD.
 */
class FliCorrelationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:eir.run')->only('run');
    }

    public function index(Request $request)
    {
        $period = (string) $request->query('period', $this->latestPeriod());
        $ym = str_replace('-', '', $period);
        $run = DB::table('analysis_runs')->where('run_type', 'correlation')->where('reporting_period', $ym)->orderByDesc('id')->first();
        $suggestions = $run ? DB::table('fli_suggestions')->where('run_id', $run->id)->orderByDesc('score')->limit(100)
            ->get(['id', 'statistic_code', 'proxy_code', 'lag_months', 'score', 'r_squared', 'sign_ok', 'verdict', 'reason']) : collect();
        $fits = DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')
            ->where('f.reporting_period', $ym)->orderByRaw("f.verdict = 'applied' desc")->orderByDesc('f.r_squared')->limit(100)
            ->get(['f.id', 'r.statistic_code', 'r.proxy_code', 'r.lag_months', 'r.expected_sign', 'r.method', 'f.slope', 'f.intercept', 'f.correlation_r', 'f.r_squared', 'f.p_value', 'f.n_obs', 'f.sign_ok', 'f.verdict', 'f.declined_reason', 'f.approval_status', 'f.computed_at']);
        $runs = DB::table('analysis_runs as a')->leftJoin('users as u', 'u.id', '=', 'a.run_by')->where('a.run_type', 'correlation')->orderByDesc('a.id')->limit(12)
            ->get(['a.id', 'a.reporting_period', 'a.inputs_hash', 'a.status', 'a.run_at', 'u.name as run_by_name'])
            ->map(fn ($r) => ['id' => $r->id, 'period' => substr($r->reporting_period, 0, 4) . '-' . substr($r->reporting_period, 4, 2), 'inputs_hash' => $r->inputs_hash, 'status' => $r->status, 'run_at' => $r->run_at, 'run_by' => $r->run_by_name,
                'suggestions' => DB::table('fli_suggestions')->where('run_id', $r->id)->count()]);
        $byVerdict = $suggestions->groupBy('verdict')->map->count();
        $lastRun = DB::table('audit_logs')->where('action', 'Correlation Finder Run')->where('reporting_period', $period)->orderByDesc('id')->first();

        return Inertia::render('FLI/Correlation', [
            'period' => $period, 'run' => $run ? ['id' => $run->id, 'run_at' => $run->run_at, 'inputs_hash' => $run->inputs_hash, 'status' => $run->status] : null,
            'suggestions' => $suggestions, 'fits' => $fits, 'runs' => $runs, 'byVerdict' => $byVerdict,
            'series' => ['macro' => (int) DB::table('macro_series')->distinct()->count('statistic_code'), 'proxies' => (int) DB::table('credit_loss_series')->distinct()->count('proxy_code')],
            'periods' => $this->periods(), 'lastRun' => $lastRun ? json_decode($lastRun->new_values, true) : null,
            'canRun' => (bool) (auth()->user()?->can('eir.run') ?? false),
        ]);
    }

    /** Run the finder for a period: the console command, called in the request, its output kept on the audit log. */
    public function run(Request $request)
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        try {
            $code = Artisan::call('fli:correlate', ['period' => $data['period'], '--user' => (int) $request->user()->id]);
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            return back()->with('error', 'The finder failed: ' . $e->getMessage());
        }
        AuditLoggerService::log('Correlation Finder Run', 'analysis_runs', null, ['reporting_period' => $data['period'],
            'new_values' => ['exit_code' => $code, 'output' => mb_substr($output, 0, 4000)], 'meta' => ['user' => $request->user()->id, 'screen' => 'Correlation Finder']]);
        if ($code !== 0) {
            return back()->with('error', 'The finder stopped for ' . $data['period'] . ': ' . mb_substr($output, 0, 500));
        }

        return redirect()->route('fli-correlation.index', ['period' => $data['period']])->with('success', 'The finder ran for ' . $data['period'] . '. ' . mb_substr(strtok($output, "\n") ?: '', 0, 300));
    }

    private function latestPeriod(): string
    {
        $run = DB::table('analysis_runs')->where('run_type', 'correlation')->max('reporting_period');
        if ($run) {
            return substr($run, 0, 4) . '-' . substr($run, 4, 2);
        }

        return (string) (DB::table('loan_books')->whereNotNull('ifrs9stage_post_qualitative')->max('reporting_period') ?? DB::table('loan_books')->max('reporting_period') ?? now()->format('Y-m'));
    }

    /** @return list<string> */
    private function periods(): array
    {
        $staged = DB::table('loan_books')->whereNotNull('ifrs9stage_post_qualitative')->distinct()->orderByDesc('reporting_period')->limit(36)->pluck('reporting_period')->all();
        $swept = DB::table('analysis_runs')->where('run_type', 'correlation')->distinct()->pluck('reporting_period')->map(fn ($p) => substr($p, 0, 4) . '-' . substr($p, 4, 2))->all();
        $all = array_values(array_unique(array_merge($staged, $swept)));
        rsort($all);

        return $all;
    }
}
