<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\StressScenario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Dedicated, standalone Stress Testing module (not a tab in the IFRS 9 hub).
 *
 * Laravel port of the FDH stress-testing methodology: per-IFRS-9-stage PD
 * multipliers and LGD add-ons (in percentage points). Unlike the FDH handler,
 * which scaled an aggregate base ECL by stage-average factors, this recomputes
 * ECL loan-by-loan (EAD x PD x LGD with 100% caps) for accuracy, then rolls up
 * by stage and by portfolio. Scenarios can be saved and reloaded.
 */
class StressTestingController extends Controller
{
    private const EAD = '(COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1))';
    private const PD  = 'COALESCE(pd_post_fli, pd_prefli, 0)';
    private const LGD = 'COALESCE(lgd_value, 0)';

    public function index(Request $request)
    {
        return Inertia::render('Reports/StressTesting', [
            'periods'    => DB::table('loan_books')->whereNotNull('reporting_period')
                ->distinct()->orderByDesc('reporting_period')->pluck('reporting_period'),
            'portfolios' => DB::table('loan_portfolios')->orderBy('name')
                ->get(['id', 'name'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->name]),
            'scenarios'  => StressScenario::orderByDesc('id')->limit(50)->get(),
        ]);
    }

    /**
     * Run a scenario and return base vs stressed ECL by stage and portfolio.
     * PD params are multipliers (1.0 = unchanged); LGD params are added
     * percentage points (5 = +5pts on the LGD decimal).
     */
    public function run(Request $request)
    {
        $v = $request->validate([
            'period'            => 'required|string',
            'loan_portfolio_id' => 'nullable|integer',
            's1_pd_mult' => 'nullable|numeric', 's2_pd_mult' => 'nullable|numeric', 's3_pd_mult' => 'nullable|numeric',
            's1_lgd_add' => 'nullable|numeric', 's2_lgd_add' => 'nullable|numeric', 's3_lgd_add' => 'nullable|numeric',
        ]);

        $pd  = [
            1 => max(0.01, (float) ($v['s1_pd_mult'] ?? 1)),
            2 => max(0.01, (float) ($v['s2_pd_mult'] ?? 1)),
            3 => max(0.01, (float) ($v['s3_pd_mult'] ?? 1)),
        ];
        // LGD add-ons arrive in percentage points; convert to decimal.
        $lg = [
            1 => max(0.0, (float) ($v['s1_lgd_add'] ?? 0)) / 100,
            2 => max(0.0, (float) ($v['s2_lgd_add'] ?? 0)) / 100,
            3 => max(0.0, (float) ($v['s3_lgd_add'] ?? 0)) / 100,
        ];

        // Per-stage CASE expressions (bindings in stage order 1,2,3).
        $pdCase  = "CASE ifrs9stage_pre_qualitative WHEN 1 THEN ? WHEN 2 THEN ? WHEN 3 THEN ? ELSE 1 END";
        $lgCase  = "CASE ifrs9stage_pre_qualitative WHEN 1 THEN ? WHEN 2 THEN ? WHEN 3 THEN ? ELSE 0 END";
        $baseEcl = self::EAD . ' * ' . self::PD . ' * ' . self::LGD;
        $strEcl  = self::EAD
            . ' * LEAST(1, ' . self::PD . " * ($pdCase))"
            . ' * LEAST(1, ' . self::LGD . " + ($lgCase))";

        // Binding order: stress PD case (3), stress LGD case (3).
        $b = [$pd[1], $pd[2], $pd[3], $lg[1], $lg[2], $lg[3]];

        $scope = function ($q) use ($v) {
            $q->where('reporting_period', $v['period']);
            if (! empty($v['loan_portfolio_id'])) {
                $q->where('loan_portfolio_id', $v['loan_portfolio_id']);
            }
            return $q;
        };

        $byStage = $scope(DB::table('loan_books'))
            ->whereIn('ifrs9stage_pre_qualitative', [1, 2, 3])
            ->groupBy('ifrs9stage_pre_qualitative')
            ->orderBy('ifrs9stage_pre_qualitative')
            ->selectRaw(
                "ifrs9stage_pre_qualitative AS stage,
                 COUNT(*) AS accounts,
                 SUM(" . self::EAD . ") AS exposure,
                 SUM($baseEcl) AS base_ecl,
                 SUM($strEcl) AS stress_ecl,
                 AVG(" . self::PD . ") AS avg_pd,
                 AVG(" . self::LGD . ") AS avg_lgd",
                $b
            )->get();

        $byPortfolio = $scope(DB::table('loan_books as lb'))
            ->leftJoin('loan_portfolios as p', 'p.id', 'lb.loan_portfolio_id')
            ->whereIn('ifrs9stage_pre_qualitative', [1, 2, 3])
            ->groupBy('p.name')
            ->selectRaw(
                "COALESCE(p.name,'Unmapped') AS portfolio,
                 COUNT(*) AS accounts,
                 SUM(" . self::EAD . ") AS exposure,
                 SUM($baseEcl) AS base_ecl,
                 SUM($strEcl) AS stress_ecl",
                $b
            )->get()
            ->sortByDesc('stress_ecl')->values();

        $totBase   = (float) $byStage->sum('base_ecl');
        $totStress = (float) $byStage->sum('stress_ecl');

        return response()->json([
            'period'           => $v['period'],
            'total_base_ecl'   => $totBase,
            'total_stress_ecl' => $totStress,
            'delta'            => $totStress - $totBase,
            'delta_pct'        => $totBase > 0 ? ($totStress - $totBase) / $totBase : 0,
            'total_exposure'   => (float) $byStage->sum('exposure'),
            'by_stage'         => $byStage,
            'by_portfolio'     => $byPortfolio,
        ]);
    }

    public function save(Request $request)
    {
        $v = $request->validate([
            'scenario_name'     => 'required|string|max:120',
            'period'            => 'required|string',
            'loan_portfolio_id' => 'nullable|integer',
            'description'       => 'nullable|string',
            's1_pd_mult' => 'nullable|numeric', 's2_pd_mult' => 'nullable|numeric', 's3_pd_mult' => 'nullable|numeric',
            's1_lgd_add' => 'nullable|numeric', 's2_lgd_add' => 'nullable|numeric', 's3_lgd_add' => 'nullable|numeric',
            'result_snapshot'   => 'nullable|array',
        ]);

        $s = StressScenario::create([
            'scenario_name'     => $v['scenario_name'],
            'reporting_period'  => $v['period'],
            'loan_portfolio_id' => $v['loan_portfolio_id'] ?? null,
            'description'       => $v['description'] ?? null,
            's1_pd_mult' => $v['s1_pd_mult'] ?? 1, 's2_pd_mult' => $v['s2_pd_mult'] ?? 1, 's3_pd_mult' => $v['s3_pd_mult'] ?? 1,
            's1_lgd_add' => $v['s1_lgd_add'] ?? 0, 's2_lgd_add' => $v['s2_lgd_add'] ?? 0, 's3_lgd_add' => $v['s3_lgd_add'] ?? 0,
            'result_snapshot'   => $v['result_snapshot'] ?? null,
            'saved_by'          => optional($request->user())->name ?? 'system',
        ]);

        return response()->json(['success' => true, 'scenario' => $s]);
    }

    public function destroy(StressScenario $stressScenario)
    {
        $stressScenario->delete();
        return response()->json(['success' => true]);
    }
}
