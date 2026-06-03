<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function probabilities(): HasMany
    {
        return $this->hasMany(ScenarioProbability::class)->orderBy('order_position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fliParameters(): HasMany
    {
        return $this->hasMany(FliReportingPeriodParameter::class);
    }

    public function fliAdjustments(): HasMany
    {
        return $this->hasMany(FliAdj::class);
    }

    // Validation: Check if probabilities sum to 100%
    public function hasValidProbabilities(): bool
    {
        return abs($this->probabilities->sum('probability') - 100.00) < 0.01;
    }
}
