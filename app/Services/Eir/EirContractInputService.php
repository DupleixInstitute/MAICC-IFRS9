<?php

namespace App\Services\Eir;

use App\Exceptions\EirContractNotReadyException;
use App\Models\ContractEir;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Converts stored, reviewed contract data into the source-independent input
 * accepted by CalculateEirService. It performs no calculation and no writes.
 */
class EirContractInputService
{
    public function __construct(private readonly EirReadinessService $readiness)
    {
    }

    /**
     * @return array{
     *   contract_id:string,initial_net_investment:float,payments_per_year:int,
     *   cash_flows:list<array{period:int,due_date:string,principal:float,interest:float,fee:float,amount:float}>,
     *   fee_adjustments:array{received:float,paid:float,net:float,lines:list<array>},
     *   metadata:array,input_snapshot:array
     * }
     */
    public function assemble(string $contractId): array
    {
        $assessment = $this->readiness->assess($contractId);
        if (! $assessment['ready']) {
            throw new EirContractNotReadyException($contractId, $assessment['issues']);
        }

        $contract = ContractEir::where('contract_id', $contractId)->firstOrFail();
        $scheduleRows = DB::table('contract_cashflow_schedule')
            ->where('contract_id', $contractId)
            ->where('schedule_version', 1)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $cashFlows = [];
        foreach ($scheduleRows as $index => $row) {
            $principal = (float) $row->principal_due;
            $interest = (float) $row->interest_due;
            $fee = (float) $row->fee_due;
            $cashFlows[] = [
                'period' => $index + 1,
                'due_date' => (string) $row->due_date,
                'principal' => $principal,
                'interest' => $interest,
                'fee' => $fee,
                'amount' => $principal + $interest + $fee,
            ];
        }

        $feeRows = DB::table('contract_fees')
            ->where('contract_id', $contractId)
            ->where('classification_status', 'REVIEWED')
            ->where('integral', true)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $feeLines = $feeRows->map(fn ($row) => [
            'id' => (int) $row->id,
            'fee_type' => (string) $row->fee_type,
            'description' => $row->description,
            'amount' => (float) $row->amount,
            'cashflow_direction' => (string) $row->cashflow_direction,
            'transaction_date' => $row->transaction_date,
            'source_reference' => $row->source_reference,
            'gl_account_ref' => $row->gl_account_ref,
        ])->values()->all();

        $received = (float) $feeRows->where('cashflow_direction', 'RECEIVED')->sum('amount');
        $paid = (float) $feeRows->where('cashflow_direction', 'PAID')->sum('amount');
        $drawn = (float) $contract->drawn_amount;
        $initialNet = $drawn - $received + $paid;

        // Guard against data changing between readiness and assembly.
        if ($initialNet <= 0 || $cashFlows === []) {
            throw new RuntimeException("Contract {$contractId} changed after readiness assessment; retry the calculation intake.");
        }

        $metadata = [
            'instrument_type' => $contract->instrument_type,
            'rate_type' => $contract->rate_type,
            'origination_date' => $contract->origination_date->toDateString(),
            'schedule_version' => 1,
            'schedule_source' => $contract->schedule_source,
            'drawn_amount' => $drawn,
            'day_count_basis' => $contract->source_day_count_basis ?: 'ACT/365',
        ];
        $feeAdjustments = [
            'received' => $received,
            'paid' => $paid,
            'net' => $paid - $received,
            'lines' => $feeLines,
        ];

        $snapshot = [
            'contract_id' => $contractId,
            'initial_net_investment' => $initialNet,
            'payments_per_year' => (int) $contract->payments_per_year,
            'metadata' => $metadata,
            'fee_adjustments' => $feeAdjustments,
            'cash_flows' => $cashFlows,
        ];

        return $snapshot + ['input_snapshot' => $snapshot];
    }
}
