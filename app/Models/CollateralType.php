<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollateralType extends Model
{
    protected $fillable = [
        'type_code', 
        'type_name', 
        'description', 
        'realisation_period', 
        'standard_haircut', 
        'liquidity_factor', 
        'is_active',
    ];
}

