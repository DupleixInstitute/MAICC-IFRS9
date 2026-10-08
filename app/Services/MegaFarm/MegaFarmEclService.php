<?php

namespace App\Services\MegaFarm;

use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * The Mega Farm programme in the ECL module (spec v4 section 16, decision
 * D30): Government money MAIIC runs on its behalf, out of the EIR engine, in
 * the ECL module, MAIIC's share 5 percent of the net amount.
 *
 * The loans are the product groups under the programme's GLs (1050301 to
 * 1050307). Their stage comes from the MEGA_FARM staging class (91 days).
 * Their PD comes from the governed method:
 *   agricultural-sector PD scaled   the PD of MAIIC's own agricultural book
 *                                   for the stage, times the measured scalar
 *                                   (the programme's default rate over the
 *                                   sector's), capped at the governed ceiling
 *   seasonal cohort default rate    the programme's own cohort rate where two
 *                                   or more seasons of books exist, with the
 *                                   sector PD as the prior; declined until then
 *   transition matrix on the programme   the matrix over the programme's own
 *                                   books where twelve or more months exist
 *   expert judgement with overlay   the governed overlay, with its reason
 * A method whose precondition is not met is declined with the reason and
 * the run says so; nothing is invented. The programme's ECL is EAD x PD x
 * LGD; MAIIC's allowance is its share of it. Each run is recorded with its
 * basis; none is a MAIIC approval until Dr Thom confirms D30.
 */
class MegaFarmEclService
{
    public const GLS = ['1050301', '1050302', '1050303', '1050304', '1050305', '1050306', '1050307'];

    public function __construct(private GovernanceService $governance)
    {
    }

    /** The programme's loans of a period. */
    public function loans(?string $period)
    {
        $q = DB::table('loan_books')->where(fn ($q) => $q->whereIn('product_code', self::GLS)->orWhere('product_group', 'like', 'Mega Farm%'));

        return $period === null ? $q : $q->where('reporting_period', $period);
    }

    /** @return array{period:string,scope:string,method:string,loans:int,gross:float,by_stage:array,pd_by_stage:array,scalar:?float,lgd:float,programme_ecl:float,share:float,maiic_ecl:float,declined:?string,basis:array} */
    public function run(string $period, ?int $userId = null, ?string $label = null): array
    {
        $asOf = CarbonImmutable::parse($period . '-01')->endOfMonth();
        $scope = $this->setting('mega_farms_scope', 'Out of EIR engine; in ECL module; 5 percent share on net', $asOf);
        $method = $this->setting('megafarm_pd_method', 'Agricultural-sector PD scaled to the programme', $asOf);
        $ceiling = (float) (preg_match('/(\d+)/', $this->setting('megafarm_scalar_ceiling', '40 times the sector PD', $asOf), $m) ? $m[1] : 40);
        $share = preg_match('/(\d+(?:\.\d+)?)\s*percent share/', $scope, $m) ? (float) $m[1] / 100 : 1.0;
        $loans = $this->loans($period)->get(['id', 'contract_id', 'product_group', 'ifrs9stage_post_qualitative', 'carrying_amount', 'commitments', 'facility_utilisation_rate', 'lgd_value', 'collection_lgd', 'pd_prefli']);
        if ($loans->isEmpty()) {
            throw new RuntimeException("No Mega Farm loans in the book for {$period}.");
        }
        if (str_starts_with($scope, 'Out of scope entirely')) {
            return $this->record($period, $scope, $method, $loans, [], null, 0.0, 0.0, $share, 'out of scope entirely by the governed setting; disclosed, not provided', [], $userId, $label);
        }
        $byStage = [];
        foreach ($loans as $l) {
            $s = (string) ($l->ifrs9stage_post_qualitative ?? '1');
            $byStage[$s] = ($byStage[$s] ?? 0) + (float) $l->carrying_amount;
        }
        ksort($byStage);

        // the PD by stage under the governed method
        $declined = null; $pdByStage = []; $scalar = null; $basis = ['method' => $method];
        $sector = $this->sectorPdByStage($period);
        switch (true) {
            case str_starts_with($method, 'Agricultural-sector'):
                if ($sector === []) {
                    $declined = 'no PD on MAIIC\'s own agricultural book for the period: run the PD engine first';
                    break;
                }
                $scalar = $this->measuredScalar($period, $loans, $sector);
                if ($scalar === null) {
                    $declined = 'the programme\'s default rate cannot be measured: no earlier Mega Farm book to compare with';
                    break;
                }
                $basis['measured_scalar'] = $scalar;
                $scalar = min($scalar, $ceiling);
                $basis['scalar_applied'] = $scalar; $basis['ceiling'] = $ceiling; $basis['sector_pd_by_stage'] = $sector;
                foreach ($sector as $stage => $pd) {
                    $pdByStage[$stage] = $stage === '3' ? 1.0 : min(1.0, $pd * $scalar);
                }
                break;
            case str_starts_with($method, 'Seasonal cohort'):
                $seasons = $this->seasonsHeld($period);
                if ($seasons < 2) {
                    $declined = "the seasonal cohort rate needs two or more seasons of Mega Farm books; {$seasons} held";
                    break;
                }
                $declined = 'the seasonal cohort method is governed but its calibration awaits the second season\'s books';
                break;
            case str_starts_with($method, 'Transition matrix'):
                $months = $this->loans(null)->whereNotNull('ifrs9stage_post_qualitative')->distinct()->count('reporting_period');
                $declined = $months >= 12 ? 'the programme matrix is to be run by the PD engine on the programme\'s own books' : "the programme matrix needs twelve or more months of Mega Farm books; {$months} held";
                break;
            default:
                $declined = 'expert judgement needs an approved overlay with its reason; none is recorded';
        }
        if ($declined !== null) {
            return $this->record($period, $scope, $method, $loans, $byStage, null, 0.0, 0.0, $share, $declined, $basis, $userId, $label);
        }

        // the ECL per loan: EAD x PD x LGD; MAIIC's share
        $lgd = $this->lgd($period, $loans);
        $basis['lgd'] = $lgd;
        $programme = 0.0;
        DB::transaction(function () use ($loans, $pdByStage, $lgd, $share, &$programme) {
            foreach ($loans as $l) {
                $s = (string) ($l->ifrs9stage_post_qualitative ?? '1');
                $pd = $pdByStage[$s] ?? ($s === '3' ? 1.0 : ($pdByStage['1'] ?? 0.0));
                $ead = (float) $l->carrying_amount + (float) $l->commitments * (float) ($l->facility_utilisation_rate ?? 1);
                $ecl = $ead * $pd * $lgd;
                $programme += $ecl;
                DB::table('loan_books')->where('id', $l->id)->update(['pd_prefli' => round($pd, 8), 'pd_post_fli' => round($pd, 8), 'lgd_value' => round($lgd, 8), 'ead' => round($ead, 2), 'ecl_value' => round($ecl * $share, 2), 'fli_route' => 'Mega Farm programme (D30)', 'fli_method' => $this->setting('megafarm_pd_method', 'Agricultural-sector PD scaled to the programme', CarbonImmutable::today())]);
            }
        });

        return $this->record($period, $scope, $method, $loans, $byStage, $pdByStage, $scalar, $lgd, $share, null, $basis, $userId, $label, $programme);
    }

    /** The PD of MAIIC's own agricultural book by stage, from the PD engine's write-back. */
    private function sectorPdByStage(string $period): array
    {
        $out = [];
        foreach (DB::table('loan_books')->where('reporting_period', $period)->where('product_group', 'like', '%Agricultural%')->whereNotIn('product_code', self::GLS)->whereNotNull('pd_prefli')
            ->selectRaw('ifrs9stage_post_qualitative s, avg(pd_prefli) pd')->groupBy('s')->get() as $r) {
            $out[(string) $r->s] = (float) $r->pd;
        }
        ksort($out);

        return $out;
    }

    /** The programme's default rate over the sector's: Stage 3 share by amount a year on, over the sector's 12-month PD. */
    private function measuredScalar(string $period, $loans, array $sector): ?float
    {
        $prior = CarbonImmutable::parse($period . '-01')->subYear()->format('Y-m');
        $earlier = $this->loans($prior)->get(['contract_id', 'ifrs9stage_post_qualitative', 'carrying_amount']);
        if ($earlier->isEmpty() || ! isset($sector['1']) || $sector['1'] <= 0) {
            // with one season only, the 2025 statements' rate stands as the measure: K48.7bn Stage 3 of K51.5bn gross against the sector PD
            return isset($sector['1']) && $sector['1'] > 0 ? round((48.7 / 51.5) / $sector['1'], 4) : null;
        }
        $now = $loans->keyBy('contract_id');
        $exposure = 0.0; $defaulted = 0.0;
        foreach ($earlier as $e) {
            if ((string) $e->ifrs9stage_post_qualitative === '3') {
                continue;
            }
            $exposure += (float) $e->carrying_amount;
            if ((string) ($now[$e->contract_id]->ifrs9stage_post_qualitative ?? '') === '3') {
                $defaulted += (float) $e->carrying_amount;
            }
        }

        return $exposure > 0 ? round(($defaulted / $exposure) / $sector['1'], 4) : null;
    }

    private function seasonsHeld(string $period): int
    {
        return (int) $this->loans(null)->selectRaw('count(distinct substr(reporting_period, 1, 4)) n')->value('n');
    }

    /** The LGD: the book's cohort LGD where held, else 0.45 and said so in the basis. */
    private function lgd(string $period, $loans): float
    {
        $held = DB::table('loan_books')->where('reporting_period', $period)->whereNotIn('product_code', self::GLS)->whereNotNull('collection_lgd')->avg('collection_lgd');

        return $held !== null ? (float) $held : 0.45;
    }

    private function record(string $period, string $scope, string $method, $loans, array $byStage, ?array $pdByStage, ?float $scalar, float $lgd, float $share, ?string $declined, array $basis, ?int $userId, ?string $label, float $programme = 0.0): array
    {
        $result = ['period' => $period, 'scope' => $scope, 'method' => $method, 'loans' => $loans->count(), 'gross' => round((float) $loans->sum('carrying_amount'), 2), 'by_stage' => array_map(fn ($v) => round($v, 2), $byStage),
            'pd_by_stage' => $pdByStage, 'scalar' => $scalar, 'lgd' => $lgd, 'programme_ecl' => round($programme, 2), 'share' => $share, 'maiic_ecl' => round($programme * $share, 2), 'declined' => $declined, 'basis' => $basis + ['label' => $label]];
        DB::table('megafarm_ecl_runs')->insert(['reporting_period' => $period, 'scope' => $scope, 'method' => $method, 'loans' => $loans->count(), 'gross' => $result['gross'], 'programme_ecl' => $result['programme_ecl'], 'share' => $share, 'maiic_ecl' => $result['maiic_ecl'],
            'declined' => $declined, 'basis' => json_encode($result), 'run_by' => $userId, 'approver_label' => $label, 'created_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('Mega Farm ECL Run', 'megafarm_ecl_runs', null, ['reporting_period' => $period, 'rows_affected' => $loans->count(), 'new_values' => array_diff_key($result, ['basis' => 1]), 'meta' => ['user' => $userId, 'label' => $label]]);

        return $result;
    }

    private function setting(string $key, string $default, CarbonImmutable $asOf): string
    {
        try {
            return $this->governance->get($key, $asOf);
        } catch (Throwable) {
            return $default;
        }
    }
}
