<?php

namespace App\Services\Eir;

use App\Models\AuditLog;
use App\Services\AuditLoggerService;
use App\Services\Ebanker\GlFeeBuildService;
use App\Support\ContractId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Held Fee Classification Export/Reimport Specification v1 (16 October 2026): a download of the
 * held GL fee queue (fee_gl_unassigned) identified by its durable external_transaction_id, and a
 * validated re-upload of Finance's decisions, where every accepted decision calls exactly the
 * governed operation the screen already calls — GlFeeBuildService::assignHeld() or
 * HeldFeeEvidence::dismiss() — plus one small new durable note for a deferral (fee_gl_held_notes).
 *
 * Not a new matching rule, not a second way to tie a fee or dismiss a candidate, and not a terminal
 * "not a loan's fee" disposition (section 3): this is a bulk front end to two operations that already
 * exist, shaped like FeeImportService::import() — not silent, every row accounted for.
 */
class HeldFeeClassificationImportService
{
    public const VERSION = 'held_fee_classification_file_v1';

    /** Export/import column order (section 5). */
    public const COLUMNS = [
        'external_transaction_id', 'held_id', 'transaction_date', 'batch', 'gl_code', 'amount', 'narration', 'reason',
        'loans_in_batch', 'candidate_contract_id', 'candidate_customer_name', 'candidate_as_of_period', 'candidate_evidence',
        'candidate_previously_dismissed', 'deferral_note', 'deferral_note_by', 'deferral_note_at',
        'contract_id', 'decision', 'reason_note',
    ];

    /**
     * Columns an E-Banker account number can appear in (or that are themselves such a number), written
     * and read as text: a leading zero or a long digit string must survive a spreadsheet round trip
     * (section 5). Always apostrophe-prefixed on export, always stripped of one leading apostrophe on
     * import, whatever the cell's content.
     */
    private const TEXT_COLUMNS = ['external_transaction_id', 'held_id', 'candidate_contract_id', 'loans_in_batch', 'contract_id'];

    private const OUTCOMES = ['unchanged', 'tied', 'candidate_rejected', 'deferred', 'rejected', 'already_resolved', 'stale_evidence', 'not_currently_held', 'conflict'];

    public const DECISIONS = ['TIE', 'CANDIDATE_REJECT', 'DEFER'];

    // -----------------------------------------------------------------
    // Export
    // -----------------------------------------------------------------

