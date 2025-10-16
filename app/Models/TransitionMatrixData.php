<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransitionMatrixData extends Model
{
    use HasFactory;

    // Tell Eloquent to use the correct table
    protected $table = 'transition_matrices_data';

    // Allow all fields for mass assignment
    protected $guarded = [];

    public function matrix()
        {
            return $this->belongsTo(TransitionMatrix::class, 'calculation_header_id'); // adjust foreign key if needed
        }
}
