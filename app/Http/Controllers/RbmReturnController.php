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

    public function export(Request $request, RbmReturnService $service): StreamedResponse
    {
        $period = (string) $request->query('period');
        $r = $service->build($period, (bool) $request->query('mega_farm', false));

        return response()->streamDownload(function () use ($r) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['RBM classification and provisioning return, PROVISIONAL LAYOUT, period ' . $r['period']]);
            foreach ($r['notes'] as $n) { fputcsv($out, [$n]); }
            fputcsv($out, []);
            fputcsv($out, ['A. Classification', 'Class', 'Short-term accounts', 'Short-term balance', 'Medium/long accounts', 'Medium/long balance', 'Total accounts', 'Total balance', 'IFRS 9 allowance']);
            foreach ($r['sections']['A_classification'] as $a) { fputcsv($out, ['', $a['class'], $a['short_term']['accounts'], round($a['short_term']['balance'], 2), $a['medium_long_term']['accounts'], round($a['medium_long_term']['balance'], 2), $a['total']['accounts'], $a['total']['balance'], $a['total']['ifrs9_allowance']]); }
            fputcsv($out, ['', 'Total', '', '', '', '', $r['loans'], $r['total_balance'], '']);
            fputcsv($out, ['', 'NPL ratio', $r['npl_ratio']]);
            fputcsv($out, []);
            fputcsv($out, ['B. Provisions', 'Class', 'Balance', 'Minimum rate', 'Minimum provision', 'IFRS 9 allowance', 'Shortfall']);
            foreach ($r['sections']['B_provisions'] as $b) { fputcsv($out, ['', $b['class'], $b['balance'], $b['minimum_rate'], $b['minimum_provision'], $b['ifrs9_allowance'], $b['shortfall']]); }
            fputcsv($out, ['', 'Totals', '', '', $r['sections']['B_totals']['minimum_provision'], $r['sections']['B_totals']['ifrs9_allowance'], 'higher of the two: ' . $r['sections']['B_totals']['higher_of_the_two']]);
            fputcsv($out, []);
            fputcsv($out, ['C. Interest in suspense']); foreach ($r['sections']['C_interest_in_suspense'] as $k => $v) { fputcsv($out, ['', $k, $v]); }
            fputcsv($out, ['D. Restructured facilities', $r['sections']['D_restructured']['note']]);
            fputcsv($out, ['E. Security']); foreach ($r['sections']['E_security'] as $k => $v) { fputcsv($out, ['', $k, $v]); }
            fputcsv($out, []);
            fputcsv($out, ['Per facility', 'Contract', 'Class', 'Term', 'Days past due', 'Balance', 'IFRS 9 stage']);
            foreach ($r['per_loan'] as $cid => $p) { fputcsv($out, ['', $cid, $p['class'], $p['term'], $p['dpd'], round($p['balance'], 2), $p['stage']]); }
            fclose($out);
        }, "RBM return (provisional) {$period}.csv", ['Content-Type' => 'text/csv']);
    }
}
