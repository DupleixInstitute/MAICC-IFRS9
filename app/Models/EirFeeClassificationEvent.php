<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EirFeeClassificationEvent extends Model
{
    protected $fillable = [
        'contract_fee_id', 'action', 'integral', 'reason',
        'accounting_rule_id', 'performed_by',
    ];

    protected $casts = ['integral' => 'boolean'];
}
