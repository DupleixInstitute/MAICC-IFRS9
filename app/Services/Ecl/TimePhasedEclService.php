<?php
namespace App\Services\Ecl;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** IFRS 9 time-phased expected cash-shortfall engine (monthly discretisation). */
class TimePhasedEclService
{
    /** The term-structure sources this engine writes, so a rerun can clear its own rows. */
    public const TERM_SOURCES=['TWELVE_MONTH_PD_CONSTANT_HAZARD','DEFAULTED_RESOLUTION_HORIZON','DEFAULTED_RECOVERY_SCHEDULE'];
    private bool $onSet=false;
    private bool $byScenarioColumn=false;
    private string $pdField='pd_post_fli';
    /** @var array<int,Collection> the scenarios of each governed set, by set id */
    private array $setScenarios=[];

    public function run(Collection $loans, string $reportingPeriod, string $lgdField='collection_lgd', ?int $userId=null, string $pdField='pd_post_fli'): array
    {
        $this->pdField=$pdField==='pd_prefli'?'pd_prefli':'pd_post_fli';
        $asOf=CarbonImmutable::parse(substr($reportingPeriod,0,7).'-01')->endOfMonth();
        // One weighting (spec 15.5; audit H3): the governed set's scenarios and
        // weights, with the per-scenario PD the route wrote on each loan, when
        // the chain has run; the legacy ecl_scenario_assumptions table only when
        // no loan carries a per-scenario PD.
        $legacyScenarios=DB::table('ecl_scenario_assumptions')->where('status','APPROVED')
            ->whereDate('effective_from','<=',$asOf)->where(fn($q)=>$q->whereNull('effective_to')->orWhereDate('effective_to','>=',$asOf))->get();
        // A run is on the governed set when any of its loans carries the set or a
        // per-scenario PD from the route. Then every loan is measured under that
        // set and the legacy table is never mixed in: its LGD and EAD multipliers
        // (0.9 and 0.98 up, 1.2 and 1.05 down) reached the Stage 3 loans, which the
        // route leaves without a per-scenario PD, and lifted their discounted
        // allowance 2.84 percent above the undiscounted one (UAT, 2026-09).
        $this->onSet=$loans->contains(fn($l)=>(isset($l->fli_by_scenario)&&$l->fli_by_scenario!==null&&$l->fli_by_scenario!==''&&$l->fli_by_scenario!=='[]')||!empty($l->fli_set_id??null));
        $this->setScenarios=[];
        // the run is refused whole, with its reason: no loan is given a proxy horizon or rate instead
        if($this->pdField!=='pd_prefli'&&$legacyScenarios->isEmpty()&&!$this->onSet) throw new EclRunRefused('Discounted ECL not calculated for '.$asOf->format('Y-m').': no approved scenario set covers the reporting period, neither a governed set written on the loans by the forward-looking route nor approved legacy assumptions. Apply the route (or approve the assumptions) first; no proxy is applied.');
        if($this->pdField!=='pd_prefli'&&!$this->onSet&&abs((float)$legacyScenarios->sum('weight')-1)>1e-8) throw new EclRunRefused('Discounted ECL not calculated for '.$asOf->format('Y-m').': the approved ECL scenario weights must sum to 1.');
        $scenarios=$this->onSet?collect():$legacyScenarios;
        $this->byScenarioColumn=\Illuminate\Support\Facades\Schema::hasColumn('loan_books','ecl_by_scenario');

        $runId=(string)Str::uuid();
        DB::table('ecl_projection_runs')->insert(['run_id'=>$runId,'reporting_period'=>$asOf->format('Y-m'),
            'methodology_version'=>'TIME_PHASED_V1','status'=>'PROCESSING','created_by'=>$userId,'created_at'=>now(),'updated_at'=>now()]);
        $exceptions=[];$processed=0;$totalUndisc=0.0;$totalDisc=0.0;

        foreach($loans as $loan){
            try{
                $result=$this->projectLoan($loan,$asOf,$scenarios,$runId,$lgdField);
                // ecl_value stays the ECL engine's own measure (EAD x stage PD x LGD at the
                // reporting date), which the FLI build-up and the month record reconcile to;
                // this engine writes the discounted allowance beside it and the effect of
                // discounting its own projection. Overwriting ecl_value with the projected
                // undiscounted shortfall broke that reconciliation, so the month-end could
                // not run discounted at all (results review of 13 October 2026).
                DB::table('loan_books')->where('id',$loan->id)->update(($this->byScenarioColumn?['ecl_by_scenario'=>json_encode($result['by_scenario'])]:[])+[
                    'ecl_value_discounted'=>round($result['discounted'],2),
                    'ecl_discounting_effect'=>round($result['undiscounted']-$result['discounted'],2),
                    'ecl_discount_rate'=>$result['rate'],'ecl_discount_rate_source'=>$result['rate_source'],
                    'ecl_discount_status'=>'CALCULATED_TIME_PHASED','ecl_discount_horizon_years'=>$result['horizon'],
                    'ecl_calculation_run_id'=>$runId,'ecl_calculated_at'=>now(),
                ]);
                $processed++;$totalUndisc+=$result['undiscounted'];$totalDisc+=$result['discounted'];
            }catch(\Throwable $e){
                $id=(string)($loan->contract_id??'row-'.$loan->id);$exceptions[$id]=$e->getMessage();
                // a loan refused part-way (the invariant below) leaves none of its projected rows on the run,
                // and no term structure from this run or an earlier one for the month
                DB::table('ecl_cashflow_projections')->where('run_id',$runId)->where('contract_id',$id)->delete();
                $this->clearTermStructure($id,$asOf);
                DB::table('loan_books')->where('id',$loan->id)->update(($this->byScenarioColumn?['ecl_by_scenario'=>null]:[])+['ecl_value_discounted'=>null,
                    'ecl_discounting_effect'=>null,'ecl_discount_rate'=>null,'ecl_discount_rate_source'=>null,
                    'ecl_discount_status'=>'TIME_PHASED_UNRESOLVED','ecl_calculation_run_id'=>$runId,'ecl_calculated_at'=>now()]);
            }
        }
        DB::table('ecl_projection_runs')->where('run_id',$runId)->update(['status'=>'COMPLETED','contracts_processed'=>$processed,
            'contracts_unresolved'=>count($exceptions),'undiscounted_ecl'=>round($totalUndisc,2),'discounted_ecl'=>round($totalDisc,2),
            'exceptions'=>json_encode($exceptions),'input_snapshot'=>json_encode(['scenario_codes'=>$this->onSet?'the governed set on each loan':$scenarios->pluck('scenario_code')->all(),'lgd_field'=>$lgdField,'pd_field'=>$this->pdField]),
            'completed_at'=>now(),'updated_at'=>now()]);
        return ['run_id'=>$runId,'calculated'=>$processed,'unresolved'=>count($exceptions),'undiscounted'=>$totalUndisc,'discounted'=>$totalDisc,'exceptions'=>$exceptions];
    }

