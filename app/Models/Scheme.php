<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * An E-Banker scheme: the six settings that shape a loan's schedule, by
 * scheme code and effective date (spec v3 section 6.3). A contract carries
 * its scheme_code; the scheme supplies the defaults where the contract row
 * does not state a value. Phase P4 (schedules) reads this table.
 */
class Scheme extends Model
{
    protected $table = 'schemes';

    protected $fillable = [
        'scheme_code',
        'product_code',
        'interest_policy',
        'floating_flag',
        'interest_calc_base',
        'installment_based_on',
        'emi_calc_type',
        'default_moratorium_type',
        'effective_from',
    ];

    protected $casts = [
        'effective_from' => 'date',
    ];

    /**
     * The scheme in force for a code on a date: the row with the latest
     * effective date on or before it. Null when the contract carries no scheme
     * code or no scheme has been loaded, which callers must read as "the scheme
     * says nothing", never as a default setting.
     */
    public static function inForce(?string $schemeCode, CarbonInterface $asOf): ?self
    {
        $code = trim((string) $schemeCode);
        if ($code === '') {
            return null;
        }

        return static::query()
            ->where('scheme_code', $code)
            ->whereDate('effective_from', '<=', $asOf->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}
