<?php

namespace App\Http\Controllers;

use App\Exports\Ifrs9ReportExport;
use App\Services\Pd\OriginationPdService;
use App\Support\ReportDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

/**
 * PD at origination (PD at Origination Specification v1, sections 4 and 7):
 * each loan's starting reading with its label, and the PD-change test run in
 * shadow for a month, exported to Excel. Reads only.
 */
class OriginationPdController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:reports.ifrs9|eir.view|eir.govern|settings');
    }

    private function input(Request $request): array
    {
        return $request->validate([
            'basis' => ['nullable', Rule::in(array_keys(OriginationPdService::LABELS))],
            'shadow' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);
    }

    public function index(Request $request, OriginationPdService $service)
    {
        $data = $this->input($request);
        $available = OriginationPdService::available();
        $months = $available ? DB::table('loan_books')->select('reporting_period')->distinct()->orderByDesc('reporting_period')->pluck('reporting_period') : collect();
        $shadow = $data['shadow'] ?? $months->first();

        return Inertia::render('PD/OriginationPd', [
            'records' => $available ? DB::table('loan_origination_pds as o')->leftJoin('loan_books as b', function ($j) {
                $j->on('b.contract_id', '=', 'o.contract_id')->whereColumn('b.reporting_period', 'o.reporting_period');
            })->when($data['basis'] ?? null, fn ($q, $b) => $q->where('o.basis', $b))->orderBy('o.contract_id')->limit(500)
                ->get(['o.contract_id', 'b.customer_name', 'o.origination_date', 'o.reporting_period', 'o.months_after_origination', 'o.basis', 'o.pd_12m_post_fli', 'o.pd_12m_pre_fli', 'o.pd_lifetime', 'o.stage', 'o.segment_key'])
                ->map(fn ($r) => (array) $r + ['label' => OriginationPdService::LABELS[$r->basis] ?? $r->basis]) : [],
            'counts' => $available ? DB::table('loan_origination_pds')->selectRaw('basis, count(*) n')->groupBy('basis')->pluck('n', 'basis') : (object) [],
            'labels' => OriginationPdService::LABELS,
            'historyStart' => $available ? $service->historyStart() : null,
            'months' => $months,
            'filters' => (object) array_filter(['basis' => $data['basis'] ?? null, 'shadow' => $shadow]),
            'shadow' => $available && $shadow ? $service->shadow($shadow) : null,
        ]);
    }

    public function export(Request $request, OriginationPdService $service)
    {
        $data = $this->input($request);
        abort_unless(OriginationPdService::available(), 404);
        $n = fn ($v) => $v === null ? '' : number_format((float) $v * 100, 2) . '%';
        $records = DB::table('loan_origination_pds')->when($data['basis'] ?? null, fn ($q, $b) => $q->where('basis', $b))->orderBy('contract_id')->get();
        $sections = [[
            'heading' => 'Starting readings', 'columns' => ['Contract', 'Origination', 'PD taken in', 'Months after origination', 'Label', '12-month PD after FLI', '12-month PD before FLI', 'Lifetime PD', 'Stage', 'Segment'],
            'align' => ['l', 'l', 'l', 'r', 'l', 'r', 'r', 'r', 'r', 'l'],
            'rows' => $records->map(fn ($r) => [(string) $r->contract_id, (string) $r->origination_date, (string) $r->reporting_period, (string) $r->months_after_origination,
                OriginationPdService::LABELS[$r->basis] ?? $r->basis, $n($r->pd_12m_post_fli), $n($r->pd_12m_pre_fli), $n($r->pd_lifetime), (string) $r->stage, (string) $r->segment_key])->all(),
        ]];
        if ($period = $data['shadow'] ?? null) {
            $s = $service->shadow($period);
            $sections[] = ['heading' => "PD-change test in shadow, {$period}: {$s['would_move']} of {$s['tested']} Stage 1 loans would move to Stage 2 (staging unchanged)",
                'columns' => ['Contract', 'Customer', 'Starting month', 'Starting label', 'PD at start', 'PD now', 'Times', 'Points higher', 'Compared', 'Would move'],
                'align' => ['l', 'l', 'l', 'l', 'r', 'r', 'r', 'r', 'l', 'l'],
                'rows' => array_map(fn ($r) => [$r['contract_id'], (string) $r['customer'], $r['starting_month'], $r['starting_basis'], $n($r['pd_start']), $n($r['pd_now']),
                    number_format($r['ratio'], 2), number_format($r['points'], 2), $r['compared'], $r['would_move'] ? 'yes' : 'no'], $s['rows'])];
        }
        $report = ['company' => ReportDownload::company(), 'title' => 'PD at origination', 'subtitle' => "Each loan's starting risk, written once (PD at Origination Specification v1)",
            'period' => $data['shadow'] ?? null, 'generated_at' => now()->format('d M Y H:i'), 'generated_by' => optional($request->user())->name, 'kpis' => [], 'sections' => $sections];

        return Excel::download(new Ifrs9ReportExport($report), 'PD at origination ' . now()->format('Y-m-d') . '.xlsx');
    }
}
