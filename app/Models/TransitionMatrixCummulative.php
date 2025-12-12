<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use function Laravel\Prompts\table;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransitionMatrixCummulative extends Model
{
   use HasFactory, SoftDeletes;

    protected $table = 'transition_matrix_cummulative';

    protected $fillable = [
        'start_period',
        'end_period',
        'pd_calculation_level',
        'pd_calculation_id',
        'pd_calculation_code',
        'transition_profile_id',
        'records_counted',
        'periods_count',
        'periods_list',
        'calculation_source',
        'last_reporting_period',
        'run_no',
        'status',
        'created_by',
    ];

    public function data()
        {
            return $this->hasMany(TransitionMatrixCummulativeData::class, 'cummulative_id');
        }

    public function portfolio()
        {
            return $this->belongsTo(LoanPortfolio::class, 'pd_calculation_id');
        }

    public function sector()
        {
            return $this->belongsTo(IndustryType::class, 'pd_calculation_code', 'code');
        }

    public function transitionProfile()
        {
            return $this->belongsTo(TransitionProfileDefinition::class, 'transition_profile_id');
        }
}
