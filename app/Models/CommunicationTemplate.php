<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class CommunicationTemplate extends Model
{
    use HasFactory;

    protected $table = 'communication_templates';
    protected $fillable = [];
    protected $casts = [
        'active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
                $query->orWhere('id', 'like', '%' . $search . '%');
                $query->orWhere('template', 'like', '%' . $search . '%');
            });
        });
    }

    public function tariff()
    {
        return $this->belongsTo(Tariff::class);
    }

    public function fields()
    {
        return $this->hasMany(FormField::class, 'form_id');
    }


    public function createdBy()
    {
        return $this->hasOne(User::class, 'id', 'created_by_id');
    }

}
