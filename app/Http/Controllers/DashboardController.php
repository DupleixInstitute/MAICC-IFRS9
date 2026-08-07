<?php

namespace App\Http\Controllers;

use App\Models\ExpectedCreditLoss;
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
    // Available periods (Y-m, newest first) - only those with ECL calculated.
    $periods = ReportingPeriods::where('ecl_calculated', true)
        ->orderBy('period', 'desc')
        ->pluck('period')
        ->map(fn ($p) => Carbon::parse($p)->format('Y-m'))
        ->unique()
        ->values();

    $portfolios = LoanPortfolio::orderBy('name')->get(['id', 'name']);

    if ($periods->isEmpty()) {
        return Inertia::render('Dashboard', [
            'summary' => $this->emptySummary(),
            'periods' => [],
            'portfolios' => $portfolios,
            'selectedPeriod' => null,
            'selectedPortfolioId' => null,
            'comparePeriod' => null,
            'eclTrends' => [],
            'error' => 'No data available. Please upload or calculate ECL first.',
        ]);
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

    // --- Loan book: EAD by stage in ONE grouped query --------------------
    $eadByStage = $loanBookScope()
        ->selectRaw('ifrs9stage_post_qualitative as stage, SUM(carrying_amount) as amount')
        ->groupBy('ifrs9stage_post_qualitative')
        ->pluck('amount', 'stage');

    $stage1Amount = (float) ($eadByStage[1] ?? 0);
    $stage2Amount = (float) ($eadByStage[2] ?? 0);
    $stage3Amount = (float) ($eadByStage[3] ?? 0);
    $grossCarryingAmount = (float) $eadByStage->sum();

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
            ->selectRaw('ifrs9stage_post_qualitative as stage, SUM(carrying_amount) as amount')
            ->groupBy('ifrs9stage_post_qualitative')
            ->pluck('amount', 'stage');

        $cS1 = (float) ($compareEad[1] ?? 0);
        $cS2 = (float) ($compareEad[2] ?? 0);
        $cS3 = (float) ($compareEad[3] ?? 0);
        $cGross = (float) $compareEad->sum();
        $cSumEad = $cS1 + $cS2 + $cS3;
        $cEcl = (float) $lastTotalECLAllowance->sum();
        $cPd1 = round((float) ($compareEclRows[1]->avg_pd ?? 0) * 100, 2);
        $cPd2 = round((float) ($compareEclRows[2]->avg_pd ?? 0) * 100, 2);
        $cPd3 = round((float) ($compareEclRows[3]->avg_pd ?? 0) * 100, 2);
        $cLgd = round((float) ($compareEclRows[3]->avg_lgd ?? 0) * 100, 2);

        $compareSummary = [
            'carrying_amount' => $cGross,
            'total_ecl' => $cEcl,
            'ecl_percentage' => $cGross > 0 ? round(($cEcl / $cGross) * 100, 2) : 0,
            'stage_3_amount' => $cS3,
            'stage_3_percentage' => $cGross > 0 ? round(($cS3 / $cGross) * 100, 2) : 0,
            'paid_amount' => $cGross - $cEcl,
            'weighted_pd' => $cSumEad > 0 ? ($cPd1 * $cS1 + $cPd2 * $cS2 + $cPd3 * $cS3) / $cSumEad : 0,
            'weighted_lgd' => $cSumEad > 0 ? ($cLgd * $cS3) / $cSumEad : 0,
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

    $paidAmount = $grossCarryingAmount - $totalECLAllowance;
    $paidPercentage = $grossCarryingAmount > 0
        ? round(($paidAmount / $grossCarryingAmount) * 100, 2)
        : 0;

    $pdPercentages = [$stage1PD, $stage2PD, $stage3PD];

    $weightedPD = $sumEad > 0 ? ($stage1PD * $stage1Amount + $stage2PD * $stage2Amount + $stage3PD * $stage3Amount) / $sumEad : 0;
    $weightedLGD = $sumEad > 0 ? ($lgdPercentage * $stage3Amount) / $sumEad : 0;

    // --- Coverage trend: ONE grouped query, optional from/to range --------
    $trendFrom = $request->input('trend_from');
    $trendTo = $request->input('trend_to');
    $trendPeriods = $periods
        ->when($periods->contains($trendFrom), fn ($c) => $c->filter(fn ($p) => $p >= $trendFrom))
        ->when($periods->contains($trendTo), fn ($c) => $c->filter(fn ($p) => $p <= $trendTo))
        ->values();

    $trendRows = tap(
        ExpectedCreditLoss::whereIn('reporting_period', $trendPeriods),
        fn ($q) => $portfolioId
            ? $q->where('ecl_calculation_level', 'portfolio')->where('ecl_calculation_id', $portfolioId)
            : null
    )
        ->selectRaw('reporting_period, SUM(total_ead) as total_ead, SUM(total_ecl) as total_ecl')
        ->groupBy('reporting_period')
        ->orderBy('reporting_period')
        ->get();

    $eclTrends = $trendRows->map(fn ($row) => [
        'period' => $row->reporting_period,
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

    return Inertia::render('Dashboard', [
        'summary' => $summary,
        'compareSummary' => $compareSummary,
        'periods' => $periods,
        'portfolios' => $portfolios,
        'selectedPeriod' => $selectedPeriod,
        'selectedPortfolioId' => $portfolioId,
        'comparePeriod' => $comparePeriod,
        'trendFrom' => $periods->contains($trendFrom) ? $trendFrom : null,
        'trendTo' => $periods->contains($trendTo) ? $trendTo : null,
        'eclTrends' => $eclTrends,
    ]);
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
        ->paginate(20);
    $approvedByMeIds = LoanApplicationLinkedApprovalStage::where('approver_id', Auth::id())
    ->where('status', 'approved')
    ->pluck('loan_application_id')->toArray();

    $approvedByMeCount = LoanApplication::whereIn('id', $approvedByMeIds)->count();
    $approvedByMeApplications = LoanApplication::with(['staff', 'client', 'product', 'currentLinkedStage', 'currentLinkedStage.stage', 'currentLinkedStage.approver', 'currentLinkedStage.assignedBy','linkedStages', 'branch'])
        ->whereIn('id', $approvedByMeIds)
        ->orderBy('created_at', 'desc')
        ->paginate(20);

    $pendingToMeIds = LoanApplicationLinkedApprovalStage::where('approver_id', Auth::id())
    ->where('stage_finished_at', null)
    ->where('is_current', 1)
    ->pluck('loan_application_id')->toArray();
    $pendingToMeCount = LoanApplication::whereIn('id', $pendingToMeIds)->count();

    $pendingToMeApplications = LoanApplication::with(['staff', 'client', 'product', 'currentLinkedStage', 'currentLinkedStage.stage', 'currentLinkedStage.approver', 'currentLinkedStage.assignedBy','linkedStages', 'branch'])
        ->whereIn('id', $pendingToMeIds)
        ->orderBy('created_at', 'desc')
        ->paginate(20);

        $query = LoanApplicationReminder::where('user_id', Auth::id()) ->orderBy('created_at', 'desc');
        $myReminders = $query->paginate(20);
        // dd($myReminders);
        $myRemindersCount = $query->count();


    // LoanApplication::where('linkedStages')->get();



        $applications = LoanApplication::with(['staff', 'client', 'product', 'currentLinkedStage', 'currentLinkedStage.stage', 'currentLinkedStage.approver', 'currentLinkedStage.assignedBy','linkedStages', 'branch'])
            ->filter(\request()->only('search', 'client_id', 'loan_product_id', 'province_id', 'branch_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status'))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
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
