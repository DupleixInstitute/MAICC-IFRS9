<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One governed EIR convention from one effective date. The value in force on
 * a date is resolved by App\Services\Eir\GovernanceService, never by reading
 * a single row on its own.
 */
class GovernanceSetting extends Model
{
    public const STATUS_PROPOSED = 'PROPOSED';
    public const STATUS_APPROVED = 'APPROVED';

    protected $fillable = [
        'key', 'value', 'options', 'label', 'description', 'effective_from',
        'set_by', 'approved_by', 'approved_at', 'reason', 'status',
    ];

    protected $casts = [
        'options' => 'array',
        'effective_from' => 'date',
        'approved_at' => 'datetime',
    ];

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(GovernanceSettingHistory::class, 'setting_id');
    }
}
