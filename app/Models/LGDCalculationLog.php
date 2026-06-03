<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class LGDCalculationLog extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lgd_calculation_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'start_period',
        'end_period',
        'portfolio_group',
        'start_time',
        'end_time',
        'duration_seconds',
        'total_contracts_processed',
        'total_records_generated',
        'total_payments_detected',
        'total_cured_contracts',
        'total_defaulted_amount',
        'status',
        'triggered_by',
        'parent_calculation_id',
        'recalculation_reason'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_period' => 'date:Y-m-d',
        'end_period' => 'date:Y-m-d',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_seconds' => 'integer',
        'total_contracts_processed' => 'integer',
        'total_records_generated' => 'integer',
        'total_payments_detected' => 'decimal:2',
        'total_cured_contracts' => 'integer',
        'total_defaulted_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<int, string>
     */
    protected $dates = [
        'start_period',
        'end_period',
        'start_time',
        'end_time',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    /**
     * Get all payment tracking records from this calculation.
     */
    public function paymentRecords(): HasMany
    {
        return $this->hasMany(LGDPaymentTrackingLong::class, 'calculation_id', 'id');
    }

    /**
     * Get the portfolio for this calculation.
     */
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(LoanPortfolio::class, 'portfolio_group', 'id');
    }

    /**
     * Get the user who triggered this calculation.
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by', 'id');
    }

    /**
     * Get the parent calculation (if this is a recalculation).
     */
    public function parentCalculation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_calculation_id', 'id');
    }

    /**
     * Get child recalculations.
     */
    public function childCalculations(): HasMany
    {
        return $this->hasMany(self::class, 'parent_calculation_id', 'id');
    }

    // =====================================================
    // Scopes
    // =====================================================

    /**
     * Scope a query to only include completed calculations.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include failed calculations.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include processing calculations.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope a query to only include pending calculations.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include calculations for a specific portfolio.
     */
    public function scopeForPortfolio($query, $portfolioId)
    {
        return $query->where('portfolio_group', $portfolioId);
    }

    /**
     * Scope a query to only include calculations in a date range.
     */
    public function scopeInPeriodRange($query, $start, $end)
    {
        return $query->where('start_period', '>=', $start)
                     ->where('end_period', '<=', $end);
    }

    /**
     * Scope a query to only include calculations triggered by a specific source.
     */
    public function scopeTriggeredBySource($query, $source)
    {
        return $query->where('trigger_source', $source);
    }

    /**
     * Scope a query to order by most recent first.
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // =====================================================
    // Accessors & Mutators
    // =====================================================

    /**
     * Get the duration in a human-readable format.
     */
    public function getDurationHumanAttribute(): string
    {
        if (!$this->duration_seconds) {
            return 'N/A';
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;

        if ($minutes > 0) {
            return "{$minutes}m {$seconds}s";
        }

        return "{$seconds}s";
    }

    /**
     * Get the status with badge color for UI.
     */
    public function getStatusBadgeAttribute(): array
    {
        $hasBeenRecalculated = $this->childCalculations()->exists();
        $isRecalculation = $this->parent_calculation_id !== null;

        $baseStatus = match($this->status) {
            'completed' => 'Completed',
            'failed' => 'Failed',
            'processing' => 'Processing',
            'pending' => 'Pending',
            default => ucfirst($this->status)
        };

        $label = $baseStatus;
        $color = match($this->status) {
            'completed' => 'green',
            'failed' => 'red',
            'processing' => 'yellow',
            'pending' => 'gray',
            default => 'blue'
        };

        // Add recalculated indicator
        if ($this->status === 'completed' && $hasBeenRecalculated) {
            $label = 'Completed (Recalculated)';
            $color = 'orange'; // Use orange to indicate it's been superseded
        }

        if ($isRecalculation) {
            $label = $baseStatus . ' (Recalculation)';
        }

        return ['label' => $label, 'color' => $color];
    }

    /**
     * Check if this calculation has been recalculated.
     */
    public function getHasBeenRecalculatedAttribute(): bool
    {
        return $this->childCalculations()->exists();
    }

    /**
     * Check if this calculation is a recalculation.
     */
    public function getIsRecalculationAttribute(): bool
    {
        return $this->parent_calculation_id !== null;
    }

    /**
     * Get the trigger source badge.
     */
    public function getTriggerSourceBadgeAttribute(): array
    {
        return match($this->trigger_source) {
            'manual' => ['label' => 'Manual', 'color' => 'blue'],
            'scheduled' => ['label' => 'Scheduled', 'color' => 'purple'],
            'api' => ['label' => 'API', 'color' => 'orange'],
            default => ['label' => ucfirst($this->trigger_source), 'color' => 'gray']
        };
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    /**
     * Start the calculation.
     */
    public function startCalculation(): self
    {
        $this->start_time = now();
        $this->status = 'processing';
        $this->save();

        return $this;
    }

    /**
     * Complete the calculation with summary data.
     */
    public function completeCalculation(
        int $totalContracts,
        int $totalRecords,
        float $totalPayments,
        int $totalCured,
        float $totalDefaulted
    ): self {
        $this->end_time = now();
        $this->duration_seconds = $this->start_time ?
            $this->start_time->diffInSeconds($this->end_time) : null;
        $this->status = 'completed';
        $this->total_contracts_processed = $totalContracts;
        $this->total_records_generated = $totalRecords;
        $this->total_payments_detected = $totalPayments;
        $this->total_cured_contracts = $totalCured;
        $this->total_defaulted_amount = $totalDefaulted;
        $this->save();

        return $this;
    }

    /**
     * Mark the calculation as failed.
     */
    public function fail(string $errorMessage): self
    {
        $this->end_time = now();
        $this->duration_seconds = $this->start_time ?
            $this->start_time->diffInSeconds($this->end_time) : null;
        $this->status = 'failed';
        //$this->error_message = $errorMessage;
        $this->save();

        return $this;
    }

    /**
     * Create a recalculation based on this calculation.
     */
    public function createRecalculation(string $reason, ?int $triggeredBy = null): self
    {
        $recalculation = self::create([
            'start_period' => $this->start_period,
            'end_period' => $this->end_period,
            'portfolio_group' => $this->portfolio_group,
            'status' => 'pending',
            'triggered_by' => $triggeredBy,
            'parent_calculation_id' => $this->id,
            'recalculation_reason' => $reason,
            'trigger_source' => 'manual'
        ]);

        return $recalculation;
    }

    /**
     * Get the summary statistics.
     */
    public function getSummaryAttribute(): array
    {
        return [
            'total_contracts' => $this->total_contracts_processed,
            'total_records' => $this->total_records_generated,
            'total_payments' => number_format($this->total_payments_detected, 2),
            'total_cured' => $this->total_cured_contracts,
            'total_defaulted' => number_format($this->total_defaulted_amount, 2),
            'cure_rate' => $this->total_defaulted_amount > 0
                ? round(($this->total_cured_contracts / $this->total_contracts_processed) * 100, 2) . '%'
                : '0%',
            'recovery_rate' => $this->total_defaulted_amount > 0
                ? round(($this->total_payments_detected / $this->total_defaulted_amount) * 100, 2) . '%'
                : '0%'
        ];
    }

    /**
     * Get recommended cure rate based on actual payments.
     * Uses cohort period balance (when contract first entered stage 3) for accurate LGD calculation.
     */
    public function getRecommendedCureRateAttribute(): string
    {
        // Get balance at cohort period (when contract first entered stage 3)
        $stage3StartingBalance = \App\Models\LGDPaymentTrackingLong::fromCalculation($this->id)
            ->whereNotNull('cohort_period')
            ->whereRaw('reporting_period = cohort_period')
            ->where('ifrs9_stage', 3)
            ->sum('starting_balance');

        $curedBalance = \App\Models\LGDPaymentTrackingLong::fromCalculation($this->id)
            ->where('is_cured', true)
            ->where('payment_amount', '>', 0)
            ->sum('starting_balance');

        return $stage3StartingBalance > 0
            ? round(($curedBalance / $stage3StartingBalance) * 100, 2) . '%'
            : '0%';
    }

    /**
     * Get balance-based cure rate.
     * Uses cohort period balance (when contract first entered stage 3) for accurate LGD calculation.
     */
    public function getBalanceBasedCureRateAttribute(): string
    {
        // Get balance at cohort period (when contract first entered stage 3)
        $stage3StartingBalance = \App\Models\LGDPaymentTrackingLong::fromCalculation($this->id)
            ->whereNotNull('cohort_period')
            ->whereRaw('reporting_period = cohort_period')
            ->where('ifrs9_stage', 3)
            ->sum('starting_balance');

        $curedBalance = \App\Models\LGDPaymentTrackingLong::fromCalculation($this->id)
            ->where('is_cured', true)
            ->whereRaw('ending_balance < starting_balance')
            ->sum('starting_balance');

        return $stage3StartingBalance > 0
            ? round(($curedBalance / $stage3StartingBalance) * 100, 2) . '%'
            : '0%';
    }
}
