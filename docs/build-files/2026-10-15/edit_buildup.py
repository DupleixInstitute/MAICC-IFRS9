p = r'C:\xampp\htdocs\MAICC-IFRS9\app\Services\Reports\EclBuildUpService.php'
s = open(p, encoding='utf-8').read()
rep = [
("""use App\\Services\\Fli\\OverlayService;""", """use App\\Services\\Fli\\OverlayService;
use App\\Services\\MegaFarm\\MegaFarmEclService;"""),
(""" * exactly. It is only shown when the booked ECL is on that basis: each
 * loan's booked ECL must equal EAD x PD over the horizon x LGD recomputed
 * from its saved PD after FLI. If the book was measured some other way (the
 * time-phased engine, or the PD changed after the last ECL run) the build-up
 * is withheld with the reason, never estimated.
 */""", """ * exactly. It is only shown when the booked ECL is on that basis: each
 * loan's booked ECL must equal EAD x PD over the horizon x LGD recomputed
 * from its saved PD after FLI. If the book was measured some other way (the
 * PD changed after the last ECL run) the build-up is withheld with the
 * reason, never estimated.
 *
 * The time-phased engine (TimePhasedEclService) leaves ecl_value as this
 * measure and writes the discounted allowance beside it, so a discounted
 * month still builds up here; discounted() reconciles the engine measure to
 * the discounted allowance (the projection effect and the discounting
 * effect). The Mega Farm programme is measured by its own engine
 * (MegaFarmEclService, spec v4 section 16, D30) on its own PD and LGD, not
 * on the post-FLI stage PD, so its loans are kept out of the build-up and
 * shown as their own line; until 14 October 2026 they were counted in it and
 * no month could be recorded.
 */"""),
("""'ecl_value', 'ecl_value_discounted',
        'ecl_discount_status'];""", """'ecl_value', 'ecl_value_discounted',
        'ecl_discount_status', 'ecl_discounting_effect'];"""),
("""    public function buildUp(Collection $loans, string $period): array
    {
        $n = $loans->count();
        $final = 0.0; $pre = 0.0; $calculated = 0; $tiePost = 0; $tiePre = 0; $timePhased = 0; $ead = 0.0;
        foreach ($loans as $l) {""", """    public function buildUp(Collection $loans, string $period): array
    {
        [$programmeLoans, $engineLoans] = $this->split($loans, $period);
        $n = $loans->count();
        $final = 0.0; $pre = 0.0; $calculated = 0; $tiePost = 0; $tiePre = 0; $ead = 0.0;
        $programme = $programmeLoans->isEmpty() ? null : ['loans' => $programmeLoans->count(), 'ecl' => round((float) $programmeLoans->sum(fn ($l) => (float) ($l->ecl_value ?? 0)), 2),
            'ead' => round((float) $programmeLoans->sum(fn ($l) => self::lineage($l)['ead']), 2),
            'note' => 'Mega Farm programme: measured by its own engine on its own PD and LGD (D30), outside the forward-looking build-up'];
        $ead += (float) ($programme['ead'] ?? 0);
        foreach ($engineLoans as $l) {"""),
("""            $tiePre += $x['ties_pre'] ? 1 : 0;
            $timePhased += ($l->ecl_discount_status ?? null) === 'CALCULATED_TIME_PHASED' ? 1 : 0;
        }

        $out = ['period' => $period, 'loans' => $n, 'calculated' => $calculated, 'ead' => round($ead, 2), 'final' => round($final, 2),
            'available' => false, 'basis' => null, 'reason' => null, 'pre_fli' => null, 'fli_total' => null, 'fli_model' => null,
            'overlays' => null, 'overlay_lines' => [], 'lineage' => $this->runUnder($loans)];""", """            $tiePre += $x['ties_pre'] ? 1 : 0;
        }

        // final is every loan's ECL as booked; the build-up adds up to the engine's part of it (engine_final)
        $out = ['period' => $period, 'loans' => $n, 'calculated' => $calculated + ($programme['loans'] ?? 0), 'ead' => round($ead, 2), 'final' => round($final + ($programme['ecl'] ?? 0), 2),
            'engine_loans' => $engineLoans->count(), 'engine_final' => round($final, 2), 'programme' => $programme,
            'available' => false, 'basis' => null, 'reason' => null, 'pre_fli' => null, 'fli_total' => null, 'fli_model' => null,
            'overlays' => null, 'overlay_lines' => [], 'lineage' => $this->runUnder($engineLoans)];"""),
("""        if ($calculated === 0) {
            $out['reason'] = 'No ECL has been calculated for these loans yet.';""", """        if ($calculated === 0) {
            $out['reason'] = $programme !== null && $engineLoans->isEmpty() ? 'These are Mega Farm programme loans, measured by the programme engine outside the forward-looking build-up (D30).' : 'No ECL has been calculated for these loans yet.';"""),
("""            $lines = $this->overlayLines($loans, $period);""", """            $lines = $this->overlayLines($engineLoans, $period);"""),
("""        $out['reason'] = $timePhased > 0
            ? 'The saved ECL was measured by the time-phased engine, which projects each loan month by month, so it cannot be split into before and after FLI from the saved PDs.'
            : number_format($calculated - $tiePost) . ' of ' . number_format($calculated) . ' loans no longer match their saved PD, LGD and EAD (the PD or the book changed after the last ECL run). Run the ECL again to see the build-up.';

        return $out;
    }""", """        $out['reason'] = number_format($calculated - $tiePost) . ' of ' . number_format($calculated) . ' loans no longer match their saved PD, LGD and EAD (the PD or the book changed after the last ECL run). Run the ECL again to see the build-up.';

        return $out;
    }

    /**
     * The loans the MAIIC engine measures and the Mega Farm programme's, by
     * the portfolios the engine leaves out (MegaFarmEclService::programmePortfolios).
     *
     * @return array{0:Collection,1:Collection} programme loans, engine loans
     */
    public function split(Collection $loans, string $period): array
    {
        $programme = MegaFarmEclService::programmePortfolios($period);
        if ($programme === []) {
            return [collect(), $loans];
        }
        [$p, $e] = $loans->partition(fn ($l) => in_array((int) ($l->loan_portfolio_id ?? 0), $programme, true));

        return [$p->values(), $e->values()];
    }

    /**
     * The discounted allowance of the engine's loans and how it reconciles to
     * the engine measure: the engine ECL of the loans discounted, plus the
     * projection effect (the time-phased engine runs the EAD down the
     * schedule month by month over the schedule's horizon), equals their
     * time-phased undiscounted shortfall; plus the discounting effect equals
     * the discounted allowance. The loans that could not be discounted are
     * counted with their engine ECL, never given a discounted figure.
     *
     * @return array{loans:int,discounted_loans:int,unresolved_loans:int,unresolved_ecl:float,engine_ecl:float,projection_effect:float,undiscounted:float,discounting_effect:float,discounted:float,complete:bool}
     */
    public static function discounted(Collection $engineLoans): array
    {
        $n = 0; $un = 0; $unEcl = 0.0; $engine = 0.0; $tp = 0.0; $disc = 0.0;
        foreach ($engineLoans as $l) {
            if ($l->ecl_value === null) {
                continue;
            }
            if (($l->ecl_discount_status ?? null) === 'CALCULATED_TIME_PHASED' && $l->ecl_value_discounted !== null) {
                $n++;
                $engine += (float) $l->ecl_value;
                $disc += (float) $l->ecl_value_discounted;
                $tp += (float) $l->ecl_value_discounted + (float) ($l->ecl_discounting_effect ?? 0);
            } else {
                $un++;
                $unEcl += (float) $l->ecl_value;
            }
        }

        return ['loans' => $n + $un, 'discounted_loans' => $n, 'unresolved_loans' => $un, 'unresolved_ecl' => round($unEcl, 2), 'engine_ecl' => round($engine, 2),
            'projection_effect' => round($tp - $engine, 2), 'undiscounted' => round($tp, 2), 'discounting_effect' => round($disc - $tp, 2), 'discounted' => round($disc, 2),
            'complete' => $un === 0 && $n > 0];
    }"""),
]
for a, b in rep:
    assert s.count(a) == 1, a[:70]
    s = s.replace(a, b)
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
