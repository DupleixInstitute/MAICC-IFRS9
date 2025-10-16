<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MacroStatsDefinition extends Model
{
    use HasFactory;

    
    protected $table = 'macro_statistics';

    protected $fillable = [
        'statistic_code',
        'statistic_name',
        'statistic_description',
        'unit',
        'frequency',
        'periodic_interval',
        'historical_periods',
        'forecasting_periods',
        'comments',
        'data_source',
        'website_link',
        'is_active',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(MacroStatsValue::class, 'macro_stat_definition_id');
    }
}
