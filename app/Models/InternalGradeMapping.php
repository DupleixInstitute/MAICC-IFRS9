<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternalGradeMapping extends Model
{

    protected $fillable = [
        'profile_id',
        'grade_code',
        'grade_name',
        'upper_bound',
        'lower_bound',
        'rbm_class',
        'sp_rating',
    ];


    public function profile(): BelongsTo
    {
        return $this->belongsTo(InternalGradeProfile::class, 'profile_id');
    }

    public function tenorPds(): HasMany
    {
        return $this->hasMany(GradeTenorPd::class, 'grade_mapping_id');
    }

}