    private function projectLoan(object $loan,CarbonImmutable $asOf,Collection $scenarios,string $runId,string $lgdField): array
    {
        $id=trim((string)($loan->contract_id??''));if($id==='')throw new RuntimeException('Contract ID is missing.');
        // the term structure an earlier run of the month wrote for this loan goes first, so a loan
        // whose horizon shortened, or that no longer has an exposure, keeps no row from before
        $this->clearTermStructure($id,$asOf);
        // the engine's EAD (EclExposure): a nil or credit balance is no exposure (spec 3.8), and a loan
        // written off to nil has none either, its undrawn amount included. The allowance is then nil,
        // measured, not unresolved: no discount rate enters a nil loss
        $ead=EclExposure::of($loan);
        if($ead<=0)return ['undiscounted'=>0.0,'discounted'=>0.0,'rate'=>null,'rate_source'=>EclExposure::closedByWriteOff($loan)?'NO_EXPOSURE_WRITTEN_OFF':'NO_EXPOSURE','horizon'=>0.0,'by_scenario'=>[]];
        // the stage the ECL engine measures: post qualitative first (ExpectedCreditLossController $stageExpr);
        // reading the calculated stage first measured a qualitatively moved loan on another horizon
        $stage=(int)($loan->ifrs9stage_post_qualitative??$loan->calculated_ifrs9_stage??$loan->ifrs9stage_pre_qualitative??0);
        if(!in_array($stage,[1,2,3],true))throw new RuntimeException('IFRS 9 stage is unavailable.');
        $eir=DB::table('contract_eir')->where('contract_id',$id)->whereNotNull('locked_at')->whereNotNull('eir_effective_annual')->first();
        // A defaulted loan with no approved recovery plan is measured at the
        // reporting date (EAD x LGD, nothing dated later), so no discount rate
        // enters it and a missing EIR does not leave it unresolved; any other
        // loan needs its locked EIR, and without one it is named, never given a rate.
        $stage3AtDate=$stage===3&&!DB::table('ecl_recovery_cashflows')->where('contract_id',$id)->where('reporting_period',$asOf->format('Y-m'))
            ->where('status','APPROVED')->whereDate('recovery_date','>',$asOf)->exists();
        if(!$eir&&!$stage3AtDate)throw new RuntimeException('A locked original EIR is unavailable.');
        $rate=$eir?(float)$eir->eir_effective_annual:0.0;if($eir&&$rate<=-1)throw new RuntimeException('Locked EIR is unusable.');
        // a negative rate discounts with a factor above one, so the discounted loss would exceed the undiscounted
        if($eir&&(float)$eir->eir_effective_annual<0)throw new RuntimeException('The locked EIR is negative ('.$eir->eir_effective_annual.'); a discount factor above one is not applied.');
        // A floating-rate loan is discounted at its current EIR (IFRS 9 B5.5.44): the EIR in force at the
        // reporting date as the revenue engine re-estimates it from the rate charged (decision D38,
        // EirRevenueService::currentFloatingEir). Where it cannot be determined the loan is named and left
        // unresolved; the original EIR is never used in its place (it was, labelled EIR_ORIGINAL_FLOATING_PROXY,
        // until 14 October 2026). A loss measured at the reporting date takes no discount rate.
        $floating=$eir&&strtoupper((string)$eir->rate_type)==='FLOATING';
        if($floating&&!$stage3AtDate){
            $current=$this->currentFloatingEir($id,$loan,$asOf);
            if($current===null)throw new RuntimeException('Floating-rate loan: its current EIR at '.$asOf->toDateString().' cannot be determined (no loan-book rate charged for '.$asOf->format('Y-m').', or no rate the locked schedule was built at), and the original EIR is not used in its place (IFRS 9 B5.5.44).');
            if($current<=0)throw new RuntimeException('The current EIR of the floating-rate loan is not positive ('.$current.').');
            $rate=$current;
        }
        // the PD the run measures, as the engine reads it: pd_post_fli, or pd_prefli on a run before FLI (never the one for the other)
        // a missing PD or LGD is refused with the loan named, never read as nil
        $pdRaw=$this->pdField==='pd_prefli'?($loan->pd_prefli??null):($loan->pd_post_fli??null);
        if($stage!==3&&$pdRaw===null)throw new RuntimeException("The PD ({$this->pdField}) is unavailable.");
        $basePd=$stage===3?1.0:(float)$pdRaw;
        $scenarios=$this->scenariosFor($loan,$basePd,$scenarios,$stage); // the legacy assumptions arrive as $scenarios (only off the governed set)
        if($basePd<0||$basePd>1)throw new RuntimeException('PD must be between 0 and 1.');
        $lgdParts=$lgdField==='both'?[$loan->customer_lgd??null,$loan->collection_lgd??null]:[$loan->{$lgdField}??null];
        if(in_array(null,$lgdParts,true))throw new RuntimeException("The LGD ({$lgdField}) is unavailable.");
        $baseLgd=array_product(array_map('floatval',$lgdParts));
        $recoveries=DB::table('ecl_recovery_cashflows')->where('contract_id',$id)->where('reporting_period',$asOf->format('Y-m'))
            ->where('status','APPROVED')->whereDate('recovery_date','>',$asOf)->orderBy('recovery_date')->get();
        if($stage===3 && $recoveries->isNotEmpty()) $baseLgd=max(0,min(1,1-(float)$recoveries->sum('expected_recovery')/$ead));
        if($baseLgd<0||$baseLgd>1)throw new RuntimeException('LGD must be between 0 and 1.');
        $months=$this->projectionMonths($loan,$id,$asOf,$stage,$recoveries);
        $principalByMonth=DB::table('contract_cashflow_schedule')->where('contract_id',$id)->where('schedule_version',1)
            ->whereDate('due_date','>',$asOf)->selectRaw("SUBSTR(due_date,1,7) ym, SUM(principal_due) principal")
            ->groupByRaw("SUBSTR(due_date,1,7)")->pluck('principal','ym');
        $rateSource=!$eir?'NONE_LOSS_AT_REPORTING_DATE':($floating?($stage3AtDate?'NONE_LOSS_AT_REPORTING_DATE':'EIR_CURRENT_FLOATING'):'EIR_ORIGINAL');
        $recoveryShares=$stage===3?$this->recoveryShares($recoveries,$asOf,$months):[];
        $pdSource=$stage===3
            ?($recoveryShares===[]?'DEFAULTED_RESOLUTION_HORIZON':'DEFAULTED_RECOVERY_SCHEDULE')
            :'TWELVE_MONTH_PD_CONSTANT_HAZARD';
        $weightedUndisc=0.0;$weightedDisc=0.0;$maxExponent=0.0;$byScenario=[];

        foreach($scenarios as $scenario){
            $u0=$weightedUndisc;$d0=$weightedDisc;$w=(float)$scenario->weight;
            if($stage===3){
                // Default has occurred: the loss is measured at the reporting
                // date, not projected along a schedule nobody is paying
                // (system audit of 9 October 2026, finding C3).
                [$undisc,$disc,$exp]=$this->stageThree($runId,$id,$asOf,$ead,$baseLgd,$recoveries,$rate,$scenario,$rateSource,$pdSource,$lgdField);
                $weightedUndisc+=$undisc*(float)$scenario->weight;$weightedDisc+=$disc*(float)$scenario->weight;$maxExponent=max($maxExponent,$exp);
                $byScenario[$scenario->scenario_code]=['weight'=>$w,'undiscounted'=>$undisc,'discounted'=>$disc];
                continue;
            }
            $scenarioPd=min(1,max(0,$basePd*(float)$scenario->pd_multiplier));
            $lgd=min(1,max(0,$baseLgd*(float)$scenario->lgd_multiplier));
            $scenarioEad=$ead*(float)$scenario->ead_multiplier;
            // The tape carries a twelve-month PD, so the hazard it implies is
            // anchored to twelve months and then run for as long as the
            // exposure lasts. Fitting it to the horizon instead — 1/$months —
            // back-solves a hazard whose cumulative default equals the
            // twelve-month figure over any length of time, so a sixty-month
            // Stage 2 lifetime carried exactly the default risk of a Stage 1
            // year and, once EAD amortisation was allowed for, less loss than
            // it. A shorter horizon than a year now correctly carries less.
            $conditional=$stage===3?1.0:($scenarioPd>=1?1.0:1-pow(1-$scenarioPd,1/12));
            $survival=1.0;$cumulative=0.0;$opening=$scenarioEad;
            for($m=1;$m<=$months;$m++){
                $date=$asOf->addMonthsNoOverflow($m)->endOfMonth();
                // Default has already occurred in Stage 3, so the exposure
                // resolves against the reviewed recovery plan: each month
                // carries the share of it the plan expects to settle then,
                // and the shortfall is discounted from those dates rather
                // than all of it from the last one. With no approved plan
                // there is only the resolution horizon to place it at.
                $marginal=$stage===3
                    ?($recoveryShares===[]?($m===$months?1.0:0.0):($recoveryShares[$m]??0.0))
                    :$survival*$conditional;
                $cumulative=min(1,$cumulative+$marginal);
                // A defaulted borrower is not paying the contractual schedule,
                // so the exposure at risk does not amortise with it. Letting it
                // amortise wrote the loss off against instalments that will
                // never arrive: 100,000 at 0.6 LGD over a 24-month schedule
                // reported 2,500 rather than 60,000.
                $scheduled=$stage===3?0.0:min($opening,(float)($principalByMonth[$date->format('Y-m')]??0)*(float)$scenario->ead_multiplier);
                $shortfall=$opening*$marginal*$lgd;$exponent=$asOf->diffInDays($date)/365;
                $factor=1/pow(1+$rate,$exponent);$discounted=$shortfall*$factor;$weighted=$discounted*(float)$scenario->weight;
                DB::table('ecl_cashflow_projections')->insert(['run_id'=>$runId,'contract_id'=>$id,'reporting_period'=>$asOf->format('Y-m'),
                    'ifrs9_stage'=>$stage,'scenario_code'=>$scenario->scenario_code,'scenario_weight'=>$scenario->weight,'period_index'=>$m,
                    'projection_date'=>$date,'opening_ead'=>round($opening,2),'scheduled_principal'=>round($scheduled,2),'closing_ead'=>round(max(0,$opening-$scheduled),2),
                    'conditional_pd'=>$conditional,'survival_open'=>$survival,'marginal_pd'=>$marginal,'cumulative_pd'=>$cumulative,'lgd'=>$lgd,
                    'undiscounted_shortfall'=>round($shortfall,2),'discount_rate'=>$rate,'discount_exponent'=>$exponent,'discount_factor'=>$factor,
                    'discounted_shortfall'=>round($discounted,2),'weighted_discounted_shortfall'=>round($weighted,2),'rate_source'=>$rateSource,
                    'pd_source'=>$pdSource,'lgd_source'=>strtoupper($lgdField),'created_at'=>now(),'updated_at'=>now()]);
                DB::table('ecl_pd_term_structures')->updateOrInsert(['contract_id'=>$id,'reporting_period'=>$asOf->format('Y-m'),
                    'scenario_code'=>$scenario->scenario_code,'period_index'=>$m],['projection_date'=>$date,'conditional_pd'=>$conditional,
                    'survival_open'=>$survival,'marginal_pd'=>$marginal,'cumulative_pd'=>$cumulative,'source'=>$pdSource,'created_at'=>now(),'updated_at'=>now()]);
                $weightedUndisc+=$shortfall*(float)$scenario->weight;$weightedDisc+=$weighted;$survival=max(0,$survival-$marginal);$opening=max(0,$opening-$scheduled);$maxExponent=max($maxExponent,$exponent);
            }
            $byScenario[$scenario->scenario_code]=['weight'=>$w,'undiscounted'=>$w>0?($weightedUndisc-$u0)/$w:0.0,'discounted'=>$w>0?($weightedDisc-$d0)/$w:0.0];
        }
        // The invariant (UI end-to-end audit, item 4): at a rate of nil or more,
        // discounting a loss dated on or after the reporting date never raises
        // it. The one exception is a defaulted loan with an approved recovery
        // plan, whose loss is the exposure less the present value of later
        // recoveries: discounting a recovery raises the loss (IFRS 9 B5.5.33).
        $planned=$stage===3&&$recoveries->isNotEmpty();
        if(!$planned&&$weightedDisc>$weightedUndisc+1e-6*max(1.0,$weightedUndisc))
            throw new RuntimeException('The discounted loss ('.round($weightedDisc,2).') is above the undiscounted ('.round($weightedUndisc,2).'); the projection is refused.');
        return ['undiscounted'=>$weightedUndisc,'discounted'=>$weightedDisc,'rate'=>$eir?$rate:null,'rate_source'=>$rateSource,'horizon'=>$maxExponent,'by_scenario'=>$byScenario];
    }

