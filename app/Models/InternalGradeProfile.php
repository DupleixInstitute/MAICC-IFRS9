<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternalGradeProfile extends Model
{
    protected $fillable = [
        'profile_code',
        'name',
        'description',
        'max_tenor_years',
        'is_active',
    ];

    public function mappings(): HasMany
        {
            return $this->hasMany(InternalGradeMapping::class, 'profile_id');
        }
    

}
