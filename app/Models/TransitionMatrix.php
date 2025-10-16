<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransitionMatrix extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transition_profile_id',
        'start_reporting_period',
        'end_reporting_period',
        'pd_start_stage_total_type',
        'portfolio_group_id',
        'calculation_source',
        'description',
        'external_file_path',
        'status',
        'start_year',
        'start_month',
        'end_year',
        'end_month',
        'transition_years',
        'run_no',
        'records_count_updated',
        'records_count_transitioned',
        'reporting_periods_count',
        'updated_balance',
        'transition_balance',
        'last_calculation_date',
        'portfolio_count',
        'book_updated_at',
        'take_on_flag',
        'comments',
        'user_name',
    ];

    // protected $casts = [
    //     'start_reporting_period' => 'date',
    //     'end_reporting_period' => 'date',
    // ];

    /**
     * Get the transition profile that owns this matrix.
     */
    public function transitionProfile()
    {
        return $this->belongsTo(LoanProduct::class, 'transition_profile_id');
    }

    /**
     * Get the entries for this transition matrix.
     */
    public function entries()
    {
        return $this->hasMany(TransitionMatrixEntry::class);
    }

    /**
     * Scope a query to only include active matrices.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the calculation method (balance or count) from the profile
     */
    public function getCalculationMethodAttribute()
    {
        return $this->transitionProfile->aggregation_criteria;
    }

    public function portfolio()
    {
        return $this->belongsTo(LoanPortfolio::class, 'portfolio_group_id');
    }
}
