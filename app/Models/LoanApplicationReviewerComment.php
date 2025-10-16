<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanApplicationReviewerComment extends Model
{
    use HasFactory;
      //make every field fillable
      protected $guarded = [];

        public function loanApplication(){
            return $this->belongsTo(LoanApplication::class);
        }
        public function scopeFilter($query, array $filters)
        {
            $query->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'like', '%' . $search . '%')
                        ->orWhere('comment_date', 'like', '%' . $search . '%');
                });
            });
            $query->when($filters['comment_date'] ?? null, function ($query, $date) {
                $query->where('comment_date', $date);
            });
            $query->when($filters['comment_type'] ?? null, function ($query, $comment_type) {
                $query->where('comment_type', $comment_type);
            });
            $query->when($filters['comment_section'] ?? null, function ($query, $comment_section) {
                $query->where('comment_section', $comment_section);
            });
            $query->when($filters['comment'] ?? null, function ($query, $comment) {
                $query->where('comment', $comment);
            });

        }
        public function user(){
            return $this->belongsTo(User::class);
        }
        public function commentSection(){
            return $this->belongsTo(ScoringAttributeGroup::class, 'comment_section', 'id');
        }
}
