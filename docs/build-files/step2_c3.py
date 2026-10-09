import io, os
os.chdir(r'C:\xampp\htdocs\MAICC-IFRS9')
p = 'app/Services/Ecl/TimePhasedEclService.php'
s = io.open(p, encoding='utf-8').read()

# 1. the scenario loop: Stage 3 takes its own path
old = """        foreach($scenarios as $scenario){
            $scenarioPd=min(1,max(0,$basePd*(float)$scenario->pd_multiplier));"""
new = """        foreach($scenarios as $scenario){
            if($stage===3){
                // Default has occurred: the loss is measured at the reporting
                // date, not projected along a schedule nobody is paying
                // (system audit of 9 October 2026, finding C3).
                [$undisc,$disc,$exp]=$this->stageThree($runId,$id,$asOf,$ead,$baseLgd,$recoveries,$rate,$scenario,$rateSource,$pdSource,$lgdField);
                $weightedUndisc+=$undisc*(float)$scenario->weight;$weightedDisc+=$disc*(float)$scenario->weight;$maxExponent=max($maxExponent,$exp);
                continue;
            }
            $scenarioPd=min(1,max(0,$basePd*(float)$scenario->pd_multiplier));"""
assert old in s; s = s.replace(old, new, 1)

# 2. the Stage 3 method, before recoveryShares
old2 = """    /**
     * The share of a defaulted exposure the approved plan expects to resolve"""
new2 = """    /**
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
     * The share of a defaulted exposure the approved plan expects to resolve"""
assert old2 in s; s = s.replace(old2, new2, 1)
io.open(p, 'w', encoding='utf-8', newline='\n').write(s)

# 3. the tests: the planned case asserts the correct figure, not the understated range
t = 'tests/Feature/Ecl/TimePhasedEclServiceTest.php'
s = io.open(t, encoding='utf-8').read()
old3 = """        // The plan recovers 40,000 of 100,000, so LGD is its own 0.6 and the
        // tape's 0.9 is superseded. Three quarters of that settles in July.
        $this->assertEqualsWithDelta(60000,$result['undiscounted'],.01);
        $shares=DB::table('ecl_cashflow_projections')->where('marginal_pd','>',0)->orderBy('period_index')->pluck('marginal_pd','period_index');
        $this->assertEqualsWithDelta(.75,(float)$shares[6],1e-8);
        $this->assertEqualsWithDelta(.25,(float)$shares[12],1e-8);

        // Placing all of it at the final recovery date discounted a loss that
        // mostly crystallises six months earlier, and understated it.
        $this->assertGreaterThan(60000/1.1,$result['discounted']);
        $this->assertLessThan(60000,$result['discounted']);
        $this->assertSame('DEFAULTED_RECOVERY_SCHEDULE',DB::table('ecl_cashflow_projections')->value('pd_source'));"""
new3 = """        // The plan recovers 40,000 of 100,000, so the undiscounted loss is 60,000
        // and the tape's 0.9 is superseded. Three quarters of it settles in July.
        $this->assertEqualsWithDelta(60000,$result['undiscounted'],.01);
        $shares=DB::table('ecl_cashflow_projections')->where('marginal_pd','>',0)->orderBy('period_index')->pluck('marginal_pd','period_index');
        $this->assertEqualsWithDelta(.75,(float)$shares[1],1e-8);
        $this->assertEqualsWithDelta(.25,(float)$shares[2],1e-8);

        // IFRS 9: the loss is EAD less the present value of each recovery from
        // its own date, 100,000 - 30,000/1.1^(181/365) - 10,000/1.1^(365/365).
        // Discounting the net shortfall from the recovery dates (as before)
        // gave about 56,560 and the test asserted that understated range.
        $expected=100000-30000/pow(1.1,181/365)-10000/pow(1.1,1);
        $this->assertEqualsWithDelta($expected,$result['discounted'],.01);
        $this->assertGreaterThan(60000,$result['discounted']);
        $this->assertSame('DEFAULTED_RECOVERY_SCHEDULE',DB::table('ecl_cashflow_projections')->value('pd_source'));"""
assert old3 in s; s = s.replace(old3, new3, 1)
old4 = """        // Amortising the exposure wrote the loss off against instalments a
        // defaulted borrower will never pay, and reported 2,500 of 60,000.
        $this->assertEqualsWithDelta(60000,$result['undiscounted'],.01);
        $this->assertSame(100000.0,(float)DB::table('ecl_cashflow_projections')->orderByDesc('period_index')->value('opening_ead'));
        $this->assertSame('DEFAULTED_RESOLUTION_HORIZON',DB::table('ecl_cashflow_projections')->value('pd_source'));"""
new4 = """        // Amortising the exposure wrote the loss off against instalments a
        // defaulted borrower will never pay, and reported 2,500 of 60,000.
        $this->assertEqualsWithDelta(60000,$result['undiscounted'],.01);
        // With no recovery plan the loss sits at the reporting date, undiscounted:
        // discounting it from the last due date gave 49,587 (audit finding C3).
        $this->assertEqualsWithDelta(60000,$result['discounted'],.01);
        $this->assertSame(100000.0,(float)DB::table('ecl_cashflow_projections')->orderByDesc('period_index')->value('opening_ead'));
        $this->assertSame('DEFAULTED_RESOLUTION_HORIZON',DB::table('ecl_cashflow_projections')->value('pd_source'));"""
assert old4 in s; s = s.replace(old4, new4, 1)
io.open(t, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
