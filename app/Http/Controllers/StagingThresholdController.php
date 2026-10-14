<?php

namespace App\Http\Controllers;

use App\Services\Eir\StagingThresholdService;
use Illuminate\Http\Request;
use Throwable;

/**
 * Settings, Staging basis: the days-past-due thresholds proposed by one
 * person and approved by another (system audit of 9 October 2026, H11).
 * Staging reads only approved rows.
 */
class StagingThresholdController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:eir.govern']);
    }

    public function propose(Request $request, StagingThresholdService $service)
    {
        $data = $request->validate([
            'facility_class' => ['required', 'string', 'max:50'],
            'min_tenor_months' => ['required', 'integer', 'min:0', 'max:600'],
            'stage2_dpd' => ['required', 'integer', 'min:1', 'max:3650'],
            'stage3_dpd' => ['required', 'integer', 'min:2', 'max:3650'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'rebuttal_basis' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->act(fn () => $service->propose($data, $request->user()->id), 'Threshold proposed; a second person approves it before staging reads it.');
    }

    public function approve(Request $request, int $threshold, StagingThresholdService $service)
    {
        return $this->act(fn () => $service->approve($threshold, $request->user()->id), 'Threshold approved; staging reads it from its effective date.');
    }

    public function reject(Request $request, int $threshold, StagingThresholdService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        return $this->act(fn () => $service->reject($threshold, $request->user()->id, $data['reason']), 'Threshold proposal rejected.');
    }

    private function act(callable $fn, string $success)
    {
        try {
            $fn();
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('settings.index', ['tab' => 'staging'])->with('success', $success);
    }
}
