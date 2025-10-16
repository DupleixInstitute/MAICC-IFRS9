<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransitionProfileOption extends Model
{
    use HasFactory;
    protected $fillable = [
            "category_name",
            "profile_id",
            "is_start_or_end",
            "ordering_index",
            "min_value",
            "max_value",
            "text_value",
            "default_value",
            "created_at",
    ];

    public function profile()
    {
        return $this->belongsTo(TransitionProfileDefinition::class, 'profile_id');
    }
}
