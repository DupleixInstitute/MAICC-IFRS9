import io, os
os.chdir(r'C:\xampp\htdocs\MAICC-IFRS9')

def sub(path, pairs):
    s = io.open(path, encoding='utf-8').read()
    for old, new in pairs:
        assert old in s, (path, old[:70])
        s = s.replace(old, new, 1)
    io.open(path, 'w', encoding='utf-8', newline='\n').write(s)

# C9: the bootstrap no longer approves a fit; the overlay is zero until two people approve one
sub('app/Console/Commands/Bootstrap.php', [
("""        // 6b the route: the best applied fit on a book-level proxy, approved under the bootstrap label, applied once per scenario
        try {
            $ym = str_replace('-', '', $to);
            $best = DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')->where('f.reporting_period', $ym)->where('f.verdict', 'applied')
                ->whereIn('r.proxy_code', ['NPL_RATIO', 'STAGE3_SHARE', 'DEFAULT_RATE_12M'])->orderByDesc('f.r_squared')->orderByDesc('f.n_obs')->first(['f.id', 'f.approval_status', 'r.statistic_code', 'r.proxy_code']);
            $routeSvc = app(\\App\\Services\\Fli\\FliRouteService::class);
            if ($best !== null && $routeSvc->approvedFit($to) === null) {
                $routeSvc->proposeFit((int) $best->id, $user, 'the best applied fit on a book-level proxy, for the bootstrap');
                $routeSvc->approveFit((int) $best->id, null, LoanBookBuildService::BOOTSTRAP_LABEL);
            }
            $fr = $routeSvc->apply($to, $user);""",
"""        // 6b the route, applied once per scenario. Spec 6.10.1 step 6: on a clean
        // install no fit is approved, so the overlay is zero and the post-FLI PD
        // is marked "no adjustment: bootstrap"; the regression is used once two
        // people approve a fit on the FLI Adjustments screen (audit C9: the
        // bootstrap had proposed and approved the best fit itself).
        try {
            $routeSvc = app(\\App\\Services\\Fli\\FliRouteService::class);
            $fr = $routeSvc->apply($to, $user);"""),
# M8: no fee review under the bootstrap label; the rulebook classifies once MAIIC approves it
("""        $sweep = app(FeeRuleMatcher::class)->sweepPending();
        $classified = 0;
        foreach (ContractFee::where('classification_status', 'PENDING')->whereNotNull('suggested_rule_id')->get() as $fee) {
            $fee->update(['integral' => (bool) $fee->suggested_integral, 'classification_status' => 'REVIEWED',
                'classification_reason' => LoanBookBuildService::BOOTSTRAP_LABEL . ' [applied by rule: ' . ($fee->suggestedRule?->name ?? 'unknown') . ']',
                'classified_by' => $user, 'classified_at' => now(), 'reviewed_by' => $user, 'reviewed_at' => now()]);
            EirFeeClassificationEvent::create(['contract_fee_id' => $fee->id, 'action' => 'REVIEWED', 'integral' => $fee->integral, 'reason' => $fee->classification_reason, 'performed_by' => $user]);
            $classified++;
        }
        $this->note('5e fee rulebook', "{$sweep['examined']} examined, {$sweep['matched']} matched a rule, {$classified} classified and reviewed under the bootstrap label, " . ContractFee::where('classification_status', 'PENDING')->count() . ' left PENDING');""",
"""        // The rulebook suggests; it does not review. Fee Classification is a
        // Governance Centre screen (spec 11.3) and the bootstrap gives no
        // approval there (spec 6.10; audit M8): a suggestion stays a suggestion
        // until a person reviews it, and the rulebook itself suggests nothing
        // until MAIIC approves it.
        $sweep = app(FeeRuleMatcher::class)->sweepPending();
        $this->note('5e fee rulebook', "{$sweep['examined']} examined, {$sweep['matched']} matched an approved rule (suggested, not reviewed: the review is a person's), " . ContractFee::where('classification_status', 'PENDING')->count() . ' PENDING');"""),
# the last ECL, discounted through the time-phased engine where a locked EIR exists
("""            Artisan::call('ifrs9:recalculate-ecl', ['period' => $to, '--level' => 'portfolio', '--portfolio' => $portfolio, '--pd' => DB::table('loan_books')->where('reporting_period', $to)->whereNotNull('pd_post_fli')->exists() ? 'pd_post_fli' : 'pd_prefli']);
            $this->note('6.7 ECL', $to . ': ' . trim(preg_replace('/\\s+/', ' ', substr(Artisan::output(), 0, 240))));""",
"""            Artisan::call('ifrs9:recalculate-ecl', ['period' => $to, '--level' => 'portfolio', '--portfolio' => $portfolio, '--discounting' => 'discounted',
                '--pd' => DB::table('loan_books')->where('reporting_period', $to)->whereNotNull('pd_post_fli')->exists() ? 'pd_post_fli' : 'pd_prefli']);
            $disc = DB::table('loan_books')->where('reporting_period', $to)->selectRaw('sum(ecl_value) ecl, sum(ecl_value_discounted) disc, sum(ecl_value_discounted is not null) n')->first();
            $this->note('6.7 ECL', $to . ': ' . trim(preg_replace('/\\s+/', ' ', substr(Artisan::output(), 0, 160))) . ' | undiscounted ' . number_format((float) $disc->ecl, 0) . "; discounted through the time-phased engine on {$disc->n} loans with a locked EIR: " . number_format((float) $disc->disc, 0));"""),
])

