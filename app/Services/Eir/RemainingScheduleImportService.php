<?php
namespace App\Services\Eir;
use App\Support\ContractId;
use Illuminate\Support\Facades\DB;

/** Extract B future rows are validation evidence, not the original contractual schedule. */
class RemainingScheduleImportService
{
    public function import(array $rows): array
    {
        $loaded=0; $unchanged=0; $held=[]; $skipped=[]; $contracts=[];
        foreach ($rows as $index=>$row) {
            $id=ContractId::normalise($row['contract_id']??null);
            if (!$id) { $skipped['row '.($index+2)]='loan account is missing'; continue; }
            if (!DB::table('loan_books')->where('contract_id',$id)->exists()) { $held[$id]='loan account is not present in the imported loan book'; continue; }
            if (empty($row['due_date'])) { $skipped[$id]='remaining-schedule due date is missing'; continue; }
            $externalId=trim((string)($row['external_transaction_id']??''));
            if ($externalId==='') $externalId=hash('sha256',implode('|',[$id,$row['due_date'],$index,$row['principal_due']??0,$row['interest_due']??0,$row['fee_due']??0]));
            $source=trim((string)($row['source_system']??'MAIIC_EXTRACT_B'))?:'MAIIC_EXTRACT_B';
            if (DB::table('contract_remaining_cashflow_schedule')->where('source_system',$source)->where('external_transaction_id',$externalId)->exists()) { $unchanged++; continue; }
            DB::table('contract_remaining_cashflow_schedule')->insert([
                'contract_id'=>$id,'due_date'=>$row['due_date'],'principal_due'=>(float)($row['principal_due']??0),
                'interest_due'=>(float)($row['interest_due']??0),'fee_due'=>(float)($row['fee_due']??0),
                'source_system'=>$source,'source_reference'=>($row['source_reference']??null)?:null,
                'external_transaction_id'=>$externalId,'row_note'=>($row['row_note']??null)?:null,
                'created_at'=>now(),'updated_at'=>now(),
            ]); $contracts[$id]=true; $loaded++;
        }
        return ['loaded_contracts'=>count($contracts),'loaded_rows'=>$loaded,'unchanged'=>$unchanged,'held'=>$held,'skipped'=>$skipped,'coverage'=>['covered'=>count($contracts),'total'=>0]];
    }
}
