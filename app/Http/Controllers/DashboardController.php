<?php

namespace App\Http\Controllers;

use App\Models\ExpectedCreditLoss;
use App\Models\Import;
use App\Models\LoanBook;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Event;
use App\Models\Vital;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Course;
use App\Models\Article;
use App\Models\Invoice;
use App\Models\Currency;
use App\Models\Province;
use App\Models\LoanProduct;
use App\Models\PaymentType;
use App\Models\UserWidgets;
use App\Models\Consultation;
use Illuminate\Http\Request;
use App\Models\CourseMaterial;
use App\Models\InvoicePayment;
use App\Models\LoanApplication;
use App\Actions\Reports\Reports;
use App\Models\CourseRegistration;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LoanApplicationsExport;
use App\Models\LoanApplicationLinkedApprovalStage;
use App\Models\LoanApplicationReminder;
use App\Models\LoanPortfolio;
use App\Models\ReportingPeriods;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthUser;

class DashboardController extends Controller
{
    public $filterOptions;
    public function __construct()
    {
        $this->middleware('auth');
        $this->filterOptions = [];
    }
/**
 * IFRS 9 ECL dashboard. Everything is scoped by the global filter bar:
 * reporting period, loan portfolio and a compare-to period - all values
 * come from the database (reporting_periods / loan_portfolios), nothing
 * is hardcoded.
 */
public function index(Request $request)
{
    return Inertia::render('Dashboard', $this->dashboardState($request));
}

/**
 * The figures the dashboard shows for a period / portfolio / compare-to
 * choice. The page and its PDF (eclReportPdf) both read them from here, so
 * the download always matches the screen.
 */
private function dashboardState(Request $request): array
{
    // Available periods (Y-m, newest first) - only those with ECL calculated.
    $periods = ReportingPeriods::where('ecl_calculated', true)
        ->orderBy('period', 'desc')
        ->pluck('period')
        ->map(fn ($p) => Carbon::parse($p)->format('Y-m'))
        ->unique()
        ->values();

    $portfolios = LoanPortfolio::orderBy('name')->get(['id', 'name']);

    // The operations row (month-end status, latest loan book, recent
    // imports) shows even before any ECL has been calculated.
    $latestLoanBookPeriod = LoanBook::max('reporting_period');
    $recentImports = Import::orderByDesc('id')->limit(5)
        ->get(['id', 'name', 'status', 'records', 'created_at', 'completed_at']);

    if ($periods->isEmpty()) {
        return [
            'summary' => $this->emptySummary(),
            'periods' => [],
            'portfolios' => $portfolios,
            'selectedPeriod' => null,
            'selectedPortfolioId' => null,
            'comparePeriod' => null,
            'eclTrends' => [],
            'monthEnd' => $latestLoanBookPeriod ? $this->monthEndStatus($latestLoanBookPeriod) : null,
            'loanBookSnapshot' => $latestLoanBookPeriod ? $this->loanBookSnapshot($latestLoanBookPeriod, null) : null,
            'recentImports' => $recentImports,
            'error' => 'No ECL has been calculated yet. Load the loan book, apply PD and LGD, then run the ECL calculation.',
        ];
    }

    // --- Global filters (whitelisted against DB values, never trusted raw) ---
    $selectedPeriod = $request->input('period');
    if (! $periods->contains($selectedPeriod)) {
        $selectedPeriod = $periods->first();
    }

    $portfolioId = $request->integer('portfolio_id') ?: null;
    if ($portfolioId && ! $portfolios->contains('id', $portfolioId)) {
        $portfolioId = null;
    }

    // Compare-to period: user-picked, else the closest earlier period.
    $comparePeriod = $request->input('compare');
    if (! $periods->contains($comparePeriod) || $comparePeriod === $selectedPeriod) {
        $comparePeriod = $periods->first(fn ($p) => $p < $selectedPeriod);
    }

    // --- Scoped query builders -------------------------------------------
    $loanBookScope = fn () => tap(
        LoanBook::where('reporting_period', $selectedPeriod),
        fn ($q) => $portfolioId ? $q->where('loan_portfolio_id', $portfolioId) : null
    );

    // ECL rows are stored per (period, stage, calculation level, calc id).
    // A portfolio filter must pin level+id; unfiltered keeps the historic
    // behaviour (sum of whatever was calculated for the period).
    $eclScope = function (string $period) use ($portfolioId) {
        $q = ExpectedCreditLoss::where('reporting_period', $period);
        if ($portfolioId) {
            $q->where('ecl_calculation_level', 'portfolio')
                ->where('ecl_calculation_id', $portfolioId);
        }
        return $q;
    };

    // --- Loan book: EAD and loan count by stage in ONE grouped query ------
    $bookByStage = $loanBookScope()
        ->selectRaw('ifrs9stage_post_qualitative as stage, SUM((COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1))) as amount, SUM(carrying_amount) as gross, COUNT(*) as loans')
        ->groupBy('ifrs9stage_post_qualitative')
        ->get()
        ->keyBy('stage');
    $eadByStage = $bookByStage->map(fn ($r) => (float) $r->amount);
    $loansByStage = [(int) ($bookByStage[1]->loans ?? 0), (int) ($bookByStage[2]->loans ?? 0), (int) ($bookByStage[3]->loans ?? 0)];

    $stage1Amount = (float) ($eadByStage[1] ?? 0);
    $stage2Amount = (float) ($eadByStage[2] ?? 0);
    $stage3Amount = (float) ($eadByStage[3] ?? 0);
    // The exposure is the EAD the ECL engine measured (carrying amount plus
    // the drawn share of undrawn commitments), so the tiles tie to the saved
    // ECL runs and to the reports; the net carrying amount stays on the
    // gross carrying amount of the book.
    $grossCarryingAmount = (float) $eadByStage->sum();
    $bookGross = (float) $bookByStage->sum('gross');

    // --- ECL: totals, PD and LGD by stage in ONE grouped query ------------
    $eclByStage = $eclScope($selectedPeriod)
        ->selectRaw('ifrs9_stage, SUM(total_ecl) as total_ecl, AVG(pd_value_used) as avg_pd, AVG(lgd_value_used) as avg_lgd')
        ->groupBy('ifrs9_stage')
        ->get()
        ->keyBy('ifrs9_stage');

    $stage1ECL = (float) ($eclByStage[1]->total_ecl ?? 0);
    $stage2ECL = (float) ($eclByStage[2]->total_ecl ?? 0);
    $stage3ECL = (float) ($eclByStage[3]->total_ecl ?? 0);

    $stage1PD = round((float) ($eclByStage[1]->avg_pd ?? 0) * 100, 2);
    $stage2PD = round((float) ($eclByStage[2]->avg_pd ?? 0) * 100, 2);
    $stage3PD = round((float) ($eclByStage[3]->avg_pd ?? 0) * 100, 2);
    $lgdPercentage = round((float) ($eclByStage[3]->avg_lgd ?? 0) * 100, 2);
    $lgdPercentages = array_map(fn ($st) => round((float) ($eclByStage[$st]->avg_lgd ?? 0) * 100, 2), [1, 2, 3]);

    // --- Compare-to period: same formulas as the selected period ----------
    $lastTotalECLAllowance = collect([1 => 0, 2 => 0, 3 => 0]);
    $lastGrossAmount = collect([1 => 0, 2 => 0, 3 => 0]);
    $compareSummary = null;

    if ($comparePeriod) {
        $compareEclRows = $eclScope($comparePeriod)
            ->selectRaw('ifrs9_stage, SUM(total_ecl) as total, SUM(total_ead) as total_ead, AVG(pd_value_used) as avg_pd, AVG(lgd_value_used) as avg_lgd')
            ->groupBy('ifrs9_stage')
            ->get()
            ->keyBy('ifrs9_stage');
        $lastTotalECLAllowance = $compareEclRows->map(fn ($r) => $r->total);
        $lastGrossAmount = $compareEclRows->map(fn ($r) => $r->total_ead);

        $compareEad = tap(
            LoanBook::where('reporting_period', $comparePeriod),
            fn ($q) => $portfolioId ? $q->where('loan_portfolio_id', $portfolioId) : null
        )
            ->selectRaw('ifrs9stage_post_qualitative as stage, SUM((COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1))) as amount, SUM(carrying_amount) as gross, COUNT(*) as loans')
            ->groupBy('ifrs9stage_post_qualitative')
            ->get()
            ->keyBy('stage');
        $cLoans = (int) $compareEad->sum('loans');
        $cBookGross = (float) $compareEad->sum('gross');
        $compareEad = $compareEad->map(fn ($r) => (float) $r->amount);

        $cS1 = (float) ($compareEad[1] ?? 0);
        $cS2 = (float) ($compareEad[2] ?? 0);
        $cS3 = (float) ($compareEad[3] ?? 0);
        $cGross = (float) $compareEad->sum();
        $cSumEad = $cS1 + $cS2 + $cS3;
        $cEcl = (float) $lastTotalECLAllowance->sum();
        $cPd1 = round((float) ($compareEclRows[1]->avg_pd ?? 0) * 100, 2);
        $cPd2 = round((float) ($compareEclRows[2]->avg_pd ?? 0) * 100, 2);
        $cPd3 = round((float) ($compareEclRows[3]->avg_pd ?? 0) * 100, 2);
        $cLgd = array_map(fn ($st) => round((float) ($compareEclRows[$st]->avg_lgd ?? 0) * 100, 2), [1 => 1, 2 => 2, 3 => 3]);

        $compareSummary = [
            'carrying_amount' => $cGross,
            'total_ecl' => $cEcl,
            'ecl_percentage' => $cGross > 0 ? round(($cEcl / $cGross) * 100, 2) : 0,
            'stage_3_amount' => $cS3,
            'stage_3_percentage' => $cGross > 0 ? round(($cS3 / $cGross) * 100, 2) : 0,
            'paid_amount' => $cBookGross - $cEcl,
            'net_carrying_amount' => $cBookGross - $cEcl,
            'total_loans' => $cLoans,
            'weighted_pd' => $cSumEad > 0 ? ($cPd1 * $cS1 + $cPd2 * $cS2 + $cPd3 * $cS3) / $cSumEad : 0,
            'weighted_lgd' => $cSumEad > 0 ? ($cLgd[1] * $cS1 + $cLgd[2] * $cS2 + $cLgd[3] * $cS3) / $cSumEad : 0,
        ];
    }

    $totalECLAllowance = $stage1ECL + $stage2ECL + $stage3ECL;
    $totalEad = [$stage1Amount, $stage2Amount, $stage3Amount];
    $sumEad = array_sum($totalEad);
    $elcTotals = [$stage1ECL, $stage2ECL, $stage3ECL];

    $coverageRatio = $grossCarryingAmount > 0
        ? round(($totalECLAllowance / $grossCarryingAmount) * 100, 2)
        : 0;

    $lastCoverageRatio = $lastGrossAmount->sum() > 0
        ? round(($lastTotalECLAllowance->sum() / $lastGrossAmount->sum()) * 100, 2)
        : 0;

    $stage3Percentage = $grossCarryingAmount > 0
        ? round(($stage3Amount / $grossCarryingAmount) * 100, 2)
        : 0;

    $paidAmount = $bookGross - $totalECLAllowance;
    $paidPercentage = $bookGross > 0
        ? round(($paidAmount / $bookGross) * 100, 2)
        : 0;

    $pdPercentages = [$stage1PD, $stage2PD, $stage3PD];

    $weightedPD = $sumEad > 0 ? ($stage1PD * $stage1Amount + $stage2PD * $stage2Amount + $stage3PD * $stage3Amount) / $sumEad : 0;
    // LGD weighted by exposure across all three stages, as PD is (it used
    // to weight the Stage 3 LGD alone over the whole book).
    $weightedLGD = $sumEad > 0 ? ($lgdPercentages[0] * $stage1Amount + $lgdPercentages[1] * $stage2Amount + $lgdPercentages[2] * $stage3Amount) / $sumEad : 0;

    // --- ECL and coverage trend: ONE grouped query, optional from/to range -
    // Default range: the 12 months up to the selected period. 'all' shows
    // every period from the first. Without an explicit end the trend stops
    // at the selected period. Only periods that really exist are plotted;
    // nothing is zero-filled.
    $trendFrom = $request->input('trend_from');
    $trendTo = $request->input('trend_to');

    if ($trendFrom === 'all') {
        $lowerBound = null;
    } elseif ($periods->contains($trendFrom)) {
        $lowerBound = $trendFrom;
    } else {
        $trendFrom = null;
        $lowerBound = Carbon::createFromFormat('Y-m-d', $selectedPeriod . '-01')->subMonths(11)->format('Y-m');
    }
    $upperBound = $periods->contains($trendTo) ? $trendTo : $selectedPeriod;

    $trendPeriods = $periods
        ->when($lowerBound !== null, fn ($c) => $c->filter(fn ($p) => $p >= $lowerBound))
        ->filter(fn ($p) => $p <= $upperBound)
        ->values();

    $trendRows = tap(
        ExpectedCreditLoss::whereIn('reporting_period', $trendPeriods),
        fn ($q) => $portfolioId
            ? $q->where('ecl_calculation_level', 'portfolio')->where('ecl_calculation_id', $portfolioId)
            : null
    )
        ->selectRaw('reporting_period, SUM(total_ead) as total_ead, SUM(total_ecl) as total_ecl,
            SUM(CASE WHEN ifrs9_stage = 1 THEN total_ecl ELSE 0 END) as ecl_s1,
            SUM(CASE WHEN ifrs9_stage = 2 THEN total_ecl ELSE 0 END) as ecl_s2,
            SUM(CASE WHEN ifrs9_stage = 3 THEN total_ecl ELSE 0 END) as ecl_s3')
        ->groupBy('reporting_period')
        ->orderBy('reporting_period')
        ->get();

    $eclTrends = $trendRows->map(fn ($row) => [
        'period' => $row->reporting_period,
        'total_ead' => (float) $row->total_ead,
        'total_ecl' => (float) $row->total_ecl,
        'ecl_by_stage' => [(float) $row->ecl_s1, (float) $row->ecl_s2, (float) $row->ecl_s3],
        'ecl_percentage' => $row->total_ead > 0
            ? round(($row->total_ecl / $row->total_ead) * 100, 2)
            : 0,
    ])->values();

    $summary = [
        'carrying_amount' => $grossCarryingAmount,
        'total_ecl' => $totalECLAllowance,
        'last_ecl' => $lastTotalECLAllowance->toArray(),
        'ecl_percentage' => $coverageRatio,
        'last_ecl_percentage' => $lastCoverageRatio,
        'stage_3_amount' => $stage3Amount,
        'paid_amount' => $paidAmount,
        'net_carrying_amount' => $paidAmount,
        'total_loans' => array_sum($loansByStage),
        'loans_by_stage' => $loansByStage,
        'lgd_percentages' => $lgdPercentages,
        'stage_3_percentage' => $stage3Percentage,
        'paid_percentage' => $paidPercentage,
        'pd_percentages' => $pdPercentages,
        'total_eads' => $totalEad,
        'ecl_totals' => $elcTotals,
        'lgd_percentage' => $lgdPercentage,
        'weighted_pd' => $weightedPD,
        'weighted_lgd' => $weightedLGD,
        'reporting_period' => $selectedPeriod,
    ];

    return [
        'summary' => $summary,
        'compareSummary' => $compareSummary,
        'periods' => $periods,
        'portfolios' => $portfolios,
        'selectedPeriod' => $selectedPeriod,
        'selectedPortfolioId' => $portfolioId,
        'comparePeriod' => $comparePeriod,
        'trendFrom' => ($trendFrom === 'all' || $periods->contains($trendFrom)) ? $trendFrom : null,
        'trendTo' => $periods->contains($trendTo) ? $trendTo : null,
        'eclTrends' => $eclTrends,
        'monthEnd' => $this->monthEndStatus($selectedPeriod),
        'loanBookSnapshot' => $latestLoanBookPeriod ? $this->loanBookSnapshot($latestLoanBookPeriod, $portfolioId) : null,
        'recentImports' => $recentImports,
    ];
}

/**
 * The dashboard as a branded A4 PDF: the same figures the screen shows for
 * the chosen period, portfolio and compare-to period (dashboardState), with
 * the stage mix and the ECL trend drawn server side, the portfolio summary
 * against the compare-to period and the month-end status.
 */
public function eclReportPdf(Request $request)
{
    $state = $this->dashboardState($request);
    if (! empty($state['error'])) {
        return redirect()->route('dashboard')->with('error', $state['error']);
    }

    $period = $state['selectedPeriod'];
    $compare = $state['comparePeriod'];
    $s = $state['summary'];
    $c = $state['compareSummary'] ?? null;
    $portfolioName = $state['selectedPortfolioId']
        ? optional($state['portfolios']->firstWhere('id', $state['selectedPortfolioId']))->name
        : null;

    // Tiles and rows as the screen lists them (Dashboard.vue kpis / summaryRows).
    $kpis = array_map(fn ($k) => ['label' => $k[0], 'value' => $k[1], 'kind' => $k[3], 'sub' => $k[5] ?? null]
        + $this->pdfChange($k[1], $k[2], $k[3], $k[4], true), [
        ['Total exposure (EAD)', $s['carrying_amount'], $c['carrying_amount'] ?? null, 'money', true],
        ['Total ECL', $s['total_ecl'], $c['total_ecl'] ?? null, 'money', false],
        ['ECL coverage', $s['ecl_percentage'], $c['ecl_percentage'] ?? null, 'pts', false],
        ['Stage 3 exposure', $s['stage_3_amount'], $c['stage_3_amount'] ?? null, 'money', false, number_format($s['stage_3_percentage'], 2) . '% of book'],
        ['Weighted PD', $s['weighted_pd'], $c['weighted_pd'] ?? null, 'pts', false],
        ['Weighted LGD', $s['weighted_lgd'], $c['weighted_lgd'] ?? null, 'pts', false],
    ]);

    $summaryRows = array_map(fn ($r) => ['label' => $r[0], 'kind' => $r[2], 'bold' => $r[4] ?? false,
        'value' => $s[$r[1]], 'compare' => $c[$r[1]] ?? null] + $this->pdfChange($s[$r[1]], $c[$r[1]] ?? null, $r[2], $r[3]), [
        ['Number of loans', 'total_loans', 'count', true],
        ['Total EAD', 'carrying_amount', 'money', true, true],
        ['Stage 3 share of book', 'stage_3_percentage', 'pts', false],
        ['Weighted PD', 'weighted_pd', 'pts', false],
        ['Weighted LGD', 'weighted_lgd', 'pts', false],
        ['ECL coverage', 'ecl_percentage', 'pts', false],
        ['Total ECL', 'total_ecl', 'money', false, true],
        ['Net carrying amount', 'net_carrying_amount', 'money', true],
    ]);

    $trend = collect($state['eclTrends'])->values()->all();
    $currency = optional(Currency::find(optional(\App\Models\Setting::where('setting_key', 'currency')->first())->setting_value ?? 1))->code ?: 'MWK';

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.dashboard_ecl_pdf', [
        'company' => \App\Support\ReportDownload::company(),
        'logo' => \App\Support\ReportDownload::logoPath(),
        'preparedOn' => now()->format('d M Y H:i'),
        'preparedBy' => optional($request->user())->name,
        'currency' => $currency,
        'period' => $period,
        'compare' => $compare,
        'portfolioName' => $portfolioName,
        'summary' => $s,
        'compareSummary' => $c,
        'kpis' => $kpis,
        'summaryRows' => $summaryRows,
        'trend' => $trend,
        'trendChart' => count($trend) ? \App\Support\PdfCharts::trend($trend, $period) : null,
        'mixChart' => \App\Support\PdfCharts::stageMix($s['total_eads'], $s['ecl_totals']),
        'monthEnd' => $state['monthEnd'],
    ])->setPaper('a4', 'portrait')->setOption('enable_font_subsetting', true);

