<?php

namespace App\Services\Stress;

use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\DB;

/**
 * Stress testing as a service (spec v4 section 6.10.1, step 11): the
 * loan-level stress of the Stress Testing screen, lifted as it is so the
 * bootstrap and the scheduler can run it, and run for every scenario of
 * the approved set with the scenario's PD multiplier.
 */
class StressTestService
{
    public const EAD = '(COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1))';
    public const PD = 'COALESCE(pd_post_fli, pd_prefli, 0)';
    public const LGD = 'COALESCE(lgd_value, 0)';

    public function execute(string $period, ?int $portfolioId, array $pd, array $lg): array
    {
        // Per-stage CASE expressions (bindings in stage order 1,2,3).
        // LEAST() is MySQL; SQLite (the test database) spells it MIN().
        $least   = DB::connection()->getDriverName() === 'sqlite' ? 'MIN' : 'LEAST';
        $pdCase  = "CASE ifrs9stage_pre_qualitative WHEN 1 THEN ? WHEN 2 THEN ? WHEN 3 THEN ? ELSE 1 END";
        $lgCase  = "CASE ifrs9stage_pre_qualitative WHEN 1 THEN ? WHEN 2 THEN ? WHEN 3 THEN ? ELSE 0 END";
        $baseEcl = self::EAD . ' * ' . self::PD . ' * ' . self::LGD;
        $strEcl  = self::EAD
            . " * $least(1, " . self::PD . " * ($pdCase))"
            . " * $least(1, " . self::LGD . " + ($lgCase))";

        // Binding order: stress PD case (3), stress LGD case (3).
        $b = [$pd[1], $pd[2], $pd[3], $lg[1], $lg[2], $lg[3]];

        $scope = function ($q) use ($period, $portfolioId) {
            $q->where('reporting_period', $period);
            if (! empty($portfolioId)) {
                $q->where('loan_portfolio_id', $portfolioId);
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

        return [
            'period'           => $period,
            'total_base_ecl'   => $totBase,
            'total_stress_ecl' => $totStress,
            'delta'            => $totStress - $totBase,
            'delta_pct'        => $totBase > 0 ? ($totStress - $totBase) / $totBase : 0,
            'total_exposure'   => (float) $byStage->sum('exposure'),
            'by_stage'         => $byStage,
            'by_portfolio'     => $byPortfolio,
        ];
    }

    /** Run the approved scenario set of the period as saved stress scenarios; returns one row per scenario. */
    public function runSet(string $period, ?int $portfolioId, ?int $userId = null, ?string $label = null): array
    {
        $set = DB::table('governed_scenario_sets')->where('reporting_period', $period)->whereIn('status', ['APPROVED', 'LOCKED'])->orderByDesc('version')->first();
        if ($set === null) {
            return [];
        }
        $out = [];
        foreach (DB::table('governed_scenarios')->where('set_id', $set->id)->orderBy('order_position')->get() as $s) {
            $m = (float) ($s->pd_multiplier ?? 1);
            $r = $this->execute($period, $portfolioId, [1 => $m, 2 => $m, 3 => 1.0], [1 => 0.0, 2 => 0.0, 3 => 0.0]);
            $name = "Set {$set->id} v{$set->version}: {$s->name}";
            DB::table('stress_scenarios')->updateOrInsert(['scenario_name' => $name, 'reporting_period' => $period, 'loan_portfolio_id' => $portfolioId], [
                'description' => ($s->anchored_to ?? '') . ($label ? ' [' . $label . ']' : ''), 's1_pd_mult' => $m, 's2_pd_mult' => $m, 's3_pd_mult' => 1, 's1_lgd_add' => 0, 's2_lgd_add' => 0, 's3_lgd_add' => 0,
                'result_snapshot' => json_encode(['total_base_ecl' => $r['total_base_ecl'], 'total_stress_ecl' => $r['total_stress_ecl'], 'delta' => $r['delta'], 'delta_pct' => $r['delta_pct'], 'total_exposure' => $r['total_exposure'], 'by_stage' => $r['by_stage'], 'by_portfolio' => $r['by_portfolio']]),
                'saved_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $out[$s->name] = ['weight' => (float) $s->weight, 'pd_multiplier' => $m, 'base_ecl' => round($r['total_base_ecl'], 2), 'stress_ecl' => round($r['total_stress_ecl'], 2), 'delta_pct' => round($r['delta_pct'] * 100, 2)];
        }
        AuditLoggerService::log('Stress Set Run', 'stress_scenarios', null, ['reporting_period' => $period, 'new_values' => $out, 'meta' => ['user' => $userId, 'label' => $label, 'set' => $set->id]]);

        return $out;
    }
}
