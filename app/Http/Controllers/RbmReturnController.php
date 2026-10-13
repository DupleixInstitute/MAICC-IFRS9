<?php

namespace App\Http\Controllers;

use App\Services\Rbm\RbmReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Risk & Regulatory, RBM Return (provisional): the classification and
 * provisioning return of the DFI directive, section 17, filled from the
 * system in the directive's order, with the CSV. Re-laid out line for
 * line when MAIIC Risk's prescribed form arrives.
 */
class RbmReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:reports.ifrs9']);
    }

    public function index(Request $request, RbmReturnService $service)
    {
        $period = (string) $request->query('period', DB::table('loan_books')->whereNotNull('build_method')->max('reporting_period') ?? DB::table('loan_books')->max('reporting_period'));
        $error = null; $return = null;
        try {
            $return = $service->build($period, (bool) $request->query('mega_farm', false));
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Reports/RbmReturn', ['period' => $period, 'return' => $return ? array_diff_key($return, ['per_loan' => 1]) : null, 'error' => $error, 'megaFarm' => (bool) $request->query('mega_farm', false),
            'periods' => DB::table('loan_books')->distinct()->orderByDesc('reporting_period')->limit(36)->pluck('reporting_period')]);
    }

    public function export(Request $request, RbmReturnService $service)
    {
        $period = (string) $request->query('period');
        $r = $service->build($period, (bool) $request->query('mega_farm', false));
        // every format is the branded report, CSV included, under one file name
        $format = in_array($request->query('format'), ['pdf', 'xlsx'], true) ? $request->query('format') : 'csv';

        return \App\Support\ReportDownload::respond($this->payload($r), 'MAIIC-RBM-return-provisional-' . $period, $format);
    }

    /** The return in the hub's report shape, for the branded PDF and workbook. */
    private function payload(array $r): array
    {
        $m = fn ($v) => $v === null ? '-' : number_format((float) $v, 2);
        $words = fn ($k) => ucfirst(str_replace('_', ' ', (string) $k));
        $kv = fn (array $a) => collect($a)->except('note')->map(fn ($v, $k) => [$words($k), is_numeric($v) && abs((float) $v) >= 1000 ? $m($v) : (string) $v])->values()->all();
        $t = $r['sections']['B_totals'];

        return [
            'title' => 'RBM return (provisional)',
            'subtitle' => 'Classification and provisioning return of the RBM DFI directive (section 17), filled from the system in the order of the directive. Provisional layout.',
            'period' => $r['period'],
            'kpis' => [
                ['label' => 'Facilities', 'value' => number_format($r['loans']), 'tone' => 'maiic'],
                ['label' => 'Total balance', 'value' => $m($r['total_balance']), 'tone' => 'maiic'],
                ['label' => 'NPL ratio', 'value' => $r['npl_ratio'] !== null ? number_format($r['npl_ratio'] * 100, 2) . '%' : '-', 'tone' => 'rose'],
                ['label' => 'Provision: the higher of the two', 'value' => $m($t['higher_of_the_two']), 'tone' => 'amber'],
            ],
            'sections' => [
                ['heading' => 'A. Classification of facilities (sections 9 to 11)', 'columns' => ['Class', 'Short-term accounts', 'Short-term balance', 'Medium/long accounts', 'Medium/long balance', 'Total accounts', 'Total balance', 'IFRS 9 allowance'],
                    'align' => ['l', 'r', 'r', 'r', 'r', 'r', 'r', 'r'],
                    'rows' => array_merge(array_map(fn ($a) => [$a['class'], (string) $a['short_term']['accounts'], $m($a['short_term']['balance']), (string) $a['medium_long_term']['accounts'], $m($a['medium_long_term']['balance']), (string) $a['total']['accounts'], $m($a['total']['balance']), $m($a['total']['ifrs9_allowance'])], $r['sections']['A_classification']),
                        [['Total', '', '', '', '', (string) $r['loans'], $m($r['total_balance']), $m($t['ifrs9_allowance'])]])],
                ['heading' => 'B. Provisions (section 12)', 'columns' => ['Class', 'Balance', 'Minimum rate', 'Minimum provision', 'IFRS 9 allowance', 'Shortfall'], 'align' => ['l', 'r', 'r', 'r', 'r', 'r'],
                    'rows' => array_merge(array_map(fn ($b) => [$b['class'], $m($b['balance']), number_format($b['minimum_rate'] * 100, 0) . '%', $m($b['minimum_provision']), $m($b['ifrs9_allowance']), $m($b['shortfall'])], $r['sections']['B_provisions']),
                        [['Totals', '', '', $m($t['minimum_provision']), $m($t['ifrs9_allowance']), 'Higher of the two: ' . $m($t['higher_of_the_two'])]])],
                ['heading' => 'C. Interest in suspense (section 13)', 'columns' => ['Item', 'Value'], 'align' => ['l', 'r'], 'rows' => $kv($r['sections']['C_interest_in_suspense'])],
                ['heading' => 'D. Restructured facilities (section 15)', 'columns' => ['Item', 'Value'], 'align' => ['l', 'l'], 'rows' => [['Note', (string) ($r['sections']['D_restructured']['note'] ?? '')]]],
                ['heading' => 'E. Security against classified facilities', 'columns' => ['Item', 'Value'], 'align' => ['l', 'r'], 'rows' => $kv($r['sections']['E_security']),
                    'note' => (string) ($r['sections']['E_security']['note'] ?? '')],
                ['heading' => 'Per facility', 'columns' => ['Contract', 'Class', 'Term', 'Days past due', 'Balance', 'IFRS 9 stage'], 'align' => ['l', 'l', 'l', 'r', 'r', 'l'],
                    'rows' => collect($r['per_loan'])->map(fn ($p, $cid) => [(string) $cid, $p['class'], $p['term'], (string) $p['dpd'], $m($p['balance']), 'Stage ' . $p['stage']])->values()->all()],
            ],
            'notes' => $r['notes'] ?? [],
        ];
    }
}