    /**
     * The scenarios a loan is measured under.
     *
     *  - A run before FLI (pd_prefli): one scenario at the PD before FLI, no
     *    shock; the scenario PDs the route wrote are post-FLI and do not apply.
     *  - Off the governed set (no loan of the run carries it): the legacy
     *    approved assumptions, as before.
     *  - On the set, a loan with the per-scenario PD the route wrote: each
     *    scenario's PD becomes a multiplier on the base, so the loop reproduces
     *    it exactly; LGD and EAD are not shocked (D-F8) and the weights are the
     *    set's.
     *  - On the set, a defaulted loan (the route writes it no PD): the set's
     *    scenarios and weights with no shock, since no scenario moves a default
     *    that has happened (D-F5); its loss is the same in every scenario.
     *  - On the set, a performing loan with no per-scenario PD: refused, never
     *    measured on the legacy table or on the base PD alone.
     */
    /** The current EIR of a floating-rate loan at the reporting date, or null (fail closed, never the original EIR). */
    private function currentFloatingEir(string $id,object $loan,CarbonImmutable $asOf): ?float
    {
        $contract=\App\Models\ContractEir::where('contract_id',$id)->first();
        if(!$contract)return null;
        // the month's charged rate: on the loan as passed, else its own loan-book row of the month
        if(!isset($loan->interest_rate)||!is_numeric($loan->interest_rate)){
            $loan=(object)['interest_rate'=>DB::table('loan_books')->where('contract_id',$id)->where('reporting_period',$asOf->format('Y-m'))->orderByDesc('id')->value('interest_rate')];
        }

        return app(\App\Services\Eir\EirRevenueService::class)->currentFloatingEir($contract,$loan,$asOf->format('Y-m'));
    }

