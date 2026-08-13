<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpArticleImage extends Model
{
    protected $guarded = [];

    public function article()
    {
        return $this->belongsTo(HelpArticle::class, 'help_article_id');
    }
}
