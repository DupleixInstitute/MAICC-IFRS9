<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A move of one loan's rate (phase P5). One row per event, and two events on
 * one date when the prime rate and the spread both move (spec v4 P5: a spread
 * change is a separate event, classified on its own under O17).
 *
 * Two owners, two states on the same row (reconciliation of 14 October 2026):
 *  - the reset pipeline (ResetDetectionService, ResetScheduleService) owns
 *    `status`: DETECTED, SCHEDULED (a Reset schedule version is built),
 *    SOLVED, BLOCKED (the pipeline cannot go on; `note` says why);
 *  - the register (RateResetService) owns `classification_status`: DETECTED,
 *    PROPOSED, APPROVED, one person proposing in-contract or negotiated and a
 *    different person approving, with the loan-book evidence on the row.
 *
 * Neither state sets the EIR: a floating loan's EIR is decision D38's monthly
 * re-estimation at the rate charged (EirRevenueService::eirInForce).
 *
 * The reference rates, the spreads and the charged rates (old_rate, new_rate)
 * are in percentage points; the contractual rates are fractions, as on
 * contract_eir.
 */
class RateResetEvent extends Model
{
    public const STATUS_DETECTED = 'DETECTED';
    public const STATUS_SCHEDULED = 'SCHEDULED';
    public const STATUS_SOLVED = 'SOLVED';
    public const STATUS_BLOCKED = 'BLOCKED';

    public const TYPE_PLR_RESET = 'PLR_RESET';
    public const TYPE_SPREAD_CHANGE = 'SPREAD_CHANGE';

    public const CLASSIFICATION_DETECTED = 'DETECTED';
    public const CLASSIFICATION_PROPOSED = 'PROPOSED';
    public const CLASSIFICATION_APPROVED = 'APPROVED';

    protected $table = 'rate_reset_events';

    protected $fillable = [
        'contract_id',
        'reset_date',
        'plr_effective_date',
        'index_code',
        'old_reference_rate',
        'new_reference_rate',
        'spread_over_prime',
        'old_contractual_rate',
        'new_contractual_rate',
        'new_schedule_version',
        'status',
        'note',
        'recorded_by',
        // the register
        'detection_key',
        'account',
        'reporting_period',
        'event_type',
        'old_rate',
        'new_rate',
        'old_spread',
        'new_spread',
        'evidence',
        'suggested_classification',
    ];

    protected $casts = [
        'reset_date'           => 'date',
        'plr_effective_date'   => 'date',
        'old_reference_rate'   => 'float',
        'new_reference_rate'   => 'float',
        'spread_over_prime'    => 'float',
        'old_contractual_rate' => 'float',
        'new_contractual_rate' => 'float',
        'new_schedule_version' => 'integer',
        'old_rate'             => 'float',
        'new_rate'             => 'float',
        'old_spread'           => 'float',
        'new_spread'           => 'float',
    ];

    public function contractEir(): BelongsTo
    {
        return $this->belongsTo(ContractEir::class, 'contract_id', 'contract_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
