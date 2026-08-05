<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EirAccountingRule extends Model
{
    protected $fillable = [
        'name', 'fee_type', 'description_contains', 'gl_account_ref',
        'cashflow_direction', 'proposed_integral', 'rationale', 'priority',
        'active', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'proposed_integral' => 'boolean',
        'active' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
