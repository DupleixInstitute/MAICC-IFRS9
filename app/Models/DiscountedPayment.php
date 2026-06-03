<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountedPayment extends Model
{
    use HasFactory;

    protected $table = 'discounted_payments';

    protected $fillable = [
        'contract_id',
        'lgd_id',
        'reporting_period',
        'payment_period',
        'interest_rate',
        'discounting_days',
        'discount_rate_source',
        'payment_type',
        'payment_amount',
        'discounted_amount',
        'discounted_loss',
    ];

    protected $casts = [
        'reporting_period' => 'date',
        'payment_period' => 'date',
        'interest_rate' => 'decimal:4',
        'payment_amount' => 'decimal:2',
        'discounted_amount' => 'decimal:2',
        'discounted_loss' => 'decimal:2',
        'discounting_days' => 'integer',
    ];

    /**
     * Get the LGD record that owns the discounted payment.
     */
    public function lossGivenDefault(): BelongsTo
    {
        return $this->belongsTo(LossGivenDefault::class, 'lgd_id');
    }

    /**
     * Scope to get payments by LGD ID.
     */
    public function scopeByLgd($query, $lgdId)
    {
        return $query->where('lgd_id', $lgdId);
    }

    /**
     * Scope to get payments by contract ID.
     */
    public function scopeByContract($query, $contractId)
    {
        return $query->where('contract_id', $contractId);
    }

    /**
     * Scope to get payments by discount rate source.
     */
    public function scopeByRateSource($query, $source)
    {
        return $query->where('discount_rate_source', $source);
    }

    /**
     * Calculate discounted amount using the formula: payment / (1 + interest_rate)^(days/365)
     */
    public static function calculateDiscountedAmount(float $paymentAmount, float $interestRate, int $days): float
    {
        // Cap discounting days at 3650 (10 years)
        $cappedDays = min($days, 3650);

        if ($cappedDays <= 0 || $interestRate <= 0) {
            return $paymentAmount;
        }

        $exponent = $cappedDays / 365;
        $discountFactor = pow(1 + $interestRate, $exponent);

        return $paymentAmount / $discountFactor;
    }

    /**
     * Calculate discount loss: payment_amount - discounted_amount
     */
    public function calculateDiscountLoss(): float
    {
        return $this->payment_amount - $this->discounted_amount;
    }

    /**
     * Upsert discounted payment record.
     */
    public static function upsertDiscountPayment(array $data): self
    {
        return self::updateOrCreate(
            [
                'contract_id' => $data['contract_id'],
                'reporting_period' => $data['reporting_period'],
                'payment_period' => $data['payment_period'],
            ],
            $data
        );
    }
}
