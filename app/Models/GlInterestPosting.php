<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What MAIIC's general ledger actually posted as interest income for a loan
 * in a period (Extract C).
 *
 * This is the contractual-basis counterpart to eir_amortisation's
 * effective-interest figure. The Phase 6 reconciliation is the difference
 * between the two, per facility per period — so this table records what was
 * posted, never what should have been posted.
 */
class GlInterestPosting extends Model
{
    protected $table = 'gl_interest_postings';

    protected $fillable = [
        'contract_id',
        'gl_account_code',
        'period_type',
        'period_year',
        'period_month',
        'reporting_period',
        'interest_income_posted',
        'transaction_count',
        'posting_references',
        'row_note',
        'generated_on',
        'source_system',
        'source_reference',
        'external_transaction_id',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'reporting_period' => 'date',
        'interest_income_posted' => 'float',
        'transaction_count' => 'integer',
        'generated_on' => 'date',
    ];

    public function contractEir(): BelongsTo
    {
        return $this->belongsTo(ContractEir::class, 'contract_id', 'contract_id');
    }
}
