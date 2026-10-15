<?php

namespace App\Support;

use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The one maker-checker check (Staging Module Specification v1, section 8.9;
 * decision S11). Every workflow that has a second person decide what a first
 * person proposed asks this class whether the same person may decide it, and
 * keeps its own message for the refusal.
 *
 * A self-approval is allowed only under UAT mode: the server's environment is
 * not production (APP_ENV), the governed setting maker_checker_admin_override
 * is "Allowed for administrators" today, and the person deciding has the admin
 * role. Each self-approval so allowed writes one audit entry, "Maker-Checker
 * Override Used", naming the workflow, the record and the person; the
 * self-approvals report reads those entries. The override is never used for a
 * change to its own setting (GovernanceService). The bootstrap label is not the
 * override and is handled by each workflow as before.
 */
final class MakerChecker
{
    public const SETTING = 'maker_checker_admin_override';
    public const ALLOWED = 'Allowed for administrators';
    public const NOT_ALLOWED = 'Not allowed';
    public const LABEL = 'UAT self-approval (administrator override)';
    public const AUDIT_ACTION = 'Maker-Checker Override Used';

    /** Whether the server may relax maker-checker at all: any environment but production. */
    public static function environmentAllows(): bool
    {
        return ! app()->environment('production');
    }

    /** UAT mode: a non-production server and the governed setting allowing it today. */
    public static function overrideInForce(): bool
    {
        if (! self::environmentAllows()) {
            return false;
        }
        try {
            return (new GovernanceService())->get(self::SETTING) === self::ALLOWED;
        } catch (Throwable) {
            return false;
        }
    }

    /** Whether this person may approve their own proposal now: UAT mode and the admin role. Nothing is logged. */
    public static function allowedFor(?object $user): bool
    {
        // UAT mode first: with it off no role is looked up at all
        return $user !== null && self::overrideInForce() && method_exists($user, 'hasRole') && $user->hasRole('admin');
    }

    /**
     * Whether the person deciding may decide what they proposed themselves, and
     * if so the use is recorded. A workflow calls this only when the maker and
     * the checker are the same named person, and refuses with its own message
     * when it returns false.
     */
    public static function selfApprovalAllowed(string $workflow, string $entity, int|string|null $record, ?int $checkerId): bool
    {
        if ($checkerId === null || ! self::overrideInForce()) {
            return false;
        }
        $user = Schema::hasTable('users') ? \App\Models\User::find($checkerId) : null;
        if (! self::allowedFor($user)) {
            return false;
        }
        self::recordUse($workflow, $entity, $record, $checkerId);

        return true;
    }

    /** One audit entry for one self-approval made under the override. */
    public static function recordUse(string $workflow, string $entity, int|string|null $record, ?int $checkerId): void
    {
        AuditLoggerService::log(self::AUDIT_ACTION, $entity, is_int($record) || (is_string($record) && ctype_digit($record) && strlen($record) < 10) ? (int) $record : null, [
            'new_values' => ['workflow' => $workflow, 'record' => $record, 'label' => self::LABEL, 'person' => $checkerId],
            'meta' => ['user' => $checkerId, 'setting' => self::SETTING . ': ' . self::ALLOWED, 'environment' => app()->environment()],
        ]);
    }

    /**
     * Every decision made under the override, newest first, for the
     * self-approvals report.
     *
     * @return \Illuminate\Support\Collection<int,array{at:string,workflow:string,record:mixed,person:?int,name:?string,entity:string,environment:?string}>
     */
    public static function selfApprovals()
    {
        if (! Schema::hasTable('audit_logs')) {
            return collect();
        }
        $names = Schema::hasTable('users') ? DB::table('users')->pluck('name', 'id') : collect();

        return DB::table('audit_logs')->where('action', self::AUDIT_ACTION)->orderByDesc('id')->get()->map(function ($r) use ($names) {
            $v = json_decode((string) $r->new_values, true) ?: [];
            $m = json_decode((string) $r->meta, true) ?: [];
            $person = isset($v['person']) ? (int) $v['person'] : null;

            return ['at' => (string) $r->created_at, 'workflow' => (string) ($v['workflow'] ?? ''), 'record' => $v['record'] ?? $r->entity_id, 'person' => $person,
                'name' => $person !== null ? ($names[$person] ?? null) : null, 'entity' => (string) $r->entity_type, 'environment' => $m['environment'] ?? null];
        });
    }
}
