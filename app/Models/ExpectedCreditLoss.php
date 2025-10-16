<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpectedCreditLoss extends Model
{
    use HasFactory;
    protected $table = 'expected_credit_loss';
    protected $fillable = [
        'reporting_period',
        'total_ead',
        'total_ecl',
        'lgd_value_used',
        'pd_value_used',
        'ifrs9_stage',
        'total_loans',
        'last_reporting_period',
    ];

    protected $casts =[
        'reporting_period' => 'string',
        'last_reporting_period' => 'string',
    ];
}
