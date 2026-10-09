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
        /*
        | On the ECL basis (system audit of 9 October 2026, H5 and the step-5
        | reconciliation): each loan's booked allowance (ecl_value, whichever
        | engine wrote it) scaled by the ratio of the stressed stage PD to the
        | booked stage PD and of the stressed LGD to the held LGD, by the stage
        | the ECL was measured on. The base case (multipliers 1, add-ons 0)
        | therefore equals the allowance to the kwacha; a loan with no booked
        | ECL is measured directly. Before, the stress recomputed 12-month PD x
        | LGD x EAD by the DPD-only stage and never agreed with the allowance.
        */
        $q = DB::table('loan_books')->where('reporting_period', $period);
        if (! empty($portfolioId)) {
            $q->where('loan_portfolio_id', $portfolioId);
        }
        $loans = $q->leftJoin('loan_portfolios as p', 'p.id', '=', 'loan_books.loan_portfolio_id')
            ->get(['loan_books.*', DB::raw("COALESCE(p.name, 'Unmapped') AS portfolio_name")]);

        $stagePd = function (string $stage, float $pd12, $tenor): float {
            $pd12 = max(0.0, min(1.0, $pd12));
            $months = $tenor === null || (float) $tenor < 1 ? 12.0 : (float) $tenor;
            return match ($stage) {
                '3' => 1.0,
                '2' => 1 - pow(1 - $pd12, $months / 12),
                default => 1 - pow(1 - $pd12, min(12.0, $months) / 12),
            };
        };

        $byStage = []; $byPortfolio = [];
        foreach ($loans as $l) {
            $stage = (string) ($l->ifrs9stage_post_qualitative ?? $l->calculated_ifrs9_stage ?? $l->ifrs9stage_pre_qualitative ?? '');
            if (! in_array($stage, ['1', '2', '3'], true)) {
                continue;
            }
            $i = (int) $stage;
            $ead = (float) ($l->carrying_amount ?? 0) + (float) ($l->commitments ?? 0) * (float) ($l->facility_utilisation_rate ?? 1);
            $pd12 = (float) ($l->pd_post_fli ?? $l->pd_prefli ?? 0);
            $lgd = (float) ($l->lgd_value ?? 0);
            $basePd = $stagePd($stage, $pd12, $l->remaining_tenor ?? null);
            $stressPd = $stagePd($stage, min(1.0, $pd12 * (float) ($pd[$i] ?? 1)), $l->remaining_tenor ?? null);
            $stressLgd = min(1.0, $lgd + (float) ($lg[$i] ?? 0));
            if ($l->ecl_value !== null && $basePd > 0 && $lgd > 0) {
                $base = (float) $l->ecl_value;
                $stress = $base * ($stressPd / $basePd) * ($stressLgd / $lgd);
            } else {
                $base = $ead * $basePd * $lgd;
                $stress = $ead * $stressPd * $stressLgd;
            }
            foreach ([['byStage', $stage], ['byPortfolio', (string) $l->portfolio_name]] as [$bucket, $key]) {
                $$bucket[$key] = $$bucket[$key] ?? ['accounts' => 0, 'exposure' => 0.0, 'base_ecl' => 0.0, 'stress_ecl' => 0.0, 'pd_sum' => 0.0, 'lgd_sum' => 0.0];
                $$bucket[$key]['accounts']++; $$bucket[$key]['exposure'] += $ead; $$bucket[$key]['base_ecl'] += $base; $$bucket[$key]['stress_ecl'] += $stress;
                $$bucket[$key]['pd_sum'] += $pd12; $$bucket[$key]['lgd_sum'] += $lgd;
            }
        }
        ksort($byStage);
        $rows = fn (array $groups, string $keyName) => collect($groups)->map(fn ($g, $k) => (object) [
            $keyName => $keyName === 'stage' ? (int) $k : $k, 'accounts' => $g['accounts'], 'exposure' => round($g['exposure'], 2),
            'base_ecl' => round($g['base_ecl'], 2), 'stress_ecl' => round($g['stress_ecl'], 2),
            'avg_pd' => $g['accounts'] ? $g['pd_sum'] / $g['accounts'] : 0, 'avg_lgd' => $g['accounts'] ? $g['lgd_sum'] / $g['accounts'] : 0,
        ])->values();
        $byStage = $rows($byStage, 'stage');
        $byPortfolio = $rows($byPortfolio, 'portfolio')->sortByDesc('stress_ecl')->values();

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
