<?php

namespace App\Http\Controllers;

use App\Services\Pd\PdSegmentationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * PD bases compared (IFRS 9 Model Setup, PD Model): for a month-end, the PD of every segmentation
 * basis measured beside the booked one (PdSegmentationService::compareBases), the cumulative PD each
 * stage's 12-month PD gives over five years, and the pooled book's PD and LGD for every month the
 * data holds. A record and a comparison: only the governed basis (Governance Centre, PD & LGD
 * segments) is booked. Viewing is open to the readers of the IFRS 9 reports; measuring again needs
 * eir.govern.
 */
class PdBasesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:reports.ifrs9|eir.view|eir.govern|settings');
        $this->middleware('permission:eir.govern')->only('measure');
    }

    public function index(Request $request, PdSegmentationService $pd)
    {
        $months = DB::table('segment_parameter_runs')->where('measure', 'PD')->whereIn('status', ['applied', 'comparison'])->distinct()->orderByDesc('reporting_period')->pluck('reporting_period');
        $period = (string) ($request->query('period') ?: ($months->first() ?? ''));
        $booked = $period === '' ? null : DB::table('segment_parameter_runs')->where('measure', 'PD')->where('reporting_period', $period)->where('status', 'applied')->orderByDesc('id')->first();
        $runs = $period === '' ? collect() : DB::table('segment_parameter_runs')->where('measure', 'PD')->where('reporting_period', $period)->where('status', 'comparison')->orderBy('id')->get();
        $labels = array_flip(PdSegmentationService::BASES);
        $bases = [];
        foreach ($runs as $r) {
            $cells = DB::table('segment_parameter_results')->where('run_id', $r->run_id)->whereIn('stage', [1, 2])->orderByRaw('parent_key is not null')->orderBy('segment_key')->orderBy('stage')->get();
            $segments = [];
            foreach ($cells as $c) {
                $segments[$c->segment_key] ??= ['key' => $c->segment_key, 'label' => $c->segment_label, 'parent' => $c->parent_key, 'stages' => []];
                $segments[$c->segment_key]['stages'][$c->stage] = [
                    'cohort' => (int) $c->cohort_loans, 'defaults' => (int) $c->default_loans, 'observed' => $c->annual_rate !== null ? (float) $c->annual_rate : null,
                    'status' => $c->status, 'from' => $c->applied_from_key, 'applied' => $c->applied_rate !== null ? (float) $c->applied_rate : null, 'loans' => (int) $c->period_loans, 'reason' => $c->reason,
                ];
            }
            $own = $cells->where('status', 'own')->count();
            $parent = $cells->where('status', 'parent')->count();
            $bases[] = [
                'basis' => $r->basis, 'label' => $labels[$r->basis] ?? $r->basis, 'booked' => $booked !== null && $booked->basis === $r->basis,
                'window' => $r->window_start ? "{$r->window_start} to {$r->window_end}" : null, 'note' => $r->failure_reason,
                'own_cells' => $own, 'parent_cells' => $parent, 'failed_cells' => $cells->where('status', 'failed')->count(),
                'segments' => array_values($segments), 'measured_at' => $r->created_at,
            ];
        }
        usort($bases, fn ($a, $b) => [$b['booked'], $a['basis'] !== 'book'] <=> [$a['booked'], $b['basis'] !== 'book']);

        // the pooled book, every month: Stage 1 and 2 PD, and the LGD of the booked run
        $history = DB::table('segment_parameter_results as r')->join('segment_parameter_runs as u', 'u.run_id', '=', 'r.run_id')
            ->where('u.status', 'applied')->where('r.segment_key', 'book')
            ->selectRaw("r.reporting_period p, r.measure m, r.stage s, r.annual_rate a, r.observed_rate o, r.cohort_loans n, r.default_loans d")
            ->orderBy('r.reporting_period')->get()->groupBy('p')->map(function ($rows, $p) {
                $pd = fn ($s) => $rows->first(fn ($r) => $r->m === 'PD' && (int) $r->s === $s);
                $lgd = $rows->first(fn ($r) => $r->m === 'LGD');

                return ['period' => $p,
                    's1' => $pd(1)?->a !== null ? (float) $pd(1)->a : null, 's1_n' => $pd(1) ? "{$pd(1)->d}/{$pd(1)->n}" : null,
                    's2' => $pd(2)?->a !== null ? (float) $pd(2)->a : null, 's2_n' => $pd(2) ? "{$pd(2)->d}/{$pd(2)->n}" : null,
                    'lgd' => $lgd?->o !== null ? (float) $lgd->o : null, 'lgd_n' => $lgd ? (int) $lgd->n : null];
            })->values();

        return Inertia::render('PD/Bases', [
            'period' => $period,
            'months' => $months,
            'booked' => $booked ? ['basis' => $booked->basis, 'label' => $labels[$booked->basis] ?? $booked->basis, 'window' => "{$booked->window_start} to {$booked->window_end}", 'run_id' => $booked->run_id] : null,
            'bases' => $bases,
            'history' => $history,
            'settings' => $period !== '' ? rescue(fn () => $pd->settings($period), null, false) : null,
            'canGovern' => (bool) ($request->user()?->can('eir.govern') ?? false),
        ]);
    }

    public function measure(Request $request, PdSegmentationService $pd)
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        try {
            $r = $pd->compareBases($data['period'], 12, (int) $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pd-bases.index', ['period' => $data['period']])
            ->with('success', "{$data['period']}: every basis measured again for the record: " . collect($r)->map(fn ($v, $b) => "{$b} {$v['status']}")->implode('; ') . '. Nothing booked changes.');
    }
}
