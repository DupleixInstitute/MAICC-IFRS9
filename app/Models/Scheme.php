<?php

namespace App\Models;

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
}