    private function scenariosFor(object $loan,float $basePd,Collection $legacy,int $stage): Collection
    {
        if($this->pdField==='pd_prefli') return collect([(object)['scenario_code'=>'Before FLI','name'=>'Before FLI','weight'=>1.0,'pd_multiplier'=>1.0,'lgd_multiplier'=>1.0,'ead_multiplier'=>1.0]]);
        if(!$this->onSet) return $legacy;
        $by=isset($loan->fli_by_scenario)&&$loan->fli_by_scenario!==null&&$loan->fli_by_scenario!==''?(json_decode((string)$loan->fli_by_scenario,true)?:[]):[];
        $sum=0.0;
        foreach($by as $s){$sum+=(float)($s['weight']??0);}
        if($by!==[]&&$sum>0){
            $out=collect();
            foreach($by as $name=>$s){
                if(!isset($s['pd'])) throw new RuntimeException("Scenario {$name} carries no PD on the loan; apply the forward-looking route again.");
                $pd=(float)$s['pd'];
                $out->push((object)['scenario_code'=>$name,'name'=>$name,'weight'=>(float)$s['weight']/$sum,'pd_multiplier'=>$basePd>0?$pd/$basePd:1.0,'lgd_multiplier'=>1.0,'ead_multiplier'=>1.0]);
            }
            return $out;
        }
        $setId=(int)($loan->fli_set_id??0);
        if($stage===3&&$setId>0) return $this->setScenarios[$setId]??=$this->neutralSet($setId);
        throw new RuntimeException($setId>0
            ?'The loan carries the scenario set but no per-scenario PD from the route; apply the forward-looking route again.'
            :'The loan is not on the scenario set the rest of the run is measured under; apply the forward-looking route to every loan of the month.');
    }

