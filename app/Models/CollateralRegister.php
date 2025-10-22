<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollateralRegister extends Model
{
    protected $fillable = [
        'register_number', 'customer_id', 'customer_name', 'collateral_type', 'property_use',
        'description', 'location', 'registration_date', 'expiry_date', 'valuation_date',
        'nominal_value', 'market_value', 'execution_value', 'status', 'notes'
    ];

    public function allocations()
    {
        return $this->hasMany(CollateralAllocation::class);
    }
}

