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

    /**
     * The portfolios of a period that hold the programme only (every loan on
     * a Mega Farm GL). The MAIIC book's PD, LGD and ECL engines leave them
     * out: the programme is staged and provided here (D30, section 16.8: "the
     * MAIIC book keeps its transition-matrix probabilities"), and its 95
     * percent default rate must not enter MAIIC's own measures.
     *
     * @return list<int>
     */
    public static function programmePortfolios(string $period): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('loan_books', 'product_code')) {
            return [];
        }
        $gls = "'" . implode("','", self::GLS) . "'";

        return DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('loan_portfolio_id')
            ->groupBy('loan_portfolio_id')->havingRaw("SUM(CASE WHEN product_code IN ({$gls}) THEN 0 ELSE 1 END) = 0")
            ->pluck('loan_portfolio_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
    }

    /** Portfolios of a period that mix programme loans with MAIIC's own (the book has not been split). @return list<int> */
    public static function mixedPortfolios(string $period): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('loan_books', 'product_code')) {
            return [];
        }
        $gls = "'" . implode("','", self::GLS) . "'";

        return DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('loan_portfolio_id')
            ->groupBy('loan_portfolio_id')
            ->havingRaw("SUM(CASE WHEN product_code IN ({$gls}) THEN 1 ELSE 0 END) > 0 AND SUM(CASE WHEN product_code IN ({$gls}) THEN 0 ELSE 1 END) > 0")
            ->pluck('loan_portfolio_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
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
        // the run this month's work belongs to (Staging Module Specification v1, section 8.8): the open month-end
        // or bootstrap run, or one of its own when run from a screen
        if (! \App\Support\RunContext::inside($period, 'Mega Farm')) {
            return \App\Support\RunContext::engine($period, 'Mega Farm', $userId, fn () => $this->run($period, $userId, $label));
        }
        // a locked month is never restated (audit H4)
        \App\Support\ReportingPeriodLock::assertOpen($period, 'the Mega Farm programme ECL');
        $asOf = CarbonImmutable::parse($period . '-01')->endOfMonth();
        // the three settings are read from the Governance Centre with no value in
        // code: a missing approved value stops the run (GovernanceSettingMissingException)
        $scope = $this->setting('mega_farms_scope', $asOf);
        $method = $this->setting('megafarm_pd_method', $asOf);
        $ceilingText = $this->setting('megafarm_scalar_ceiling', $asOf);
        if (preg_match('/(\d+(?:\.\d+)?)/', $ceilingText, $m) !== 1) {
            throw new RuntimeException("The governed scalar ceiling '{$ceilingText}' names no number; the Mega Farm run stops.");
        }
        $ceiling = (float) $m[1];
        $loans = $this->loans($period)->get(array_merge(['id', 'contract_id', 'product_group', 'ifrs9stage_post_qualitative', 'carrying_amount', 'commitments', 'facility_utilisation_rate', 'lgd_value', 'collection_lgd', 'pd_prefli'],
            \App\Services\Ecl\EclExposure::hasBasis() ? ['build_basis'] : []));
        if ($loans->isEmpty()) {
            throw new RuntimeException("No Mega Farm loans in the book for {$period}.");
        }
        // MAIIC's share is the one the governed scope states. A scope that states
        // none is declined; it used to fall back to 100 percent (audit, 14 October 2026)
        $share = self::shareFromScope($scope);
        if (str_starts_with($scope, 'Out of scope entirely')) {
            return $this->record($period, $scope, $method, $loans, [], null, 0.0, 0.0, $share ?? 0.0, 'out of scope entirely by the governed setting; disclosed, not provided', [], $userId, $label);
        }
        if ($share === null) {
            return $this->record($period, $scope, $method, $loans, [], null, 0.0, 0.0, 0.0, "the governed scope '{$scope}' states no MAIIC share of the programme; a share is not assumed", [], $userId, $label);
        }
        // a programme loan with no stage is not taken as Stage 1: staging runs first
        $unstaged = $loans->filter(fn ($l) => $l->ifrs9stage_post_qualitative === null || trim((string) $l->ifrs9stage_post_qualitative) === '')->count();
        if ($unstaged > 0) {
            return $this->record($period, $scope, $method, $loans, [], null, 0.0, 0.0, $share, "{$unstaged} programme loans carry no stage for {$period}: run staging first", [], $userId, $label);
        }
        $byStage = [];
        foreach ($loans as $l) {
            $s = (string) $l->ifrs9stage_post_qualitative;
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
                    // the array key comes back as an integer, so the stage is compared as a string:
                    // compared as it was, Stage 3 took the scaled sector PD (0.385 in January 2026) instead of 1
                    $pdByStage[(string) $stage] = (string) $stage === '3' ? 1.0 : min(1.0, $pd * $scalar);
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
        if ($declined === null) {
            // every stage the programme holds needs its PD: Stage 3 is 1; a Stage 1 or 2
            // with no sector PD is declined, not given another stage's PD or nil
            if (isset($byStage['3'])) {
                $pdByStage['3'] = 1.0;
            }
            $missing = array_values(array_diff(array_map('strval', array_keys($byStage)), array_map('strval', array_keys($pdByStage))));
            if ($missing !== []) {
                $declined = 'no PD for programme loans in stage ' . implode(', ', $missing) . ': MAIIC\'s agricultural book holds no PD for that stage in the period';
            }
        }
        if ($declined !== null) {
            return $this->record($period, $scope, $method, $loans, $byStage, null, 0.0, 0.0, $share, $declined, $basis, $userId, $label);
        }

        // the ECL per loan: EAD x PD x LGD; MAIIC's share
        [$lgd, $lgdBasis] = $this->lgd($period);
        if ($lgd === null) {
            return $this->record($period, $scope, $method, $loans, $byStage, $pdByStage, $scalar, 0.0, $share, $lgdBasis, $basis, $userId, $label);
        }
        $basis['lgd'] = $lgd;
        $basis['lgd_basis'] = $lgdBasis;
        $programme = 0.0;
        DB::transaction(function () use ($loans, $pdByStage, $lgd, $share, $method, &$programme) {
            foreach ($loans as $l) {
                $s = (string) $l->ifrs9stage_post_qualitative;
                $pd = $pdByStage[$s];
                // the engine's EAD (EclExposure): a credit balance is owed to the farmer, not an exposure
                // (spec 3.8), so no negative allowance; a loan written off to nil has no exposure at all
                $ead = \App\Services\Ecl\EclExposure::of($l);
                $ecl = $ead * $pd * $lgd;
                $programme += $ecl;
                DB::table('loan_books')->where('id', $l->id)->update(['pd_prefli' => round($pd, 8), 'pd_post_fli' => round($pd, 8), 'lgd_value' => round($lgd, 8), 'ead' => round($ead, 2), 'ecl_value' => round($ecl * $share, 2), 'fli_route' => 'Mega Farm programme (D30)', 'fli_method' => $method]);
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
            // no programme book a year earlier (or no sector PD): the scalar cannot be
            // measured and the method is declined. It used to fall back to a ratio typed
            // from the 2025 statements (48.7 / 51.5), a number no data in the system produced.
            return null;
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

    /**
     * The LGD: the programme's own cohort workout (spec 16.8, step 4: the loss
     * is built from the programme's collection experience), the Stage 3 Mega
     * Farm loans of twelve months earlier followed to the period by
     * LgdEngineService on the programme's portfolio. It used to take the
     * average LGD of MAIIC's own book, or a typed 0.45 when none was held:
     * neither is the programme's experience. With no programme portfolio or no
     * Stage 3 cohort to follow, the run is declined with the reason.
     *
     * @return array{0:?float,1:string} the LGD and its basis (or the reason it is declined)
     */
    private function lgd(string $period): array
    {
        $portfolios = self::programmePortfolios($period);
        if ($portfolios === []) {
            return [null, 'the programme has no portfolio of its own: run ifrs9:segment-portfolios so its LGD can be measured on its own loans'];
        }
        try {
            $m = app(\App\Services\Lgd\LgdEngineService::class)->measure($period, $portfolios, 12);
        } catch (Throwable $e) {
            return [null, 'the programme LGD cannot be measured: ' . $e->getMessage()];
        }
        if ($m['cohort'] === 0 || $m['start_balance'] <= 0) {
            return [null, "no Stage 3 programme loan at {$m['start_period']} to follow: the programme LGD cannot be measured"];
        }

        return [(float) $m['lgd'], sprintf('programme cohort workout %s to %s: %d loans, %s; cure %.2f%%, recovery %.2f%%', $m['start_period'], $period, $m['cohort'], number_format($m['start_balance'], 0), $m['cure_rate'] * 100, $m['recovery_rate'] * 100)];
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

    /**
     * MAIIC's share of the programme as the governed scope states it ("5 percent
     * share on net" gives 0.05), or null when the scope states none.
     */
    public static function shareFromScope(string $scope): ?float
    {
        return preg_match('/(\d+(?:\.\d+)?)\s*percent share/i', $scope, $m) === 1 ? (float) $m[1] / 100 : null;
    }

    /** The governed value in force; with no approved value the run stops (no value in code). */
    private function setting(string $key, CarbonImmutable $asOf): string
    {
        return $this->governance->get($key, $asOf);
    }
}
