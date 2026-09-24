<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One drawdown on a facility: the date money left MAIIC, the amount and the
 * reference (spec v3 section 7.6). The sum of a facility's drawdowns is what
 * has been drawn; the approved amount less that sum is the undrawn commitment
 * reported under IFRS 9.
 */
class ContractDisbursement extends Model
{
    /** The per-drawdown extract MAIIC sends; named so an auditor can ask for it. */
    public const SOURCE = 'MAIIC_DISBURSEMENTS';

    protected $table = 'contract_disbursements';

    protected $fillable = [
        'contract_id',
        'sub_account_no',
        'tranche_no',
        'disbursement_date',
        'amount',
        'reference',
        'source_system',
        'source_reference',
        'external_transaction_id',
        'import_id',
        'created_by',
    ];

    protected $casts = [
        'disbursement_date' => 'date',
        'amount' => 'float',
        'tranche_no' => 'integer',
        'import_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
