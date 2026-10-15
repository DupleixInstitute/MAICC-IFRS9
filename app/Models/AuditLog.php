<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'scope',
        'reporting_period',
        'rows_affected',
        'old_values',
        'new_values',
        'meta',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'meta'       => 'array',
    ];

    /**
     * Every new entry joins the chain (Staging Module Specification v1, section
     * 8.5; App\Support\AuditChain): it carries the fingerprint of the entry
     * before it and its own. The log is append-only: an entry is never updated
     * or deleted through the system, and on MySQL a trigger refuses it.
     */
    protected static function booted(): void
    {
        static::creating(function (AuditLog $log) {
            if (! \App\Support\AuditChain::enabled()) {
                return;
            }
            $log->created_at ??= $log->freshTimestamp();
            $log->updated_at ??= $log->created_at;
            $prev = \App\Support\AuditChain::last();
            $log->prev_hash = $prev;
            $log->entry_hash = \App\Support\AuditChain::hash($prev, $log->getAttributes());
        });
        static::updating(fn () => throw new \LogicException('The audit log is append-only: an entry cannot be changed.'));
        static::deleting(fn () => throw new \LogicException('The audit log is append-only: an entry cannot be deleted.'));
    }
}

