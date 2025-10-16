<?php

namespace App\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BalanceSheet extends Model
{
    use LogsActivity, HasFactory;

    protected $casts = [
        'total_assets' => 'double',
        'total_equity' => 'double',
        'total_liabilities' => 'double',
        'total_working_capital' => 'double',
        'total_equity_liabilities' => 'double',
        'total_current_assets' => 'double',
        'total_current_liabilities' => 'double',
        'total_long_term_liabilities' => 'double',
        'total_other_current_assets' => 'double',
        'total_tangible_net_worth' => 'double',
        'total_retained_earnings' => 'double',
        'total_accounts_receivable' => 'double',
        'total_accounts_payable' => 'double',
        'total_other_assets' => 'double',
        'total_fixed_assets' => 'double',
    ];
    protected $appends = [
        'total_tangible_net_worth',
        'total_retained_earnings',
        'total_accounts_receivable',
        'total_accounts_payable',
    ];

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('year', 'like', '%' . $search . '%');
            });
        });
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function data()
    {
        return $this->hasMany(BalanceSheetData::class);
    }

    protected function totalTangibleNetWorth(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                return  $this->data()->whereHas('chart',function (Builder $query){
                    $query->where('account_type','intangible_asset');
                })->sum('amount');
            },
            set: fn($value) => $value,
        );
    }
    protected function totalRetainedEarnings(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                return  $this->data()->whereHas('chart',function (Builder $query){
                    $query->where('account_type','retained_earning');
                })->sum('amount');
            },
            set: fn($value) => $value,
        );
    }
    protected function totalAccountsReceivable(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                return  $this->data()->whereHas('chart',function (Builder $query){
                    $query->where('account_type','accounts_receivable');
                })->sum('amount');
            },
            set: fn($value) => $value,
        );
    }
    protected function totalAccountsPayable(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                return  $this->data()->whereHas('chart',function (Builder $query){
                    $query->where('account_type','accounts_payable');
                })->sum('amount');
            },
            set: fn($value) => $value,
        );
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

}
