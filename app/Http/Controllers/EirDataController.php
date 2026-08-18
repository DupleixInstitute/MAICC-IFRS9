<?php

namespace App\Http\Controllers;

use App\Models\ContractCashflowSchedule;
use App\Models\ContractEir;
use App\Models\GlInterestPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EirDataController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:settings']);
    }

    public function index(Request $request)
    {
        $tab = in_array($request->input('tab'), ['contracts', 'cashflows', 'gl'], true) ? $request->input('tab') : 'contracts';
        $search = trim((string) $request->input('search'));

        $data = match ($tab) {
            'cashflows' => $this->cashflows($search),
            'gl' => $this->glPostings($search),
            default => $this->contracts($search),
        };

        return Inertia::render('Eir/Data', [
            'activeTab' => $tab,
            'data' => $data,
            'filters' => ['search' => $search],
            'summary' => [
                'contracts' => ContractEir::count(),
                'cashflows' => ContractCashflowSchedule::count(),
                'gl_postings' => GlInterestPosting::count(),
                'locked_eirs' => ContractEir::whereNotNull('locked_at')->count(),
                'reconciliation' => $this->reconciliationSummary(),
            ],
        ]);
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

    private function glPostings(string $search)
    {
        return GlInterestPosting::query()
            ->leftJoin('eir_amortisation as ea', function ($join) {
                $join->on('ea.contract_id', '=', 'gl_interest_postings.contract_id')
                    ->on('ea.reporting_period', '=', DB::raw("CONCAT(gl_interest_postings.period_year, '-', LPAD(gl_interest_postings.period_month, 2, '0'))"));
            })
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('gl_interest_postings.contract_id', 'like', "%{$search}%")
                ->orWhere('gl_interest_postings.gl_account_code', 'like', "%{$search}%")
                ->orWhere('gl_interest_postings.source_reference', 'like', "%{$search}%")))
            ->select('gl_interest_postings.*', 'ea.interest_accrued as eir_interest', 'ea.interest_basis', 'ea.opening_gross', 'ea.closing_gross')
            ->selectRaw('(ea.interest_accrued - gl_interest_postings.interest_income_posted) as variance')
            ->selectRaw("CASE
                WHEN ea.id IS NULL THEN 'NOT_CALCULATED'
                WHEN ABS(ea.interest_accrued - gl_interest_postings.interest_income_posted) <= GREATEST(1, ABS(gl_interest_postings.interest_income_posted) * 0.01) THEN 'WITHIN_TOLERANCE'
                ELSE 'VARIANCE'
            END as reconciliation_status")
            ->orderByDesc('gl_interest_postings.reporting_period')
            ->orderByDesc('gl_interest_postings.id')
            ->paginate(30)
            ->withQueryString();
    }

    private function reconciliationSummary(): array
    {
        $row = DB::table('gl_interest_postings as gl')
            ->leftJoin('eir_amortisation as ea', function ($join) {
                $join->on('ea.contract_id', '=', 'gl.contract_id')
                    ->on('ea.reporting_period', '=', DB::raw("CONCAT(gl.period_year, '-', LPAD(gl.period_month, 2, '0'))"));
            })
            ->selectRaw('COUNT(*) as posting_rows')
            ->selectRaw('COUNT(ea.id) as calculated_rows')
            ->selectRaw('SUM(CASE WHEN ea.id IS NULL THEN 1 ELSE 0 END) as missing_rows')
            ->selectRaw('SUM(gl.interest_income_posted) as gl_total')
            ->selectRaw('SUM(CASE WHEN ea.id IS NOT NULL THEN gl.interest_income_posted ELSE 0 END) as matched_gl_total')
            ->selectRaw('SUM(CASE WHEN ea.id IS NULL THEN gl.interest_income_posted ELSE 0 END) as missing_gl_total')
            ->selectRaw('COALESCE(SUM(ea.interest_accrued), 0) as eir_total')
            ->selectRaw('SUM(CASE WHEN ea.id IS NOT NULL THEN ea.interest_accrued - gl.interest_income_posted ELSE 0 END) as net_variance')
            ->selectRaw('SUM(CASE WHEN ea.id IS NOT NULL AND ABS(ea.interest_accrued - gl.interest_income_posted) <= GREATEST(1, ABS(gl.interest_income_posted) * 0.01) THEN 1 ELSE 0 END) as within_tolerance')
            ->first();

        return [
            'posting_rows' => (int) ($row->posting_rows ?? 0),
            'calculated_rows' => (int) ($row->calculated_rows ?? 0),
            'missing_rows' => (int) ($row->missing_rows ?? 0),
            'within_tolerance' => (int) ($row->within_tolerance ?? 0),
            'variance_rows' => max(0, (int) ($row->calculated_rows ?? 0) - (int) ($row->within_tolerance ?? 0)),
            'gl_total' => (float) ($row->gl_total ?? 0),
            'matched_gl_total' => (float) ($row->matched_gl_total ?? 0),
            'missing_gl_total' => (float) ($row->missing_gl_total ?? 0),
            'eir_total' => (float) ($row->eir_total ?? 0),
            'net_variance' => (float) ($row->net_variance ?? 0),
            'tolerance_percent' => 1,
        ];
    }
}
