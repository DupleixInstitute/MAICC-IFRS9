<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ScoringAttribute extends Model
{
    use LogsActivity, HasFactory;

    protected $appends = [

    ];
    protected $casts = [
        'required'=>'boolean',
        'active'=>'boolean',
        'used'=>'boolean',
        'is_system' => 'boolean',
        'is_ratio' => 'boolean',
        'is_corporate' => 'boolean',
        'is_industry_analysis' => 'boolean',
        'is_shareholder_analysis' => 'boolean',
        'is_management_analysis' => 'boolean',
    ];
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
    public function loan()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_id');
    }

    public function getTimeAgoAttribute()
    {
        return $this->created_at->diffForHumans();
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

}
