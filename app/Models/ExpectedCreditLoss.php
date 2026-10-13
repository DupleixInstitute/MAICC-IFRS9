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
        'total_ecl_discounted',
        'total_discounting_effect',
        'discount_status',
        'discount_unresolved_loans',
        'ecl_calculation_run_id',
        'lgd_value_used',
        'pd_value_used',
        'ifrs9_stage',
        'total_loans',
        'last_reporting_period',
        'pd_segment_run_id',
        'pd_segment_keys',
    ];

    /** Every saved row is stamped with the PD segments of the loans it sums (EclSegmentLineage). */
    protected static function booted(): void
    {
        static::saving(fn (ExpectedCreditLoss $row) => \App\Services\Pd\EclSegmentLineage::stamp($row));
    }

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
