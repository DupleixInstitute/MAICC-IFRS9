<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractFee extends Model
{
    protected $table = 'contract_fees';

    protected $fillable = [
        'contract_id',
        'fee_type',
        'description',
        'amount',
        'transaction_date',
        'cashflow_direction',
        'currency',
        'source_system',
        'source_reference',
        'external_transaction_id',
        'basis',
        'integral',
        'classification_status',
        'classification_reason',
        'suggested_rule_id',
        'suggested_integral',
        'classified_by',
        'classified_at',
        'reviewed_by',
        'reviewed_at',
        'gl_account_ref',
    ];

    protected $casts = [
        'amount'   => 'float',
        'integral' => 'boolean',
        'suggested_integral' => 'boolean',
        'transaction_date' => 'date',
        'classified_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function contractEir(): BelongsTo
    {
        return $this->belongsTo(ContractEir::class, 'contract_id', 'contract_id');
    }

    /**
     * Only fees that are directly attributable to origination belong inside
     * the EIR (IFRS 9 B5.4.1); non-integral fees are period income.
     */
    public function scopeIntegral($query)
    {
        return $query->where('integral', true)->where('classification_status', 'REVIEWED');
    }

    public function suggestedRule(): BelongsTo
    {
        return $this->belongsTo(EirAccountingRule::class, 'suggested_rule_id');
    }
}
