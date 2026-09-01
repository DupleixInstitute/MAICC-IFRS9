<?php
namespace App\Services\Eir;

use App\Models\ContractEir;
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
        $derivedFirst=$start->copy()->addMonthsNoOverflow((int)$contract->moratorium_months+$interval);
        // Some Extract A rows carry a first-repayment date at/before the
        // origination date. It cannot be contractual cash flow evidence;
        // derive the first due date from the stated moratorium and frequency.
        $sourceFirst=$contract->first_repayment_date?->copy();
        $first=$sourceFirst && $sourceFirst->gte($derivedFirst) ? $sourceFirst : $derivedFirst;
        if ($first->gt($maturity)) throw new InvalidArgumentException('First repayment date falls after maturity.');
        $payments=1; $cursor=$first->copy();
        while ($cursor->copy()->addMonthsNoOverflow($interval)->lte($maturity)) { $payments++; $cursor->addMonthsNoOverflow($interval); }

        $rate=(float)$contract->contractual_rate;
        if ($rate>1) $rate/=100;
        $result=$this->generator->generate(['principal'=>(float)$contract->drawn_amount,'annual_rate'=>$rate,
            'payments_per_year'=>$frequency,'n_payments'=>$payments,'start_date'=>$start,
            'first_due_date'=>$first,'moratorium_months'=>(int)$contract->moratorium_months]);

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
                'schedule_generated_at'=>$now,'schedule_approved_at'=>null,'schedule_approved_by'=>null]);
        });
        $comparison=$this->comparison($contract->fresh());
        $contract->update(['schedule_comparison_status'=>$comparison['status']]);
        return ['rows'=>count($result['rows']),'comparison'=>$comparison];
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
    }
}
