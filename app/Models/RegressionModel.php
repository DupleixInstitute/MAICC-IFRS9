<?php
// app/Models/RegressionModel.php

namespace App\Models;

use App\Models\CreditLossDefinition;
use App\Models\MacroStatsDefinition;
use App\Models\RegressionPrediction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegressionModel extends Model
{
    protected $fillable = [
        'name',
        'type', 
        'portfolio_id',
        'dep_var_id', 
        'indep_vars',
        'coeffs',
        'r_squared',
        'adj_r_squared',
        'stats',
        'train_start',
        'train_end',
        'train_periods',
        'is_active',
        'is_approved',
        'validation_notes',
        'created_by',
    ];

    protected $casts = [
        'indep_vars' => 'array',
        'coeffs' => 'array',
        'stats' => 'array',
        'train_start' => 'date',
        'train_end' => 'date',
        'is_active' => 'boolean',
        'is_approved' => 'boolean',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(LoanPortfolio::class);
    }

    public function dependentVariable(): BelongsTo
    {
        return $this->belongsTo(CreditLossDefinition::class, 'dep_var_id');
    }

      public function predictions(): HasMany
    {
        return $this->hasMany(RegressionPrediction::class, 'model_id');
    }

    public function macroStatistics()
    {
        return MacroStatsDefinition::whereIn('id', $this->indep_vars ?? [])->get();
    }



    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get independent variable names
     */
    public function getIndependentVariableNames(): array
    {
        return MacroStatsDefinition::whereIn('id', $this->independent_variables)
            ->pluck('statistic_name', 'id')
            ->toArray();
    }

    /**
     * Check if model meets validation criteria
     */
    public function meetsValidationCriteria(): bool
    {
        return $this->r_squared >= 0.7 && 
               $this->adjusted_r_squared >= 0.65 &&
               $this->training_periods >= 24; // At least 2 years of data
    }

    // Backward-compatibility accessors for older attribute names used in code
    public function getIndependentVariablesAttribute()
    {
        return $this->indep_vars ?? [];
    }

    public function getCoefficientsAttribute()
    {
        return $this->coeffs;
    }

    public function getAdjustedRSquaredAttribute()
    {
        return $this->adj_r_squared;
    }

    public function getStatisticsAttribute()
    {
        return $this->stats;
    }

    public function getTrainingPeriodsAttribute()
    {
        return $this->train_periods;
    }
}