<?php
$out = app(\App\Services\Ebanker\ContractInputsBuildService::class)->fees();
echo "rows ", $out['rows'], " loaded ", $out['result']['loaded_rows'], " skipped ", $out['result']['skipped_rows'], " direction ", json_encode($out['result']['direction_filled']), "\n";
echo json_encode(DB::table('contract_fees')->whereIn('contract_id',['104430000073','104430000074'])->get(['id','contract_id','fee_type','cashflow_direction','classification_status'])), "\n";
echo json_encode(DB::table('contract_fees')->where('source_system','E-Banker LOS_DISB_CHARGES_POST')->selectRaw('cashflow_direction, classification_status, count(*) n')->groupBy('cashflow_direction','classification_status')->get()), "\n";
foreach (['104430000073','104430000074'] as $x) echo "$x: ", implode(', ', array_column(app(\App\Services\Eir\EirReadinessService::class)->assess($x)['issues'], 'code')), "\n";
