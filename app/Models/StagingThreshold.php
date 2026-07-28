<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StagingThreshold extends Model
{
    protected $table = 'staging_thresholds';

    protected $fillable = [
        'facility_class',
        'min_tenor_months',
        'stage2_dpd',
        'stage3_dpd',
        'rebuttal_basis',
        'effective_from',
    ];

    protected $casts = [
        'min_tenor_months' => 'integer',
        'stage2_dpd'       => 'integer',
        'stage3_dpd'       => 'integer',
        'effective_from'   => 'date',
    ];

    /**
     * The threshold row governing a facility: the most specific class whose
     * minimum tenor the loan meets, latest effective_from wins. Falls back
     * to DEFAULT so staging never silently loses its rule set.
     *
     * Future-dated rules are inactive: a rule awaiting sign-off (e.g. the
     * RBM-based 90-day long-tenor backstop) is seeded with a far-future
     * effective_from and only governs once that date is brought forward.
     */
    public static function forFacility(?string $facilityClass, int $tenorMonths): ?self
    {
        return static::query()
            ->whereIn('facility_class', array_filter([$facilityClass, 'DEFAULT']))
            ->where('min_tenor_months', '<=', $tenorMonths)
            ->whereDate('effective_from', '<=', now())
            ->orderByRaw("facility_class = 'DEFAULT'")
            ->orderByDesc('min_tenor_months')
            ->orderByDesc('effective_from')
            ->first();
    }
}
