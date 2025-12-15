<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        array $data = []
    ): void {
        AuditLog::create([
            'user_id'         => auth()->id(),
            'action'          => $action,
            'entity_type'     => $entityType,
            'entity_id'       => $entityId,
            'scope'           => $data['scope'] ?? null,
            'reporting_period'=> $data['reporting_period'] ?? null,
            'old_values'      => $data['old_values'] ?? null,
            'new_values'      => $data['new_values'] ?? null,
            'meta'            => $data['meta'] ?? null,
            'ip_address'      => request()?->ip(),
            'user_agent'      => request()?->userAgent(),
        ]);
    }
}