    /**
     * One row per currently-held posting, current state (section 5): the posting itself, the build's
     * own candidate (a label, never a match key — section 4), whether that exact candidate was already
     * dismissed on an earlier rebuild, and the latest deferral note, if any. The three decision columns
     * are left blank for Finance to fill in.
     *
     * @return list<array<string,string>>
     */
    public function exportRows(): array
    {
        if (! Schema::hasTable('fee_gl_unassigned')) {
            return [];
        }
        $held = DB::table('fee_gl_unassigned')->orderBy('transaction_date')->orderBy('id')->get();
        $dismissed = Schema::hasTable('fee_gl_candidate_dismissals')
            ? DB::table('fee_gl_candidate_dismissals')->get(['external_transaction_id', 'contract_id'])
                ->keyBy(fn ($d) => $d->external_transaction_id . '|' . ltrim((string) $d->contract_id, '0'))
            : collect();
        $notes = Schema::hasTable('fee_gl_held_notes')
            ? DB::table('fee_gl_held_notes')->get()->keyBy('external_transaction_id')
            : collect();
        $candidateIds = $held->pluck('candidate_contract_id')->filter()->unique()->values()->all();
        // customer_name is already landed on loan_books per contract_id (section 3): a join against data
        // already held, not a new E-Banker extract. The latest reporting_period per candidate, for the label.
        $names = $candidateIds === [] ? collect() : DB::table('loan_books')
            ->whereIn('contract_id', $candidateIds)->orderByDesc('reporting_period')
            ->get(['contract_id', 'customer_name', 'reporting_period'])->unique('contract_id')->keyBy('contract_id');

        return $held->map(function ($h) use ($dismissed, $notes, $names) {
            $cid = $h->candidate_contract_id;
            $name = $cid !== null ? ($names[$cid] ?? null) : null;
            $note = $notes[$h->external_transaction_id] ?? null;
            $dismissedKey = $cid !== null ? $h->external_transaction_id . '|' . ltrim((string) $cid, '0') : null;

            return [
                'external_transaction_id' => (string) $h->external_transaction_id,
                'held_id' => (string) $h->id,
                'transaction_date' => (string) ($h->transaction_date ?? ''),
                'batch' => (string) $h->batch,
                'gl_code' => (string) $h->gl_code,
                'amount' => number_format((float) $h->amount, 2, '.', ''),
                'narration' => (string) ($h->narration ?? ''),
                'reason' => (string) $h->reason,
                'loans_in_batch' => implode(';', json_decode((string) ($h->loans_in_batch ?? '[]'), true) ?: []),
                'candidate_contract_id' => (string) ($cid ?? ''),
                'candidate_customer_name' => (string) ($name->customer_name ?? ''),
                'candidate_as_of_period' => (string) ($name->reporting_period ?? ''),
                'candidate_evidence' => (string) ($h->candidate_evidence ?? ''),
                'candidate_previously_dismissed' => ($dismissedKey !== null && $dismissed->has($dismissedKey)) ? 'true' : 'false',
                'deferral_note' => (string) ($note->note ?? ''),
                'deferral_note_by' => $note !== null ? (string) $note->author_id : '',
                'deferral_note_at' => (string) ($note->updated_at ?? ''),
                'contract_id' => '', 'decision' => '', 'reason_note' => '',
            ];
        })->all();
    }