# the label bypass of maker-checker is the bootstrap's constant only
sub('app/Services/Fli/FliRouteService.php', [
("        if ($label === null && $approverId !== null && (int) $fit->proposed_by === $approverId) {",
 "        if ($label !== \\App\\Services\\Ebanker\\LoanBookBuildService::BOOTSTRAP_LABEL && $approverId !== null && (int) $fit->proposed_by === $approverId) {"),
])
sub('app/Services/Scenario/ScenarioSetService.php', [
("        if ($label === null && $approverId !== null && (int) $set->proposed_by === $approverId) {",
 "        if ($label !== \\App\\Services\\Ebanker\\LoanBookBuildService::BOOTSTRAP_LABEL && $approverId !== null && (int) $set->proposed_by === $approverId) {"),
# H3 / M9: the sensitivity is the ECL per scenario from the chain's per-scenario PDs, on the ECL's own stage basis and EAD
("""        $loans = DB::table('loan_books')->where('reporting_period', $set->reporting_period)
            ->selectRaw('ifrs9stage_post_qualitative, coalesce(pd_prefli, pd_value, `12m_pd`) as pd_prefli, lgd_value, ead, carrying_amount')
            ->whereRaw('coalesce(pd_prefli, pd_value, `12m_pd`) is not null')->get();
        $eclUnder = function (float $mult) use ($loans): float {
            $ecl = 0.0;
            foreach ($loans as $l) {
                $ead = (float) ($l->ead ?? $l->carrying_amount);
                $lgd = $l->lgd_value !== null ? (float) $l->lgd_value : 0.45;
                $pd = $l->ifrs9stage_post_qualitative === '3' ? 1.0 : max(0.0, min(1.0, (float) $l->pd_prefli * $mult));
                $ecl += $ead * $pd * $lgd;
            }

            return round($ecl, 2);
        };
        $perScenario = [];
        foreach ($scenarios as $s) {
            $perScenario[$s->name] = ['weight' => (float) $s->weight, 'pd_multiplier' => (float) ($s->pd_multiplier ?? 1), 'ecl' => $eclUnder((float) ($s->pd_multiplier ?? 1))];
        }""",
"""        // The ECL per scenario, from the per-scenario PD the route wrote on the
        // loan (fli_by_scenario) when the chain has run for this set, else the
        // typed multiplier on the pre-FLI PD; on the ECL engine's own basis:
        // the stage the staging engine measured, lifetime PD for Stage 2 over
        // the remaining months, Stage 3 at 1, EAD = carrying + commitments x
        // utilisation. One weighting, the set's (spec 15.5; audit H3, M9).
        $loans = DB::table('loan_books')->where('reporting_period', $set->reporting_period)
            ->selectRaw('coalesce(ifrs9stage_post_qualitative, calculated_ifrs9_stage, ifrs9stage_pre_qualitative) as stage, coalesce(pd_prefli, pd_value, `12m_pd`) as pd_prefli, lgd_value, remaining_tenor, carrying_amount, commitments, facility_utilisation_rate, fli_by_scenario, fli_set_id')
            ->whereRaw('coalesce(pd_prefli, pd_value, `12m_pd`) is not null')->get();
        $fromChain = $loans->isNotEmpty() && $loans->every(fn ($l) => $l->fli_by_scenario !== null && (int) $l->fli_set_id === $setId);
        $stagePd = function (object $l, float $pd12): float {
            $pd12 = max(0.0, min(1.0, $pd12));
            $months = $l->remaining_tenor === null || (float) $l->remaining_tenor < 1 ? 12.0 : (float) $l->remaining_tenor;
            return match ((string) $l->stage) {
                '3' => 1.0,
                '2' => 1 - pow(1 - $pd12, $months / 12),
                default => 1 - pow(1 - $pd12, min(12.0, $months) / 12),
            };
        };
        $eclUnder = function (string $name, float $mult) use ($loans, $fromChain, $stagePd): float {
            $ecl = 0.0;
            foreach ($loans as $l) {
                $ead = (float) ($l->carrying_amount ?? 0) + (float) ($l->commitments ?? 0) * (float) ($l->facility_utilisation_rate ?? 1);
                $lgd = $l->lgd_value !== null ? (float) $l->lgd_value : 0.45;
                $by = $fromChain ? (json_decode((string) $l->fli_by_scenario, true) ?: []) : [];
                $pd12 = isset($by[$name]['pd']) ? (float) $by[$name]['pd'] : (float) $l->pd_prefli * $mult;
                $ecl += $ead * $stagePd($l, $pd12) * $lgd;
            }

            return round($ecl, 2);
        };
        $perScenario = [];
        foreach ($scenarios as $s) {
            $perScenario[$s->name] = ['weight' => (float) $s->weight, 'pd_multiplier' => (float) ($s->pd_multiplier ?? 1), 'ecl' => $eclUnder($s->name, (float) ($s->pd_multiplier ?? 1))];
        }"""),
("""            'ten_points_to_downside' => $shift($down), 'ten_points_to_upside' => $shift($up), 'basis' => 'pre-FLI PD x the scenario multiplier, Stage 3 at 100 percent, LGD as held (0.45 where none); the chain per scenario replaces the multiplier once a fit per scenario is approved'];""",
"""            'ten_points_to_downside' => $shift($down), 'ten_points_to_upside' => $shift($up),
            'basis' => $fromChain ? 'the per-scenario PD the route wrote on each loan, on the ECL basis by stage (lifetime for Stage 2, 1 for Stage 3), LGD as held; weighted once by the set' : 'pre-FLI PD x the typed scenario multiplier on the ECL basis by stage, LGD as held (0.45 where none); the chain per scenario replaces the multiplier once the route has run for this set'];"""),
])

