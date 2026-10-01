<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A prime-rate change applied to one floating loan (phase P5). The reference
 * rates and the spread are in percentage points; the contractual rates are
 * fractions, as on contract_eir.
 */
class RateResetEvent extends Model
{
    public const STATUS_DETECTED = 'DETECTED';
    public const STATUS_SCHEDULED = 'SCHEDULED';
    public const STATUS_SOLVED = 'SOLVED';
    public const STATUS_BLOCKED = 'BLOCKED';

    protected $table = 'rate_reset_events';

    protected $fillable = [
        'contract_id',
        'reset_date',
        'plr_effective_date',
        'index_code',
        'old_reference_rate',
        'new_reference_rate',
        'spread_over_prime',
        'old_contractual_rate',
        'new_contractual_rate',
        'new_schedule_version',
        'status',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'reset_date'           => 'date',
        'plr_effective_date'   => 'date',
        'old_reference_rate'   => 'float',
        'new_reference_rate'   => 'float',
        'spread_over_prime'    => 'float',
        'old_contractual_rate' => 'float',
        'new_contractual_rate' => 'float',
        'new_schedule_version' => 'integer',
    ];

    public function contractEir(): BelongsTo
    {
        return $this->belongsTo(ContractEir::class, 'contract_id', 'contract_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
