<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeTenorPd extends Model
{
    protected $table = 'grade_tenor_pd';
    protected $fillable = [
        'grade_mapping_id',
        'tenor_years',
        'pd_probability',
    ];

    protected $casts = [
        'pd_probability' => 'float',
    ];

    public function gradeMapping(): BelongsTo
    {
        return $this->belongsTo(InternalGradeMapping::class, 'grade_mapping_id');
    }
}
