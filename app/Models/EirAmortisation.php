<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EirAmortisation extends Model
{
    protected $table = 'eir_amortisation';

    protected $fillable = [
        'contract_id',
        'reporting_period',
        'opening_gross',
        'interest_accrued',
        'interest_basis',
        'unwind_amount',
        'cash_received',
        'cash_source',
        'modification_gain_loss',
        'closing_gross',
        'ecl_allowance',
    ];

    protected $casts = [
        'opening_gross'          => 'float',
        'interest_accrued'       => 'float',
        'unwind_amount'          => 'float',
        'cash_received'          => 'float',
        'modification_gain_loss' => 'float',
        'closing_gross'          => 'float',
        'ecl_allowance'          => 'float',
    ];

    public function contractEir(): BelongsTo
    {
        return $this->belongsTo(ContractEir::class, 'contract_id', 'contract_id');
    }

    public function scopeForPeriod($query, string $period)
    {
        return $query->where('reporting_period', $period);
    }

    /**
     * Stage 3 discloses the interest not recognised because accrual runs on
     * the net carrying amount instead of gross.
     */
    public function suspendedInterest(): float
    {
        if ($this->interest_basis !== 'NET') {
            return 0.0;
        }

        $gross = (float) $this->opening_gross;
        $net   = $gross - (float) $this->ecl_allowance;

        if ($net <= 0 || $gross <= 0) {
            return 0.0;
        }

        // interest_accrued was computed on net; scale up to the gross basis.
        return (float) $this->interest_accrued * ($gross / $net) - (float) $this->interest_accrued;
    }
}
