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

    public function comparison(ContractEir $contract): array
    {
        $remaining=DB::table('contract_remaining_cashflow_schedule')->where('contract_id',$contract->contract_id)->orderBy('due_date')->get();
        if ($remaining->isEmpty()) return ['status'=>'NO_REMAINING_DATA','cutoff_date'=>null,'generated_rows'=>0,'remaining_rows'=>0,
            'principal_variance'=>null,'interest_variance'=>null,'matched_dates'=>0];
        $cutoff=(string)$remaining->min('due_date');
        $generated=DB::table('contract_cashflow_schedule')->where('contract_id',$contract->contract_id)->where('schedule_version',1)
            ->whereDate('due_date','>=',$cutoff)->get();
        $pVar=(float)$generated->sum('principal_due')-(float)$remaining->sum('principal_due');
        $iVar=(float)$generated->sum('interest_due')-(float)$remaining->sum('interest_due');
        $dates=$generated->pluck('due_date')->map(fn($v)=>(string)$v)->intersect($remaining->pluck('due_date')->map(fn($v)=>(string)$v))->unique()->count();
        $pBase=abs((float)$remaining->sum('principal_due')); $iBase=abs((float)$remaining->sum('interest_due'));
        $pOk=abs($pVar)<=max(1,$pBase*.01); $iOk=abs($iVar)<=max(1,$iBase*.01);
        $status=$pOk&&$iOk?'WITHIN_TOLERANCE':(!$pOk?'PRINCIPAL_VARIANCE':'INTEREST_VARIANCE');
        return ['status'=>$status,'cutoff_date'=>$cutoff,'generated_rows'=>$generated->count(),'remaining_rows'=>$remaining->count(),
            'generated_principal'=>(float)$generated->sum('principal_due'),'remaining_principal'=>(float)$remaining->sum('principal_due'),
            'principal_variance'=>$pVar,'generated_interest'=>(float)$generated->sum('interest_due'),
            'remaining_interest'=>(float)$remaining->sum('interest_due'),'interest_variance'=>$iVar,'matched_dates'=>$dates];
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
