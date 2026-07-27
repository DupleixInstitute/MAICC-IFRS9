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
        'amount',
        'basis',
        'integral',
        'gl_account_ref',
    ];

    protected $casts = [
        'amount'   => 'float',
        'integral' => 'boolean',
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
        return $query->where('integral', true);
    }
}
