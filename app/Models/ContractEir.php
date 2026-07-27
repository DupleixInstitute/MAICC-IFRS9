<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractEir extends Model
{
    protected $table = 'contract_eir';

    protected $fillable = [
        'contract_id',
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
        'eir_period',
        'eir_nominal_annual',
        'eir_effective_annual',
        'rate_source',
        'schedule_source',
        'below_market_flag',
        'solver_iterations',
        'solver_residual',
        'input_snapshot',
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
        'eir_period'                    => 'float',
        'eir_nominal_annual'            => 'float',
        'eir_effective_annual'          => 'float',
        'below_market_flag'             => 'boolean',
        'solver_iterations'             => 'integer',
        'solver_residual'               => 'float',
        'input_snapshot'                => 'array',
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
