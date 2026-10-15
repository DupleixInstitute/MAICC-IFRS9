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
        $this->middleware('auth');
        // the calculations themselves sit behind settings; the reopening of a locked EIR is a
        // governed act under maker-checker (EirReopenRequestService), so a person with
        // eir.govern reaches the screen and the reopening actions
        $this->middleware('permission:settings|eir.govern')->only(['index']);
        $this->middleware('permission:settings')->only(['calculate', 'approve', 'approveAll']);
        $this->middleware('permission:eir.govern')->only(['reopen', 'approveReopen', 'closeReopen', 'clearReview']);
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
                ->orderByDesc('updated_at')->paginate(15)->withQueryString(),
            'filters' => ['status' => $status, 'contract_id' => $search],
            'summary' => ContractEir::selectRaw('calculation_status, COUNT(*) as contract_count')->groupBy('calculation_status')->get()->keyBy('calculation_status'),
            'approvalSummary' => [
                'total' => $approvalTotal,
                'eligible' => $approvalEligible,
                'own' => $approvalOwn,
                'missing_maker' => $approvalMissingMaker,
                'admin_override' => $adminOverride,
            ],
            // any person with eir.govern may request a reopening; a different one approves it
            'canReopen' => (bool) (auth()->user()?->can('eir.govern') ?? false),
            'canCalculate' => (bool) (auth()->user()?->can('settings') ?? false),
            'reopenRequests' => app(\App\Services\Eir\EirReopenRequestService::class)->pending(),
            // locked EIRs with fee evidence that was not in their inputs (EirLockReviewService, 15 October 2026)
            'lockReviews' => app(\App\Services\Eir\EirLockReviewService::class)->open(),
            // the contracts readiness clears (no blocker), not yet calculated: "Calculate all ready" sends them in one go
            'readyIds' => $this->readyIds(),
            'me' => $reviewerId,
        ]);
    }

    /** A second person clears a review flag on a locked EIR, with the reason (the flag never reopens anything by itself). */
    public function clearReview(Request $request, int $flag, \App\Services\Eir\EirLockReviewService $reviews)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        try {
            $reviews->clear($flag, (int) $request->user()->id, $data['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'The review flag is cleared, with the reason recorded.');
    }

    /** @return list<string> unlocked contracts not yet calculated whose readiness reports no blocker */
    private function readyIds(): array
    {
        $readiness = app(\App\Services\Eir\EirReadinessService::class);

        return ContractEir::query()->whereNull('locked_at')->whereIn('calculation_status', ['PENDING', 'BLOCKED', 'REOPENED'])->orderBy('contract_id')->get()
            ->filter(fn (ContractEir $c) => array_diff(array_column($readiness->assessContract($c)['issues'] ?? [], 'code'), ['EIR_LOCKED']) === [])
            ->pluck('contract_id')->values()->all();
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

    /**
     * Request the reopening of a locked original EIR, with the reason
     * (documentation audit of 14 October 2026). Until then this aborted unless
     * the administrator override was in force, so under the default nobody
     * could reopen a locked EIR. Now one person with eir.govern requests it
     * and a different person approves it (approveReopen); only then does
     * EirCalculationService::reopen run.
     */
    public function reopen(Request $request, ContractEir $contractEir, \App\Services\Eir\EirReopenRequestService $requests)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        try {
            $requests->request($contractEir->contract_id, (int) auth()->id(), $data['reason']);
        } catch (LogicException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('success', "Reopening of the original EIR for {$contractEir->contract_id} requested. A different person with EIR governance approves it before the EIR is reopened.");
    }

    /** A second person approves a reopening; the EIR is then reopened, or the reason it cannot be is shown. */
    public function approveReopen(int $reopen, \App\Services\Eir\EirReopenRequestService $requests)
    {
        try {
            $reopened = $requests->approve($reopen, (int) auth()->id());
        } catch (\Throwable $e) {
            if (! $e instanceof LogicException && ! $e instanceof \App\Support\LockedPeriodException) {
                throw $e;
            }
            return back()->withErrors(['reopen' => $e->getMessage()]);
        }

        return back()->with('success', "Original EIR for {$reopened->contract_id} reopened. Recalculate and obtain independent approval before reuse.");
    }

    /** Reject a reopening (another person) or withdraw it (the person who asked), with a reason. */
    public function closeReopen(Request $request, int $reopen, \App\Services\Eir\EirReopenRequestService $requests)
    {
        $data = $request->validate(['action' => ['required', 'in:reject,withdraw'], 'reason' => ['required', 'string', 'min:10', 'max:500']]);
        try {
            $requests->close($reopen, (int) auth()->id(), $data['action'], $data['reason']);
        } catch (LogicException $e) {
            return back()->withErrors(['reopen' => $e->getMessage()]);
        }

        return back()->with('success', $data['action'] === 'reject' ? 'Reopening rejected; the EIR stays locked.' : 'Reopening withdrawn; the EIR stays locked.');
    }

    private function adminOverride(): bool
    {
        return app(\App\Services\Eir\GovernanceService::class)->adminOverrideAllowed(auth()->user()); // governed (audit H9)
    }
}
