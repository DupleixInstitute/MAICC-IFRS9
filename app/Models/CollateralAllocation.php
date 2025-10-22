<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollateralAllocation extends Model
{
    protected $fillable = [
        'reporting_year', 'reporting_month', 'collateral_register_id', 'client_id', 'client_name', 
        'contract_id', 'account_balance', 'total_customer_exposure', 'allocated_collateral',
        'allocation_percentage', 'total_collateral_value', 'EIR', 'realisation_months', 
        'discounted_collateral', 'coverage_ratio', 'allocation_basis', 'allocation_notes'
    ];

    public function collateral()
    {
        return $this->belongsTo(CollateralRegister::class, 'collateral_register_id');
    }

    public function getReportingPeriodAttribute()
    {
        return $this->reporting_year * 100 + $this->reporting_month;
    }
}
