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
    public function __construct(
        private readonly EirReadinessService $readiness,
        private readonly GovernanceService $governance,
        private readonly ?DisbursementService $disbursements = null,
    ) {
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

        /*
        | A facility drawn in tranches (system audit of 9 October 2026, finding
        | H2; spec v4 counts 58 of them). The money out at origination is the
        | tranche(s) disbursed by then; every later tranche enters the vector
        | at its own date as a negative flow marked DISBURSEMENT, which is the
        | one kind of negative row the solver accepts. Solving on the whole
        | drawn amount at t0 against a schedule that repays it all made the
        | EIR of a two-tranche facility far above its contractual rate. With
        | no tranche register the drawn amount stands, as before.
        */
        $later = [];
        $atOrigination = null;
        if ($this->disbursements !== null && $contract->origination_date) {
            $later = $this->disbursements->laterDrawdownFlows($contractId, $contract->origination_date);
            $origination = $contract->origination_date->toDateString();
            $byOrigination = array_filter($this->disbursements->tranchesFor($contractId), fn ($t) => $t['disbursement_date'] <= $origination);
            if ($byOrigination !== [] || $later !== []) {
                $atOrigination = (float) array_sum(array_column($byOrigination, 'amount'));
            }
        }
        $drawn = $atOrigination !== null && $atOrigination > 0 ? $atOrigination : (float) $contract->drawn_amount;
        if ($atOrigination !== null && $atOrigination <= 0 && $later !== []) {
            // nothing drawn at signature: the first tranche is the initial investment and the rest follow
            $first = array_shift($later);
            $drawn = -(float) $first['amount'];
        }
        $initialNet = $drawn - $received + $paid;
        foreach ($later as $flow) {
            $cashFlows[] = [
                'period' => 0, 'due_date' => $flow['due_date'], 'principal' => (float) $flow['amount'], 'interest' => 0.0, 'fee' => 0.0,
                'amount' => (float) $flow['amount'], 'flow_type' => CalculateEirService::FLOW_DISBURSEMENT, 'reference' => $flow['reference'] ?? null,
            ];
        }
        if ($later !== []) {
            usort($cashFlows, fn ($a, $b) => [$a['due_date'], $a['flow_type'] ?? ''] <=> [$b['due_date'], $b['flow_type'] ?? '']);
            foreach ($cashFlows as $i => &$flow) { $flow['period'] = $i + 1; } unset($flow);
        }

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
            'later_drawdowns' => count($later),
            'total_drawn' => (float) $contract->drawn_amount,
            // The contract's own stated basis wins. Where the source system stated none,
            // the governed convention in force at origination applies, so that a later
            // change to the setting never restates a contract already solved. There is
            // no basis written into this code: a missing setting fails closed.
            'day_count_basis' => $contract->source_day_count_basis
                ?: $this->governance->get('day_count', $contract->origination_date),
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
