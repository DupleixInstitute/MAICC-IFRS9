<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HelpCategory extends Model
{
    protected $guarded = [];

    protected $attributes = ['manual' => 'user'];

    public function articles()
    {
        return $this->hasMany(HelpArticle::class)->orderBy('order');
    }

    /** Chapters belonging to one manual (user or admin). */
    public function scopeManual(Builder $query, string $manual): Builder
    {
        return $query->where('manual', $manual);
    }
}
