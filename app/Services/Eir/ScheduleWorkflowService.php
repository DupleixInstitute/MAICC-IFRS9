<?php
namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Models\Scheme;
use App\Services\AuditLoggerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScheduleWorkflowService
{
    public function __construct(private readonly ScheduleGeneratorService $generator) {}

    public function readiness(ContractEir $contract): array
    {
        $issues=[];
        if (!$contract->isInEirScope()) $issues[]='Equity-excluded instrument';
        if ($contract->locked_at) $issues[]='EIR is already locked';
        if (!$contract->origination_date) $issues[]='Origination date is missing';
        if (!$contract->maturity_date) $issues[]='Maturity date is missing';
        if ((float)$contract->drawn_amount<=0) $issues[]='Drawn amount is not positive';
        if ($contract->contractual_rate===null || (float)$contract->contractual_rate<0) $issues[]='Contractual rate is missing or invalid';
        if (!in_array((int)$contract->payments_per_year,[1,2,4,6,12],true)) $issues[]='Payment frequency is invalid';
        if ($contract->frequency_source!=='STATED') $issues[]='Payment frequency was not stated by the source';
        if ($contract->origination_date && $contract->maturity_date && $contract->maturity_date->lte($contract->origination_date)) $issues[]='Maturity must be after origination';
        // The shapes the generator has to be told, so the dry run reports them
        // contract by contract instead of one refusal at a time (spec 7.1).
        $scheme=$this->scheme($contract);
        if ((int)$contract->moratorium_months>0 && !$contract->moratorium_type && !($scheme['default_moratorium_type']??null))
            $issues[]='A moratorium is stated but its type is not, on the contract or on its scheme. E-Banker offers "Principle Only" and "Both (Interest + Principle)" and the two give different cash flows';
        if (strtoupper((string)($contract->emi_calc_type ?: $scheme['emi_calc_type'] ?? ''))===ScheduleGeneratorService::EMI_FLEXIBLE)
            $issues[]='EMI calculation type F (flexible) is not built; no example of the instalment pattern it produces exists';
        if ($this->partlyDrawn($contract)) {
            if (!$this->stated($contract->interest_calc_base) && !$this->stated($scheme['interest_calc_base']??null))
                $issues[]='The facility is drawn in part and neither the contract nor its scheme says whether interest is charged on the approved amount or on the balance (Loan Interest Cal. Base On)';
            if (!$this->stated($contract->installment_based_on) && !$this->stated($scheme['installment_based_on']??null))
                $issues[]='The facility is drawn in part and neither the contract nor its scheme says whether the instalment is sized on the sanctioned amount or on the amount disbursed (Installment Based On)';
        }
        return ['ready'=>$issues===[],'issues'=>$issues];
    }

    public function dryRun(): array
    {
        $eligible=0; $blocked=[];
        ContractEir::orderBy('contract_id')->each(function($contract) use (&$eligible,&$blocked) {
            $r=$this->readiness($contract); if ($r['ready']) $eligible++; else $blocked[$contract->contract_id]=$r['issues'];
        });
        return ['contracts'=>ContractEir::count(),'eligible'=>$eligible,'blocked'=>count($blocked),'exceptions'=>$blocked];
    }

    public function generate(ContractEir $contract): array
    {
        $check=$this->readiness($contract);
        if (!$check['ready']) throw new InvalidArgumentException(implode('; ',$check['issues']));
        if ($contract->schedule_approval_status==='APPROVED') throw new InvalidArgumentException('Approved schedule cannot be overwritten.');

        $frequency=(int)$contract->payments_per_year;
        $interval=intdiv(12,$frequency);
        $start=$contract->origination_date->copy();
        $maturity=$contract->maturity_date->copy();
        // The moratorium runs from the first disbursement (E-Banker p.31), not
        // from approval, so the instalments start from the date it ends.
        $moratoriumFrom=($contract->moratorium_from ?: $contract->interest_start_date ?: $contract->origination_date)->copy();
        $derivedFirst=$moratoriumFrom->copy()->addMonthsNoOverflow((int)$contract->moratorium_months)->addMonthsNoOverflow($interval);
        // Some Extract A rows carry a first-repayment date at/before the
        // origination date. It cannot be contractual cash flow evidence;
        // derive the first due date from the stated moratorium and frequency.
        $sourceFirst=($contract->first_instalment_date ?: $contract->first_repayment_date)?->copy();
        $first=$sourceFirst && $sourceFirst->gte($derivedFirst) ? $sourceFirst : $derivedFirst;
        if ($first->gt($maturity)) throw new InvalidArgumentException('First repayment date falls after maturity.');
        $payments=1; $cursor=$first->copy();
        while ($cursor->copy()->addMonthsNoOverflow($interval)->lte($maturity)) { $payments++; $cursor->addMonthsNoOverflow($interval); }

        $rate=(float)$contract->contractual_rate;
        if ($rate>1) $rate/=100;
        $result=$this->generator->generate([
            'principal'=>(float)$contract->drawn_amount,
            'approved_amount'=>(float)$contract->approved_amount,
            'annual_rate'=>$rate,
            'payments_per_year'=>$frequency,
            'n_payments'=>$payments,
            'start_date'=>$start,
            'interest_start_date'=>$contract->interest_start_date,
            'first_due_date'=>$first,
            'moratorium_months'=>(int)$contract->moratorium_months,
            'moratorium_type'=>$contract->moratorium_type ?: $contract->moratorium_type_verbatim,
            'moratorium_from'=>$moratoriumFrom,
            'grace_period_months'=>(int)$contract->grace_period_months,
            'emi_calc_type'=>$contract->emi_calc_type,
            'interest_calc_base'=>$contract->interest_calc_base,
            'installment_based_on'=>$contract->installment_based_on,
            // The conventions are read as at origination, so a later change to
            // the Governance Centre never restates a schedule already built.
            'as_of'=>$start,
            'scheme'=>$this->scheme($contract),
        ]);

        DB::transaction(function() use ($contract,$result) {
            DB::table('contract_cashflow_schedule')->where('contract_id',$contract->contract_id)
                ->where('schedule_version',1)->where('schedule_source','GENERATED')->delete();
            $now=now();
            DB::table('contract_cashflow_schedule')->insert(array_map(fn($row)=>[
                'contract_id'=>$contract->contract_id,'schedule_version'=>1,'effective_from'=>$contract->origination_date,
                'due_date'=>$row['due_date'],'principal_due'=>$row['principal_due'],'interest_due'=>$row['interest_due'],
                'fee_due'=>0,'schedule_source'=>'GENERATED','source_system'=>'EIR_GENERATOR',
                'source_reference'=>'Extract A contract terms','external_transaction_id'=>null,'created_at'=>$now,'updated_at'=>$now,
            ],$result['rows']));
            $contract->update(['schedule_source'=>'GENERATED','schedule_approval_status'=>'DRAFT',
                'schedule_generated_at'=>$now,'schedule_approved_at'=>null,'schedule_approved_by'=>null,
                'schedule_amortising_balance'=>$result['amortising_opening_balance'],
                'schedule_moratorium_type'=>$result['moratorium_type'],
                'schedule_emi_calc_type'=>$result['emi_calc_type'],
                'schedule_interest_basis'=>$result['interest_basis'],
                'schedule_instalment_basis'=>$result['instalment_basis'],
                'schedule_day_count'=>$result['day_count'],
                'schedule_basis_sources'=>$this->basisSourcesText($result['basis_sources']),
            ]);
            AuditLoggerService::log('EIR Schedule Generated', ContractEir::class, $contract->id, ['new_values'=>[
                'contract_id'=>$contract->contract_id,'rows'=>count($result['rows']),
                'instalment'=>$result['instalment'],'instalment_shape'=>$result['instalment_shape'],
                'moratorium_type'=>$result['moratorium_type'],'moratorium_months'=>$result['moratorium_months'],
                'interest_basis'=>$result['interest_basis'],'instalment_basis'=>$result['instalment_basis'],
                'day_count'=>$result['day_count'],'amortising_opening_balance'=>$result['amortising_opening_balance'],
                'capitalised_interest'=>$result['capitalised_interest'],
            ],'meta'=>['basis_sources'=>$result['basis_sources'],'grace_period_months'=>$result['grace_period_months']]]);
        });
        $comparison=$this->comparison($contract->fresh());
        $contract->update(['schedule_comparison_status'=>$comparison['status']]);
        return ['rows'=>count($result['rows']),'comparison'=>$comparison,'shape'=>[
            'instalment'=>$result['instalment'],'instalment_shape'=>$result['instalment_shape'],
            'moratorium_type'=>$result['moratorium_type'],'interest_basis'=>$result['interest_basis'],
            'instalment_basis'=>$result['instalment_basis'],'day_count'=>$result['day_count'],
            'capitalised_interest'=>$result['capitalised_interest'],'basis_sources'=>$result['basis_sources'],
        ]];
    }

    public function generateEligible(): array
    {
        $generated=0; $skipped=[];
        ContractEir::orderBy('contract_id')->each(function($contract) use (&$generated,&$skipped) {
            if ($contract->schedule_approval_status==='APPROVED') return;
            try { $this->generate($contract); $generated++; } catch (\Throwable $e) { $skipped[$contract->contract_id]=$e->getMessage(); }
        });
        return ['generated'=>$generated,'skipped'=>count($skipped),'exceptions'=>$skipped];
    }

    /** Statuses the comparison can end in. */
    public const COMPARISON_WITHIN_TOLERANCE = 'WITHIN_TOLERANCE';
    public const COMPARISON_CASH_VARIANCE = 'CASH_VARIANCE';
    public const COMPARISON_NOT_COMPARABLE = 'NOT_COMPARABLE';
    public const COMPARISON_NO_REMAINING_DATA = 'NO_REMAINING_DATA';

    /**
     * Version 1 against E-Banker's own schedule (Extract B or the EMI chart).
     *
     * Three rules, all learned on JAT Group (104430000087):
     *
     *  - Cash, not principal and interest. E-Banker adds the interest of the
     *    months with no instalment to the balance and repays it as principal,
     *    so its principal ran 77,672,610 above ours while the cash was the same
     *    thing split differently. Each instalment is compared on its total.
     *
     *  - Only the instalments before E-Banker recalculated. The EMI chart is
     *    E-Banker's current schedule: it is regenerated at every rate reset and
     *    whenever arrears are spread over the instalments left. Version 1 is the
     *    promise at origination, so it can only be held to the rows before the
     *    first regeneration; the rest are labelled and belong to P5 (resets)
     *    and P6 (arrears). A level instalment that changes, other than the last
     *    one or the first after an interest-only spell, marks a regeneration.
     *
     *  - Instalment by instalment, not date by date. The nth instalment is set
     *    against the nth, and a different due date is named as a cause instead
     *    of leaving both rows unmatched.
     */
    public function comparison(ContractEir $contract): array
    {
        $remaining=DB::table('contract_remaining_cashflow_schedule')->where('contract_id',$contract->contract_id)->orderBy('due_date')->orderBy('id')->get()->values();
        $empty=['cutoff_date'=>null,'generated_rows'=>0,'remaining_rows'=>0,'compared_rows'=>0,'recalculated_rows'=>0,
            'recalculated_from'=>null,'cash_variance'=>null,'principal_variance'=>null,'interest_variance'=>null,'matched_dates'=>0,'rows'=>[]];
        if ($remaining->isEmpty()) return ['status'=>self::COMPARISON_NO_REMAINING_DATA]+$empty;

        $cutoff=(string)$remaining->min('due_date');
        $generated=DB::table('contract_cashflow_schedule')->where('contract_id',$contract->contract_id)->where('schedule_version',1)
            ->whereDate('due_date','>=',$cutoff)->orderBy('due_date')->get()->values();

        $total=fn($r)=>$r===null?null:round((float)$r->principal_due+(float)$r->interest_due+(float)$r->fee_due,2);
        $recalculatedFrom=$this->firstRegeneration($remaining,(string)$contract->emi_calc_type);
        $resetDates=$this->repricesWithPlr($contract)
            ? DB::table('reference_rate_series')->whereDate('effective_date','>',$contract->origination_date ?? $cutoff)->orderBy('effective_date')->pluck('effective_date')->map(fn($d)=>substr((string)$d,0,10))->all()
            : [];

        $rows=[]; $compared=0; $recalculated=0; $cash=0.0; $pVar=0.0; $iVar=0.0; $ebankerCash=0.0; $sameDate=0;
        $count=max($generated->count(),$remaining->count());
        for ($i=0; $i<$count; $i++) {
            $g=$generated[$i]??null; $e=$remaining[$i]??null;
            $gTotal=$total($g); $eTotal=$total($e);
            $due=(string)($e->due_date??$g->due_date);
            $causes=[];
            if ($e!==null && $recalculatedFrom!==null && $i>=$recalculatedFrom) {
                $segment='RECALCULATED'; $recalculated++;
                if ($i===$recalculatedFrom) {
                    $previous=(string)$remaining[$i-1]->due_date;
                    foreach ($resetDates as $d) if ($d>$previous && $d<=$due) { $causes[]='Rate reset on '.$d.' (compared in P5)'; break; }
                    if ($this->inArrearsBetween($contract->contract_id,$previous,$due)) $causes[]='Arrears spread over the instalments left (handled in P6)';
                    if ($causes===[]) $causes[]='E-Banker regenerated its schedule here: a rate reset (P5) or arrears spread over the instalments left (P6)';
                } else {
                    $causes[]='After E-Banker recalculated';
                }
            } elseif ($g!==null && $e!==null) {
                $segment='COMPARED'; $compared++;
                $cash+=$gTotal-$eTotal; $ebankerCash+=$eTotal;
                $pVar+=(float)$g->principal_due-(float)$e->principal_due; $iVar+=(float)$g->interest_due-(float)$e->interest_due;
                if ((string)$g->due_date===(string)$e->due_date) $sameDate++; else $causes[]='Due dates differ ('.$g->due_date.' against '.$e->due_date.')';
                if (abs($gTotal-$eTotal)>1) $causes[]='Instalment differs: check the rate and how the instalment is sized';
                if ($causes===[]) $causes[]='Agrees';
            } elseif ($g!==null) {
                $segment='ONLY_GENERATED'; $causes[]='No E-Banker instalment here: the two schedules have a different number of instalments';
            } else {
                $segment='ONLY_EBANKER'; $causes[]='No generated instalment here: the two schedules have a different number of instalments';
            }
            $rows[]=['number'=>$i+1,'generated_due_date'=>$g?->due_date,'ebanker_due_date'=>$e?->due_date,
                'generated_total'=>$gTotal,'ebanker_total'=>$eTotal,
                'difference'=>$gTotal!==null&&$eTotal!==null?round($gTotal-$eTotal,2):null,
                'segment'=>$segment,'causes'=>$causes];
        }

        $status=$compared===0 ? self::COMPARISON_NOT_COMPARABLE
            : (abs($cash)<=max(1,abs($ebankerCash)*.01) ? self::COMPARISON_WITHIN_TOLERANCE : self::COMPARISON_CASH_VARIANCE);

        return ['status'=>$status,'cutoff_date'=>$cutoff,'generated_rows'=>$generated->count(),'remaining_rows'=>$remaining->count(),
            'compared_rows'=>$compared,'recalculated_rows'=>$recalculated,
            'recalculated_from'=>$recalculatedFrom===null?null:(string)$remaining[$recalculatedFrom]->due_date,
            'cash_variance'=>$compared?round($cash,2):null,
            // Kept for the screens that show them, and measured on the same
            // compared instalments only, so capitalised interest after a
            // regeneration cannot swell them.
            'principal_variance'=>$compared?round($pVar,2):null,'interest_variance'=>$compared?round($iVar,2):null,
            'matched_dates'=>$sameDate,'rows'=>$rows];
    }

    /**
     * Index of the first E-Banker row that belongs to a regenerated schedule,
     * or null. A level instalment (EMI type E) is regenerated when it changes;
     * an equal-principal one (type P) when its principal changes. The final
     * row may differ (it retires what is left), and the first row after an
     * interest-only spell is the instalment starting, not a regeneration.
     */
    private function firstRegeneration($rows, string $emiCalcType): ?int
    {
        $level=fn($r)=>strtoupper($emiCalcType)==='P'
            ? round((float)$r->principal_due,2)
            : round((float)$r->principal_due+(float)$r->interest_due+(float)$r->fee_due,2);
        $last=$rows->count()-1;
        for ($i=1; $i<$last; $i++) {
            if ((float)$rows[$i-1]->principal_due<=0.0) continue;
            if (abs($level($rows[$i])-$level($rows[$i-1]))>1) return $i;
        }
        return null;
    }

    private function repricesWithPlr(ContractEir $contract): bool
    {
        return (bool)$contract->reprice_flag || strtoupper((string)$contract->interest_policy)==='P';
    }

    /** Any loan-book month-end between two dates that shows the account overdue. */
    private function inArrearsBetween(string $contractId, string $from, string $to): bool
    {
        return DB::table('loan_books')->where('contract_id',$contractId)
            ->where('reporting_period','>=',substr($from,0,7))->where('reporting_period','<=',substr($to,0,7))
            ->where('overdue_days','>',0)->exists();
    }

    public function approve(ContractEir $contract, int $userId, ?string $notes=null): void
    {
        if (!DB::table('contract_cashflow_schedule')->where('contract_id',$contract->contract_id)->where('schedule_version',1)->exists())
            throw new InvalidArgumentException('Generate or import an original schedule before approval.');
        if (!in_array($contract->schedule_approval_status,['DRAFT','PENDING_REVIEW'],true))
            throw new InvalidArgumentException('Only a draft schedule can be approved.');
        $comparison=$this->comparison($contract);
        if ($comparison['status'] !== 'WITHIN_TOLERANCE' && trim((string)$notes) === '')
            throw new InvalidArgumentException('A review note is required when the generated schedule does not reconcile within tolerance to Extract B remaining cash flows.');
        $contract->update(['schedule_approval_status'=>'APPROVED','schedule_comparison_status'=>$comparison['status'],
            'schedule_review_notes'=>$notes,'schedule_approved_at'=>now(),'schedule_approved_by'=>$userId]);
        AuditLoggerService::log('EIR Schedule Approved', ContractEir::class, $contract->id, ['new_values'=>[
            'contract_id'=>$contract->contract_id,'comparison_status'=>$comparison['status'],'notes'=>$notes,
        ],'meta'=>['approved_by'=>$userId]]);
    }

    /**
     * The scheme's settings, used only where the contract row states none
     * (spec v3 section 6.3). The scheme in force is the latest one effective
     * on or before origination.
     *
     * @return array<string,mixed>
     */
    private function scheme(ContractEir $contract): array
    {
        $scheme=Scheme::inForce($contract->scheme_code, $contract->origination_date ?: Carbon::today());
        return $scheme===null ? [] : [
            'interest_policy'=>$scheme->interest_policy,
            'floating_flag'=>$scheme->floating_flag,
            'interest_calc_base'=>$scheme->interest_calc_base,
            'installment_based_on'=>$scheme->installment_based_on,
            'emi_calc_type'=>$scheme->emi_calc_type,
            'default_moratorium_type'=>$scheme->default_moratorium_type,
            'scheme_code'=>$scheme->scheme_code,
        ];
    }

    /** True when the approved amount is above the amount drawn. */
    private function partlyDrawn(ContractEir $contract): bool
    {
        return round((float)$contract->approved_amount - (float)$contract->drawn_amount, 2) > 0.0;
    }

    /** E-Banker writes N where no basis was chosen, which is the same as blank. */
    private function stated($value): bool
    {
        $text=strtoupper(trim((string)$value));
        return $text !== '' && $text !== 'N';
    }

    private function basisSourcesText(array $sources): string
    {
        $parts=[];
        foreach ($sources as $key=>$source) $parts[]=$key.'='.$source;
        return mb_substr(implode(', ',$parts),0,255);
    }
}
