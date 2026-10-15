<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EirAccountingRule extends Model
{
    // maker-checker (Staging Module Specification v1, section 8.6): the fee matcher uses only APPROVED, active rules
    public const STATUS_PROPOSED = 'PROPOSED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_WITHDRAWN = 'WITHDRAWN';

    protected $fillable = [
        'name', 'fee_type', 'description_contains', 'gl_account_ref',
        'cashflow_direction', 'proposed_integral', 'rationale', 'priority',
        'active', 'created_by', 'approved_by', 'approved_at',
        'status', 'updated_by', 'approval_reason', 'approval_to_confirm',
        'rejected_by', 'rejected_at', 'rejection_reason', 'withdrawn_by', 'withdrawn_at', 'withdrawal_reason',
        'proposed_active', 'switch_proposed_by', 'switch_proposed_at', 'switch_reason',
    ];

    protected $casts = [
        'proposed_integral' => 'boolean',
        'active' => 'boolean',
        'approved_at' => 'datetime',
        'approval_to_confirm' => 'boolean',
        'proposed_active' => 'boolean',
        'rejected_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'switch_proposed_at' => 'datetime',
    ];

    /** The person the approver must differ from: who last wrote the rule, else who created it. */
    public function makerId(): ?int
    {
        return $this->updated_by !== null ? (int) $this->updated_by : ($this->created_by !== null ? (int) $this->created_by : null);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
