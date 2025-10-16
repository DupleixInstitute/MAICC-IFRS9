<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\LoanPortfolio;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LossGivenDefault extends Model
{
    use HasFactory, softDeletes;
    protected $table = 'loss_given_default';
    protected $fillable = [
        'reporting_period',
        'start_period',
        'portfolio_group',
        'start_total_stage3',
        'end_total_stage3',
        'loss_given_default_percentage',
        'cured_amount',
        'cure_rate',
        'cure_rate_average_monthly',
        'cure_amount_stage1',
        'cure_amount_stage2',
        'partially_recovered_amount',
        'fully_recovered_amount',
        'recovered_amount',
        'recovery_rate',
        'recovery_rate_average_monthly',
        'total_disbursments',
        'last_reporting_period',
        'is_active_or_closed',
        'calculation_source',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'reporting_period' => 'date',
        'start_period' => 'date',
        'last_reporting_period' => 'date',
        'is_active_or_closed' => 'string',
        'calculation_source' => 'string',
    ];
    /**
     * Get the portfolio group associated with the loss given default.
     *
     * @return BelongsTo
     */
    public function portfolioGroup()
    {
        return $this->belongsTo(LoanPortfolio::class, 'portfolio_group');
}
}