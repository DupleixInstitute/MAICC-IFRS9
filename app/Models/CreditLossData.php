<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLossData extends Model
{
    use HasFactory;
    protected $table = 'macro_credit_loss_data';

    protected $fillable = [
        'portfolio_id',
        'period',
        'ecl_value',
        'npl_value',
        'pd_value',
        'lgd_value',
        'ead_value',
        'stage',
        'credit_rating',
        'provision_value',
        'write_off_value',
        'recovery_value',
        'migration_matrix',
        'scenario_profile_id',
        'scenario_id',
        'created_by',
        'source',
        'notes'
    ];

    protected $casts = [
        'period' => 'date:Y-m',
        'ecl_value' => 'decimal:8',
        'npl_value' => 'decimal:8',
        'pd_value' => 'decimal:6',
        'lgd_value' => 'decimal:6',
        'ead_value' => 'decimal:8',
        'migration_matrix' => 'array',
        'is_forecast' => 'boolean',
    ];

    // Relationships
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(LoanPortfolio::class, 'portfolio_id');
    }

    public function scenarioProfile(): BelongsTo
    {
        return $this->belongsTo(ScenarioProfiles::class);
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenarios::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}