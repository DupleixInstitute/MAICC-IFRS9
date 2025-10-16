<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class MacroForecastWeighted extends Model
{
    protected $table = 'macro_forecast_weighted';

    protected $fillable = [
        'macro_statistic_id',
        'scenario_profile_id',
        'reporting_period_id',
        'start_period',
        'end_period',
        'weighted_value',
        'revision', 
        'is_current', 
        'standard_deviation', 
        'confidence_level',
    ];

    protected $casts = [
        'start_period' => 'date',
        'end_period' => 'date',
        'weighted_value' => 'decimal:4',
        'revision' => 'integer', 
        'is_current' => 'boolean', 
        'standard_deviation' => 'decimal:4', 
        'confidence_level' => 'decimal:2', 
    ];

    
    protected function confidenceInterval(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->weighted_value || !$this->standard_deviation) {
                    return null;
                }
                
                $lower = $this->weighted_value - (1.96 * $this->standard_deviation);
                $upper = $this->weighted_value + (1.96 * $this->standard_deviation);
                
                return [
                    'lower' => round($lower, 4),
                    'upper' => round($upper, 4),
                    'display' => round($lower, 2) . ' to ' . round($upper, 2)
                ];
            },
        );
    }

    
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    
    public function scopeWithConfidence($query, $level = 0.95)
    {
        return $query->where('confidence_level', '>=', $level);
    }

    public function macroVariable()
    {
        return $this->belongsTo(MacroStatsDefinition::class);
    }

    public function macroStatistic()
    {
        return $this->belongsTo(MacroStatsDefinition::class, 'macro_statistic_id');
    }

    public function scenarioProfile()
    {
        return $this->belongsTo(ScenarioProfiles::class,'scenario_profile_id');
    }

    public function reportingPeriod()
    {
        return $this->belongsTo(ReportingPeriods::class, 'reporting_period_id');
    }

}