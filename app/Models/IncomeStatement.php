<?php

namespace App\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class IncomeStatement extends Model
{
    use LogsActivity, HasFactory;

    protected $appends = [
        'total_stock',
        'total_net_finance_costs',
        'total_cost_of_goods_sold_depreciation',
        'total_amortisation',
        'total_depreciation_property_plant_equipment',
    ];

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('year', 'like', '%' . $search . '%');
            });
        });
    }

    public function chart()
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function data()
    {
        return $this->hasMany(IncomeStatementData::class);
    }

    protected function totalStock(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                return $this->data()->whereHas('chart', function (Builder $query) {
                    $query->where('account_type', 'stock');
                })->sum('amount');
            },
            set: fn($value) => $value,
        );
    }
    protected function totalNetFinanceCosts(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                return $this->data()->whereHas('chart', function (Builder $query) {
                    $query->whereIn('account_type', ['net_finance_costs_banks','net_finance_costs_finance_leases']);
                })->sum('amount');
            },
            set: fn($value) => $value,
        );
    }
    protected function totalCostOfGoodsSoldDepreciation(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                return $this->data()->whereHas('chart', function (Builder $query) {
                    $query->whereIn('account_type', ['cost_of_goods_sold_depreciation']);
                })->sum('amount');
            },
            set: fn($value) => $value,
        );
    }
    protected function totalAmortisation(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                return $this->data()->whereHas('chart', function (Builder $query) {
                    $query->whereIn('account_type', ['amortisation_intangible_assets']);
                })->sum('amount');
            },
            set: fn($value) => $value,
        );
    }
    protected function totalDepreciationPropertyPlantEquipment(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                return $this->data()->whereHas('chart', function (Builder $query) {
                    $query->whereIn('account_type', ['depreciation_property_plant_equipment']);
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
