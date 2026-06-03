<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class ExpectedCreditLoss extends Model
{
    use HasFactory;
    protected $table = 'expected_credit_loss';
    protected $fillable = [
        'reporting_period',
        'ecl_calculation_level',
        'ecl_calculation_id',
        'ecl_calculation_code',
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
        'ecl_calculation_level' => 'string',
    ];

    public function portfolios(){
        return $this->belongsTo(LoanPortfolio::class, 'ecl_calculated_id', 'id');
    }

    public function sector(){
        return $this->belongsTo(IndustryType::class, 'ecl_calculated_code', 'code');
    }

    
}
