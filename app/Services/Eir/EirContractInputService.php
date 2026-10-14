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
        if ($contract->schedule_source === \App\Services\Ebanker\TakeonLandingService::ROUTE) {
            return $this->assembleTakeon($contract);
        }
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

        $feeRows = $this->reviewedIntegralFees($contractId);

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
        // Nothing drawn at signature: the first drawdown (every tranche of that
        // day) is the initial investment, on its own date, and the rest follow.
        // Discounting from the signing date counted the weeks before any money
        // went out (Mphunzitsi SACCO: signed 15 July 2025, first drawn 8 August).
        // DisbursementService::solvePlan is the one rule; the readiness check reads it too.
        $plan = $this->disbursements !== null && $contract->origination_date
            ? $this->disbursements->solvePlan($contractId, $contract->origination_date)
            : ['drawn' => null, 'solve_from' => $contract->origination_date->toDateString(), 'later' => [], 'register' => false];
        $later = $plan['later'];
        $drawn = $plan['drawn'] ?? (float) $contract->drawn_amount;
        $solveFrom = $plan['solve_from'];
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
            // the date the first money went out, which the solve discounts from
            'origination_date' => $solveFrom,
            'contract_origination_date' => $contract->origination_date->toDateString(),
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

    /**
     * The fee lines an EIR reads: reviewed, integral, and from the source of
     * record (a line superseded under fee_source_precedence is kept and shown
     * but never enters the EIR, spec 7.8.2).
     */
    private function reviewedIntegralFees(string $contractId, ?string $onlySource = null)
    {
        $q = DB::table('contract_fees')
            ->where('contract_id', $contractId)
            ->where('classification_status', 'REVIEWED')
            ->where('integral', true);
        if (DB::getSchemaBuilder()->hasColumn('contract_fees', 'superseded_by_source')) {
            $q->whereNull('superseded_by_source');
        }
        if ($onlySource !== null) {
            $q->where('source_system', $onlySource);
        }

        return $q->orderBy('transaction_date')->orderBy('id')->get();
    }

    /**
     * A take-on loan's input (spec 6.9; results review of 13 October 2026,
     * E2). Its version 1 schedule is the take-on workbook's, solved from the
     * date the workbook starts charging interest on the balance it opens with.
     * The vector is the one the take-on build checked: every line's principal
     * and interest as a receipt, each later rise in the balance the lines do
     * not explain as money advanced on its date, each fall as money received
     * (contract_takeon.recompute_detail). Only the fees of the take-on
     * workbook enter it, and only once a person has reviewed them: a fee
     * charged after the take-on on another source belongs to a later event,
     * not to the loan's origination.
     */
    private function assembleTakeon(ContractEir $contract): array
    {
        $contractId = (string) $contract->contract_id;
        $takeon = DB::table('contract_takeon')->where('contract_id', $contractId)->first();
        $chain = $takeon ? (json_decode((string) $takeon->recompute_detail, true)['chain'] ?? null) : null;
        if (! $takeon || ! is_array($chain) || empty($chain['flows']) || ($takeon->eir_check ?? null) !== 'PASS') {
            throw new RuntimeException("Contract {$contractId}: the take-on solve has not passed its check, so there is nothing to carry into the EIR.");
        }

        $cashFlows = [];
        foreach (array_values($chain['flows']) as $i => $f) {
            $cashFlows[] = ['period' => $i + 1, 'due_date' => (string) $f['due_date'], 'principal' => (float) $f['amount'], 'interest' => 0.0, 'fee' => 0.0,
                'amount' => (float) $f['amount']] + (isset($f['flow_type']) ? ['flow_type' => $f['flow_type']] : []) + (isset($f['note']) ? ['note' => $f['note']] : []);
        }

        $feeRows = $this->reviewedIntegralFees($contractId, FeeSourcePrecedence::SOURCE_TAKEON);
        $received = (float) $feeRows->where('cashflow_direction', 'RECEIVED')->sum('amount');
        $paid = (float) $feeRows->where('cashflow_direction', 'PAID')->sum('amount');
        $opening = (float) $chain['opening'];
        $initialNet = $opening - $received + $paid;
        if ($initialNet <= 0) {
            throw new RuntimeException("Contract {$contractId}: the fee-adjusted initial net investment of the take-on loan is not positive.");
        }
        $nofee = $takeon->nofee_eir !== null ? (float) $takeon->nofee_eir : null;

        $metadata = [
            'instrument_type' => $contract->instrument_type,
            'rate_type' => $contract->rate_type,
            'origination_date' => (string) $chain['start'],
            'contract_origination_date' => $contract->origination_date?->toDateString(),
            'schedule_version' => 1,
            'schedule_source' => \App\Services\Ebanker\TakeonLandingService::ROUTE,
            'drawn_amount' => $opening,
            'later_drawdowns' => count(array_filter($cashFlows, fn ($f) => ($f['flow_type'] ?? null) === CalculateEirService::FLOW_DISBURSEMENT)),
            'total_drawn' => (float) $contract->drawn_amount,
            // the rate the workbook schedule itself carries (its no-fee solve, nominal):
            // a floating loan's EIR moves from it with the rate E-Banker charges (D38)
            'schedule_rate' => $nofee === null ? null : 12 * (pow(1 + $nofee, 1 / 12) - 1),
            'takeon_block_id' => $takeon->block_id,
            'day_count_basis' => $this->governance->get('day_count', \Carbon\CarbonImmutable::parse((string) $chain['start'])),
        ];
        $feeLines = $feeRows->map(fn ($row) => ['id' => (int) $row->id, 'fee_type' => (string) $row->fee_type, 'description' => $row->description, 'amount' => (float) $row->amount,
            'cashflow_direction' => (string) $row->cashflow_direction, 'transaction_date' => $row->transaction_date, 'source_reference' => $row->source_reference, 'gl_account_ref' => $row->gl_account_ref])->values()->all();
        $snapshot = [
            'contract_id' => $contractId,
            'initial_net_investment' => $initialNet,
            'payments_per_year' => (int) $contract->payments_per_year,
            'metadata' => $metadata,
            'fee_adjustments' => ['received' => $received, 'paid' => $paid, 'net' => $paid - $received, 'lines' => $feeLines],
            'cash_flows' => $cashFlows,
        ];

        return $snapshot + ['input_snapshot' => $snapshot];
    }
}
