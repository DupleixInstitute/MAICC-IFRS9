<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\Reports\EclReconciliationService;
use App\Services\Reports\LoanBookReconciliationService;
use App\Services\Reports\LoanBookExportService;
use App\Services\Reports\ECLExportService;
use App\Services\Reports\DisbursementReportService;
use App\Support\ReportDownload;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:reports'])->only(['index']);
    }

    /**
     * The old reports list: every report now sits in the Reports hub.
     */
    public function index()
    {
        return redirect()->route('ifrs9-reports.index');
    }

    /**
     * ECL Reconciliation (stage transitions). The screen is absorbed by the
     * IFRS 9 note, which carries the same movement by stage between two
     * months; the detailed CSV of the transitions still downloads here.
     */
    public function eclReconciliation(Request $request)
    {
        $serviceParams = [
            'portfolio_id' => $request->portfolio_id,
            'start_period' => $request->start_period,
            'end_period' => $request->end_period,
            'movement_type' => $request->movement_type ?? 'ecl_value',
            'report_type' => $request->report_type ?? 'summary',
            'detail_type' => $request->detail_type ?: null,
        ];

        // The summary reconciliation is the IFRS 9 note (its transfers net to
        // nil and every column ties); only the detailed contract lists
        // (new loans, derecognised loans, stage transitions) download here.
        if ($request->export && $serviceParams['report_type'] !== 'detailed') {
            return redirect()->route('ifrs9-reports.fs-disclosure', array_filter([
                'opening' => $request->start_period,
                'closing' => $request->end_period,
                'portfolio_id' => $request->portfolio_id,
                'unit' => 'full',
                'download' => 'csv',
            ]));
        }

        if ($request->export && $serviceParams['portfolio_id'] && $serviceParams['start_period'] && $serviceParams['end_period']) {
            try {
                return EclReconciliationService::exportToCsv($serviceParams);
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to export report: ' . $e->getMessage());
            }
        }

        return redirect()->route('ifrs9-reports.fs-disclosure', array_filter([
            'opening' => $request->start_period,
            'closing' => $request->end_period,
            'portfolio_id' => $request->portfolio_id,
        ]));
    }

    /**
     * Loan book reconciliation: opening book plus new loans, less loans that
     * left the book, plus the net change on continuing loans, to the closing
     * book. Shown on screen and downloadable as PDF, Excel or CSV.
     */
    public function loanBookReconciliation(Request $request)
    {
        $portfolioId = $request->portfolio_id;
        $startPeriod = $request->start_period;
        $endPeriod = $request->end_period;
        $report = null;
        $error = null;

        if ($portfolioId && $startPeriod && $endPeriod) {
            if ($startPeriod >= $endPeriod) {
                $error = 'The end period must be after the start period.';
            } else {
                $report = $this->loanBookBridge((int) $portfolioId, $startPeriod, $endPeriod);
            }
        }

        $format = $request->query('download') ?: ($request->export ? 'csv' : null);
        if ($report && in_array($format, ['pdf', 'xlsx', 'csv'], true)) {
            return ReportDownload::respond($this->loanBookPayload($report), 'MAIIC-loan-book-reconciliation-' . $startPeriod . '-to-' . $endPeriod, $format, 'portrait');
        }

        return Inertia::render('Reports/LoanBookReconciliation', [
            'report' => $report,
            'error' => $error,
            'portfolios' => LoanBookReconciliationService::getAvailablePortfolios(),
            'periods' => $portfolioId ? LoanBookReconciliationService::getAvailablePeriods((int) $portfolioId) : [],
            'selectedPortfolio' => $portfolioId,
            'selectedStartPeriod' => $startPeriod,
            'selectedEndPeriod' => $endPeriod,
        ]);
    }

    /** The bridge, from grouped queries over the two loan books. */
    private function loanBookBridge(int $portfolioId, string $start, string $end): array
    {
        $sum = fn ($q) => (object) ['n' => (int) ($q->n ?? 0), 'amount' => (float) ($q->amount ?? 0)];
        $book = fn (string $p) => $sum(DB::table('loan_books')->where('loan_portfolio_id', $portfolioId)->where('reporting_period', $p)
            ->selectRaw('COUNT(*) n, SUM(COALESCE(carrying_amount,0)) amount')->first());
        $opening = $book($start);
        $closing = $book($end);
        $new = $sum(DB::table('loan_books as e')
            ->leftJoin('loan_books as s', fn ($j) => $j->on('s.contract_id', '=', 'e.contract_id')->on('s.loan_portfolio_id', '=', 'e.loan_portfolio_id')->where('s.reporting_period', '=', $start))
            ->where('e.loan_portfolio_id', $portfolioId)->where('e.reporting_period', $end)->whereNull('s.id')
            ->selectRaw('COUNT(*) n, SUM(COALESCE(e.carrying_amount,0)) amount')->first());
        $gone = $sum(DB::table('loan_books as s')
            ->leftJoin('loan_books as e', fn ($j) => $j->on('e.contract_id', '=', 's.contract_id')->on('e.loan_portfolio_id', '=', 's.loan_portfolio_id')->where('e.reporting_period', '=', $end))
            ->where('s.loan_portfolio_id', $portfolioId)->where('s.reporting_period', $start)->whereNull('e.id')
            ->selectRaw('COUNT(*) n, SUM(COALESCE(s.carrying_amount,0)) amount')->first());
        $continuing = DB::table('loan_books as s')
            ->join('loan_books as e', fn ($j) => $j->on('e.contract_id', '=', 's.contract_id')->on('e.loan_portfolio_id', '=', 's.loan_portfolio_id')->where('e.reporting_period', '=', $end))
            ->where('s.loan_portfolio_id', $portfolioId)->where('s.reporting_period', $start)
            ->selectRaw('COUNT(*) n, SUM(COALESCE(e.carrying_amount,0) - COALESCE(s.carrying_amount,0)) amount')->first();
        $writeOffs = $sum(DB::table('loan_books')->where('loan_portfolio_id', $portfolioId)->where('reporting_period', $end)
            ->where('contract_status', 'like', '%writ%')->selectRaw('COUNT(*) n, SUM(COALESCE(carrying_amount,0)) amount')->first());

        $expected = $opening->amount + $new->amount - $gone->amount + (float) $continuing->amount;
        $expectedLoans = $opening->n + $new->n - $gone->n;

        return [
            'portfolio' => DB::table('loan_portfolios')->where('id', $portfolioId)->value('name'),
            'start_period' => $start,
            'end_period' => $end,
            'lines' => [
                ['key' => 'opening', 'label' => 'Opening loan book (' . $start . ')', 'loans' => $opening->n, 'amount' => $opening->amount],
                ['key' => 'new', 'label' => 'Add: new loans (in the closing book only)', 'loans' => $new->n, 'amount' => $new->amount],
                ['key' => 'gone', 'label' => 'Less: loans repaid in full or derecognised (in the opening book only)', 'loans' => $gone->n, 'amount' => -$gone->amount],
                ['key' => 'continuing', 'label' => 'Net change on continuing loans (interest and drawdowns less repayments)', 'loans' => (int) $continuing->n, 'amount' => (float) $continuing->amount],
                ['key' => 'expected', 'label' => 'Expected closing loan book', 'loans' => $expectedLoans, 'amount' => $expected],
                ['key' => 'closing', 'label' => 'Closing loan book (' . $end . ')', 'loans' => $closing->n, 'amount' => $closing->amount],
                ['key' => 'variance', 'label' => 'Difference', 'loans' => $closing->n - $expectedLoans, 'amount' => round($closing->amount - $expected, 2)],
            ],
            'write_offs' => ['loans' => $writeOffs->n, 'amount' => $writeOffs->amount],
            'ties' => abs($closing->amount - $expected) < 0.005 && $closing->n === $expectedLoans,
        ];
    }

    private function loanBookPayload(array $r): array
    {
        $m = fn ($v) => ($v < 0 ? '(' : '') . number_format(abs((float) $v), 2) . ($v < 0 ? ')' : '');
        $w = $r['write_offs'];

        return [
            'title' => 'Loan book reconciliation',
            'subtitle' => ($r['portfolio'] ?: 'Portfolio') . ', ' . $r['start_period'] . ' to ' . $r['end_period'] . '. Carrying amount.',
            'period' => $r['end_period'],
            'kpis' => [
                ['label' => 'Opening loan book', 'value' => $m($r['lines'][0]['amount']), 'tone' => 'maiic'],
                ['label' => 'Closing loan book', 'value' => $m($r['lines'][5]['amount']), 'tone' => 'maiic'],
                ['label' => 'Difference', 'value' => $m($r['lines'][6]['amount']), 'tone' => $r['ties'] ? 'maiic' : 'rose'],
            ],
            'sections' => [[
                'heading' => 'Reconciliation of the loan book',
                'columns' => ['', 'Loans', 'Carrying amount'],
                'align' => ['l', 'r', 'r'],
                'rows' => array_map(fn ($l) => [$l['label'], number_format($l['loans']), $m($l['amount'])], $r['lines']),
            ]],
            'notes' => [
                $r['ties'] ? 'The opening book plus the movements agrees with the closing book.' : 'The opening book plus the movements does not agree with the closing book: see the difference.',
                'Write-offs: ' . ($w['loans'] ? number_format($w['loans']) . ' loans in the closing book carry a write-off status (' . $m($w['amount']) . ').' : 'no loan carries a write-off status, so loans that leave the book are shown as repaid or derecognised.'),
            ],
        ];
    }

    /**
     * Loan Book Export Report
     */
    public function loanBookExport(Request $request)
    {
        $portfolioId = $request->portfolio_id;
        $startPeriod = $request->start_period;
        $endPeriod = $request->end_period;
        $mode = $request->mode ?? 'summary';

        if ($request->export && $startPeriod && $endPeriod) {
            try {
                return LoanBookExportService::exportToCsv([
                    'portfolio_id' => $portfolioId,
                    'start_period' => $startPeriod,
                    'end_period' => $endPeriod,
                    'mode' => $mode,
                ]);
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
            }
        }

        return Inertia::render('Reports/LoanBookExport', [
            'portfolios' => LoanBookExportService::getAvailablePortfolios(),
            'periods' => $portfolioId ? LoanBookExportService::getAvailablePeriods((int) $portfolioId) : [],
            'selectedPortfolio' => $portfolioId,
            'selectedStartPeriod' => $startPeriod,
            'selectedEndPeriod' => $endPeriod,
            'selectedMode' => $mode,
        ]);
    }

    /**
     * ECL Export Report
     */
    public function eclExport(Request $request)
    {
        $portfolioId = $request->portfolio_id;
        $reportingPeriod = $request->reporting_period;
        $mode = $request->mode ?? 'summary';
        $columns = $request->columns ?? [];

        if ($request->export && $portfolioId && $reportingPeriod) {
            try {
                return ECLExportService::exportToCsv([
                    'portfolio_id' => $portfolioId,
                    'reporting_period' => $reportingPeriod,
                    'mode' => $mode,
                    'columns' => $columns,
                ]);
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
            }
        }

        return Inertia::render('Reports/ECLExport', [
            'portfolios' => ECLExportService::getAvailablePortfolios(),
            'periods' => $portfolioId ? ECLExportService::getAvailablePeriods((int) $portfolioId) : [],
            'availableColumns' => ECLExportService::getAvailableColumns(),
            'selectedPortfolio' => $portfolioId,
            'selectedPeriod' => $reportingPeriod,
            'selectedMode' => $mode,
            'selectedColumns' => $columns,
        ]);
    }

    /**
     * Disbursement Report
     */
    public function disbursementReport(Request $request)
    {
        $portfolioId = $request->portfolio_id;
        $startPeriod = $request->start_period;
        $endPeriod = $request->end_period;
        $mode = $request->mode ?? 'summary';

        if ($request->export && $startPeriod && $endPeriod) {
            try {
                return DisbursementReportService::exportToCsv([
                    'portfolio_id' => $portfolioId,
                    'start_period' => $startPeriod,
                    'end_period' => $endPeriod,
                    'mode' => $mode,
                ]);
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to generate report: ' . $e->getMessage());
            }
        }

        return Inertia::render('Reports/DisbursementReport', [
            'portfolios' => DisbursementReportService::getAvailablePortfolios(),
            'periods' => $portfolioId ? DisbursementReportService::getAvailablePeriods((int) $portfolioId) : [],
            'selectedPortfolio' => $portfolioId,
            'selectedStartPeriod' => $startPeriod,
            'selectedEndPeriod' => $endPeriod,
            'selectedMode' => $mode,
        ]);
    }
}
