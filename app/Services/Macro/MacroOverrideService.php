<?php

namespace App\Services\Macro;

use App\Services\AuditLoggerService;
use App\Support\Approvals;
use App\Support\MakerChecker;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * A manual change to the macro data, under maker-checker (Staging Module
 * Specification v1, section 8.6): a manual value for a series and period, a
 * correction of an observation, or its deletion. Each is proposed with a
 * reason and applied only when a second person approves it with the source of
 * the value attached (the one maker-checker check, MakerChecker; the evidence,
 * Approvals). Rejecting or withdrawing goes through Approvals::decide.
 */
class MacroOverrideService
{
    public const KINDS = ['manual', 'correction', 'deletion'];

    public function __construct(private MacroImportService $importer)
    {
    }

    /** @param array<string,mixed> $payload */
    public function propose(string $kind, ?string $seriesCode, ?int $observationId, array $payload, string $reason, int $userId): int
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException("'{$kind}' is not a macro override.");
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('A macro override needs a specific reason of at least 10 characters: where the value comes from.');
        }
        if ($kind !== 'manual' && ! DB::table('macro_statistics_data')->where('id', $observationId)->exists()) {
            throw new InvalidArgumentException("No observation {$observationId}.");
        }
        $id = (int) DB::table('macro_override_requests')->insertGetId([
            'kind' => $kind, 'series_code' => $seriesCode, 'observation_id' => $observationId, 'payload' => json_encode($payload), 'reason' => $reason,
            'status' => 'PROPOSED', 'proposed_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLoggerService::log('Macro Override Proposed', 'macro_override_requests', $id, ['new_values' => ['kind' => $kind, 'series' => $seriesCode, 'observation' => $observationId, 'payload' => $payload, 'reason' => $reason], 'meta' => ['user' => $userId]]);

        return $id;
    }

    /** @return array<string,mixed> what was applied */
    public function approve(int $id, int $approverId): array
    {
        return DB::transaction(function () use ($id, $approverId) {
            $r = DB::table('macro_override_requests')->where('id', $id)->lockForUpdate()->first() ?? throw new InvalidArgumentException("No macro override {$id}.");
            if ($r->status !== 'PROPOSED') {
                throw new LogicException("Macro override {$id} is " . strtolower($r->status) . ', not waiting for approval.');
            }
            if ((int) $r->proposed_by === $approverId && ! MakerChecker::selfApprovalAllowed('Macro data override', 'macro_override_requests', $id, $approverId)) {
                throw new LogicException('Maker-checker: the person who proposed a macro override cannot approve it; a second person approves.');
            }
            Approvals::assertEvidence('macro-override', $id);
            $payload = json_decode($r->payload, true);
            $result = match ($r->kind) {
                'manual' => $this->applyManual($r, $payload, $approverId),
                'correction' => $this->applyCorrection($r, $payload, $approverId),
                'deletion' => $this->applyDeletion($r, $approverId),
            };
            DB::table('macro_override_requests')->where('id', $id)->update(['status' => 'APPROVED', 'approved_by' => $approverId, 'approved_at' => now(), 'result' => json_encode($result), 'updated_at' => now()]);
            AuditLoggerService::log('Macro Override Approved', 'macro_override_requests', $id, ['new_values' => ['kind' => $r->kind, 'result' => $result], 'meta' => ['user' => $approverId, 'proposed_by' => $r->proposed_by]]);

            return $result;
        });
    }

    private function applyManual(object $r, array $p, int $approverId): array
    {
        $preview = ['series_code' => $r->series_code, 'indicator_code' => null, 'country' => 'MWI', 'source' => 'Manual entry: ' . $r->reason, 'address' => null, 'fetched_at' => now()->toDateTimeString(),
            'rows' => [['period' => $p['period'], 'year' => (int) substr((string) $p['period'], 0, 4), 'value' => (float) $p['value'], 'value_type' => ! empty($p['is_forecast']) ? 'forecast' : 'actual']]];
        $res = $this->importer->commit($preview, (int) $r->proposed_by, 'manual', null, mb_substr($r->reason, 0, 255));
        if (! empty($p['published_at'])) {
            DB::table('macro_statistics_data')->where('source_import_batch_id', $res['batch_id'])->update(['published_at' => $p['published_at']]);
        }

        return ['batch_id' => $res['batch_id']];
    }

    private function applyCorrection(object $r, array $p, int $approverId): array
    {
        $o = DB::table('macro_statistics_data')->where('id', $r->observation_id)->first() ?? throw new LogicException("Observation {$r->observation_id} no longer exists.");
        $new = ['value' => $p['value'], 'is_forecast' => ! empty($p['is_forecast']) ? 1 : 0, 'actual_value' => empty($p['is_forecast']) ? $p['value'] : null,
            'published_at' => $p['published_at'] ?? null, 'notes' => mb_substr(trim(($o->notes ? $o->notes . ' | ' : '') . 'Corrected: ' . $r->reason), 0, 2000), 'updated_at' => now()];
        DB::table('macro_statistics_data')->where('id', $o->id)->update($new);
        AuditLoggerService::log('Macro Observation Corrected', 'macro_statistics_data', (int) $o->id, ['old_values' => ['value' => $o->value, 'is_forecast' => $o->is_forecast, 'published_at' => $o->published_at],
            'new_values' => $new, 'meta' => ['user' => $approverId, 'proposed_by' => $r->proposed_by, 'override' => $r->id, 'reason' => $r->reason]]);

        return ['observation' => (int) $o->id, 'old_value' => $o->value, 'new_value' => $p['value']];
    }

    private function applyDeletion(object $r, int $approverId): array
    {
        $o = DB::table('macro_statistics_data')->where('id', $r->observation_id)->first() ?? throw new LogicException("Observation {$r->observation_id} no longer exists.");
        DB::table('macro_statistics_data')->where('id', $o->id)->delete();
        AuditLoggerService::log('Macro Observation Deleted', 'macro_statistics_data', (int) $o->id, ['old_values' => (array) $o, 'meta' => ['user' => $approverId, 'proposed_by' => $r->proposed_by, 'override' => $r->id, 'reason' => $r->reason]]);

        return ['observation' => (int) $o->id, 'deleted_value' => $o->value];
    }
}
