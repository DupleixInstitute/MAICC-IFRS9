<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpCategory extends Model
{
    protected $guarded = [];

    public function articles()
    {
        return $this->hasMany(HelpArticle::class)->orderBy('order');
    }
}
