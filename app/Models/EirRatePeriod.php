<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stretch of a loan's life at one rate, and the EIR that applies to it
 * (decision D8, IFRS 9 B5.4.5). Period 1 starts at origination; a floating
 * loan gains a period at each prime-rate change. See the migration of
 * 2026-10-01 for the units of each column.
 *
 * The period records the rate in force (prime, locked spread, contract rate)
 * and the schedule version built for it. It is not a source of the EIR: the
 * EIR a floating loan accrues at, and the current EIR the ECL discounts at,
 * is decision D38's monthly re-estimation at the rate E-Banker charged
 * (EirRevenueService::eirInForce and currentFloatingEir, recorded on each
 * eir_amortisation row as eir_annual). Solving an EIR here as well would
 * apply each rate change twice, so no engine fills or reads the eir_*
 * columns (reconciliation of 14 October 2026).
 */
class EirRatePeriod extends Model
{
    public const STATUS_PENDING_EIR = 'PENDING_EIR';
    public const STATUS_SOLVED = 'SOLVED';
    public const STATUS_BLOCKED = 'BLOCKED';
    public const STATUS_LOCKED = 'LOCKED';

    protected $table = 'eir_rate_periods';

    protected $fillable = [
        'contract_id', 'sub_account_no', 'period_no', 'effective_from', 'effective_to', 'index_code',
        'reference_rate', 'spread_over_prime', 'spread_basis', 'contractual_rate', 'opening_amortised_cost',
        'eir_period', 'eir_nominal_annual', 'eir_effective_annual', 'eir_resolved_effective_annual',
        'eir_disagreement_flag', 'solver_method', 'solver_iterations', 'solver_residual', 'input_snapshot',
        'rate_reset_event_id', 'schedule_version', 'status', 'note', 'locked_at', 'locked_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'reference_rate' => 'float',
        'spread_over_prime' => 'float',
        'contractual_rate' => 'float',
        'opening_amortised_cost' => 'float',
        'eir_period' => 'float',
        'eir_nominal_annual' => 'float',
        'eir_effective_annual' => 'float',
        'eir_resolved_effective_annual' => 'float',
        'eir_disagreement_flag' => 'boolean',
        'input_snapshot' => 'array',
        'locked_at' => 'datetime',
    ];

    public function contractEir(): BelongsTo
    {
        return $this->belongsTo(ContractEir::class, 'contract_id', 'contract_id');
    }

    public function resetEvent(): BelongsTo
    {
        return $this->belongsTo(RateResetEvent::class, 'rate_reset_event_id');
    }
}