    /** Removes the term structure this engine wrote for a loan's month (FLI projection rows are not its own). */
    private function clearTermStructure(string $id,CarbonImmutable $asOf): void
    {
        DB::table('ecl_pd_term_structures')->where('contract_id',$id)->where('reporting_period',$asOf->format('Y-m'))->whereIn('source',self::TERM_SOURCES)->delete();
    }

    /** The scenarios of a governed set with their weights and no shock. */
    private function neutralSet(int $setId): Collection
    {
        $rows=DB::table('governed_scenarios')->where('set_id',$setId)->orderBy('order_position')->get(['name','weight']);
        $sum=(float)$rows->sum('weight');
        if($rows->isEmpty()||$sum<=0) throw new RuntimeException("Scenario set {$setId} holds no weighted scenario.");
        return $rows->map(fn($r)=>(object)['scenario_code'=>$r->name,'name'=>$r->name,'weight'=>(float)$r->weight/$sum,'pd_multiplier'=>1.0,'lgd_multiplier'=>1.0,'ead_multiplier'=>1.0])->values();
    }

    /**
     * Stage 3 under IFRS 9 5.5.3 and B5.5.33: the exposure has defaulted, so the
     * allowance is the shortfall measured at the reporting date, EAD less the
     * present value of the recoveries the approved plan expects, each
     * discounted at the locked EIR from its own date. With no approved plan
     * the loss is EAD times the cohort LGD at the reporting date, undiscounted,
     * because nothing in it is dated later. Projecting EAD x LGD along the
     * contractual schedule and discounting it from the last due date (as this
     * engine did) understated a 60,000 loss to 49,587 over 24 months at 10
     * percent, and netting the recoveries before discounting understated the
     * planned case too.
     *
     * The rows written: period 0 at the reporting date carries the exposure
     * (or the loss, when there is no plan); each recovery month carries the
     * recovery as a negative shortfall with its own discount factor, so the
     * projection reads as EAD less PV(recoveries) line by line.
     *
     * @return array{0:float,1:float,2:float} undiscounted loss, discounted loss, last exponent
     */
    private function stageThree(string $runId,string $id,CarbonImmutable $asOf,float $ead,float $baseLgd,Collection $recoveries,float $rate,object $scenario,string $rateSource,string $pdSource,string $lgdField): array
    {
        $scenarioEad=$ead*(float)$scenario->ead_multiplier;
        $period=$asOf->format('Y-m');
        $row=fn(int $index,CarbonImmutable $date,float $opening,float $marginal,float $lgd,float $shortfall,float $exponent)=>DB::table('ecl_cashflow_projections')->insert([
            'run_id'=>$runId,'contract_id'=>$id,'reporting_period'=>$period,'ifrs9_stage'=>3,'scenario_code'=>$scenario->scenario_code,
            'scenario_weight'=>$scenario->weight,'period_index'=>$index,'projection_date'=>$date,'opening_ead'=>round($opening,2),'scheduled_principal'=>0,
            'closing_ead'=>round($opening,2),'conditional_pd'=>1,'survival_open'=>1,'marginal_pd'=>$marginal,'cumulative_pd'=>1,'lgd'=>$lgd,
            'undiscounted_shortfall'=>round($shortfall,2),'discount_rate'=>$rate,'discount_exponent'=>$exponent,'discount_factor'=>1/pow(1+$rate,$exponent),
            'discounted_shortfall'=>round($shortfall/pow(1+$rate,$exponent),2),'weighted_discounted_shortfall'=>round($shortfall/pow(1+$rate,$exponent)*(float)$scenario->weight,2),
            'rate_source'=>$rateSource,'pd_source'=>$pdSource,'lgd_source'=>strtoupper($lgdField),'created_at'=>now(),'updated_at'=>now()]);

        if($recoveries->isEmpty()){
            $lgd=min(1,max(0,$baseLgd*(float)$scenario->lgd_multiplier));
            $loss=$scenarioEad*$lgd;
            $row(0,$asOf,$scenarioEad,1.0,$lgd,$loss,0.0);
            DB::table('ecl_pd_term_structures')->updateOrInsert(['contract_id'=>$id,'reporting_period'=>$period,'scenario_code'=>$scenario->scenario_code,'period_index'=>0],
                ['projection_date'=>$asOf,'conditional_pd'=>1,'survival_open'=>1,'marginal_pd'=>1,'cumulative_pd'=>1,'source'=>$pdSource,'created_at'=>now(),'updated_at'=>now()]);
            return [$loss,$loss,0.0];
        }

        $totalRecovery=0.0;$pvRecovery=0.0;$maxExponent=0.0;$index=0;
        $row(0,$asOf,$scenarioEad,0.0,1.0,$scenarioEad,0.0);
        foreach($recoveries as $recovery){
            $amount=max(0.0,(float)$recovery->expected_recovery)*(float)$scenario->ead_multiplier;
            if($amount<=0) continue;
            $date=CarbonImmutable::parse($recovery->recovery_date);
            $exponent=$asOf->diffInDays($date)/365;
            $totalRecovery+=$amount;$pvRecovery+=$amount/pow(1+$rate,$exponent);$maxExponent=max($maxExponent,$exponent);
            $row(++$index,$date,$scenarioEad,$amount/max(1e-9,$recoveries->sum('expected_recovery')),1-min(1,$totalRecovery/$scenarioEad),-$amount,$exponent);
        }
        $undisc=max(0,$scenarioEad-$totalRecovery);$disc=max(0,$scenarioEad-$pvRecovery);
        // the scenario's LGD multiplier scales the loss the plan leaves, never above the exposure
        $undisc=min($scenarioEad,$undisc*(float)$scenario->lgd_multiplier);$disc=min($scenarioEad,$disc*(float)$scenario->lgd_multiplier);
        DB::table('ecl_pd_term_structures')->updateOrInsert(['contract_id'=>$id,'reporting_period'=>$period,'scenario_code'=>$scenario->scenario_code,'period_index'=>0],
            ['projection_date'=>$asOf,'conditional_pd'=>1,'survival_open'=>1,'marginal_pd'=>1,'cumulative_pd'=>1,'source'=>$pdSource,'created_at'=>now(),'updated_at'=>now()]);
        return [$undisc,$disc,$maxExponent];
    }

