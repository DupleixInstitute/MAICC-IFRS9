<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ScoringAttributeGroup extends Model
{
    use LogsActivity, HasFactory;

    protected $appends = [
        'total_attributes'
    ];
    protected $casts = [
        'active' => 'boolean',
        'used'=>'boolean',
        'is_system' => 'boolean',
        'is_ratio' => 'boolean',
        'is_corporate' => 'boolean',
        'is_industry_analysis' => 'boolean',
        'is_shareholder_analysis' => 'boolean',
        'is_management_analysis' => 'boolean',
    ];

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            });
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scoringAttributes()
    {
        return $this->hasMany(ScoringAttribute::class, 'scoring_attribute_group_id');
    }

    public function getTotalAttributesAttribute()
    {
        return ScoringAttribute::where('scoring_attribute_group_id', $this->id)->count();
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

}
