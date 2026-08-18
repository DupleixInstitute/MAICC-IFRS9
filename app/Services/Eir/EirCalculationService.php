<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/** Orchestrates assembly, calculation and persistence of an unlocked original EIR. */
class EirCalculationService
{
    public function __construct(
        private readonly EirContractInputService $inputs,
        private readonly CalculateEirService $solver,
    ) {}

    /** @return array{contract_id:string,status:string,eir_effective_annual?:float,error?:string} */
    public function calculate(string $contractId, ?int $userId = null): array
    {
        try {
            $input = $this->inputs->assemble($contractId);
            $result = $this->solver->calculate(
                $input['initial_net_investment'],
                $input['cash_flows'],
                $input['payments_per_year'],
            );

            DB::transaction(function () use ($contractId, $userId, $input, $result) {
                $contract = ContractEir::where('contract_id', $contractId)->lockForUpdate()->firstOrFail();
                if ($contract->locked_at !== null) {
                    throw new LogicException('A locked original EIR cannot be recalculated.');
                }

                $snapshot = $input['input_snapshot'];
                $snapshot['solver'] = [
                    'method' => $result['method'],
                    'iterations' => $result['solver_iterations'],
                    'residual' => $result['solver_residual'],
                ];

                $contract->update([
                    'eir_period' => $result['eir_period'],
                    'eir_nominal_annual' => $result['eir_nominal_annual'],
                    'eir_effective_annual' => $result['eir_effective_annual'],
                    // rate_source describes where the rate came from; the
                    // separate calculation_status field describes workflow.
                    // The database enum deliberately accepts SOLVED_EIR, not
                    // CALCULATED.
                    'rate_source' => ContractEir::RATE_SOURCE_SOLVED,
                    'solver_iterations' => $result['solver_iterations'],
                    'solver_residual' => $result['solver_residual'],
                    'solver_method' => $result['method'],
                    'input_snapshot' => $snapshot,
                    'calculation_status' => 'CALCULATED',
                    'calculation_error' => null,
                    'calculated_at' => now(),
                    'calculated_by' => $userId,
                ]);
            });

            return ['contract_id' => $contractId, 'status' => 'CALCULATED', 'eir_effective_annual' => $result['eir_effective_annual']];
        } catch (Throwable $e) {
            ContractEir::where('contract_id', $contractId)->whereNull('locked_at')->update([
                'calculation_status' => 'BLOCKED',
                'calculation_error' => $e->getMessage(),
            ]);

            return ['contract_id' => $contractId, 'status' => 'BLOCKED', 'error' => $e->getMessage()];
        }
    }

    public function lock(string $contractId, int $reviewerId, bool $allowMakerCheckerOverride = false): ContractEir
    {
        return DB::transaction(function () use ($contractId, $reviewerId, $allowMakerCheckerOverride) {
            $contract = ContractEir::where('contract_id', $contractId)->lockForUpdate()->firstOrFail();
            if ($contract->locked_at !== null) return $contract;
            if ($contract->calculation_status !== 'CALCULATED' || $contract->eir_period === null) {
                throw new LogicException('Only a successfully calculated EIR can be approved and locked.');
            }
            if (!$allowMakerCheckerOverride && $contract->calculated_by === null) {
                throw new LogicException('The calculation has no identifiable maker and cannot be approved.');
            }
            if (!$allowMakerCheckerOverride && (int) $contract->calculated_by === $reviewerId) {
                throw new LogicException('The calculator cannot approve and lock their own EIR.');
            }

            $contract->update([
                'calculation_status' => 'LOCKED',
                'locked_at' => now(),
                'locked_by' => $reviewerId,
            ]);

            return $contract->fresh();
        });
    }

    /**
     * Lock a reviewer-approved batch without allowing one ineligible contract
     * to stop the others. The same maker/checker and finality rules used by
     * the single-contract action remain authoritative.
     *
     * @param iterable<string> $contractIds
     * @return array{locked:list<ContractEir>,skipped:array<string,string>}
     */
    public function lockMany(iterable $contractIds, int $reviewerId, bool $allowMakerCheckerOverride = false): array
    {
        $locked = [];
        $skipped = [];

        foreach (array_values(array_unique(array_map('strval', [...$contractIds]))) as $contractId) {
            try {
                $locked[] = $this->lock($contractId, $reviewerId, $allowMakerCheckerOverride);
            } catch (LogicException $e) {
                $skipped[$contractId] = $e->getMessage();
            }
        }

        return compact('locked', 'skipped');
    }
}
