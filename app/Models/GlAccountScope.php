<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What a GL code in MAIIC's ledgers IS (spec §3.4.3, §3.4.4).
 *
 * Reference data, not transaction data. It exists so a control total can say
 * "loan interest" rather than "income accounts": MAIIC's 4xxx range includes
 * T-bill and USD call income (4205/4211/4213) which is explicitly outside the
 * EIR (§3.4.3). Summing the range would inflate the ledger side and manufacture
 * a variance the engine can never explain, because it is not real.
 */
class GlAccountScope extends Model
{
    protected $table = 'gl_account_scope';

    protected $fillable = [
        'gl_code', 'gl_title', 'chart', 'quickbooks_code', 'statement', 'normal_balance',
        'category', 'eir_door', 'in_eir_scope', 'portfolio', 'retired', 'notes',
    ];

    protected $casts = [
        'eir_door' => 'integer',
        'in_eir_scope' => 'boolean',
        'retired' => 'boolean',
    ];

    /**
     * Whether a code's balance accumulates through the year.
     *
     * Falls back to the code prefix rather than failing, so a GL code that
     * appears for the first time in next month's file is still differenced
     * correctly before anyone has classified it. Getting this wrong silently
     * is the single most damaging thing this ingestion can do (§3.4.1), so the
     * unknown case resolves to a rule rather than to a guess.
     */
    public static function isProfitAndLossCode(string $glCode): bool
    {
        return in_array($glCode[0] ?? '', ['4', '5', '6'], true);
    }

    /** Income, liabilities and equity accrue on the credit side; assets and expenses on the debit side. */
    public static function defaultNormalBalance(string $glCode): string
    {
        return in_array($glCode[0] ?? '', ['2', '3', '4'], true) ? 'CR' : 'DR';
    }

    public function scopeInEirScope($query)
    {
        return $query->where('in_eir_scope', true)->where('retired', false);
    }
}
