<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MacroVariable extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'unit',
        'frequency',
        'source',
        'description',
        'is_active',
        'icaap_selected',
        'sort_order',
        'shock_direction',
        'default_mild_shock',
        'default_severe_shock',
        'shock_unit',
        'external_codes',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'icaap_selected'       => 'boolean',
        'sort_order'           => 'integer',
        'default_mild_shock'   => 'decimal:4',
        'default_severe_shock' => 'decimal:4',
        'external_codes'       => 'array',
    ];

    /**
     * The macro indicators chosen as the institution's ICAAP macro "house view"
     * for the cycle (icaap_selected). These drive the ICAAP macro tables; their
     * VALUES come from the latest imported observations, not from this flag. The
     * set is user-selectable on the Macro page / seeded by IcaapMacroSetSeeder.
     */
    public function scopeIcaapSelected(Builder $query): Builder
    {
        return $query->where('icaap_selected', true)->where('is_active', true);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(MacroObservation::class);
    }

    public function latestObservation()
    {
        return $this->hasOne(MacroObservation::class)->latestOfMany('period_date');
    }
}
