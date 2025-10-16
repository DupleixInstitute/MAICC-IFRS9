<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanApplicationComment extends Model
{
    protected $fillable = [
        'loan_application_id',
        'user_id',
        'comment_section_id',
        'content',
        'metadata',
        'status'
    ];

    protected $casts = [
        'metadata' => 'array',
        'edited_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function commentSection()
    {
        return $this->belongsTo(ScoringAttributeGroup::class, 'comment_section_id');
    }

    public function replies()
    {
        return $this->hasMany(CommentReply::class, 'comment_id');
    }

    public function attachments()
    {
        return $this->morphMany(CommentAttachment::class, 'attachable');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    public function scopeFilter($query, array $filters)
        {
            $query->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'like', '%' . $search . '%')
                        ->orWhere('created_at', 'like', '%' . $search . '%');
                });
            });
            $query->when($filters['created_at'] ?? null, function ($query, $date) {
                $query->where('created_at', $date);
            });
            $query->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            });
            $query->when($filters['content'] ?? null, function ($query, $content) {
                $query->where('content', $content);
            });

        }
}
