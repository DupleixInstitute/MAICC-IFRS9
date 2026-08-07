<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'title', 'description', 'category', 'priority', 'status',
        'requested_by', 'source', 'assigned_to', 'created_by', 'resolution',
        'due_date', 'resolved_at',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected $appends = [
        'reference_display', 'status_label', 'category_label', 'priority_label',
    ];

    public const STATUSES = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'on_hold' => 'On Hold',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    public const CATEGORIES = [
        'enhancement' => 'Enhancement',
        'issue' => 'Issue',
        'change_request' => 'Change Request',
        'other' => 'Other',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    /* --------------------------------------------------------------------- */
    /* Relationships                                                          */
    /* --------------------------------------------------------------------- */

    public function updates()
    {
        return $this->hasMany(TicketUpdate::class)->orderBy('created_at');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* --------------------------------------------------------------------- */
    /* Accessors                                                              */
    /* --------------------------------------------------------------------- */

    public function getReferenceDisplayAttribute(): string
    {
        return '#' . $this->reference;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? ucfirst((string) $this->priority);
    }

    /* --------------------------------------------------------------------- */
    /* Scopes & helpers                                                       */
    /* --------------------------------------------------------------------- */

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('reference', 'like', '%' . $search . '%')
                    ->orWhere('title', 'like', '%' . $search . '%')
                    ->orWhere('requested_by', 'like', '%' . $search . '%');
            });
        })->when($filters['status'] ?? null, function ($query, $status) {
            $query->where('status', $status);
        })->when($filters['category'] ?? null, function ($query, $category) {
            $query->where('category', $category);
        });
    }

    /**
     * Next zero-padded reference, e.g. 001, 002, 010. Numeric part only so the
     * sequence stays clean and human-friendly (shown as #001).
     */
    public static function nextReference(): string
    {
        $max = (int) static::query()->max(\DB::raw('CAST(reference AS UNSIGNED)'));

        return str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }
}
