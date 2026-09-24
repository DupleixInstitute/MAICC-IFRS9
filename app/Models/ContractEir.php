<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractEir extends Model
{
    public const RATE_SOURCE_SOLVED = 'SOLVED_EIR';
    public const RATE_SOURCE_PROXY = 'CONTRACTUAL_PROXY';

    protected $table = 'contract_eir';

    protected $fillable = [
        'contract_id',
        'portfolio',
        'product_type',
        'source_day_count_basis',
        'source_compounding',
        'disbursement_tranches',
        'instrument_type',
        'rate_type',
        'reference_rate_at_origination',
        'markup',
        'fee_spread',
        'origination_date',
        'approved_amount',
        'drawn_amount',
        'moratorium_months',
        'payments_per_year',
        'frequency_source',
        'tenor_months',
        'contractual_rate',
        'rate_basis',
        'first_repayment_date',
        'maturity_date',
        'closure_date',
        'last_restructure_date',
        'currency',
        'sub_account_no',
        'gl_account_code',
        'opening_amortised_cost',
        'opening_amortised_cost_date',
        'terms_source_system',
        'terms_source_reference',
        'terms_imported_at',
        // E-Banker codes stored verbatim (D16) and what the engine derived from them (P2).
        'scheme_code',
        'interest_policy',
        'floating_flag',
        'interest_calc_base',
        'installment_based_on',
        'emi_calc_type',
        'moratorium_type',
        'moratorium_type_verbatim',
        'grace_period_months',
        'moratorium_from',
        'interest_start_date',
        'first_instalment_date',
        'spread_over_prime',
        'spread_source',
        'spread_drift_flag',
        'reprice_flag',
        'account_status_code',
        'los_application_no',
        'los_process_ref',
        'predecessor_sub_account',
        'eir_period',
        'eir_nominal_annual',
        'eir_effective_annual',
        'rate_source',
        'schedule_source',
        'schedule_approval_status',
        'schedule_comparison_status',
        'schedule_review_notes',
        'schedule_generated_at',
        'schedule_approved_at',
        'schedule_approved_by',
        // What the generator decided, recorded beside the terms it decided it
        // from (P4): the balance the instalments retire, the shapes and the
        // day count in force when the draft was built.
        'schedule_amortising_balance',
        'schedule_moratorium_type',
        'schedule_emi_calc_type',
        'schedule_interest_basis',
        'schedule_instalment_basis',
        'schedule_day_count',
        'schedule_basis_sources',
        'below_market_flag',
        'solver_iterations',
        'solver_residual',
        'solver_method',
        'input_snapshot',
        'calculation_status',
        'calculation_error',
        'calculated_at',
        'calculated_by',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'reference_rate_at_origination' => 'float',
        'markup'                        => 'float',
        'fee_spread'                    => 'float',
        'origination_date'              => 'date',
        'approved_amount'               => 'float',
        'drawn_amount'                  => 'float',
        'moratorium_months'             => 'integer',
        'payments_per_year'             => 'integer',
        'tenor_months'                  => 'integer',
        'contractual_rate'              => 'float',
        'first_repayment_date'          => 'date',
        'maturity_date'                 => 'date',
        'closure_date'                  => 'date',
        'last_restructure_date'         => 'date',
        'opening_amortised_cost'        => 'float',
        'opening_amortised_cost_date'   => 'date',
        'terms_imported_at'             => 'datetime',
        'grace_period_months'           => 'integer',
        'moratorium_from'               => 'date',
        'interest_start_date'           => 'date',
        'first_instalment_date'         => 'date',
        'spread_over_prime'             => 'float',
        'spread_drift_flag'             => 'boolean',
        'reprice_flag'                  => 'boolean',
        'schedule_generated_at'         => 'datetime',
        'schedule_approved_at'          => 'datetime',
        'schedule_amortising_balance'   => 'float',
        'eir_period'                    => 'float',
        'eir_nominal_annual'            => 'float',
        'eir_effective_annual'          => 'float',
        'below_market_flag'             => 'boolean',
        'solver_iterations'             => 'integer',
        'solver_residual'               => 'float',
        'input_snapshot'                => 'array',
        'calculated_at'                 => 'datetime',
        'locked_at'                     => 'datetime',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(ContractCashflowSchedule::class, 'contract_id', 'contract_id');
    }

    public function fees(): HasMany
    {
        return $this->hasMany(ContractFee::class, 'contract_id', 'contract_id');
    }

    public function amortisation(): HasMany
    {
        return $this->hasMany(EirAmortisation::class, 'contract_id', 'contract_id');
    }

    public function rateResets(): HasMany
    {
        return $this->hasMany(RateResetEvent::class, 'contract_id', 'contract_id');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    /**
     * Latest schedule version for this contract (restructures append
     * versions; the original is preserved for modification accounting).
     */
    public function currentScheduleVersion(): int
    {
        return (int) $this->schedules()->max('schedule_version') ?: 1;
    }

    public function isInEirScope(): bool
    {
        return $this->instrument_type !== 'EQUITY_EXCLUDED';
    }
}
