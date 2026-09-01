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
    public function run(Collection $loans, string $reportingPeriod, string $lgdField='collection_lgd', ?int $userId=null): array
    {
        $asOf=CarbonImmutable::parse(substr($reportingPeriod,0,7).'-01')->endOfMonth();
        $scenarios=DB::table('ecl_scenario_assumptions')->where('status','APPROVED')
            ->whereDate('effective_from','<=',$asOf)->where(fn($q)=>$q->whereNull('effective_to')->orWhereDate('effective_to','>=',$asOf))->get();
        if($scenarios->isEmpty()) throw new RuntimeException('No approved ECL scenario assumptions cover the reporting period.');
        $weight=(float)$scenarios->sum('weight');
        if(abs($weight-1)>1e-8) throw new RuntimeException('Approved ECL scenario weights must sum to 1.');

        $runId=(string)Str::uuid();
        DB::table('ecl_projection_runs')->insert(['run_id'=>$runId,'reporting_period'=>$asOf->format('Y-m'),
            'methodology_version'=>'TIME_PHASED_V1','status'=>'PROCESSING','created_by'=>$userId,'created_at'=>now(),'updated_at'=>now()]);
        $exceptions=[];$processed=0;$totalUndisc=0.0;$totalDisc=0.0;

        foreach($loans as $loan){
            try{
                $result=$this->projectLoan($loan,$asOf,$scenarios,$runId,$lgdField);
                DB::table('loan_books')->where('id',$loan->id)->update([
                    'ecl_value'=>round($result['undiscounted'],2),'ecl_value_discounted'=>round($result['discounted'],2),
                    'ecl_discounting_effect'=>round($result['undiscounted']-$result['discounted'],2),
                    'ecl_discount_rate'=>$result['rate'],'ecl_discount_rate_source'=>$result['rate_source'],
                    'ecl_discount_status'=>'CALCULATED_TIME_PHASED','ecl_discount_horizon_years'=>$result['horizon'],
                    'ecl_calculation_run_id'=>$runId,'ecl_calculated_at'=>now(),
                ]);
                $processed++;$totalUndisc+=$result['undiscounted'];$totalDisc+=$result['discounted'];
            }catch(\Throwable $e){
                $id=(string)($loan->contract_id??'row-'.$loan->id);$exceptions[$id]=$e->getMessage();
                DB::table('loan_books')->where('id',$loan->id)->update(['ecl_value_discounted'=>null,
                    'ecl_discounting_effect'=>null,'ecl_discount_rate'=>null,'ecl_discount_rate_source'=>null,
                    'ecl_discount_status'=>'TIME_PHASED_UNRESOLVED','ecl_calculation_run_id'=>$runId,'ecl_calculated_at'=>now()]);
            }
        }
        DB::table('ecl_projection_runs')->where('run_id',$runId)->update(['status'=>'COMPLETED','contracts_processed'=>$processed,
            'contracts_unresolved'=>count($exceptions),'undiscounted_ecl'=>round($totalUndisc,2),'discounted_ecl'=>round($totalDisc,2),
            'exceptions'=>json_encode($exceptions),'input_snapshot'=>json_encode(['scenario_codes'=>$scenarios->pluck('scenario_code')->all(),'lgd_field'=>$lgdField]),
            'completed_at'=>now(),'updated_at'=>now()]);
        return ['run_id'=>$runId,'calculated'=>$processed,'unresolved'=>count($exceptions),'undiscounted'=>$totalUndisc,'discounted'=>$totalDisc,'exceptions'=>$exceptions];
    }

    private function projectLoan(object $loan,CarbonImmutable $asOf,Collection $scenarios,string $runId,string $lgdField): array
    {
        $id=trim((string)($loan->contract_id??''));if($id==='')throw new RuntimeException('Contract ID is missing.');
        $eir=DB::table('contract_eir')->where('contract_id',$id)->whereNotNull('locked_at')->whereNotNull('eir_effective_annual')->first();
        if(!$eir)throw new RuntimeException('A locked original EIR is unavailable.');
        $rate=(float)$eir->eir_effective_annual;if($rate<=-1)throw new RuntimeException('Locked EIR is unusable.');
        $stage=(int)($loan->calculated_ifrs9_stage??$loan->ifrs9stage_post_qualitative??$loan->ifrs9stage_pre_qualitative??0);
        if(!in_array($stage,[1,2,3],true))throw new RuntimeException('IFRS 9 stage is unavailable.');
        $ead=(float)($loan->carrying_amount??0)+(float)($loan->commitments??0)*(float)($loan->facility_utilisation_rate??1);
        if($ead<=0)throw new RuntimeException('EAD is not positive.');
        $basePd=(float)($loan->pd_post_fli??$loan->pd_prefli??0);if($stage===3)$basePd=1.0;
        if($basePd<0||$basePd>1)throw new RuntimeException('PD must be between 0 and 1.');
        $baseLgd=$lgdField==='both'
            ? (float)($loan->customer_lgd??0)*(float)($loan->collection_lgd??0)
            : (float)($loan->{$lgdField}??0);
        $recoveries=DB::table('ecl_recovery_cashflows')->where('contract_id',$id)->where('reporting_period',$asOf->format('Y-m'))
            ->where('status','APPROVED')->whereDate('recovery_date','>',$asOf)->orderBy('recovery_date')->get();
        if($stage===3 && $recoveries->isNotEmpty()) $baseLgd=max(0,min(1,1-(float)$recoveries->sum('expected_recovery')/$ead));
        if($baseLgd<0||$baseLgd>1)throw new RuntimeException('LGD must be between 0 and 1.');
        $months=$this->projectionMonths($loan,$id,$asOf,$stage,$recoveries);
        $principalByMonth=DB::table('contract_cashflow_schedule')->where('contract_id',$id)->where('schedule_version',1)
            ->whereDate('due_date','>',$asOf)->selectRaw("SUBSTR(due_date,1,7) ym, SUM(principal_due) principal")
            ->groupByRaw("SUBSTR(due_date,1,7)")->pluck('principal','ym');
        $rateSource=$eir->rate_type==='FLOATING'?'EIR_ORIGINAL_FLOATING_PROXY':'EIR_ORIGINAL';
        $recoveryShares=$stage===3?$this->recoveryShares($recoveries,$asOf,$months):[];
        $pdSource=$stage===3
            ?($recoveryShares===[]?'DEFAULTED_RESOLUTION_HORIZON':'DEFAULTED_RECOVERY_SCHEDULE')
            :'TWELVE_MONTH_PD_CONSTANT_HAZARD';
        $weightedUndisc=0.0;$weightedDisc=0.0;$maxExponent=0.0;

        foreach($scenarios as $scenario){
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
        }
        return ['undiscounted'=>$weightedUndisc,'discounted'=>$weightedDisc,'rate'=>$rate,'rate_source'=>$rateSource,'horizon'=>$maxExponent];
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
        $last=DB::table('contract_cashflow_schedule')->where('contract_id',$id)->where('schedule_version',1)->whereDate('due_date','>',$asOf)->max('due_date');
        if($stage===3 && $recoveries->isNotEmpty()) $last=$recoveries->max('recovery_date');
        $months=$last?max(1,$asOf->diffInMonths(CarbonImmutable::parse($last)->endOfMonth())):(int)ceil((float)($loan->remaining_tenor??0)*12);
        if($months<1)throw new RuntimeException('A remaining contractual horizon is unavailable.');
        return $stage===1?min(12,$months):min(600,$months);
    }
}