    \App\Support\ReportDownload::stampPageNumbers($pdf);

    return $pdf->download('MAIIC-IFRS9-dashboard-' . $period . ($compare ? '-vs-' . $compare : '')
        . ($portfolioName ? '-' . \Illuminate\Support\Str::slug($portfolioName) : '') . '.pdf');
}

/**
 * Change against the compare-to period, with the screen's status rules
 * (Dashboard.vue deltaInfo / summaryRows): money and counts in %, rates in
 * points; tiles are neutral only under 0.005 and have no "Watch".
 */
private function pdfChange($now, $then, string $kind, bool $goodWhenUp, bool $tile = false): array
{
    if ($then === null) {
        return ['change' => null, 'status' => null, 'up' => null];
    }
    $now = (float) $now;
    $then = (float) $then;
    if ($kind === 'money' || $kind === 'count') {
        if ($then == 0.0) {
            return ['change' => null, 'status' => null, 'up' => null];
        }
        $d = ($now - $then) / abs($then) * 100;
        $text = ($d > 0 ? '+' : '') . number_format($d, 1) . '%';
        $neutral = abs($d) < 1;
        $watch = abs($d) < 10;
    } else {
        $d = $now - $then;
        $text = ($d > 0 ? '+' : '') . number_format($d, 2) . ' pts';
        $neutral = abs($d) < 0.05;
        $watch = abs($d) < 1;
    }
    $good = $goodWhenUp ? $d > 0 : $d < 0;
    if ($tile) {
        $neutral = abs($d) < 0.005;
        $watch = false;
    }
    $status = $neutral ? 'Stable' : ($good ? 'Favourable' : ($watch ? 'Watch' : 'Adverse'));

    return ['change' => $text, 'status' => $status, 'up' => $neutral ? null : $d > 0];
}

