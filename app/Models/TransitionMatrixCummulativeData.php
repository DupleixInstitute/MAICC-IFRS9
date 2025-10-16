<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransitionMatrixCummulativeData extends Model
{
     use HasFactory;

    protected $table = 'transition_matrix_cummulative_data';

    protected $fillable = [
        'cummulative_id',
        'start_stage',
        'end_stage',
        'stage_transitions',
        'start_total_cummulated',
        'transition_balance_cummulated',
        'transition_probability_cummulated',
        'status',
    ];

    public function cummulative()
    {
        return $this->belongsTo(TransitionMatrixCummulative::class, 'cummulative_id');
    }
}
