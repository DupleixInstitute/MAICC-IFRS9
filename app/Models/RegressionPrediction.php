<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\RegressionModel;
use App\Models\ScenarioProfiles;

class RegressionPrediction extends Model
{
    use HasFactory;
  protected $table = 'regression_predictions';
    
    protected $fillable = [
        'model_id',
        'scenario_id',
        'period',
        'pred_value',
        'ci_lower',
        'ci_upper',
        'actual_value',
        'error',
        'is_actual',
        'macro_data_used',
    ];

    protected $casts = [
        'pred_value' => 'decimal:6',
        'ci_lower' => 'decimal:6',
        'ci_upper' => 'decimal:6',
        'actual_value' => 'decimal:6',
        'error' => 'decimal:6',
        'is_actual' => 'boolean',
        'macro_data_used' => 'array',
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(RegressionModel::class, 'model_id');
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(ScenarioProfiles::class);
    }

}