# H3: the time-phased engine takes its scenarios from the governed set written on the loan, not a second set
sub('app/Services/Ecl/TimePhasedEclService.php', [
("""        $scenarios=DB::table('ecl_scenario_assumptions')->where('status','APPROVED')
            ->whereDate('effective_from','<=',$asOf)->where(fn($q)=>$q->whereNull('effective_to')->orWhereDate('effective_to','>=',$asOf))->get();""",
"""        // One weighting (spec 15.5; audit H3): the governed set's scenarios and
        // weights, with the per-scenario PD the route wrote on each loan, when
        // the chain has run; the legacy ecl_scenario_assumptions table only when
        // no loan carries a per-scenario PD.
        $legacyScenarios=DB::table('ecl_scenario_assumptions')->where('status','APPROVED')
            ->whereDate('effective_from','<=',$asOf)->where(fn($q)=>$q->whereNull('effective_to')->orWhereDate('effective_to','>=',$asOf))->get();"""),
("""        $basePd=(float)($loan->pd_post_fli??$loan->pd_prefli??0);if($stage===3)$basePd=1.0;""",
"""        $basePd=(float)($loan->pd_post_fli??$loan->pd_prefli??0);if($stage===3)$basePd=1.0;
        $scenarios=$this->scenariosFor($loan,$basePd,$legacyScenarios);"""),
("""    /**
     * Stage 3 under IFRS 9 5.5.3 and B5.5.33:""",
"""    /**
     * The scenarios a loan is measured under: the governed set written on the
     * loan by the route (each scenario's PD becomes a multiplier on the base,
     * so the loop below reproduces it exactly; LGD and EAD unshocked, weights
     * from the set), else the legacy assumptions table.
     */
    private function scenariosFor(object $loan,float $basePd,Collection $legacy): Collection
    {
        $by=isset($loan->fli_by_scenario)&&$loan->fli_by_scenario!==null?(json_decode((string)$loan->fli_by_scenario,true)?:[]):[];
        if($by===[]) return $legacy;
        $out=collect();$sum=0.0;
        foreach($by as $name=>$s){$sum+=(float)($s['weight']??0);}
        if($sum<=0) return $legacy;
        foreach($by as $name=>$s){
            $pd=(float)($s['pd']??$basePd);
            $out->push((object)['scenario_code'=>$name,'name'=>$name,'weight'=>(float)$s['weight']/$sum,'pd_multiplier'=>$basePd>0?$pd/$basePd:1.0,'lgd_multiplier'=>1.0,'ead_multiplier'=>1.0]);
        }
        return $out;
    }

    /**
     * Stage 3 under IFRS 9 5.5.3 and B5.5.33:"""),
])
print('ok')
