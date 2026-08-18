<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One superseded amortisation row, kept so a restated figure can be explained. */
class EirAmortisationHistory extends Model
{
    protected $table = 'eir_amortisation_history';

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
        'originally_created_at',
        'superseded_at',
        'superseded_by',
        'supersession_reason',
    ];

    protected $casts = [
        'opening_gross'          => 'float',
        'interest_accrued'       => 'float',
        'unwind_amount'          => 'float',
        'cash_received'          => 'float',
        'modification_gain_loss' => 'float',
        'closing_gross'          => 'float',
        'ecl_allowance'          => 'float',
        'originally_created_at'  => 'datetime',
        'superseded_at'          => 'datetime',
    ];

    public function contractEir(): BelongsTo
    {
        return $this->belongsTo(ContractEir::class, 'contract_id', 'contract_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superseded_by');
    }
}
