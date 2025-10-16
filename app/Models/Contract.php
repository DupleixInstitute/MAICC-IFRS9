<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'contract_id',
        'customer_id',
        'create_date',
        'due_date',
        'opening_score',
        'opening_score_period',
        'closed_date',
        'write_off_date',
        'update_period'
    ];

    protected $casts = [
        'create_date' => 'date',
        'due_date' => 'date',
        'closed_date' => 'date',
        'write_off_date' => 'date',
        'opening_score' => 'decimal:2'
    ];

    public function loanBooks()
    {
        return $this->hasMany(LoanBook::class, 'contract_id', 'contract_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'customer_id', 'id');
    }
}
