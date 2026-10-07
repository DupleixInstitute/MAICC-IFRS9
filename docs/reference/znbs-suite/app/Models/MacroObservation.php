<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MacroObservation extends Model
{
    /** Governance statuses (Phase 2A): only APPROVED / VALIDATED feed official runs. */
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    /** Statuses accepted by an OFFICIAL macro-dependent run. */
    public const OFFICIAL_STATUSES = [self::STATUS_APPROVED, self::STATUS_VALIDATED];

    protected $fillable = [
        'macro_variable_id',
        'period_date',
        'period_label',
        'period_type',
        'value',
        'value_stressed_mild',
        'value_stressed_severe',
        'source',
        'notes',
        'created_by',
        'value_type',
        'approval_status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'period_date'           => 'date',
        'value'                 => 'decimal:6',
        'value_stressed_mild'   => 'decimal:6',
        'value_stressed_severe' => 'decimal:6',
        'approved_at'           => 'datetime',
    ];

    /** Observations an official macro-dependent run may consume. */
    public function scopeOfficial(Builder $q): Builder
    {
        return $q->whereIn('approval_status', self::OFFICIAL_STATUSES);
    }

    public function variable(): BelongsTo
    {
        return $this->belongsTo(MacroVariable::class, 'macro_variable_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
