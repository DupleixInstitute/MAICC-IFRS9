<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scenarios extends Model
{
    use HasFactory;

    protected $table = 'scenarios';
    
    protected $fillable = [
        'profile_id',
        'name',
        'profile_id',
        'description',
        'probability',
        'is_base_case',
        'is_active',
        'tags',
        'risk_disclaimer', 
        'key_drivers', 
        'version', 
        'published_at', 
        'status', 
        'updated_by',
    ];

    protected $casts = [
        'probability' => 'decimal:2',
        'is_base_case' => 'boolean',
        'is_active' => 'boolean',
        'tags' => AsArrayObject::class, // NEW: Proper array casting
        'version' => 'integer', // NEW
        'published_at' => 'datetime', // NEW
        'status' => 'string', // NEW
    ];

 

    // NEW: Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_PUBLISHED = 'published';


    public function scenarioProfile(): BelongsTo
    {
        return $this->belongsTo(ScenarioProfiles::class, 'profile_id');
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->name} v{$this->version}",
        );
    }

    
    protected function isPublished(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === self::STATUS_PUBLISHED && $this->published_at !== null,
        );
    }

    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED)
                    ->whereNotNull('published_at');
    }

    public function macroData()
    {
        return $this->hasMany(MacroStatsValue::class, 'scenario_id', 'id');
    }

    // public function auditLogs()
    // {
    //     return $this->morphMany(FliAuditLog::class, 'auditable');
    // }
}