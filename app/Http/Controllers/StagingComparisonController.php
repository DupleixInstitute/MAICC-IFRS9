<?php

namespace App\Http\Controllers;

use App\Services\Eir\StagingComparisonService;
use App\Services\Eir\StagingPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Staging & SICR Rules, Staging Comparison (Staging Module Specification v1,
 * section 4): run a month's comparison against another staging policy, or the
 * policy in force with settings changed, and read it loan by loan with the
 * setting behind each difference. Nothing booked is changed.
 */
class StagingComparisonController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:settings');
    }

    public function index(Request $request)
    {
        $runs = Schema::hasTable('staging_comparison_runs')
            ? DB::table('staging_comparison_runs as r')->leftJoin('users as u', 'u.id', '=', 'r.run_by')->orderByDesc('r.id')->limit(50)
                ->get(['r.id', 'r.reporting_period', 'r.booked_preset', 'r.compared_preset', 'r.loans', 'r.loans_moved', 'r.booked_ecl', 'r.compared_ecl', 'r.note', 'r.created_at', 'u.name as run_by'])
            : collect();
        $chosen = $request->integer('run') ?: ($runs->first()->id ?? null);
        $run = $chosen ? DB::table('staging_comparison_runs')->find($chosen) : null;
        $rows = $run === null ? null : DB::table('staging_comparison_rows')->where('run_id', $run->id)
            ->when($request->query('show', 'moved') === 'moved', fn ($q) => $q->whereColumn('booked_stage', '!=', 'compared_stage'))
            ->orderByDesc('carrying_amount')->paginate(15)->withQueryString();

        return Inertia::render('StageingRules/Comparison', [
            'runs' => $runs,
            'run' => $run === null ? null : (array) $run + ['totals_decoded' => json_decode($run->totals, true), 'compared_rules_decoded' => json_decode($run->compared_rules, true), 'booked_rules_decoded' => json_decode($run->booked_rules, true)],
            'rows' => $rows,
            'show' => $request->query('show', 'moved'),
            'periods' => DB::table('loan_books')->distinct()->orderByDesc('reporting_period')->pluck('reporting_period'),
            'presets' => [StagingPolicy::PRESET_SINGLE, StagingPolicy::PRESET_DIRECTIVE, StagingPolicy::PRESET_CUSTOM],
            'settings' => collect(StagingPolicy::SETTINGS)->keys()->mapWithKeys(fn ($k) => [$k => ['label' => \App\Services\Eir\GovernanceService::catalogue()[$k]['label'], 'options' => \App\Services\Eir\GovernanceService::catalogue()[$k]['options']]]),
        ]);
    }

    public function store(Request $request, StagingComparisonService $comparison)
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'preset' => ['required', 'in:' . implode(',', [StagingPolicy::PRESET_SINGLE, StagingPolicy::PRESET_DIRECTIVE, StagingPolicy::PRESET_CUSTOM])],
            'settings' => ['array'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $settings = array_intersect_key(array_filter($data['settings'] ?? [], fn ($v) => $v !== null && $v !== ''), StagingPolicy::SETTINGS);
        try {
            $rules = $data['preset'] === StagingPolicy::PRESET_CUSTOM
                ? $comparison->rulesInForce($data['period'])->with($settings)
                : $comparison->rules($data['preset'], $settings);
            $id = $comparison->run($data['period'], $rules, $request->user()->id, $data['note'] ?? null);
        } catch (Throwable $e) {
            return back()->with('error', 'The comparison could not be run: ' . $e->getMessage());
        }

        return redirect()->route('staging-comparison.index', ['run' => $id])->with('success', "Comparison run {$id} for {$data['period']} is ready. Nothing booked was changed.");
    }

    public function export(int $run): StreamedResponse
    {
        $r = DB::table('staging_comparison_runs')->find($run);
        abort_if($r === null, 404);

        return response()->streamDownload(function () use ($r) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ["Staging comparison run {$r->id}, {$r->reporting_period}: booked under '{$r->booked_preset}', compared with '{$r->compared_preset}'. Nothing booked was changed."]);
            fputcsv($out, ['Contract', 'Customer', 'Portfolio', 'Term (months)', 'Booked stage', 'Compared stage', 'Days (booked rules)', 'Days (compared rules)', 'Reason', 'Carrying amount', 'EAD', 'Booked ECL', 'Compared ECL', 'ECL effect', 'Stage 3 interest effect', 'RBM class']);
            foreach (DB::table('staging_comparison_rows')->where('run_id', $r->id)->orderBy('contract_id')->cursor() as $x) {
                fputcsv($out, [$x->contract_id, $x->customer_name, $x->product_group, $x->tenor, $x->booked_stage, $x->compared_stage, $x->booked_dpd, $x->compared_dpd, $x->reason, $x->carrying_amount, $x->ead, $x->booked_ecl, $x->compared_ecl, $x->ecl_effect, $x->stage3_interest_effect, $x->rbm_class]);
            }
            fclose($out);
        }, "Staging comparison {$r->reporting_period} run {$r->id}.csv", ['Content-Type' => 'text/csv']);
    }
}
