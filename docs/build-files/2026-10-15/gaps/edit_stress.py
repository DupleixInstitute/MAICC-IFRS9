import os
os.chdir('C:/xampp/htdocs/MAICC-IFRS9')
p = 'app/Services/Stress/StressTestService.php'
s = open(p, encoding='utf-8').read()
i = s.index('class StressTestService')
j = s.index('    /** Run the approved scenario set of the period as saved stress scenarios; returns one row per scenario. */')
new_head = '''use App\\Services\\Ecl\\EclExposure;
use App\\Services\\Reports\\BookedEcl;

/**
 * Stress testing as a service (spec v4 section 6.10.1, step 11): the
 * loan-level stress of the Stress Testing screen, lifted as it is so the
 * bootstrap and the scheduler can run it, and run for every scenario of
 * the approved set with the scenario's PD multiplier.
 *
 * The base is the booked loss allowance (BookedEcl: the ECL discounted at the
 * EIR, the Mega Farm programme measure, or the undiscounted ECL where no
 * discounted figure exists, counted and named), so the base case reconciles
 * to the allowance every report carries; each stressed scenario scales the
 * same figure, so base and stress are on one basis. The undiscounted measure
 * is shown beside both, stressed the same way (documentation audit of 14
 * October 2026: the base had been the undiscounted ecl_value, and a loan with
 * no ECL was measured directly as EAD x PD x LGD, outside the allowance). The
 * exposure is the engine's (EclExposure).
 */
class StressTestService
{
    public function execute(string $period, ?int $portfolioId, array $pd, array $lg): array
    {
        /*
        | On the ECL basis (system audit of 9 October 2026, H5 and the step-5
        | reconciliation): each loan's booked allowance scaled by the ratio of
        | the stressed stage PD to the booked stage PD and of the stressed LGD
        | to the held LGD, by the stage the ECL was measured on. The base case
        | (multipliers 1, add-ons 0) therefore equals the booked allowance to
        | the kwacha. A loan with no ECL calculated has no allowance to stress:
        | it is counted with its exposure as not measured, never estimated. A
        | loan whose PD or LGD is nil has no ratio to scale by: it is kept at
        | its allowance and counted as not scaled.
        */
        $q = DB::table('loan_books')->where('reporting_period', $period);
        if (! empty($portfolioId)) {
            $q->where('loan_portfolio_id', $portfolioId);
        }
        $loans = $q->leftJoin('loan_portfolios as p', 'p.id', '=', 'loan_books.loan_portfolio_id')
            ->get(['loan_books.*', DB::raw("COALESCE(p.name, 'Unmapped') AS portfolio_name"),
                DB::raw(BookedEcl::sql('loan_books.') . ' AS ecl_booked'), DB::raw(BookedEcl::basisSql('loan_books.') . ' AS ecl_basis')]);

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
        $notMeasured = ['loans' => 0, 'exposure' => 0.0];
        $notScaled = ['loans' => 0, 'base_ecl' => 0.0];
        $notBooked = ['loans' => 0, 'base_ecl' => 0.0];
        foreach ($loans as $l) {
            $stage = (string) ($l->ifrs9stage_post_qualitative ?? $l->calculated_ifrs9_stage ?? $l->ifrs9stage_pre_qualitative ?? '');
            if (! in_array($stage, ['1', '2', '3'], true)) {
                continue;
            }
            $i = (int) $stage;
            $ead = EclExposure::of($l);
            $pd12 = (float) ($l->pd_post_fli ?? $l->pd_prefli ?? 0);
            $lgd = (float) ($l->lgd_value ?? 0);
            $basePd = $stagePd($stage, $pd12, $l->remaining_tenor ?? null);
            $stressPd = $stagePd($stage, min(1.0, $pd12 * (float) ($pd[$i] ?? 1)), $l->remaining_tenor ?? null);
            $stressLgd = min(1.0, $lgd + (float) ($lg[$i] ?? 0));
            $base = 0.0; $stress = 0.0; $baseU = 0.0; $stressU = 0.0;
            if ($l->ecl_basis === 'none') {
                $notMeasured['loans']++;
                $notMeasured['exposure'] += $ead;
            } else {
                $base = (float) $l->ecl_booked;
                $baseU = (float) ($l->ecl_value ?? 0);
                if ($basePd > 0 && $lgd > 0) {
                    $factor = ($stressPd / $basePd) * ($stressLgd / $lgd);
                } else {
                    $factor = 1.0;
                    $notScaled['loans']++;
                    $notScaled['base_ecl'] += $base;
                }
                $stress = $base * $factor;
                $stressU = $baseU * $factor;
                if ($l->ecl_basis === 'undiscounted') {
                    $notBooked['loans']++;
                    $notBooked['base_ecl'] += $base;
                }
            }
            foreach ([['byStage', $stage], ['byPortfolio', (string) $l->portfolio_name]] as [$bucket, $key]) {
                $$bucket[$key] = $$bucket[$key] ?? ['accounts' => 0, 'exposure' => 0.0, 'base_ecl' => 0.0, 'stress_ecl' => 0.0, 'base_ecl_undiscounted' => 0.0, 'stress_ecl_undiscounted' => 0.0, 'pd_sum' => 0.0, 'lgd_sum' => 0.0];
                $$bucket[$key]['accounts']++; $$bucket[$key]['exposure'] += $ead; $$bucket[$key]['base_ecl'] += $base; $$bucket[$key]['stress_ecl'] += $stress;
                $$bucket[$key]['base_ecl_undiscounted'] += $baseU; $$bucket[$key]['stress_ecl_undiscounted'] += $stressU;
                $$bucket[$key]['pd_sum'] += $pd12; $$bucket[$key]['lgd_sum'] += $lgd;
            }
        }
        ksort($byStage);
        $rows = fn (array $groups, string $keyName) => collect($groups)->map(fn ($g, $k) => (object) [
            $keyName => $keyName === 'stage' ? (int) $k : $k, 'accounts' => $g['accounts'], 'exposure' => round($g['exposure'], 2),
            'base_ecl' => round($g['base_ecl'], 2), 'stress_ecl' => round($g['stress_ecl'], 2),
            'base_ecl_undiscounted' => round($g['base_ecl_undiscounted'], 2), 'stress_ecl_undiscounted' => round($g['stress_ecl_undiscounted'], 2),
            'avg_pd' => $g['accounts'] ? $g['pd_sum'] / $g['accounts'] : 0, 'avg_lgd' => $g['accounts'] ? $g['lgd_sum'] / $g['accounts'] : 0,
        ])->values();
        $byStage = $rows($byStage, 'stage');
        $byPortfolio = $rows($byPortfolio, 'portfolio')->sortByDesc('stress_ecl')->values();

        $totBase   = (float) $byStage->sum('base_ecl');
        $totStress = (float) $byStage->sum('stress_ecl');
        $baseU = (float) $byStage->sum('base_ecl_undiscounted');
        $stressU = (float) $byStage->sum('stress_ecl_undiscounted');

        return [
            'period'           => $period,
            'total_base_ecl'   => $totBase,
            'total_stress_ecl' => $totStress,
            'delta'            => $totStress - $totBase,
            'delta_pct'        => $totBase > 0 ? ($totStress - $totBase) / $totBase : 0,
            'total_exposure'   => (float) $byStage->sum('exposure'),
            'by_stage'         => $byStage,
            'by_portfolio'     => $byPortfolio,
            // the basis of the figures above, and the undiscounted measure beside them
            'basis' => [
                'base' => 'booked',
                'label' => 'Booked loss allowance (BookedEcl), stressed on the same basis',
                'undiscounted' => ['base_ecl' => round($baseU, 2), 'stress_ecl' => round($stressU, 2), 'delta' => round($stressU - $baseU, 2)],
                'not_booked' => ['loans' => $notBooked['loans'], 'base_ecl' => round($notBooked['base_ecl'], 2)],
                'not_measured' => ['loans' => $notMeasured['loans'], 'exposure' => round($notMeasured['exposure'], 2)],
                'not_scaled' => ['loans' => $notScaled['loans'], 'base_ecl' => round($notScaled['base_ecl'], 2)],
            ],
        ];
    }

'''
# keep the namespace and the first use statements, drop the old class head
head = s[:s.index('/**\n * Stress testing as a service')]
s = head.replace("use Illuminate\\Support\\Facades\\DB;\n", "use Illuminate\\Support\\Facades\\DB;\n") + new_head + s[j:]
# the snapshot of a set run carries the basis
s = s.replace("""'by_stage' => $r['by_stage'], 'by_portfolio' => $r['by_portfolio']]),""", """'by_stage' => $r['by_stage'], 'by_portfolio' => $r['by_portfolio'], 'basis' => $r['basis']]),""")
open(p, 'w', encoding='utf-8', newline='').write(s)
print('done')
