<?php

namespace App\Http\Controllers;

use App\Models\ContractCashflowSchedule;
use App\Models\ContractEir;
use App\Models\GlInterestPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Eir\EirGlReconciliationService;
use App\Services\Eir\ScheduleWorkflowService;
use Inertia\Inertia;

class EirDataController extends Controller
{
    public function __construct()
    {
        // Read-only screen: the EIR view permission, not the broad Settings one.
        $this->middleware(['auth', 'permission:eir.view']);
    }

    public function index(Request $request, EirGlReconciliationService $reconciliation)
    {
        $tab = in_array($request->input('tab'), ['contracts', 'cashflows', 'schedules', 'gl'], true) ? $request->input('tab') : 'contracts';
        $search = trim((string) $request->input('search'));
        $comparisonStatus = in_array($request->input('comparison_status'), [
            'WITHIN_TOLERANCE', 'PRINCIPAL_VARIANCE', 'INTEREST_VARIANCE', 'NO_REMAINING_DATA', 'NOT_COMPARED',
        ], true) ? $request->input('comparison_status') : '';

        $data = match ($tab) {
            'cashflows' => $this->cashflows($search),
            'gl' => $this->glPostings($search, $reconciliation),
            'schedules' => $this->scheduleReviews($search, $comparisonStatus),
            default => $this->contracts($search),
        };

        return Inertia::render('Eir/Data', [
            'activeTab' => $tab,
            'data' => $data,
            'filters' => ['search' => $search, 'comparison_status' => $comparisonStatus],
            'summary' => [
                'contracts' => ContractEir::count(),
                'cashflows' => ContractCashflowSchedule::count(),
                'gl_postings' => GlInterestPosting::count(),
                'remaining_cashflows' => DB::table('contract_remaining_cashflow_schedule')->count(),
                'approved_schedules' => ContractEir::where('schedule_approval_status','APPROVED')->count(),
                'schedule_comparisons' => [
                    'within_tolerance' => ContractEir::where('schedule_comparison_status','WITHIN_TOLERANCE')->count(),
                    'principal_variance' => ContractEir::where('schedule_comparison_status','PRINCIPAL_VARIANCE')->count(),
                    'interest_variance' => ContractEir::where('schedule_comparison_status','INTEREST_VARIANCE')->count(),
                    'no_remaining_data' => ContractEir::where('schedule_comparison_status','NO_REMAINING_DATA')->count(),
                    'not_compared' => ContractEir::whereNull('schedule_comparison_status')->count(),
                ],
                'locked_eirs' => ContractEir::whereNotNull('locked_at')->count(),
                'reconciliation' => $reconciliation->overallSummary(),
            ],
        ]);
    }

    private function scheduleReviews(string $search, string $comparisonStatus)
    {
        $page=ContractEir::query()->when($search!=='',fn($q)=>$q->where('contract_id','like',"%{$search}%"))
            ->when($comparisonStatus === 'NOT_COMPARED', fn($q) => $q->whereNull('schedule_comparison_status'))
            ->when($comparisonStatus !== '' && $comparisonStatus !== 'NOT_COMPARED', fn($q) => $q->where('schedule_comparison_status',$comparisonStatus))
            ->withCount(['schedules'])->orderBy('contract_id')->paginate(15)->withQueryString();
        $workflow=app(ScheduleWorkflowService::class);
        $page->getCollection()->transform(function($contract) use ($workflow) {
            $readiness=$workflow->readiness($contract); $comparison=$workflow->comparison($contract);
            $contract->setAttribute('generation_ready',$readiness['ready']);
            $contract->setAttribute('generation_issues',$readiness['issues']);
            $contract->setAttribute('comparison',$comparison);
            $contract->setAttribute('remaining_rows',$comparison['remaining_rows']);
            return $contract;
        });
        return $page;
    }

    private function contracts(string $search)
    {
        return ContractEir::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('contract_id', 'like', "%{$search}%")
                ->orWhere('sub_account_no', 'like', "%{$search}%")->orWhere('portfolio', 'like', "%{$search}%")
                ->orWhere('product_type', 'like', "%{$search}%")))
            ->withCount(['schedules', 'fees'])
            ->orderByDesc('terms_imported_at')->orderByDesc('id')->paginate(30)->withQueryString();
    }

    private function cashflows(string $search)
    {
        return ContractCashflowSchedule::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('contract_id', 'like', "%{$search}%")
                ->orWhere('source_reference', 'like', "%{$search}%")))
            ->select('*')->selectRaw('(principal_due + interest_due + fee_due) as total_due')
            ->orderByDesc('due_date')->orderByDesc('id')->paginate(30)->withQueryString();
    }

    /**
     * The GL postings list, reconciled by the same service the GL
     * Reconciliation screen uses.
     *
     * This page used to join the postings to the amortisation rows in raw SQL
     * and repeat the tolerance rule in a CASE expression, with CONCAT and LPAD
     * that only MySQL runs. That was a second reconciliation in the system: a
     * change to the governed band or to the expected-interest basis had to be
     * made twice, and the sqlite test suite never exercised this one. There is
     * now one implementation, and each row also carries the expected
     * contractual interest and the named cause of any difference.
     */
    private function glPostings(string $search, EirGlReconciliationService $reconciliation)
    {
        $page = GlInterestPosting::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('contract_id', 'like', "%{$search}%")
                ->orWhere('gl_account_code', 'like', "%{$search}%")
                ->orWhere('source_reference', 'like', "%{$search}%")))
            ->orderByDesc('reporting_period')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $reconciled = $reconciliation->forPostings($page->getCollection());
        $page->getCollection()->transform(function ($posting) use ($reconciled) {
            $row = $reconciled[(int) $posting->id] ?? [];
            foreach (['eir_interest' => 'eir_accrued', 'interest_basis' => 'interest_basis',
                'opening_gross' => 'opening_gross', 'closing_gross' => 'closing_gross',
                'variance' => 'variance', 'expected_interest' => 'expected_interest',
                'cause' => 'cause', 'cause_detail' => 'cause_detail'] as $attribute => $key) {
                $posting->setAttribute($attribute, $row[$key] ?? null);
            }
            $posting->setAttribute('reconciliation_status', $row['status'] ?? 'NOT_CALCULATED');

            return $posting;
        });

        return $page;
    }
}