    /**
     * The share of a defaulted exposure the approved plan expects to resolve
     * in each projected month, keyed by period index and summing to 1.
     *
     * Weighting by the recovery expected in a month leaves LGD identical in
     * every row — it is the plan's own 1 - recoveries/EAD either way — while
     * moving each slice of the shortfall to the date the plan actually names.
     * The denominator is the recovery that lands inside the projected grid,
     * not the collection total, so a row dated outside the horizon cannot
     * quietly scale the whole allowance down.
     *
     * Returns an empty array when no approved recovery falls in the grid, and
     * the caller then places the loss at the resolution horizon instead.
     *
     * @return array<int,float>
     */
    private function recoveryShares(Collection $recoveries,CarbonImmutable $asOf,int $months): array
    {
        $indexByMonth=[];
        for($m=1;$m<=$months;$m++) $indexByMonth[$asOf->addMonthsNoOverflow($m)->endOfMonth()->format('Y-m')]=$m;

        $byIndex=[];$total=0.0;
        foreach($recoveries as $recovery){
            $index=$indexByMonth[CarbonImmutable::parse($recovery->recovery_date)->format('Y-m')]??null;
            if($index===null) continue;
            $amount=max(0.0,(float)$recovery->expected_recovery);
            $byIndex[$index]=($byIndex[$index]??0.0)+$amount;$total+=$amount;
        }
        if($total<=0) return [];

        return array_map(fn($amount)=>$amount/$total,$byIndex);
    }

    private function projectionMonths(object $loan,string $id,CarbonImmutable $asOf,int $stage,Collection $recoveries): int
    {
        // a defaulted loan with no recovery plan is measured at the reporting date: no horizon enters it
        // (a matured Stage 3 loan has no remaining schedule, and was left unresolved for want of one)
        if($stage===3 && $recoveries->isEmpty()) return 1;
        $last=DB::table('contract_cashflow_schedule')->where('contract_id',$id)->where('schedule_version',1)->whereDate('due_date','>',$asOf)->max('due_date');
        if($stage===3 && $recoveries->isNotEmpty()) $last=$recoveries->max('recovery_date');
        $months=$last?max(1,$asOf->diffInMonths(CarbonImmutable::parse($last)->endOfMonth())):(int)ceil((float)($loan->remaining_tenor??0)); // remaining_tenor is in months
        if($months<1)throw new RuntimeException('A remaining contractual horizon is unavailable.');
        return $stage===1?min(12,$months):min(600,$months);
    }
}
