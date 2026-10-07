<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Generic before/after audit-log entry.
 *
 * Step 24 — every regulatory state change (report lifecycle
 * transition, etc.) records who, when, before, after and why.
 *
 * @property string $subject_type
 * @property int $subject_id
 * @property string $event
 * @property string|null $before_value
 * @property string|null $after_value
 * @property string|null $reason
 * @property int|null $actor_user_id
 */
class ApprovalAuditLog extends Model
{
    protected $table = 'approval_audit_logs';

    protected $fillable = [
        'subject_type', 'subject_id', 'event',
        'before_value', 'after_value', 'reason', 'actor_user_id',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Record an audit entry for any model subject.
     */
    public static function record(
        Model $subject,
        string $event,
        ?string $before,
        ?string $after,
        ?string $reason = null,
        ?int $actorUserId = null,
    ): self {
        return self::create([
            'subject_type'  => $subject::class,
            'subject_id'    => $subject->getKey(),
            'event'         => $event,
            'before_value'  => $before,
            'after_value'   => $after,
            'reason'        => $reason,
            'actor_user_id' => $actorUserId,
        ]);
    }
}
