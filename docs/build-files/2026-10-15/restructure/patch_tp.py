p = 'C:/xampp/htdocs/MAICC-IFRS9/app/Services/Ecl/TimePhasedEclService.php'
s = open(p, encoding='utf-8').read()


def rep(a, b):
    global s
    assert s.count(a) == 1, a[:80]
    s = s.replace(a, b)


rep("""        if($eir&&(float)$eir->eir_effective_annual<0)throw new RuntimeException('The locked EIR is negative ('.$eir->eir_effective_annual.'); a discount factor above one is not applied.');
""", """        if($eir&&(float)$eir->eir_effective_annual<0)throw new RuntimeException('The locked EIR is negative ('.$eir->eir_effective_annual.'); a discount factor above one is not applied.');
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
""")
rep("""        $rateSource=!$eir?'NONE_LOSS_AT_REPORTING_DATE':($eir->rate_type==='FLOATING'?'EIR_ORIGINAL_FLOATING_PROXY':'EIR_ORIGINAL');""",
    """        $rateSource=!$eir?'NONE_LOSS_AT_REPORTING_DATE':($floating?($stage3AtDate?'NONE_LOSS_AT_REPORTING_DATE':'EIR_CURRENT_FLOATING'):'EIR_ORIGINAL');""")
rep("""    private function scenariosFor(object $loan,float $basePd,Collection $legacy,int $stage): Collection""",
    """    /** The current EIR of a floating-rate loan at the reporting date, or null (fail closed, never the original EIR). */
    private function currentFloatingEir(string $id,object $loan,CarbonImmutable $asOf): ?float
    {
        $contract=\\App\\Models\\ContractEir::where('contract_id',$id)->first();
        if(!$contract)return null;
        // the month's charged rate: on the loan as passed, else its own loan-book row of the month
        if(!isset($loan->interest_rate)||!is_numeric($loan->interest_rate)){
            $loan=(object)['interest_rate'=>DB::table('loan_books')->where('contract_id',$id)->where('reporting_period',$asOf->format('Y-m'))->orderByDesc('id')->value('interest_rate')];
        }

        return app(\\App\\Services\\Eir\\EirRevenueService::class)->currentFloatingEir($contract,$loan,$asOf->format('Y-m'));
    }

    private function scenariosFor(object $loan,float $basePd,Collection $legacy,int $stage): Collection""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
