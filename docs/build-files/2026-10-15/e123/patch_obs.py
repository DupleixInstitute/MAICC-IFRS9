p = 'app/Services/Eir/ScheduleWorkflowService.php'
s = open(p, encoding='utf-8').read()
start = s.index('    /**\n     * Which basis E-Banker charged interest on while the facility was partly')
end = s.index('    /** True when the approved amount is above the amount drawn. */')
new = '''    /**
     * Which basis E-Banker charged interest on while the facility was partly
     * drawn, read from its own interest postings in the ledger (types 303 and
     * 120) against what each basis gives at the rate E-Banker charged: the
     * principal outstanding at the month before plus each tranche from its
     * date, or the sanctioned amount, over the days from the first drawdown.
     * The rate is the one the posting narrates ("IntDR Fm 27-NOV-24 To
     * 30-NOV-24 @ 85%"), else the month's loan-book rate. The basis whose
     * figure is nearer the postings is the one E-Banker applied; both figures
     * are kept as the evidence. With no postings for those months nothing can
     * be read and null is returned, so the generator refuses rather than
     * assumes.
     *
     * @param list<array{disbursement_date:string, amount:float}> $tranches
     */
    private function observedInterestBasis(ContractEir $contract, array $tranches): ?array
    {
        $first=Carbon::parse($tranches[0]['disbursement_date'])->startOfDay();
        $last=Carbon::parse($tranches[count($tranches)-1]['disbursement_date'])->startOfDay();
        $total=round(array_sum(array_column($tranches,'amount')),2);
        $approved=max((float)$contract->approved_amount,$total);
        $account=str_pad((string)$contract->contract_id,15,'0',STR_PAD_LEFT);
        try {
            $ledger=app(\\App\\Services\\Ebanker\\LandingZoneReader::class)->ledgerByAccount($last->copy()->endOfMonth()->toDateString(),$account)[$account] ?? [];
        } catch (\\Throwable) {
            return null;
        }
        $posted=[]; $narrated=[];
        foreach ($ledger as $row) {
            $x=$row['payload'];
            if (!in_array((string)($x['TRANTYPE'] ?? ''),\\App\\Services\\Ebanker\\LoanBookBuildService::TYPE_INTEREST,true) || $row['row_date']===null) continue;
            $ym=substr((string)$row['row_date'],0,7);
            $posted[$ym]=($posted[$ym] ?? 0.0)-(float)str_replace(',','',(string)($x['TRANSAMT'] ?? 0));
            if (!isset($narrated[$ym]) && preg_match('/@\\s*(\\d+(?:\\.\\d+)?)\\s*%/',(string)($x['PARTICULARS'] ?? ''),$m)===1) $narrated[$ym]=(float)$m[1]/100;
        }
        $schema=DB::getSchemaBuilder();
        $book=fn(string $ym)=>$schema->hasTable('loan_books') ? DB::table('loan_books')->where('contract_id',$contract->contract_id)
            ->whereRaw("REPLACE(SUBSTR(reporting_period, 1, 7), '-', '') = ?",[str_replace('-','',$ym)])->orderByDesc('id')->first() : null;
        $months=[]; $sumPosted=0.0; $onBalance=0.0; $onSanction=0.0;
        for ($m=$first->copy()->startOfMonth(); $m->lte($last); $m->addMonthNoOverflow()) {
            $ym=$m->format('Y-m');
            $p=$posted[$ym] ?? 0.0;
            $row=$book($ym);
            $rate=$narrated[$ym] ?? ($row && is_numeric($row->interest_rate) && (float)$row->interest_rate>0 ? (float)$row->interest_rate/100 : (float)$contract->contractual_rate);
            if ($p<=0 || $rate<=0) continue;
            $from=$m->copy()->max($first); $end=$m->copy()->endOfMonth()->startOfDay();
            $prior=$m->isSameMonth($first) ? 0.0 : (float)($book($m->copy()->subMonthNoOverflow()->format('Y-m'))->principal_balance ?? 0);
            $b=$prior*$rate*$from->diffInDays($end)/365;
            foreach ($tranches as $t) {
                $d=Carbon::parse($t['disbursement_date'])->startOfDay();
                if ($d->isSameMonth($m)) $b+=$t['amount']*$rate*$d->diffInDays($end)/365;
            }
            $sanction=$approved*$rate*$from->diffInDays($end)/365;
            $months[]=['month'=>$ym,'posted'=>round($p,2),'on_balance'=>round($b,2),'on_sanction'=>round($sanction,2),'rate'=>$rate,'rate_from'=>isset($narrated[$ym])?'narration':'loan book'];
            $sumPosted+=$p; $onBalance+=$b; $onSanction+=$sanction;
        }
        if ($months===[] || $sumPosted<=0 || $onBalance<=0 || $onSanction<=0) return null;
        $basis=abs(log($sumPosted/$onBalance))<=abs(log($sumPosted/$onSanction)) ? 'B' : 'S';
        return ['basis'=>$basis,'source'=>ScheduleGeneratorService::BASIS_OBSERVED,'evidence'=>[
            'months'=>$months,'posted'=>round($sumPosted,2),'expected_on_balance'=>round($onBalance,2),'expected_on_sanction'=>round($onSanction,2),
            'read_as'=>$basis==='B'?'the drawn balance':'the sanctioned amount',
        ]];
    }

'''
s = s[:start] + new + s[end:]
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