    /** The export as a CSV string: a version comment line, the header, then one row per posting (section 5). */
    public function toCsv(array $rows): string
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, '#' . self::VERSION . "\n");
        fputcsv($out, self::COLUMNS);
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($col) => $this->safeCell($col, (string) ($row[$col] ?? '')), self::COLUMNS));
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * A text-identity column is always written with a leading apostrophe, whatever its content, so a
     * spreadsheet application never auto-detects it as a number and strips a leading zero or renders a
     * long account number in scientific notation (section 5). Any other column whose value would open
     * with =, +, - or @ gets the same apostrophe, so it can never be read as a formula on open.
     */
    private function safeCell(string $column, string $value): string
    {
        if (in_array($column, self::TEXT_COLUMNS, true)) {
            return "'" . $value;
        }
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /** The apostrophe safeCell() writes, stripped back off on read, from either kind of column. */
    private function unsafeCell(string $value): string
    {
        return str_starts_with($value, "'") ? substr($value, 1) : $value;
    }

    // -----------------------------------------------------------------
    // Import
    // -----------------------------------------------------------------

    /**
     * Reads, validates and applies a decision file, inside the uploader's own authenticated request
     * (section 6: the execution model, stated because it determines who every audit entry is
     * attributed to). Returns the outcome counts and one result row per input row, in file order; a
     * duplicate external_transaction_id rejects every row sharing it, deterministically, regardless of
     * file order.
     *
     * @return array{outcomes: array<string,int>, rows: list<array<string,mixed>>, audit_id: int}
     */
    public function import(string $contents, string $fileName, int $userId): array
    {
        $rows = $this->parseCsv($contents);

        $counts = [];
        foreach ($rows as $r) {
            $counts[$r['external_transaction_id']] = ($counts[$r['external_transaction_id']] ?? 0) + 1;
        }
        $duplicates = array_keys(array_filter($counts, fn ($n) => $n > 1));

        // A stable id correlating every row's own audit entry back to this one upload, generated
        // before any row is processed so it can be threaded through $origin on each row. Deliberately
        // not the upload-level entry's own database id: AuditLog is strictly append-only (its model
        // refuses an update once created, so the entry can only be written once, with its final
        // content), and the final content — every row's outcome — is only known once every row has
        // been processed, which must happen before that one entry can be written at all. A
        // self-generated id, carried on both sides, serves the correlation the specification asks
        // for without needing to update an audit entry after writing it.
        $batchId = (string) \Illuminate\Support\Str::uuid();

        $outcomes = array_fill_keys(self::OUTCOMES, 0);
        $results = [];
        foreach ($rows as $r) {
            $xid = $r['external_transaction_id'];
            if ($xid === '' || in_array($xid, $duplicates, true)) {
                $detail = $xid === '' ? 'No external_transaction_id.' : 'Duplicate external_transaction_id in this file.';
                $outcomes['rejected']++;
                $results[] = ['external_transaction_id' => $xid, 'decision' => $r['decision'], 'outcome' => 'rejected', 'detail' => $detail];
                continue;
            }
            $res = $this->processRow($r, $userId, $batchId);
            $outcomes[$res['outcome']]++;
            $results[] = ['external_transaction_id' => $xid, 'decision' => $r['decision']] + $res;
        }

        // the one upload-level audit entry per import (section 6.1), written once, with its final content
        $entry = AuditLog::create([
            'user_id' => auth()->id(), 'action' => 'Held Fee Import Processed', 'entity_type' => 'fee_gl_unassigned', 'entity_id' => null,
            'new_values' => ['file_name' => $fileName, 'file_checksum' => hash('sha256', $contents), 'row_count' => count($rows), 'import_batch_id' => $batchId,
                'outcomes' => $outcomes, 'rows' => array_map(fn ($r) => array_intersect_key($r, array_flip(['external_transaction_id', 'decision', 'outcome', 'detail'])), $results)],
            'meta' => ['user' => $userId], 'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent(),
        ]);

        return ['outcomes' => $outcomes, 'rows' => $results, 'audit_id' => $batchId, 'audit_log_id' => (int) $entry->id];
    }

    /** One row, from identity resolution through the decision, locked and transacted so nothing interleaves (section 6). */
    private function processRow(array $r, int $userId, string $importBatchId): array
    {
        return DB::transaction(function () use ($r, $userId, $importBatchId) {
            $xid = $r['external_transaction_id'];
            $held = DB::table('fee_gl_unassigned')->where('external_transaction_id', $xid)->lockForUpdate()->first();

            if ($held === null) {
                $existing = DB::table('contract_fees')->where('source_system', GlFeeBuildService::SOURCE)->where('external_transaction_id', $xid)->first();
                if ($existing === null) {
                    return ['outcome' => 'not_currently_held', 'detail' => 'No current held posting, and no resolved fee line carries this id: it may have been reversed upstream.'];
                }

                return $this->conflictOrResolved($r, $existing);
            }

            if ($this->stale($r, $held)) {
                return ['outcome' => 'stale_evidence', 'detail' => 'The evidence in the file no longer matches the current held posting (a rebuild changed it). Export again before deciding this row.'];
            }

            return $this->decide($r, $held, $userId, $importBatchId);
        });
    }

    /** The file's exported evidence against the current held row: any difference and the decision is refused, not applied blind. */
    private function stale(array $r, object $held): bool
    {
        $fileAmount = number_format((float) $r['amount'], 2, '.', '');
        $heldAmount = number_format((float) $held->amount, 2, '.', '');
        $fileCandidate = $r['candidate_contract_id'] !== '' ? ltrim($r['candidate_contract_id'], '0') : null;
        $heldCandidate = $held->candidate_contract_id !== null ? ltrim((string) $held->candidate_contract_id, '0') : null;

        return $fileAmount !== $heldAmount
            || $r['gl_code'] !== (string) $held->gl_code
            || $r['transaction_date'] !== substr((string) $held->transaction_date, 0, 10)
            || $r['batch'] !== (string) $held->batch
            || $fileCandidate !== $heldCandidate;
    }

    /** The posting is not currently held because it is already resolved: already_resolved, or conflict if the file's TIE names a different loan. */
    private function conflictOrResolved(array $r, object $existing): array
    {
        if ($r['decision'] === 'TIE' && $r['contract_id'] !== '') {
            $want = ContractId::normalise($r['contract_id']);
            if ($want !== null && ltrim((string) $existing->contract_id, '0') !== ltrim($want, '0')) {
                return ['outcome' => 'conflict', 'detail' => "Already tied to loan {$existing->contract_id}, not {$want}: never silently reassigned."];
            }
        }

        return ['outcome' => 'already_resolved', 'detail' => "Already resolved: tied to loan {$existing->contract_id}."];
    }

    /** The held row is current and its evidence is fresh: evaluate the file's decision for it. */
    private function decide(array $r, object $held, int $userId, string $importBatchId): array
    {
        $decision = $r['decision'];
        $contractIdRaw = $r['contract_id'];
        $reasonNote = $r['reason_note'];

        // rule 0: all three blank is an explicit no-op, never a rejection
        if ($decision === '' && $contractIdRaw === '' && $reasonNote === '') {
            return ['outcome' => 'unchanged', 'detail' => ''];
        }

        $origin = ['channel' => 'bulk_import', 'import_batch_id' => $importBatchId];

        return match ($decision) {
            'TIE' => $this->decideTie($contractIdRaw, $reasonNote, $held, $userId, $origin),
            'CANDIDATE_REJECT' => $this->decideCandidateReject($contractIdRaw, $reasonNote, $held, $userId, $origin),
            'DEFER' => $this->decideDefer($contractIdRaw, $reasonNote, $held, $userId, $origin),
            default => ['outcome' => 'rejected', 'detail' => $decision === ''
                ? 'contract_id or reason_note is filled but decision is blank: an inconsistent row is never guessed at.'
                : "Unrecognised decision '{$decision}'."],
        };
    }

    private function reasonError(string $reasonNote): ?string
    {
        $len = mb_strlen(trim($reasonNote));
        if ($len < 10) {
            return 'reason_note must be at least 10 characters.';
        }
        if ($len > 500) {
            return 'reason_note must be at most 500 characters.';
        }

        return null;
    }

    private function decideTie(string $contractIdRaw, string $reasonNote, object $held, int $userId, array $origin): array
    {
        if ($contractIdRaw === '' || $reasonNote === '') {
            return ['outcome' => 'rejected', 'detail' => 'TIE needs both contract_id and reason_note.'];
        }
        if (($e = $this->reasonError($reasonNote)) !== null) {
            return ['outcome' => 'rejected', 'detail' => $e];
        }
        $contractId = ContractId::normalise($contractIdRaw);
        if ($contractId === null) {
            return ['outcome' => 'rejected', 'detail' => "Malformed contract_id '{$contractIdRaw}'."];
        }
        // the unique source/external-id constraint on contract_fees, read directly: a concurrent tie
        // (by the screen, or another import) for this exact posting is never silently re-tied (acceptance 9)
        $existing = DB::table('contract_fees')->where('source_system', GlFeeBuildService::SOURCE)->where('external_transaction_id', $held->external_transaction_id)->first();
        if ($existing !== null) {
            return $this->conflictOrResolved(['decision' => 'TIE', 'contract_id' => $contractId], $existing);
        }
        try {
            app(GlFeeBuildService::class)->assignHeld((int) $held->id, $contractId, $userId, trim($reasonNote), $origin);
        } catch (InvalidArgumentException $e) {
            return ['outcome' => 'rejected', 'detail' => $e->getMessage()];
        }

        return ['outcome' => 'tied', 'detail' => "Tied to loan {$contractId}; resolved, out of the held queue."];
    }

    private function decideCandidateReject(string $contractIdRaw, string $reasonNote, object $held, int $userId, array $origin): array
    {
        if ($contractIdRaw === '' || $reasonNote === '') {
            return ['outcome' => 'rejected', 'detail' => 'CANDIDATE_REJECT needs both contract_id and reason_note.'];
        }
        if (($e = $this->reasonError($reasonNote)) !== null) {
            return ['outcome' => 'rejected', 'detail' => $e];
        }
        $contractId = ContractId::normalise($contractIdRaw);
        $currentCandidate = $held->candidate_contract_id !== null ? ltrim((string) $held->candidate_contract_id, '0') : null;
        if ($contractId === null || $currentCandidate === null || ltrim($contractId, '0') !== $currentCandidate) {
            return ['outcome' => 'rejected', 'detail' => "contract_id does not match this posting's current candidate (" . ($held->candidate_contract_id ?? 'none held') . '): never substituted for a different one.'];
        }
        try {
            HeldFeeEvidence::dismiss((int) $held->id, $contractId, trim($reasonNote), $userId, $origin);
        } catch (InvalidArgumentException $e) {
            return ['outcome' => 'rejected', 'detail' => $e->getMessage()];
        }

        return ['outcome' => 'candidate_rejected', 'detail' => "Candidate {$contractId} rejected for this posting. The posting stays held, for another loan or for a person to tie."];
    }

    private function decideDefer(string $contractIdRaw, string $reasonNote, object $held, int $userId, array $origin): array
    {
        if ($contractIdRaw !== '') {
            return ['outcome' => 'rejected', 'detail' => 'DEFER must leave contract_id blank.'];
        }
        if (($e = $this->reasonError($reasonNote)) !== null) {
            return ['outcome' => 'rejected', 'detail' => $e];
        }
        $note = trim($reasonNote);
        DB::table('fee_gl_held_notes')->updateOrInsert(
            ['external_transaction_id' => $held->external_transaction_id],
            ['note' => mb_substr($note, 0, 2000), 'author_id' => $userId, 'created_at' => now(), 'updated_at' => now()]
        );
        AuditLoggerService::log('Held Fee Deferred', 'fee_gl_unassigned', (int) $held->id, [
            'new_values' => ['external_transaction_id' => $held->external_transaction_id, 'note' => $note],
            'meta' => $origin + ['user' => $userId],
        ]);

        return ['outcome' => 'deferred', 'detail' => 'Deferral note recorded; the posting stays held.'];
    }

    /**
     * @return list<array<string,string>>
     */
    private function parseCsv(string $contents): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $contents);
        rewind($stream);

        $first = fgets($stream);
        if ($first === false || ! str_starts_with(trim($first), '#')) {
            fclose($stream);

            throw new InvalidArgumentException('Not a held fee classification file: the version comment line is missing.');
        }
        if (! str_contains($first, self::VERSION)) {
            fclose($stream);

            throw new InvalidArgumentException("Wrong file version: expected a line naming '" . self::VERSION . "'.");
        }

        $header = fgetcsv($stream);
        if ($header === false || $header === null) {
            fclose($stream);

            throw new InvalidArgumentException('The file has no header row.');
        }
        $header = array_map(fn ($h) => $this->unsafeCell(trim((string) $h)), $header);
        $missing = array_diff(self::COLUMNS, $header);
        if ($missing !== []) {
            fclose($stream);

            throw new InvalidArgumentException('The file is missing column(s): ' . implode(', ', $missing) . '.');
        }

        $rows = [];
        while (($line = fgetcsv($stream)) !== false) {
            if ($line === [null] || $line === []) {
                continue; // a trailing blank line
            }
            $byHeader = [];
            foreach ($header as $i => $col) {
                $byHeader[$col] = $this->unsafeCell(trim((string) ($line[$i] ?? '')));
            }
            $row = [];
            foreach (self::COLUMNS as $col) {
                $row[$col] = $byHeader[$col] ?? '';
            }
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }
}
