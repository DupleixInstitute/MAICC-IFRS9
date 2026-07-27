<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateResetEvent extends Model
{
    protected $table = 'rate_reset_events';

    protected $fillable = [
        'contract_id',
        'reset_date',
        'old_reference_rate',
        'new_reference_rate',
        'new_schedule_version',
        'recorded_by',
    ];

    protected $casts = [
        'reset_date'           => 'date',
        'old_reference_rate'   => 'float',
        'new_reference_rate'   => 'float',
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
