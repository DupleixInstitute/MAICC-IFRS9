<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LoanProduct extends Model
{
    use LogsActivity, HasFactory;

    protected $appends = [

    ];
    protected $casts = [
        'active' => 'boolean',
    ];

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            });
        })->when($filters['aggregation_criteria'] ?? null, function ($query, $criteria) {
            $query->where('aggregation_criteria', $criteria);
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function category()
    {
        return $this->belongsTo(LoanProductCategory::class, 'loan_product_category_id', 'id');
    }

    public function scoringAttributes()
    {
        return $this->hasMany(LoanProductScoringAttribute::class, 'loan_product_id', 'id')->orderBy('order_position');
    }

    public function endTransitionProfile()
    {
        return $this->belongsTo(LoanProduct::class, 'end_transition_profile_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

}
