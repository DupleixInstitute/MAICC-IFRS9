<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransitionProfileDefinition extends Model
{
    use HasFactory;
    protected $fillable = [
        "profile_code",
        "short_name",
        "description",
        "start_table",
        "end_table",
        "start_grading_col",
        "end_grading_col",
        "start_value_type",
        "end_value_type",
        "start_client_id_col",
        "end_client_id_col",
        "aggregation_criteria",
        "created_at",
    ];

    public function options()
    {
        return $this->hasMany(TransitionProfileOption::class, 'profile_id');
    }
}
