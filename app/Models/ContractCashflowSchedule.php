<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractCashflowSchedule extends Model
{
    protected $table = 'contract_cashflow_schedule';

    protected $fillable = [
        'contract_id',
        'schedule_version',
        'effective_from',
        'due_date',
        'principal_due',
        'interest_due',
        'fee_due',
        'schedule_source',
    ];

    protected $casts = [
        'schedule_version' => 'integer',
        'effective_from'   => 'date',
        'due_date'         => 'date',
        'principal_due'    => 'float',
        'interest_due'     => 'float',
        'fee_due'          => 'float',
    ];

    public function contractEir(): BelongsTo
    {
        return $this->belongsTo(ContractEir::class, 'contract_id', 'contract_id');
    }

    public function scopeForVersion($query, int $version)
    {
        return $query->where('schedule_version', $version);
    }

    public function totalDue(): float
    {
        return (float) $this->principal_due + (float) $this->interest_due + (float) $this->fee_due;
    }
}
