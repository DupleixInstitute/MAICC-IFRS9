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
            $hasDates = collect($input['cash_flows'])->every(fn ($flow) => ! empty($flow['due_date']));
            $result = $hasDates
                ? $this->solver->calculateDated($input['initial_net_investment'], $input['cash_flows'],
                    $input['payments_per_year'], $input['metadata']['origination_date'],
                    $input['metadata']['day_count_basis'] ?? 'ACT/365')
                : $this->solver->calculate($input['initial_net_investment'], $input['cash_flows'], $input['payments_per_year']);

            DB::transaction(function () use ($contractId, $userId, $input, $result) {
                $contract = ContractEir::where('contract_id', $contractId)->lockForUpdate()->firstOrFail();
                if ($contract->locked_at !== null) {
                    throw new LogicException('A locked original EIR cannot be recalculated.');
                }

                if ($contract->calculated_at !== null && $contract->eir_effective_annual !== null) {
                    DB::table('eir_calculation_history')->insert([
                        'contract_id' => $contract->contract_id,
                        'eir_period' => $contract->eir_period,
                        'eir_nominal_annual' => $contract->eir_nominal_annual,
                        'eir_effective_annual' => $contract->eir_effective_annual,
                        'rate_source' => $contract->rate_source,
                        'solver_iterations' => $contract->solver_iterations,
                        'solver_residual' => $contract->solver_residual,
                        'solver_method' => $contract->solver_method,
                        'input_snapshot' => json_encode($contract->input_snapshot),
                        'calculation_status' => $contract->calculation_status,
                        'calculation_error' => $contract->calculation_error,
                        'calculated_at' => $contract->calculated_at,
                        'calculated_by' => $contract->calculated_by,
                        'locked_at' => $contract->locked_at,
                        'locked_by' => $contract->locked_by,
                        'archive_action' => 'RECALCULATED',
                        'archive_reason' => 'Recalculated before final approval.',
                        'archived_by' => $userId,
                        'archived_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
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

    /**
     * Reopen a locked original EIR for a controlled correction. The locked
     * measurement remains append-only history and every downstream live
     * result that depended on it is invalidated before recalculation.
     */
    public function reopen(string $contractId, int $userId, string $reason): ContractEir
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new LogicException('A specific reopening reason of at least 10 characters is required.');
        }

        return DB::transaction(function () use ($contractId, $userId, $reason) {
            $contract = ContractEir::where('contract_id', $contractId)->lockForUpdate()->firstOrFail();
            if ($contract->locked_at === null || $contract->calculation_status !== 'LOCKED') {
                throw new LogicException('Only a locked original EIR can be reopened.');
            }

            DB::table('eir_calculation_history')->insert([
                'contract_id' => $contract->contract_id,
                'eir_period' => $contract->eir_period,
                'eir_nominal_annual' => $contract->eir_nominal_annual,
                'eir_effective_annual' => $contract->eir_effective_annual,
                'rate_source' => $contract->rate_source,
                'solver_iterations' => $contract->solver_iterations,
                'solver_residual' => $contract->solver_residual,
                'solver_method' => $contract->solver_method,
                'input_snapshot' => json_encode($contract->input_snapshot),
                'calculation_status' => $contract->calculation_status,
                'calculation_error' => $contract->calculation_error,
                'calculated_at' => $contract->calculated_at,
                'calculated_by' => $contract->calculated_by,
                'locked_at' => $contract->locked_at,
                'locked_by' => $contract->locked_by,
                'archive_action' => 'REOPENED',
                'archive_reason' => $reason,
                'archived_by' => $userId,
                'archived_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $amortisation = DB::table('eir_amortisation')->where('contract_id', $contractId)->get();
            foreach ($amortisation as $row) {
                DB::table('eir_amortisation_history')->insert([
                    'contract_id' => $row->contract_id,
                    'reporting_period' => $row->reporting_period,
                    'opening_gross' => $row->opening_gross,
                    'interest_accrued' => $row->interest_accrued,
                    'interest_basis' => $row->interest_basis,
                    'unwind_amount' => $row->unwind_amount,
                    'cash_received' => $row->cash_received,
                    'cash_source' => $row->cash_source,
                    'modification_gain_loss' => $row->modification_gain_loss,
                    'closing_gross' => $row->closing_gross,
                    'ecl_allowance' => $row->ecl_allowance,
                    'originally_created_at' => $row->created_at,
                    'superseded_at' => now(),
                    'superseded_by' => $userId,
                    'supersession_reason' => 'Original EIR reopened: '.$reason,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('eir_amortisation')->where('contract_id', $contractId)->delete();

            DB::table('loan_books')->where('contract_id', $contractId)->update([
                'ecl_value_discounted' => null,
                'ecl_discounting_effect' => null,
                'ecl_discount_rate' => null,
                'ecl_discount_rate_source' => null,
                'ecl_discount_status' => 'STALE_EIR_REOPENED',
                'ecl_discount_horizon_years' => null,
                'ecl_calculation_run_id' => null,
                'ecl_calculated_at' => null,
            ]);

            $contract->update([
                'eir_period' => null,
                'eir_nominal_annual' => null,
                'eir_effective_annual' => null,
                'solver_iterations' => null,
                'solver_residual' => null,
                'solver_method' => null,
                'input_snapshot' => null,
                'calculation_status' => 'REOPENED',
                'calculation_error' => null,
                'calculated_at' => null,
                'calculated_by' => null,
                'locked_at' => null,
                'locked_by' => null,
            ]);

            return $contract->fresh();
        });
    }
}
