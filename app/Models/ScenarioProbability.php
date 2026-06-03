<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioProbability extends Model
{
    use HasFactory;

    protected $fillable = [
        'scenario_set_id',
        'scenario_name',
        'probability',
        'order_position',
    ];

    protected $casts = [
        'probability' => 'decimal:2',
    ];

    public function scenarioSet(): BelongsTo
    {
        return $this->belongsTo(ScenarioSet::class);
    }
}
