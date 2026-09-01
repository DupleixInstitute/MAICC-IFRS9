<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One GL code's balance in one monthly trial balance, as delivered (§3.4).
 *
 * Balances only — never movements. The P&L figures in these files are
 * cumulative year-to-date (§3.4.1), so a row here is a running total, not a
 * month. Anything wanting a month must go through TrialBalanceMovementService.
 */
class GlTrialBalanceLine extends Model
{
    protected $table = 'gl_trial_balance_lines';

    public const BASIS_PRECLOSING = 'PRECLOSING';
    public const BASIS_POSTCLOSING = 'POSTCLOSING';

    protected $fillable = [
        'period', 'source_period_stamp', 'gl_code', 'gl_title',
        'debit', 'credit', 'basis', 'source_file', 'source_sheet',
    ];

    protected $casts = [
        'period' => 'date',
        'source_period_stamp' => 'date',
        'debit' => 'float',
        'credit' => 'float',
    ];

    public function scope(): BelongsTo
    {
        return $this->belongsTo(GlAccountScope::class, 'gl_code', 'gl_code');
    }

    /**
     * The balance signed so a normal balance reads positive.
     *
     * Income accounts net credits less debits, expenses the other way round.
     * Without this, 6242 Impairment reads as a large negative next to 4216
     * Interest reading positive, and any total spanning both is meaningless.
     */
    public function signedBalance(string $normalBalance = 'CR'): float
    {
        return $normalBalance === 'CR'
            ? (float) $this->credit - (float) $this->debit
            : (float) $this->debit - (float) $this->credit;
    }
}
