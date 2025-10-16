<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommentReply extends Model
{
    protected $fillable = [
        'comment_id',
        'user_id',
        'content',
        'parent_reply_id',
        'metadata',
        'status'
    ];

    protected $casts = [
        'metadata' => 'array',
        'edited_at' => 'datetime'
    ];

    public function comment()
    {
        return $this->belongsTo(LoanApplicationComment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parentReply()
    {
        return $this->belongsTo(self::class, 'parent_reply_id');
    }

    public function childReplies()
    {
        return $this->hasMany(self::class, 'parent_reply_id');
    }

    public function attachments()
    {
        return $this->morphMany(CommentAttachment::class, 'attachable');
    }
}
