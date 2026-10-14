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
 * provisioning return of the Credit Risk Management for DFIs Directive,
 * 2018 (paragraphs 10 to 16; the loan loss reserve on the call report,
 * paragraph 15(3)), filled from the system in the directive's order, with
 * the CSV. Re-laid out line for line when MAIIC Risk's prescribed form
 * arrives. Paragraph references corrected 14 October 2026 against the
 * Gazette (they cited section 17, the monetary penalties, for the return).
 */
class RbmReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:reports.ifrs9']);
    }

    public function index(Request $request, RbmReturnService $service)
    {
        \App\Support\LedgerReadLimits::raise(); // reads the whole landing-zone ledger
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
        \App\Support\LedgerReadLimits::raise(); // reads the whole landing-zone ledger
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
            'subtitle' => 'Classification and provisioning return of the RBM Credit Risk Management for DFIs Directive, 2018 (paragraphs 10 to 16; the loan loss reserve on the call report, paragraph 15(3)), filled from the system in the order of the directive. Provisional layout.',
            'period' => $r['period'],
            'kpis' => [
                ['label' => 'Facilities', 'value' => number_format($r['loans']), 'tone' => 'maiic'],
                ['label' => 'Total balance', 'value' => $m($r['total_balance']), 'tone' => 'maiic'],
                ['label' => 'NPL ratio', 'value' => $r['npl_ratio'] !== null ? number_format($r['npl_ratio'] * 100, 2) . '%' : '-', 'tone' => 'rose'],
                ['label' => 'Provision: the higher of the two', 'value' => $m($t['higher_of_the_two']), 'tone' => 'amber'],
            ],
            'sections' => [
                ['heading' => 'A. Classification of facilities (paragraphs 10 and 11)', 'columns' => ['Class', 'Short-term accounts', 'Short-term balance', 'Medium/long accounts', 'Medium/long balance', 'Total accounts', 'Total balance', 'IFRS 9 allowance'],
                    'align' => ['l', 'r', 'r', 'r', 'r', 'r', 'r', 'r'],
                    'rows' => array_merge(array_map(fn ($a) => [$a['class'], (string) $a['short_term']['accounts'], $m($a['short_term']['balance']), (string) $a['medium_long_term']['accounts'], $m($a['medium_long_term']['balance']), (string) $a['total']['accounts'], $m($a['total']['balance']), $m($a['total']['ifrs9_allowance'])], $r['sections']['A_classification']),
                        [['Total', '', '', '', '', (string) $r['loans'], $m($r['total_balance']), $m($t['ifrs9_allowance'])]])],
                ['heading' => 'B. Provisions (paragraph 14 and the Schedule; loan loss reserve, paragraph 15)', 'columns' => ['Class', 'Balance', 'Minimum rate', 'Minimum provision', 'IFRS 9 allowance', 'Shortfall'], 'align' => ['l', 'r', 'r', 'r', 'r', 'r'],
                    'note' => isset($r['sections']['B_allowance_basis']) ? 'IFRS 9 allowance: ' . $r['sections']['B_allowance_basis']['message'] : null,
                    'rows' => array_merge(array_map(fn ($b) => [$b['class'], $m($b['balance']), number_format($b['minimum_rate'] * 100, 0) . '%', $m($b['minimum_provision']), $m($b['ifrs9_allowance']), $m($b['shortfall'])], $r['sections']['B_provisions']),
                        [['Totals', '', '', $m($t['minimum_provision']), $m($t['ifrs9_allowance']), 'Higher of the two: ' . $m($t['higher_of_the_two'])]],
                        isset($t['loan_loss_reserve']) ? [['Loan loss reserve (paragraph 15(1)): the minimum provision over the IFRS 9 allowance', '', '', '', '', $m($t['loan_loss_reserve'])]] : [])],
                ...(isset($r['sections']['B_loss_write_offs']) ? [['heading' => 'Loss facilities to be written off (paragraph 12)', 'note' => $r['sections']['B_loss_write_offs']['note'],
                    'columns' => ['Contract', 'Customer', 'Days past due', 'Loss since', 'Months loss', 'Write off by', 'Balance', 'IFRS 9 allowance', 'Status'], 'align' => ['l', 'l', 'r', 'l', 'r', 'l', 'r', 'r', 'l'],
                    'rows' => array_merge(array_map(fn ($l) => [$l['contract_id'], (string) ($l['customer_name'] ?? '-'), (string) $l['days_past_due'], ($l['loss_since_open'] ? 'at or before ' : '') . $l['loss_since'], (string) $l['months_classified_loss'], $l['write_off_due_by'], $m($l['balance']), $m($l['ifrs9_allowance']), $l['status']], $r['sections']['B_loss_write_offs']['rows']),
                        [['Total', '', '', '', '', '', $m($r['sections']['B_loss_write_offs']['balance']), '', $r['sections']['B_loss_write_offs']['overdue'] . ' past the write-off quarter']])]] : []),
                ['heading' => 'C. Interest in suspense (paragraph 13)', 'columns' => ['Item', 'Value'], 'align' => ['l', 'r'], 'rows' => $kv($r['sections']['C_interest_in_suspense']),
                    'note' => (string) ($r['sections']['C_interest_in_suspense']['note'] ?? '')],
                ...$this->restructured($r['sections']['D_restructured'] ?? [], $m),
                ['heading' => 'E. Security against classified facilities', 'columns' => ['Item', 'Value'], 'align' => ['l', 'r'], 'rows' => $kv($r['sections']['E_security']),
                    'note' => (string) ($r['sections']['E_security']['note'] ?? '')],
                ['heading' => 'Per facility', 'columns' => ['Contract', 'Class', 'Term', 'Days past due', 'Balance', 'IFRS 9 stage'], 'align' => ['l', 'l', 'l', 'r', 'r', 'l'],
                    'rows' => collect($r['per_loan'])->map(fn ($p, $cid) => [(string) $cid, $p['class'], $p['term'], (string) $p['dpd'], $m($p['balance']), 'Stage ' . $p['stage']])->values()->all()],
            ],
            'notes' => $r['notes'] ?? [],
        ];
    }

    /**
     * Section D for the downloads (paragraph 16(1)): the counts, then each restructured
     * facility with its Board approval, as on the screen. Only confirmed restructures are
     * listed; the candidates awaiting confirmation are counted, never reported as restructured.
     */
    private function restructured(array $d, \Closure $m): array
    {
        $heading = 'D. Restructured facilities (paragraph 16(1))';
        if (($d['accounts'] ?? null) === null) {
            return [['heading' => $heading, 'columns' => ['Item', 'Value'], 'align' => ['l', 'l'], 'rows' => [['Note', (string) ($d['note'] ?? '')]]]];
        }
        $words = fn ($v) => ucfirst(strtolower(str_replace('_', ' ', (string) $v)));
        $out = [['heading' => $heading, 'note' => (string) ($d['note'] ?? ''), 'columns' => ['Item', 'Value'], 'align' => ['l', 'r'], 'rows' => [
            ['Facilities restructured (confirmed on the register by the month-end)', (string) $d['accounts']],
            ['Their balance', $m($d['balance'])],
            ['Restructured in the month', (string) ($d['restructured_in_period'] ?? 0)],
            ['Without a Board approval recorded (paragraph 16(1) requires one)', (string) ($d['without_board_approval'] ?? 0)],
            ['Candidates awaiting confirmation (not reported as restructured)', (string) ($d['candidates_awaiting_confirmation'] ?? 0)],
        ]]];
        $out[] = ['heading' => 'D. Restructured facilities: by facility', 'columns' => ['Contract', 'Customer', 'Restructured on', 'Classification', 'Treatment', 'Board approval', 'Class', 'Balance'],
            'align' => ['l', 'l', 'l', 'l', 'l', 'l', 'l', 'r'],
            'rows' => ($d['facilities'] ?? []) === []
                ? [['No facility in this return has a restructure confirmed on the register by the month-end.', '', '', '', '', '', '', '']]
                : array_merge(array_map(fn ($f) => [$f['contract_id'], (string) ($f['customer_name'] ?? '-'), $f['event_date'] . (! empty($f['in_period']) ? ' (this month)' : ''), $words($f['classification']),
                    $f['treatment'] ? $words($f['treatment']) : 'None booked: before the take-on, or not yet applied by the revenue run',
                    $f['board_approval_ref'] ? $f['board_approval_ref'] . ($f['board_approval_date'] ? ' (' . $f['board_approval_date'] . ')' : '') : 'NONE RECORDED', $f['class'], $m($f['balance'])], $d['facilities']),
                    [['Total', '', '', '', '', '', $d['accounts'] . ' facilit' . ($d['accounts'] === 1 ? 'y' : 'ies'), $m($d['balance'])]])];

        return $out;
    }
}
