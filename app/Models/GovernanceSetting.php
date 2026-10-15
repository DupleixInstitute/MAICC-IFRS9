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
    // a proposal that was not wanted stays on record (Staging Module Specification v1, section 8.2)
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_WITHDRAWN = 'WITHDRAWN';

    protected $fillable = [
        'key', 'value', 'options', 'label', 'description', 'effective_from',
        'set_by', 'approved_by', 'approved_at', 'reason', 'status',
        'reviewed_by', 'reviewed_at', 'review_reason',
    ];

    protected $casts = [
        'options' => 'array',
        'effective_from' => 'date',
        'approved_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /** The supporting documents of this change (section 8.3), the removed ones included and marked. */
    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(SupportingDocument::class, 'documentable')->withTrashed();
    }

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
