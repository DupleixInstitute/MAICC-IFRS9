<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollateralAllocation extends Model
{
    protected $fillable = [
        'reporting_year', 'reporting_month', 'reporting_period','collateral_register_id', 'customer_id', 'customer_name', 
        'contract_id', 'account_balance', 'total_customer_exposure', 'allocated_collateral',
        'allocation_percentage', 'total_collateral_value', 'EIR', 'realisation_months', 
        'discounted_collateral', 'coverage_ratio', 'allocation_basis', 'allocation_notes'
    ];

    public function collateral()
    {
        return $this->belongsTo(CollateralRegister::class, 'collateral_register_id');
    }

    public function collateralRegister()
    {
        return $this->belongsTo(CollateralRegister::class, 'collateral_register_id', 'id');
    }


    public function loanBook()
    {
        return $this->belongsTo(LoanBook::class, 'contract_id', 'contract_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'customer_id', 'id');
    }

    public function collateralType()
    {
        return $this->belongsTo(CollateralType::class, 'collateral_register_id', 'id')
                    ->join('collateral_registers', 'collateral_registers.collateral_type', '=', 'collateral_types.type_code');
    }


}
