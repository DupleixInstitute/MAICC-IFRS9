<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditLossDefinition extends Model
{
    protected $table = 'macro_credit_loss_definitions';
    protected $fillable = ['code', 'name', 'description'];

    public function data()
    {
        return $this->hasMany(CreditLossData::class, 'definition_id');
    }
}

