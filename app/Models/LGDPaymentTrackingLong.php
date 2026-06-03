<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class LGDPaymentTrackingLong extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lgd_payment_tracking_long';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'contract_id',
        'portfolio_group',
        'reporting_period',
        'cohort_period',
        'payment_period',
        'starting_balance',
        'ending_balance',
        'payment_amount',
        'cumulative_payments',
        'payment_type',
        'ifrs9_stage',
        'months_since_default',
        'is_cured',
        'cure_stage',
        'calculation_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'reporting_period' => 'date:Y-m-d',
        'cohort_period' => 'date:Y-m-d',
        'payment_period' => 'date:Y-m-d',
        'starting_balance' => 'decimal:2',
        'ending_balance' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'cumulative_payments' => 'decimal:2',
        'is_cured' => 'boolean',
        'months_since_default' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<int, string>
     */
    protected $dates = [
        'reporting_period',
        'cohort_period',
        'payment_period',
        'created_at',
        'updated_at'
    ];

    /**
     * The default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'payment_type' => 'none',
        'months_since_default' => 0,
        'is_cured' => false,
        'starting_balance' => 0.00,
        'ending_balance' => 0.00,
        'payment_amount' => 0.00,
        'cumulative_payments' => 0.00
    ];

    // =====================================================
    // Relationships
    // =====================================================

    /**
     * Get the portfolio that owns this record.
     */
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(LoanPortfolio::class, 'portfolio_group', 'id');
    }

    /**
     * Get the calculation log that created this record.
     */
    public function calculation(): BelongsTo
    {
        return $this->belongsTo(LGDCalculationLog::class, 'calculation_id', 'id');
    }

    // =====================================================
    // Scopes
    // =====================================================

    /**
     * Scope a query to only include records from a specific calculation.
     */
    public function scopeFromCalculation($query, $calculationId)
    {
        return $query->where('calculation_id', $calculationId);
    }

    /**
     * Scope a query to only include records with payments.
     */
    public function scopeWithPayments($query)
    {
        return $query->where('payment_amount', '>', 0);
    }

    /**
     * Scope a query to only include records without payments.
     */
    public function scopeWithoutPayments($query)
    {
        return $query->where('payment_amount', '=', 0);
    }

    /**
     * Scope a query to only include cured contracts.
     */
    public function scopeCured($query)
    {
        return $query->where('is_cured', true);
    }

    /**
     * Scope a query to only include non-cured contracts.
     */
    public function scopeNotCured($query)
    {
        return $query->where('is_cured', false);
    }

    /**
     * Scope a query to only include Stage 3 contracts.
     */
    public function scopeStage3($query)
    {
        return $query->where('ifrs9_stage', '3');
    }

    /**
     * Scope a query to only include Stage 2 contracts.
     */
    public function scopeStage2($query)
    {
        return $query->where('ifrs9_stage', '2');
    }

    /**
     * Scope a query to only include Stage 1 contracts.
     */
    public function scopeStage1($query)
    {
        return $query->where('ifrs9_stage', '1');
    }

    /**
     * Scope a query to only include records for a specific contract.
     */
    public function scopeForContract($query, $contractId)
    {
        return $query->where('contract_id', $contractId);
    }

    /**
     * Scope a query to only include records for a specific portfolio.
     */
    public function scopeForPortfolio($query, $portfolioId)
    {
        return $query->where('portfolio_group', $portfolioId);
    }

    /**
     * Scope a query to only include records in a specific reporting period.
     */
    public function scopeInReportingPeriod($query, $period)
    {
        return $query->where('reporting_period', $period);
    }

    /**
     * Scope a query to only include records in a reporting period range.
     */
    public function scopeInReportingPeriodRange($query, $start, $end)
    {
        return $query->whereBetween('reporting_period', [$start, $end]);
    }

    /**
     * Scope a query to only include records from a specific cohort.
     */
    public function scopeInCohort($query, $cohortPeriod)
    {
        return $query->where('cohort_period', $cohortPeriod);
    }

    /**
     * Scope a query to order by contract and period.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('contract_id')->orderBy('reporting_period');
    }

    // =====================================================
    // Accessors & Mutators
    // =====================================================

    /**
     * Get the reporting period in Y-m format.
     */
    public function getReportingPeriodYearMonthAttribute(): string
    {
        return $this->reporting_period ? Carbon::parse($this->reporting_period)->format('Y-m') : '';
    }

    /**
     * Get the cohort period in Y-m format.
     */
    public function getCohortPeriodYearMonthAttribute(): string
    {
        return $this->cohort_period ? Carbon::parse($this->cohort_period)->format('Y-m') : '';
    }

    /**
     * Get the payment amount with currency formatting.
     */
    public function getPaymentAmountFormattedAttribute(): string
    {
        return number_format($this->payment_amount, 2);
    }

    /**
     * Get the balance with currency formatting.
     */
    public function getBalanceFormattedAttribute(): string
    {
        return number_format($this->ending_balance, 2);
    }

    /**
     * Get the payment type with badge color for UI.
     */
    public function getPaymentTypeBadgeAttribute(): array
    {
        return match($this->payment_type) {
            'full' => ['label' => 'Full Payment', 'color' => 'green'],
            'partial' => ['label' => 'Partial Payment', 'color' => 'yellow'],
            'none' => ['label' => 'No Payment', 'color' => 'gray'],
            default => ['label' => ucfirst($this->payment_type), 'color' => 'blue']
        };
    }

    /**
     * Get the stage with badge color.
     */
    public function getStageBadgeAttribute(): array
    {
        return match($this->ifrs9_stage) {
            '1' => ['label' => 'Stage 1', 'color' => 'green'],
            '2' => ['label' => 'Stage 2', 'color' => 'yellow'],
            '3' => ['label' => 'Stage 3', 'color' => 'red'],
            default => ['label' => $this->ifrs9_stage ?? 'N/A', 'color' => 'gray']
        };
    }

    /**
     * Get the cured status badge.
     */
    public function getCuredBadgeAttribute(): array
    {
        if (!$this->is_cured) {
            return ['label' => 'Not Cured', 'color' => 'gray'];
        }

        return [
            'label' => "Cured (Stage {$this->cure_stage})",
            'color' => $this->cure_stage === '1' ? 'green' : 'blue'
        ];
    }

    /**
     * Check if this record shows a payment.
     */
    public function getHasPaymentAttribute(): bool
    {
        return $this->payment_amount > 0;
    }

    /**
     * Get the percentage of original balance paid.
     */
    public function getPercentPaidAttribute(): float
    {
        if ($this->starting_balance <= 0) {
            return 0;
        }

        return round(($this->cumulative_payments / $this->starting_balance) * 100, 2);
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    /**
     * Calculate months since default based on dates.
     */
    public function calculateMonthsSinceDefault(): int
    {
        if (!$this->cohort_period || !$this->reporting_period) {
            return 0;
        }

        $cohort = Carbon::parse($this->cohort_period);
        $reporting = Carbon::parse($this->reporting_period);

        return $cohort->diffInMonths($reporting);
    }

    /**
     * Check if this record is the first default period.
     */
    public function isFirstDefaultPeriod(): bool
    {
        return $this->cohort_period &&
               $this->cohort_period->format('Y-m') === $this->reporting_period->format('Y-m');
    }

    /**
     * Get the balance change from previous period.
     */
    public function getBalanceChangeAttribute(): float
    {
        return $this->ending_balance - $this->starting_balance;
    }

    /**
     * Determine if balance decreased (payment made).
     */
    public function balanceDecreased(): bool
    {
        return $this->ending_balance < $this->starting_balance;
    }

    /**
     * Determine if balance increased (disbursement).
     */
    public function balanceIncreased(): bool
    {
        return $this->ending_balance > $this->starting_balance;
    }
}
