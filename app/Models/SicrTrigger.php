<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An entry of the qualitative SICR register. Written through
 * App\Services\Eir\QualitativeSicrService (raise, approve, reject, close),
 * never directly: the staging engine reads the approved entries in force.
 */
class SicrTrigger extends Model
{
    protected $table = 'finance_sicr_triggers';

    protected $guarded = ['id'];

    protected $casts = [
        'is_demo' => 'boolean',
        'stage_implied' => 'integer',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SicrGroup::class, 'group_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SicrItem::class, 'item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
