<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LoanProductScoringAttribute extends Model
{
    use LogsActivity, HasFactory;

    protected $casts = [
        'accept_value'=>'array',
        'used'=>'boolean',
        'is_ratio' => 'boolean',
        'is_industry_analysis' => 'boolean',
        'is_shareholder_analysis' => 'boolean',
        'is_management_analysis' => 'boolean',
    ];

    public function attribute()
    {
        return $this->belongsTo(ScoringAttribute::class, 'scoring_attribute_id', 'id');
    }
    public function options()
    {
        return $this->hasMany(LoanProductScoringAttributeOptionValue::class, 'loan_product_scoring_attribute_id', 'id');
    }

    public function loan()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_id', 'id');
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

}
