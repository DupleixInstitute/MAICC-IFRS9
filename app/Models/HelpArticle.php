<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpArticle extends Model
{
    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(HelpCategory::class, 'help_category_id');
    }

    public function steps()
    {
        return $this->hasMany(HelpArticleStep::class)->orderBy('step_no');
    }

    public function images()
    {
        return $this->hasMany(HelpArticleImage::class)->orderBy('order');
    }

    public function routes()
    {
        return $this->hasMany(HelpArticleRoute::class);
    }
}
