<?php

namespace App\Models;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportingPeriods extends Model
{
    use HasFactory;
    protected $table = 'reporting_periods';
    protected $fillable = [

        'reporting_year',
        'reporting_month',
        'period',
        'lgd_id',
        'lgd_calculation_source',
        'pd_id',
        'pd_calculation_source',
        'ecl_calculated',
        'ecl_calculation_time',
        'ecl_calculation_level',
        'ecl_calculation_id',
        'ecl_calculation_code',
    ];

    protected $casts = [
        'reporting_year' => 'integer',
        'reporting_month' => 'integer',
        'period' => 'string',
        'ecl_calculated' => 'boolean',
        'ecl_calculation_level' => 'string',
    ];
}
