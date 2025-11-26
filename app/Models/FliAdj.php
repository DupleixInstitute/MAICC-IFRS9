<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FliAdj extends Model
{
    use HasFactory;

    protected $table = 'fli_adj';

    protected $fillable = [
        'reporting_period',
        'scenario_set_id',
        'forecast_period',
        'forecast_window_in_months',
        'weighted_macro_data_value',
        'predicted_value',
        'fli_adj',
        'created_by',
    ];

    protected $casts = [
        'reporting_period' => 'date',
        'forecast_period' => 'date',
        'weighted_macro_data_value' => 'decimal:6',
        'predicted_value' => 'decimal:6',
        'fli_adj' => 'decimal:6',
    ];

    public function scenarioSet(): BelongsTo
    {
        return $this->belongsTo(ScenarioSet::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Calculate predicted value using regression formula
    public static function calculatePredictedValue(float $macroValue, float $slope, float $intercept): float
    {
        return ($slope * $macroValue) + $intercept;
    }

    // Calculate FLI adjustment
    public static function calculateFliAdj(float $predictedValue, float $basePredictedValue): float
    {
        if ($basePredictedValue == 0) {
            return 0;
        }
        return ($predictedValue / $basePredictedValue) - 1;
    }
}
