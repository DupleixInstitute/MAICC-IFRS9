<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class MacroStatsValue extends Model
{
    protected $table = 'macro_statistics_data';
    
    protected $fillable = [
        'macro_stat_definition_id',
        'scenario_profile_id', 
        'period',
        'value',
        'actual_value', 
        'is_forecast',
        'is_fli', 
        'confidence_interval_lower', 
        'confidence_interval_upper', 
        'scenario_id',
        'source',
        'notes',
        'key_assumptions', 
        'sensitivity_analysis', 
        'created_by'
    ];

    protected $casts = [
        'period' => 'string',
        'is_forecast' => 'boolean',
        'is_fli' => 'boolean', 
        'value' => 'decimal:4',
        'actual_value' => 'decimal:4', 
        'confidence_interval_lower' => 'decimal:4', 
        'confidence_interval_upper' => 'decimal:4',
    ];

    protected function confidenceInterval(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->confidence_interval_lower && $this->confidence_interval_upper 
                ? "{$this->confidence_interval_lower} to {$this->confidence_interval_upper}"
                : null,
        );
    }

    protected function isActual(): Attribute
    {
        return Attribute::make(
            get: fn () => !$this->is_forecast && !$this->is_fli,
        );
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(MacroStatsDefinition::class, 'macro_stat_definition_id');
    }

    public function profiles(): BelongsTo
    {
        return $this->belongsTo(ScenarioProfiles::class,'profile_id');
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenarios::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // NEW: Scope for FLI items
    public function scopeForwardLooking($query)
    {
        return $query->where('is_fli', true)->orWhere('is_forecast', true);
    }

    // NEW: Scope for actual results
    public function scopeActuals($query)
    {
        return $query->where('is_forecast', false)->where('is_fli', false);
    }
}