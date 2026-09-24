<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only copy of a governance setting at the moment a later approval superseded it. */
class GovernanceSettingHistory extends Model
{
    protected $table = 'governance_setting_history';

    protected $fillable = [
        'setting_id', 'key', 'value', 'options', 'label', 'description', 'effective_from',
        'set_by', 'approved_by', 'approved_at', 'reason', 'status',
        'superseded_at', 'superseded_by', 'superseded_by_setting_id',
    ];

    protected $casts = [
        'options' => 'array',
        'effective_from' => 'date',
        'approved_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function superseder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superseded_by');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
