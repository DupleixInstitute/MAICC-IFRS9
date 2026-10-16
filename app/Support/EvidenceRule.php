<?php

namespace App\Support;

use App\Models\GovernanceSetting;
use App\Services\Eir\GovernanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The supporting-document rule's start on this database, and the approvals
 * made before it (Staging Module Specification v1, sections 8.3 and 8.10).
 * A change to a setting that requires evidence, approved with no document
 * before the rule came into force, is "approved before supporting documents
 * were required": shown so on the history and the register, never refused,
 * and carried so by a decisions pack. One made after it with no document
 * cannot exist on this database; one in a pack is refused.
 */
final class EvidenceRule
{
    public const LABEL = 'approved before supporting documents were required';

    public const MILESTONE = 'approval_evidence_rule';

    /** When the rule came into force here; null on a database migrated before it (where every approval predates it). */
    public static function since(): ?CarbonImmutable
    {
        if (! Schema::hasTable('system_milestones')) {
            return null;
        }
        $at = DB::table('system_milestones')->where('key', self::MILESTONE)->value('at');

        return $at === null ? null : CarbonImmutable::parse($at);
    }

    /** Whether an approval dated $approvedAt predates the rule that came into force at $since (null: not yet in force). */
    public static function before(mixed $approvedAt, CarbonImmutable|string|null $since): bool
    {
        if ($approvedAt === null) {
            return false;
        }

        return $since === null || CarbonImmutable::parse((string) $approvedAt)->lt(is_string($since) ? CarbonImmutable::parse($since) : $since);
    }

    /** A change on this database approved, without the document its setting now requires, before the rule. */
    public static function grandfathered(object $setting, ?CarbonImmutable $since = null, bool $sinceKnown = false): bool
    {
        if (($setting->status ?? null) !== GovernanceSetting::STATUS_APPROVED || ($setting->set_by ?? null) === null && ($setting->approved_by ?? null) === null) {
            return false;
        }
        if (! GovernanceService::evidenceRequired((string) $setting->key) || SupportingDocuments::count(GovernanceSetting::class, (int) $setting->id) > 0) {
            return false;
        }

        return self::before($setting->approved_at ?? null, $sinceKnown ? $since : self::since());
    }
}
