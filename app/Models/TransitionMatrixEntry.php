<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransitionMatrixEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transition_matrix_id',
        'portfolio_group',
        'start_state',
        'end_state',
        'start_balance',
        'start_count',
        'end_balance',
        'end_count',
        'transitional_probability',
        'is_default',
    ];

    protected $casts = [
        'start_balance' => 'decimal:2',
        'end_balance' => 'decimal:2',
        'start_count' => 'integer',
        'end_count' => 'integer',
        'transitional_probability' => 'decimal:6',
        'is_default' => 'boolean',
    ];

    /**
     * Get the transition matrix that owns this entry.
     */
    public function transitionMatrix()
    {
        return $this->belongsTo(TransitionMatrix::class);
    }

    /**
     * Calculate the transitional probability based on the profile's calculation method
     */
    public function calculateProbability()
    {
        $method = $this->transitionMatrix->calculation_method;
        
        if ($method === 'balance') {
            return $this->start_balance > 0 ? $this->end_balance / $this->start_balance : 0;
        } else { // count method
            return $this->start_count > 0 ? $this->end_count / $this->start_count : 0;
        }
    }

    /**
     * Scope query to order by the required fields
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('start_state')
                    ->orderBy('start_balance')
                    ->orderBy('start_count')
                    ->orderBy('end_state')
                    ->orderBy('end_balance')
                    ->orderBy('end_count');
    }
}
