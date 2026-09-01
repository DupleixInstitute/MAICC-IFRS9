<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ContractRemainingCashflowSchedule extends Model
{
    protected $table = 'contract_remaining_cashflow_schedule';
    protected $guarded = [];
    protected $casts = ['due_date'=>'date','principal_due'=>'float','interest_due'=>'float','fee_due'=>'float'];
}
