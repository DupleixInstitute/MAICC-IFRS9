<?php

namespace App\Http\Controllers;

use App\Models\ContractEir;
use App\Services\Eir\EirGlReconciliationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EirReconciliationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:settings']);
    }

    public function index(Request $request, EirGlReconciliationService $reconciliation)
    {
        $periods = $reconciliation->availablePeriods();
        $period = (string) $request->input('period', '');
        if (! in_array($period, $periods, true)) {
            $period = $periods[0] ?? null;
        }

        $portfolio = trim((string) $request->input('portfolio', ''));
        $result = $reconciliation->forPeriod($period, $portfolio !== '' ? $portfolio : null);

        return Inertia::render('Eir/Reconciliation', [
            'period' => $result['period'],
            'periods' => $periods,
            'portfolios' => ContractEir::query()->whereNotNull('portfolio')->where('portfolio', '<>', '')
                ->distinct()->orderBy('portfolio')->pluck('portfolio'),
            'filters' => ['period' => $period, 'portfolio' => $portfolio],
            'rows' => $result['rows'],
            'bridge' => $result['bridge'],
            'summary' => $result['summary'],
        ]);
    }
}
