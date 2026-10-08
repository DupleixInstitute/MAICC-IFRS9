<?php

namespace App\Services\Compliance;

use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The compliance audit register (spec v4 section 12.5): the workbooks'
 * rows loaded from docs/compliance/<stem>.json (the builder's export of
 * the data modules), signed under maker-checker, exported back as the
 * signed state the builder reads when it regenerates the three files.
 */
class ComplianceAuditService
{
    public const STATUSES = ['Done', 'Partially done', 'Outstanding', 'Not applicable, documented', 'Evidence needed from MAIIC'];

    /** Load (or refresh) the register from the builder's JSON files; a signed row keeps its signed state. */
    public function load(?string $dir = null): array
    {
        $dir ??= base_path('docs/compliance');
        $out = [];
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            if (str_ends_with($file, '.signed.json')) {
                continue;
            }
            $data = json_decode(file_get_contents($file), true);
            if (! is_array($data) || ! isset($data['key'], $data['meta'], $data['rows'])) {
                continue;
            }
            $m = $data['meta'];
            $auditId = (int) (DB::table('compliance_audits')->where('key', $data['key'])->value('id') ?? 0);
            $values = ['file_stem' => $m['file_stem'], 'short' => $m['short'], 'title' => $m['title'], 'basis' => $m['basis'] ?? null, 'source' => $m['source'] ?? null, 'reviewer' => $m['reviewer'] ?? null, 'loaded_at' => now(), 'updated_at' => now()];
            if ($auditId) {
                DB::table('compliance_audits')->where('id', $auditId)->update($values);
            } else {
                $auditId = (int) DB::table('compliance_audits')->insertGetId($values + ['key' => $data['key'], 'created_at' => now()]);
            }
            $n = 0;
            foreach ($data['rows'] as $i => $r) {
                $existing = DB::table('compliance_audit_rows')->where('audit_id', $auditId)->where('reference', $r['reference'])->first();
                $row = array_intersect_key($r, array_flip(['part', 'section_name', 'requirement', 'engine_comment', 'general_comment', 'compliance_comment', 'where_to_see', 'governance_setting', 'test'])) + ['ordering' => $i, 'updated_at' => now()];
                if ($existing) {
                    // the module's status is taken only where no one has signed the row
                    if ($existing->signed_at === null) {
                        $row['status'] = $r['status'];
                    }
                    DB::table('compliance_audit_rows')->where('id', $existing->id)->update($row);
                } else {
                    DB::table('compliance_audit_rows')->insert($row + ['audit_id' => $auditId, 'reference' => $r['reference'], 'status' => $r['status'], 'created_at' => now()]);
                }
                $n++;
            }
            foreach ($data['findings'] ?? [] as $f) {
                DB::table('compliance_findings')->updateOrInsert(['audit_id' => $auditId, 'number' => $f['number']], array_intersect_key($f, array_flip(['reference', 'finding', 'what_was_found', 'impact', 'recommended_action', 'owner', 'status'])) + ['updated_at' => now(), 'created_at' => now()]);
            }
            $out[$data['key']] = $n;
        }

        return $out;
    }

    /** A reviewer proposes a status and signs the row; a second person approves. */
    public function sign(int $rowId, string $status, ?int $userId, ?string $note = null): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new RuntimeException("Unknown status '{$status}'.");
        }
        $row = DB::table('compliance_audit_rows')->where('id', $rowId)->first() ?? throw new RuntimeException("No row {$rowId}.");
        if ($status === 'Done' && trim((string) $row->test) === '') {
            throw new RuntimeException('A row may be Done only when the test that proves it is named.');
        }
        DB::table('compliance_audit_rows')->where('id', $rowId)->update(['proposed_status' => $status, 'signed_by' => $userId, 'signed_at' => now(), 'sign_note' => $note, 'approved_by' => null, 'approved_at' => null, 'updated_at' => now()]);
        AuditLoggerService::log('Compliance Row Signed', 'compliance_audit_rows', $rowId, ['old_values' => ['status' => $row->status], 'new_values' => ['proposed_status' => $status, 'note' => $note], 'meta' => ['user' => $userId]]);
    }

    public function approve(int $rowId, ?int $approverId): void
    {
        $row = DB::table('compliance_audit_rows')->where('id', $rowId)->first() ?? throw new RuntimeException("No row {$rowId}.");
        if ($row->proposed_status === null) {
            throw new RuntimeException('Nothing proposed on this row.');
        }
        if ($approverId !== null && (int) $row->signed_by === $approverId) {
            throw new RuntimeException('Maker-checker: the approver must be a different person from the signer.');
        }
        DB::table('compliance_audit_rows')->where('id', $rowId)->update(['status' => $row->proposed_status, 'proposed_status' => null, 'approved_by' => $approverId, 'approved_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('Compliance Row Approved', 'compliance_audit_rows', $rowId, ['old_values' => ['status' => $row->status], 'new_values' => ['status' => $row->proposed_status], 'meta' => ['approved_by' => $approverId, 'signed_by' => $row->signed_by]]);
    }

    /** Write the signed state beside each workbook for the builder to read. */
    public function exportSigned(?string $dir = null): array
    {
        $dir ??= base_path('docs/compliance');
        $out = [];
        foreach (DB::table('compliance_audits')->get() as $a) {
            $rows = DB::table('compliance_audit_rows as r')->leftJoin('users as s', 's.id', '=', 'r.signed_by')->leftJoin('users as ap', 'ap.id', '=', 'r.approved_by')->where('r.audit_id', $a->id)->whereNotNull('r.approved_at')
                ->get(['r.reference', 'r.status', 's.name as signed_by', 'r.signed_at', 'ap.name as approved_by', 'r.approved_at'])->map(fn ($r) => (array) $r)->all();
            file_put_contents("{$dir}/{$a->file_stem}.signed.json", json_encode(['key' => $a->key, 'exported_at' => now()->toDateTimeString(), 'rows' => $rows], JSON_PRETTY_PRINT));
            $out[$a->key] = count($rows);
        }

        return $out;
    }

    public function counts(int $auditId): array
    {
        $c = array_fill_keys(self::STATUSES, 0);
        foreach (DB::table('compliance_audit_rows')->where('audit_id', $auditId)->selectRaw('status, count(*) n')->groupBy('status')->get() as $r) {
            $c[$r->status] = (int) $r->n;
        }

        return $c;
    }
}
