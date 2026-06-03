<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FliReportingPeriodParameter extends Model
{
    use HasFactory;

    protected $table = 'fli_reporting_periods_parameters';

    protected $fillable = [
        'reporting_period',
        'scenario_set_id',
        'number_of_forecasting_periods',
        'forecasting_period_length_months',
        'economic_data_statistic',
        'pd_proxy_statistic',
        'base_forecast_period',
        'base_macro_data_value',
        'base_pd_proxy_value',
        'regression_slope',
        'regression_intercept',
        'created_by',
    ];

    protected $casts = [
        'reporting_period' => 'date',
        'base_forecast_period' => 'date',
        'base_macro_data_value' => 'decimal:6',
        'base_pd_proxy_value' => 'decimal:6',
        'regression_slope' => 'decimal:6',
        'regression_intercept' => 'decimal:6',
    ];

    public function scenarioSet(): BelongsTo
    {
        return $this->belongsTo(ScenarioSet::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
