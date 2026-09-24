<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of the reference-rate series: the rate an index (the Reserve Bank
 * prime lending rate, PLR) carried from an effective date until the next row.
 *
 * Rates are percentages (25.3 means 25.3 percent), matching the delivered
 * file and the loan book, so the spread added to the prime rate (margin) is
 * loan-book rate minus this rate with no unit conversion.
 */
class ReferenceRate extends Model
{
    public const DEFAULT_INDEX = 'PLR';

    protected $table = 'reference_rate_series';

    protected $fillable = [
        'index_code',
        'effective_date',
        'rate',
        'source_row',
        'as_delivered',
        'interpretation',
        'import_id',
        'created_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'rate' => 'float',
        'import_id' => 'integer',
        'created_by' => 'integer',
    ];

    /**
     * The rate in force on a date: the row with the latest effective date on
     * or before it. Null when the series starts after the date asked for,
     * which callers must treat as "no reference rate", never as zero.
     */
    public static function inForce(string $index, CarbonInterface $date): ?self
    {
        return static::query()
            ->where('index_code', strtoupper(trim($index)))
            ->whereDate('effective_date', '<=', $date->toDateString())
            ->orderByDesc('effective_date')
            ->first();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