/**
 * Where the month-end run stands for one period: loan book loaded, PD
 * applied, LGD applied, ECL calculated. reporting_periods.period is a
 * date (Y-m-01); loan_books.reporting_period is Y-m.
 */
private function monthEndStatus(string $period): array
{
    $rp = ReportingPeriods::whereDate('period', $period . '-01')->first();

    return [
        'period' => $period,
        'loan_book_rows' => (int) LoanBook::where('reporting_period', $period)->count(),
        'pd_applied' => (bool) ($rp?->pd_id),
        'pd_source' => $rp?->pd_calculation_source,
        'lgd_applied' => (bool) ($rp?->lgd_id),
        'lgd_source' => $rp?->lgd_calculation_source,
        'ecl_calculated' => (bool) ($rp?->ecl_calculated),
    ];
}

/**
 * Carrying amount and loan count by stage for the latest loaded loan book,
 * under the dashboard's portfolio filter.
 */
private function loanBookSnapshot(string $period, ?int $portfolioId): array
{
    $rows = LoanBook::where('reporting_period', $period)
        ->when($portfolioId, fn ($q) => $q->where('loan_portfolio_id', $portfolioId))
        ->selectRaw('ifrs9stage_post_qualitative as stage, COUNT(*) as loans, SUM(carrying_amount) as balance')
        ->groupBy('ifrs9stage_post_qualitative')
        ->get()
        ->keyBy('stage');

    $balance = [];
    $loans = [];
    foreach ([1, 2, 3] as $st) {
        $balance[] = (float) ($rows[$st]->balance ?? 0);
        $loans[] = (int) ($rows[$st]->loans ?? 0);
    }

    return [
        'period' => $period,
        'balance_by_stage' => $balance,
        'loans_by_stage' => $loans,
        'total_balance' => array_sum($balance),
        'total_loans' => array_sum($loans),
    ];
}

