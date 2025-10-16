<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $casts = [
        'allow_manual' => 'boolean',
        'enable_reconciliation' => 'boolean',
        'active' => 'boolean',
    ];

    public function parentChart()
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id', 'id');
    }


    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        });
        $query->when($filters['account_type'] ?? null, function ($query, $accountType) {
            $query->where('account_type', $accountType);
        });
    }

    public function scopeAccountType($query, $accountType)
    {
        $query->when($accountType, function ($query, $accountType) {
            $query->where('account_type', $accountType);
        });
    }
}
