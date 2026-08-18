<?php

namespace App\Http\Controllers;

use App\Models\ContractEir;
use App\Services\Eir\EirCoverageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EirCoverageController extends Controller
{
    /** The drill-down is a worked example, not an export: the book is 3,600 rows. */
    private const DRILLDOWN_LIMIT = 50;

    public function __construct()
    {
        $this->middleware(['auth', 'permission:settings']);
    }

    public function index(Request $request, EirCoverageService $coverage)
    {
        $periods = $coverage->availablePeriods();
        $period = (string) $request->input('period', '');
        if (! in_array($period, $periods, true)) {
            $period = $periods[0] ?? null;
        }

        $portfolio = trim((string) $request->input('portfolio', ''));
        $issue = trim((string) $request->input('issue', ''));

        $profile = $coverage->profile($period, $portfolio !== '' ? $portfolio : null);

        // Largest exposures first, optionally narrowed to one blocker: the
        // question a data request needs answered is which facilities are worth
        // chasing, not which happen to sort first by identifier.
        $contracts = $profile['contracts'];
        if ($issue !== '') {
            $contracts = array_filter($contracts, fn ($c) => in_array($issue, $c['issues'], true));
        }
        usort($contracts, fn ($a, $b) => $b['exposure'] <=> $a['exposure']);

        return Inertia::render('Eir/Coverage', [
            'period' => $profile['period'],
            'periods' => $periods,
            'portfolios' => ContractEir::query()->whereNotNull('portfolio')->where('portfolio', '<>', '')
                ->distinct()->orderBy('portfolio')->pluck('portfolio'),
            'filters' => ['period' => $period, 'portfolio' => $portfolio, 'issue' => $issue],
            'summary' => $profile['summary'],
            'states' => $profile['states'],
            'issues' => $profile['issues'],
            'portfolioBreakdown' => $profile['portfolios'],
            'contracts' => array_slice(array_values($contracts), 0, self::DRILLDOWN_LIMIT),
            'contractsTotal' => count($contracts),
            'drilldownLimit' => self::DRILLDOWN_LIMIT,
        ]);
    }
}
