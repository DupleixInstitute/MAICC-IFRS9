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
    ];

    protected $casts = [
        'reporting_year' => 'integer',
        'reporting_month' => 'integer',
        'period' => 'string',
        'lgd_id' => 'integer',
        'lgd_calculation_source' => 'string',
        'lgd_calculation_time' => 'string',
        'pd_id' => 'integer',
        'pd_calculation_source' => 'string',
        'pd_calculation_time' => 'string',
        'ecl_calculated' => 'boolean',
    ];
}
