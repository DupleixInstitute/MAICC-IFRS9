<?php

namespace App\Http\Controllers;

use App\Jobs\CalculateEirJob;
use App\Models\ContractEir;
use App\Services\AuditLoggerService;
use App\Services\Eir\EirCalculationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use LogicException;

class EirCalculationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:settings']);
    }

    public function index(Request $request)
    {
        $status = strtoupper(trim((string) $request->input('status', '')));
        $search = trim((string) $request->input('contract_id', ''));
        if (!in_array($status, ['PENDING', 'REOPENED', 'BLOCKED', 'CALCULATED', 'LOCKED'], true)) {
            $status = '';
        }

        $query = ContractEir::query()->with(['calculatedBy:id,name', 'lockedBy:id,name']);
        if ($status !== '') {
            $query->where('calculation_status', $status);
        }
        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($searchQuery) use ($like) {
                $searchQuery->where('contract_id', 'like', $like)
                    ->orWhere('sub_account_no', 'like', $like)
                    ->orWhere('gl_account_code', 'like', $like)
                    ->orWhere('terms_source_reference', 'like', $like)
                    ->orWhere('portfolio', 'like', $like)
                    ->orWhere('product_type', 'like', $like);
            });
        }

        $reviewerId = (int) auth()->id();
        $adminOverride = $this->adminOverride();
        $approvalQuery = ContractEir::query()
            ->where('calculation_status', 'CALCULATED')
            ->whereNull('locked_at');
        $approvalTotal = (clone $approvalQuery)->count();
        $approvalOwn = (clone $approvalQuery)->where('calculated_by', $reviewerId)->count();
        $approvalMissingMaker = (clone $approvalQuery)->whereNull('calculated_by')->count();
        $approvalEligible = $adminOverride
            ? $approvalTotal
            : (clone $approvalQuery)
                ->whereNotNull('calculated_by')
                ->where('calculated_by', '<>', $reviewerId)
                ->count();

        return Inertia::render('Eir/Calculations', [
            'contracts' => $query->orderByRaw("CASE calculation_status WHEN 'BLOCKED' THEN 1 WHEN 'REOPENED' THEN 2 WHEN 'CALCULATED' THEN 3 WHEN 'PENDING' THEN 4 ELSE 5 END")
                ->orderByDesc('updated_at')->paginate(30)->withQueryString(),
            'filters' => ['status' => $status, 'contract_id' => $search],
            'summary' => ContractEir::selectRaw('calculation_status, COUNT(*) as contract_count')->groupBy('calculation_status')->get()->keyBy('calculation_status'),
            'approvalSummary' => [
                'total' => $approvalTotal,
                'eligible' => $approvalEligible,
                'own' => $approvalOwn,
                'missing_maker' => $approvalMissingMaker,
                'admin_override' => $adminOverride,
            ],
            'canReopen' => $adminOverride,
        ]);
    }

    public function calculate(Request $request)
    {
        $data = $request->validate([
            'contract_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'contract_ids.*' => ['string', 'distinct', 'exists:contract_eir,contract_id'],
        ]);

        CalculateEirJob::dispatch($data['contract_ids'], auth()->id());
        AuditLoggerService::log('EIR Calculation Requested', ContractEir::class, null, ['meta' => ['contract_ids' => $data['contract_ids']]]);

        return back()->with('success', count($data['contract_ids']) . ' contract(s) queued for EIR calculation.');
    }

    public function approve(ContractEir $contractEir, EirCalculationService $calculations)
    {
        $adminOverride = $this->adminOverride();

        try {
            $locked = $calculations->lock($contractEir->contract_id, (int) auth()->id(), $adminOverride);
        } catch (LogicException $e) {
            return back()->withErrors(['contract_id' => $e->getMessage()]);
        }

        AuditLoggerService::log('Original EIR Approved and Locked', ContractEir::class, $locked->id, [
            'new_values' => ['contract_id' => $locked->contract_id, 'eir_effective_annual' => $locked->eir_effective_annual, 'locked_at' => $locked->locked_at],
            'meta' => ['maker_checker_override' => $adminOverride],
        ]);

        return back()->with('success', "Original EIR for {$locked->contract_id} approved and locked.");
    }

    public function approveAll(EirCalculationService $calculations)
    {
        $reviewerId = (int) auth()->id();
        $adminOverride = $this->adminOverride();
        $contractIds = ContractEir::query()
            ->where('calculation_status', 'CALCULATED')
            ->whereNull('locked_at')
            ->orderBy('id')
            ->pluck('contract_id')
            ->all();

        if ($contractIds === []) {
            return back()->withErrors(['approval' => 'There are no calculated, unlocked EIRs to approve.']);
        }

        $result = $calculations->lockMany($contractIds, $reviewerId, $adminOverride);

        foreach ($result['locked'] as $locked) {
            AuditLoggerService::log('Original EIR Approved and Locked', ContractEir::class, $locked->id, [
                'new_values' => [
                    'contract_id' => $locked->contract_id,
                    'eir_effective_annual' => $locked->eir_effective_annual,
                    'locked_at' => $locked->locked_at,
                ],
                'meta' => [
                    'approval_mode' => 'BULK',
                    'maker_checker_override' => $adminOverride,
                ],
            ]);
        }

        $lockedCount = count($result['locked']);
        $skippedCount = count($result['skipped']);
        AuditLoggerService::log('Bulk Original EIR Approval Completed', ContractEir::class, null, [
            'rows_affected' => $lockedCount,
            'meta' => [
                'requested' => count($contractIds),
                'locked' => $lockedCount,
                'skipped' => $skippedCount,
                'skipped_contracts' => $result['skipped'],
                'maker_checker_override' => $adminOverride,
            ],
        ]);

        if ($lockedCount === 0) {
            return back()->withErrors([
                'approval' => "No EIRs were locked. {$skippedCount} contract(s) failed maker-checker or approval validation.",
            ]);
        }

        $message = "{$lockedCount} EIR(s) approved and permanently locked.";
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} contract(s) were skipped by maker-checker or approval validation.";
        }

        return back()->with('success', $message);
    }

    public function reopen(Request $request, ContractEir $contractEir, EirCalculationService $calculations)
    {
        if (! $this->adminOverride()) {
            abort(403, 'Only an administrator can reopen a locked original EIR.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $oldValues = [
            'contract_id' => $contractEir->contract_id,
            'eir_effective_annual' => $contractEir->eir_effective_annual,
            'locked_at' => $contractEir->locked_at,
            'locked_by' => $contractEir->locked_by,
        ];

        try {
            $reopened = $calculations->reopen(
                $contractEir->contract_id,
                (int) auth()->id(),
                $data['reason']
            );
        } catch (LogicException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        AuditLoggerService::log('Locked Original EIR Reopened', ContractEir::class, $reopened->id, [
            'old_values' => $oldValues,
            'new_values' => ['calculation_status' => $reopened->calculation_status, 'locked_at' => null],
            'meta' => ['reason' => $data['reason'], 'downstream_results_invalidated' => true],
        ]);

        return back()->with('success', "Original EIR for {$reopened->contract_id} reopened. Recalculate and obtain independent approval before reuse.");
    }

    private function adminOverride(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }
}