private function emptySummary(): array
{
    return [
        'carrying_amount' => 0,
        'total_ecl' => 0,
        'last_ecl' => [1 => 0, 2 => 0, 3 => 0],
        'ecl_percentage' => 0,
        'last_ecl_percentage' => 0,
        'stage_3_amount' => 0,
        'paid_amount' => 0,
        'net_carrying_amount' => 0,
        'total_loans' => 0,
        'loans_by_stage' => [0, 0, 0],
        'lgd_percentages' => [0, 0, 0],
        'stage_3_percentage' => 0,
        'paid_percentage' => 0,
        'pd_percentages' => [0, 0, 0],
        'total_eads' => [0, 0, 0],
        'ecl_totals' => [0, 0, 0],
        'lgd_percentage' => 0,
        'weighted_pd' => 0,
        'weighted_lgd' => 0,
        'reporting_period' => null,
    ];
}

    public function test()
    {
        return Inertia::render('Test', []);
    }

    public function saveWidgets(Request $request)
    {
        $widgets = config('widgets');
        $userWidgets = UserWidgets::where('user_id', Auth::id())->first();
        if (empty($userWidgets)) {
            $userWidgets = new UserWidgets();
            $userWidgets->user_id = Auth::id();
            $userWidgets->widgets = [];
            $userWidgets->save();
        }
        $selectedWidgets = [];
        foreach ($request->widgets as $key) {
            if (empty($userWidgets->widgets[$key])) {
                foreach ($widgets as $widget) {
                    if ($widget['id'] === $key) {
                        $selectedWidgets[$key] = $widget;
                    }
                }
            }
        }
        foreach ($userWidgets->widgets as $widget) {
            if (in_array($widget['id'], $request->widgets)) {
                $selectedWidgets[$widget['id']] = $widget;
            }
        }
        $userWidgets->widgets = $selectedWidgets;
        $userWidgets->save();
        return redirect()->back()->with('success', 'Updated successfully.');
    }

    public function updateWidgets(Request $request)
    {
        $widgets = config('widgets');

        $userWidgets = UserWidgets::where('user_id', Auth::id())->first();
        if (empty($userWidgets)) {
            $userWidgets = new UserWidgets();
            $userWidgets->user_id = Auth::id();
            $userWidgets->widgets = [];
            $userWidgets->save();
        }
        $selectedWidgets = [];
        foreach ($request->widgets as $key) {
            foreach ($widgets as $widget) {
                if ($widget['id'] === $key['id']) {
                    $selectedWidgets[$key['id']] = $key;
                }
            }
        }
        $userWidgets->widgets = $selectedWidgets;
        $userWidgets->save();
        return response()->json([
            'success' => true
        ]);
    }

    public function getTotalConsultationsCount()
    {
        $consultations = Consultation::count();
        $consultationsLastMonth = Consultation::whereBetween('created_at', [Carbon::today()->subMonth()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->subMonth()->endOfMonth()->format('Y-m-d H:i:s')])->count();
        $consultationsThisMonth = Consultation::whereBetween('created_at', [Carbon::today()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->endOfMonth()->format('Y-m-d H:i:s')])->count();
        $consultationsChange = 0;
        $consultationsChangeClass = 'text-green-500';
        if ($consultationsLastMonth > 0) {
            $consultationsChange = abs(($consultationsThisMonth - $consultationsLastMonth) * 100 / $consultationsLastMonth);
            if ($consultationsThisMonth < $consultationsLastMonth) {
                $consultationsChangeClass = 'text-red-500';
            }
        }
        if ($consultationsLastMonth === 0 && $consultationsThisMonth > 0) {
            $consultationsChange = 100;
        }
        return response()->json([
            'consultations' => number_format($consultations),
            'consultationsChange' => number_format($consultationsChange, 2),
            'consultationsChangeClass' => $consultationsChangeClass,
        ]);
    }

    public function getTotalMembersCount()
    {
        $members = Client::count();
        $membersLastMonth = Client::whereBetween('created_at', [Carbon::today()->subMonth()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->subMonth()->endOfMonth()->format('Y-m-d H:i:s')])->count();
        $membersThisMonth = Client::whereBetween('created_at', [Carbon::today()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->endOfMonth()->format('Y-m-d H:i:s')])->count();
        $membersChange = 0;
        $membersChangeClass = 'text-green-500';
        if ($membersLastMonth > 0) {
            $membersChange = abs(($membersThisMonth - $membersLastMonth) * 100 / $membersLastMonth);
            if ($membersThisMonth < $membersLastMonth) {
                $membersChangeClass = 'text-red-500';
            }
        }
        if ($membersLastMonth === 0 && $membersThisMonth > 0) {
            $membersChange = 100;
        }
        return response()->json([
            'members' => number_format($members),
            'membersChange' => number_format($membersChange, 2),
            'membersChangeClass' => $membersChangeClass,
        ]);
    }

    public function getTotalAppointmentsCount()
    {
        $appointments = Event::count();
        $appointmentsLastMonth = Event::whereBetween('created_at', [Carbon::today()->subMonth()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->subMonth()->endOfMonth()->format('Y-m-d H:i:s')])->count();
        $appointmentsThisMonth = Event::whereBetween('created_at', [Carbon::today()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->endOfMonth()->format('Y-m-d H:i:s')])->count();
        $appointmentsChange = 0;
        $appointmentsChangeClass = 'text-green-500';
        if ($appointmentsLastMonth > 0) {
            $appointmentsChange = abs(($appointmentsThisMonth - $appointmentsLastMonth) * 100 / $appointmentsLastMonth);
            if ($appointmentsThisMonth < $appointmentsLastMonth) {
                $appointmentsChangeClass = 'text-red-500';
            }
        }
        if ($appointmentsLastMonth === 0 && $appointmentsThisMonth > 0) {
            $appointmentsChange = 100;
        }
        return response()->json([
            'appointments' => number_format($appointments),
            'appointmentsChange' => number_format($appointmentsChange, 2),
            'appointmentsChangeClass' => $appointmentsChangeClass,
        ]);
    }

    public function getTotalPaymentsAmount()
    {
        $payments = InvoicePayment::selectRaw('coalesce(sum(if(xrate>1,amount*xrate,amount/xrate)),0) as total_amount')
            ->first()->total_amount ?? 0;
        $paymentsLastMonth = InvoicePayment::whereBetween('created_at', [Carbon::today()->subMonth()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->subMonth()->endOfMonth()->format('Y-m-d H:i:s')])
            ->selectRaw('coalesce(sum(if(xrate>1,amount*xrate,amount/xrate)),0) as total_amount')
            ->first()->total_amount ?? 0;
        $paymentsThisMonth = InvoicePayment::whereBetween('created_at', [Carbon::today()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->endOfMonth()->format('Y-m-d H:i:s')])
            ->selectRaw('coalesce(sum(if(xrate>1,amount*xrate,amount/xrate)),0) as total_amount')
            ->first()->total_amount ?? 0;
        $paymentsChange = 0;
        $paymentsChangeClass = 'text-green-500';
        if ($paymentsLastMonth > 0) {
            $paymentsChange = abs(($paymentsThisMonth - $paymentsLastMonth) * 100 / $paymentsLastMonth);
            if ($paymentsThisMonth < $paymentsLastMonth) {
                $paymentsChangeClass = 'text-red-500';
            }
        }
        if ($paymentsLastMonth === 0 && $paymentsThisMonth > 0) {
            $paymentsChange = 100;
        }
        return response()->json([
            'payments' => number_format($payments),
            'paymentsChange' => number_format($paymentsChange, 2),
            'paymentsChangeClass' => $paymentsChangeClass,
        ]);
    }

    public function getTotalInvoicesAmount()
    {
        $invoices = Invoice::selectRaw('coalesce(sum(if(xrate>1,amount*xrate,amount/xrate)),0) as total_amount')
            ->first()->total_amount ?? 0;
        $invoicesLastMonth = Invoice::whereBetween('created_at', [Carbon::today()->subMonth()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->subMonth()->endOfMonth()->format('Y-m-d H:i:s')])
            ->selectRaw('coalesce(sum(if(xrate>1,amount*xrate,amount/xrate)),0) as total_amount')
            ->first()->total_amount ?? 0;
        $invoicesThisMonth = Invoice::whereBetween('created_at', [Carbon::today()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->endOfMonth()->format('Y-m-d H:i:s')])
            ->selectRaw('coalesce(sum(if(xrate>1,amount*xrate,amount/xrate)),0) as total_amount')
            ->first()->total_amount ?? 0;
        $invoicesChange = 0;
        $invoicesChangeClass = 'text-green-500';
        if ($invoicesLastMonth > 0) {
            $invoicesChange = abs(($invoicesThisMonth - $invoicesLastMonth) * 100 / $invoicesLastMonth);
            if ($invoicesThisMonth < $invoicesLastMonth) {
                $invoicesChangeClass = 'text-red-500';
            }
        }
        if ($invoicesLastMonth === 0 && $invoicesThisMonth > 0) {
            $invoicesChange = 100;
        }
        return response()->json([
            'invoices' => number_format($invoices),
            'invoicesChange' => number_format($invoicesChange, 2),
            'invoicesChangeClass' => $invoicesChangeClass,
        ]);
    }

    public function getWaitingList()
    {
        $query = Consultation::with(['doctor', 'member', 'nurse']);
        if (Auth::user()->hasRole('doctor') && Auth::user()->hasPermissionTo('consultations.view_assigned_consultations_only')) {
            $query->where('doctor_id', Auth::id());
            $query->where('stage', 'waiting_for_doctor');
        }
        if (Auth::user()->hasRole('nurse') && Auth::user()->hasPermissionTo('consultations.view_assigned_consultations_only')) {
            $query->where('nurse_id', Auth::id());
            $query->where('stage', 'waiting_for_nurse');
        }
        if (Auth::user()->hasRole('receptionist') && Auth::user()->hasPermissionTo('consultations.view_assigned_consultations_only')) {
            $query->where('receptionist_id', Auth::id());
            $query->where('stage', 'with_receptionist');
        }
        $members = $query->orderBy('created_at')->get()->map(function ($item) {
            if (Auth::user()->hasRole('doctor')) {
                $item->waiting_time = Carbon::now()->diffForHumans($item->nurse_completed_at, true, false);
            } elseif (Auth::user()->hasRole('nurse')) {
                $item->waiting_time = Carbon::now()->diffForHumans($item->receptionist_completed_at, true, false);
            } else {
                $item->waiting_time = Carbon::now()->diffForHumans($item->created_at, true, false);
            }
            return $item;
        });
        return response()->json($members);
    }

    public function getAppointments(Request $request)
    {
        $doctorID = null;
        $nurseID = null;
        $receptionistID = null;
        if (Auth::user()->hasRole('doctor') && Auth::user()->hasPermissionTo('appointments.view_assigned_appointments_only')) {
            $doctorID = Auth::id();
        }
        $appointments = Event::with(['doctor', 'member'])
            ->filter(\request()->only('search', 'branch_id', 'status', 'created_by_type', 'member_id', 'doctor_id', 'appointment_type', 'date_range'))
            ->doctor($doctorID)
            ->where('start_date', '>=', Carbon::today()->format('Y-m-d'))
            ->get();
        return response()->json($appointments);
    }

    public function getAppointmentsByStatusPieChart(Request $request)
    {
        $reports = new Reports();
        $doctorID = null;
        $nurseID = null;
        $receptionistID = null;
        if (Auth::user()->hasRole('doctor') && Auth::user()->hasPermissionTo('appointments.view_assigned_appointments_only')) {
            $doctorID = Auth::id();
        }
        $appointments = $reports->getAppointmentsByStatus([
            'doctor_id' => $doctorID,
        ]);
        return response()->json($appointments);
    }

    public function getAppointmentsByPeriodGraph(Request $request)
    {
        $reports = new Reports();
        $doctorID = null;
        $nurseID = null;
        $receptionistID = null;
        if (Auth::user()->hasRole('doctor') && Auth::user()->hasPermissionTo('appointments.view_assigned_appointments_only')) {
            $doctorID = Auth::id();
        }
        $appointments = $reports->getAppointmentsByPeriod([
            'doctor_id' => $doctorID,
            'period' => $request->period,
        ]);
        return response()->json($appointments);
    }

    public function getPaymentsByPaymentTypePieChart(Request $request)
    {
        $reports = new Reports();
        $data = $reports->getPaymentsByPaymentType([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'currency_id' => $request->currency_id,
            'co_payer_id' => $request->co_payer_id,
            'payment_type_id' => $request->payment_type_id,
            'paid_by' => $request->paid_by,
            'period' => $request->period,
        ]);
        return response()->json($data);
    }

    public function getPaymentsByPeriodGraph(Request $request)
    {
        $reports = new Reports();
        $data = $reports->getPaymentsByPeriod([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'currency_id' => $request->currency_id,
            'co_payer_id' => $request->co_payer_id,
            'payment_type_id' => $request->payment_type_id,
            'paid_by' => $request->paid_by,
            'period' => $request->period,
        ]);
        return response()->json($data);
    }

    public function getIncomeExpensesPieChart(Request $request)
    {
        $reports = new Reports();
        $data = $reports->getIncomeExpenses([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'currency_id' => $request->currency_id,
            'co_payer_id' => $request->co_payer_id,
            'payment_type_id' => $request->payment_type_id,
            'paid_by' => $request->paid_by,
            'period' => $request->period,
        ]);
        return response()->json($data);
    }

    public function getIncomeExpensesGraph(Request $request)
    {
        $reports = new Reports();
        $data = $reports->getPeriodIncomeExpenses([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'currency_id' => $request->currency_id,
            'co_payer_id' => $request->co_payer_id,
            'payment_type_id' => $request->payment_type_id,
            'paid_by' => $request->paid_by,
            'period' => $request->period,
        ]);
        return response()->json($data);
    }

    public function getConsultationsByPeriodGraph(Request $request)
    {
        $reports = new Reports();
        $data = $reports->getConsultationsByPeriod([
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'co_payer_id' => $request->co_payer_id,
            'period' => $request->period,
        ]);
        return response()->json($data);
    }

    public function filter($scope)
    {

        $products = LoanProduct::with(['category', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();
        // $branches = Branch::get();
        $filterScope = $scope;
        $users = User::where('active', 1)->get();

        return Inertia::render('Dashboard/CreateFilter', [
            'scope' => $filterScope,
            'provinces' =>  Province::all()->transform(function ($province) {
                return [
                    'value' => $province->id,
                    'label' => $province->name,
                ];
            }),
            'products' => $products,
            'branches' => Branch::all()->transform(function ($branch) {
                return [
                    'value' => $branch->id,
                    'label' => $branch->name,
                ];
            }),
            'users' => $users

        ]);
    }

    public function filterResults(Request $request)
    {

        $this->filterOptions = $request->all();

        // dd($this->filterOptions);

        switch($request->scope){
            case 'all':
                $applications = LoanApplication::with(['staff', 'client', 'product', 'currentLinkedStage', 'currentLinkedStage.stage', 'currentLinkedStage.approver', 'currentLinkedStage.assignedBy', 'branch', 'createdBy', 'client.province'])
            ->filter(\request()->only('search', 'client_id', 'loan_product_id', 'province_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status'));
            break;

            case 'pending':
                $applications = LoanApplication::with(['staff', 'client', 'product', 'currentLinkedStage', 'currentLinkedStage.stage', 'currentLinkedStage.approver', 'currentLinkedStage.assignedBy', 'branch', 'createdBy',  'client.province'])
            ->filter(\request()->only('search', 'client_id', 'loan_product_id', 'province_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status'))
            ->whereHas('currentLinkedStage', function ($query) {
                $query->where('status', '!=','approved')
                    ->where('status', '!=','rejected');
            });
            break;
            case 'rejected':
                $applications = LoanApplication::with(['staff', 'client', 'product', 'currentLinkedStage', 'currentLinkedStage.stage', 'currentLinkedStage.approver', 'currentLinkedStage.assignedBy', 'branch', 'createdBy',  'client.province'])
            ->filter(\request()->only('search', 'client_id', 'loan_product_id', 'province_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status'))
            ->whereHas('currentLinkedStage', function ($query) {
                $query->where('status', 'rejected');
            });
            break;
            case 'approved':
                $applications = LoanApplication::with(['staff', 'client', 'product', 'currentLinkedStage', 'currentLinkedStage.stage', 'currentLinkedStage.approver', 'currentLinkedStage.assignedBy', 'branch', 'createdBy',  'client.province'])
            ->filter(\request()->only('search', 'client_id', 'loan_product_id', 'province_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status'))
            ->whereHas('currentLinkedStage', function ($query) {
                $query->where('status', 'approved');
            });
            break;
            default:
            return redirect()->back()->with('error', 'Invalid filter scope');
            break;

        }

             // Apply start date and end date logic
        $endDate = $request->filled('loan_end_date') ? $request->loan_end_date : Carbon::today()->format('Y-m-d');
        $startDate = $request->loan_start_date;

        $applications = $applications->whereBetween('date', [$startDate, $endDate]);


        // Handle optional branch ID
        if ($request->filled('branch')) {
            $applications = $applications->whereIn('branch_id', $request->branch);
        }

        //handle region
        if ($request->filled('region')) {
            $applications = $applications->whereHas('client.province', function ($query) use ($request) {
                $query->whereIn('id', $request->region);
            });
        }

        if ($request->filled('product')) {
            $applications = $applications->where('loan_product_id', $request->product);
        }

        if ($request->filled('loan_initiator_id')) {
            $applications = $applications->where('created_by_id',  $request->loan_initiator_id);
        }

        if ($request->filled('loan_approver_id')) {
            $applications = $applications->where('approved_by_id', $request->loan_approver_id);
        }


        // Apply loan amount logic based on operator
        $loanAmount = $request->loan_amount;
        //validate the loan amount, it shoulf be 0 or greater
        if ($loanAmount < 0) {
            return redirect()->back()->with('error', 'Invalid loan amount');
        }
        $operator = $request->loan_amount_operator;

        switch ($operator) {
            case 'greater':
                $applications = $applications->where('amount', '>', $loanAmount);
                break;
            case 'less':
                $applications = $applications->where('amount', '<', $loanAmount);
                break;
            case 'equal':
                $applications = $applications->where('amount', '=', $loanAmount);
                break;
            default:
                // Handle invalid operator (optional)
                break;
        }
        $applicationCount = $applications->count();
        $applications = $applications->orderBy('created_at', 'desc')
            ->paginate($applicationCount);

            // dd($applications);


        $hiddenColumns =  [
            'loan_description' => $request->loan_description,
            'cif' =>$request->cif,
            'user_id' => $request->user_id,
            'show_branch' =>$request->show_branch,
            'show_region' => $request->show_region,
            'show_loan_approver' => $request->show_loan_approver,

        ];



        // region in in the client table and branch

        return Inertia::render('LoanApplications/Filtered', [
            'filters' => \request()->all('search', 'client_id', 'loan_product_id', 'province_id', 'branch_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status'),
            'applications' => $applications,
            'products' => LoanProduct::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'scope' => $request->scope,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'hiddenColumns' => $hiddenColumns
        ]);

        // For example, you might want to redirect back with a success message
        // return redirect()->back()->with('success', 'Filter applied successfully!');
    }

    public function export(Request $request)
    {
        // dd('ok');

        $formData = json_decode($request->get('applications'), true);
        $applications = $formData['data'];

        $extraColumns = json_decode($request->get('hiddenColumns'), true);



        $exportData = [];
        foreach ($applications as $application) {
            $exportData[] = [
                'ID' => $application['id'],
                'Loan Date' => $application['date'],
                'Client' => Client::find($application['client_id'])->name,
                'Product' => LoanProduct::find($application['loan_product_id'])->name,
                'Amount' => $application['amount'],
                'Score' => $application['score'],
                'Status' => $application['current_stage_status'],
                'Created At' =>  $application['created_at'],
                'loan_description' => $extraColumns['loan_description'] != null ? $application['description'] : null,
                'Created By' =>   $extraColumns['user_id'] != null ? User::find($application['created_by_id'])->name : null,
                'CIF' =>  $extraColumns['cif'] != null ? Client::find($application['client_id'])->external_id : null,
            ];

        }
        // dd($extraColumns);


        // Instantiate the export class
        $export = new LoanApplicationsExport($exportData, $extraColumns);

    // Optionally, you can modify the export class properties or methods here if needed

    // Use Laravel Excel to export data
    return Excel::download($export, 'loan_applications.xlsx');
    }

    public function myWorkspace()
    {

    $assignedToMeIds = LoanApplicationLinkedApprovalStage::where('approver_id', Auth::id())->pluck('loan_application_id')->toArray();
    // dd($assignedToMeIds);
    $query = LoanApplication::with(['staff', 'client', 'product', 'currentLinkedStage', 'currentLinkedStage.stage', 'currentLinkedStage.approver', 'currentLinkedStage.assignedBy','linkedStages', 'branch'])
    ->whereIn('id', $assignedToMeIds);

    $assignedToMeCount = $query->count();
    $assignedToMeApplications = $query->orderBy('created_at', 'desc')
        ->paginate(15);
    $approvedByMeIds = LoanApplicationLinkedApprovalStage::where('approver_id', Auth::id())
    ->where('status', 'approved')
    ->pluck('loan_application_id')->toArray();

    $approvedByMeCount = LoanApplication::whereIn('id', $approvedByMeIds)->count();
    $approvedByMeApplications = LoanApplication::with(['staff', 'client', 'product', 'currentLinkedStage', 'currentLinkedStage.stage', 'currentLinkedStage.approver', 'currentLinkedStage.assignedBy','linkedStages', 'branch'])
        ->whereIn('id', $approvedByMeIds)
        ->orderBy('created_at', 'desc')
        ->paginate(15);

    $pendingToMeIds = LoanApplicationLinkedApprovalStage::where('approver_id', Auth::id())
    ->where('stage_finished_at', null)
    ->where('is_current', 1)
    ->pluck('loan_application_id')->toArray();
    $pendingToMeCount = LoanApplication::whereIn('id', $pendingToMeIds)->count();

    $pendingToMeApplications = LoanApplication::with(['staff', 'client', 'product', 'currentLinkedStage', 'currentLinkedStage.stage', 'currentLinkedStage.approver', 'currentLinkedStage.assignedBy','linkedStages', 'branch'])
        ->whereIn('id', $pendingToMeIds)
        ->orderBy('created_at', 'desc')
        ->paginate(15);

        $query = LoanApplicationReminder::where('user_id', Auth::id()) ->orderBy('created_at', 'desc');
        $myReminders = $query->paginate(15);
        // dd($myReminders);
        $myRemindersCount = $query->count();


    // LoanApplication::where('linkedStages')->get();



        $applications = LoanApplication::with(['staff', 'client', 'product', 'currentLinkedStage', 'currentLinkedStage.stage', 'currentLinkedStage.approver', 'currentLinkedStage.assignedBy','linkedStages', 'branch'])
            ->filter(\request()->only('search', 'client_id', 'loan_product_id', 'province_id', 'branch_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status'))
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        // dd($applications);

        return Inertia::render('Dashboard/MyWorkspace', [
            'filters' => \request()->all('search', 'client_id', 'loan_product_id', 'province_id', 'branch_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status'),
            'applications' => $applications,
            'products' => LoanProduct::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'assignedToMeApplications' => $assignedToMeApplications,
            'assignedToMeCount' => $assignedToMeCount,
            'approvedByMeApplications' => $approvedByMeApplications,
            'approvedByMeCount' => $approvedByMeCount,
            'pendingToMeApplications' => $pendingToMeApplications,
            'pendingToMeCount' => $pendingToMeCount,
            'myReminders' => $myReminders,
            'myRemindersCount' => $myRemindersCount
        ]);
        // return Inertia::render('Dashboard/MyWorkspace', []);
    }


}
