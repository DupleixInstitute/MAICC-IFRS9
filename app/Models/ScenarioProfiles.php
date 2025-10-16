<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScenarioProfiles extends Model
{
    use HasFactory;
    protected $table = 'scenario_profiles';

    protected $fillable = [
        'name',
        'profile_code',
        'description',
        'complete',
        'data',
        'created_by', // 👈 Add this line
    ];


    protected $casts = [
        'complete' => 'boolean',
        'data' => 'array',
    ];

    public function scenarios()
    {
        return $this->hasMany(Scenarios::class, 'profile_id');
    }

    protected $appends = ['is_complete'];

  public function getIsCompleteAttribute()
    {
        $total = $this->scenarios->sum('probability');

        // Use a tolerance to allow small decimal inaccuracies
        return abs($total - 100.0) < 0.01; // within 0.01 of 100 is "complete"
    }

}
