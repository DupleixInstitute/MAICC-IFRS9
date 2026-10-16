<?php

namespace Tests\Feature\Eir;

use App\Models\AuditLog;
use App\Services\Ebanker\GlFeeBuildService;
use App\Services\Eir\HeldFeeClassificationImportService;
use App\Services\Eir\HeldFeeEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Held Fee Classification Export/Reimport Specification v1 (16 October 2026): the held GL fee queue
 * (fee_gl_unassigned) as a CSV identified by external_transaction_id, and a validated re-upload of
 * Finance's decisions, where every accepted row calls GlFeeBuildService::assignHeld() or
 * HeldFeeEvidence::dismiss() exactly as the screen does. Acceptance criteria of section 8 are covered
 * by name in each test's docblock.
 */
class HeldFeeClassificationImportTest extends TestCase
{
    use RefreshDatabase;

    private function contract(string $id): void
    {
        DB::table('contract_eir')->insert(['contract_id' => $id, 'instrument_type' => 'AMORTISED_LOAN', 'rate_type' => 'FIXED', 'origination_date' => '2025-01-01',
            'drawn_amount' => 1000, 'payments_per_year' => 12, 'frequency_source' => 'STATED', 'schedule_source' => 'GENERATED', 'schedule_approval_status' => 'APPROVED',
            'calculation_status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function held(string $ext, array $more = []): int
    {
        return DB::table('fee_gl_unassigned')->insertGetId($more + ['external_transaction_id' => $ext, 'transaction_date' => '2026-08-15', 'batch' => 'TRF 1', 'gl_code' => '4873',
            'amount' => 25.00, 'narration' => 'By Trf Acme arrangement fees', 'reason' => 'NO_LOAN_IN_BATCH: no loan account is a leg of the batch', 'loans_in_batch' => '[]',
            'candidate_contract_id' => null, 'candidate_evidence' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function service(): HeldFeeClassificationImportService
    {
        return app(HeldFeeClassificationImportService::class);
    }

    /** Fills in the decision columns of an exported row (the rest is the posting's own evidence, read back unchanged). */
    private function decided(array $exported, string $decision = '', string $contractId = '', string $reasonNote = ''): array
    {
        return array_merge($exported, ['decision' => $decision, 'contract_id' => $contractId, 'reason_note' => $reasonNote]);
    }

    private function roundTrip(array $rows, int $userId = 1): array
    {
        $csv = $this->service()->toCsv($rows);

        return $this->service()->import($csv, 'test.csv', $userId);
    }

    // -----------------------------------------------------------------
    // Export shape (acceptance 14: no customer_id; customer_name is a label only)
    // -----------------------------------------------------------------

    public function test_export_shows_the_candidate_label_and_whether_it_was_already_dismissed(): void
    {
        $this->contract('104430000072');
        DB::table('loan_books')->insert(['contract_id' => '104430000072', 'customer_id' => 'C1', 'customer_name' => 'MALAWI POLICE SACCO', 'reporting_year' => 2026, 'reporting_month' => 8, 'reporting_period' => '2026-08']);
        $h = $this->held('GL_04a:1', ['candidate_contract_id' => '104430000072', 'candidate_evidence' => 'name and drawdown date']);
        DB::table('fee_gl_candidate_dismissals')->insert(['external_transaction_id' => 'GL_04a:1', 'contract_id' => '104430000072', 'reason' => 'Wrong borrower, checked the letter.', 'dismissed_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('fee_gl_held_notes')->insert(['external_transaction_id' => 'GL_04a:1', 'note' => 'Awaiting Finance confirmation of the batch.', 'author_id' => 2, 'created_at' => now(), 'updated_at' => now()]);

        $rows = $this->service()->exportRows();
        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame('GL_04a:1', $row['external_transaction_id']);
        $this->assertSame((string) $h, $row['held_id']);
        $this->assertSame('MALAWI POLICE SACCO', $row['candidate_customer_name']);
        $this->assertSame('2026-08', $row['candidate_as_of_period']);
        $this->assertSame('true', $row['candidate_previously_dismissed']);
        $this->assertSame('Awaiting Finance confirmation of the batch.', $row['deferral_note']);
        $this->assertSame('', $row['contract_id']);
        $this->assertSame('', $row['decision']);
        $this->assertArrayNotHasKey('customer_id', $row);

        $csv = $this->service()->toCsv($rows);
        $this->assertStringNotContainsString('customer_id', $csv);
        $this->assertStringContainsString(HeldFeeClassificationImportService::VERSION, $csv);
    }

    // -----------------------------------------------------------------
    // File format (acceptance 15): leading-zero and long account numbers, narration with
    // commas/quotes/newlines, formula-injection neutralised, round-tripped losslessly.
    // -----------------------------------------------------------------

    public function test_the_csv_round_trip_preserves_every_value_and_neutralises_a_formula(): void
    {
        $h = $this->held('GL_04a:2', [
            'candidate_contract_id' => '000104430000072', // leading zeros: significant
            'batch' => 'TRF 1', 'narration' => '=SUM(A1:A2), says "pay now"' . "\nsecond line",
            'loans_in_batch' => json_encode(['000104430000072', '0104450000048']),
        ]);
        $rows = $this->service()->exportRows();
        $csv = $this->service()->toCsv($rows);

        // the leading-zero candidate and the formula-like narration are both apostrophe-guarded in the file
        $this->assertStringContainsString("'000104430000072", $csv);
        $this->assertStringContainsString("'=SUM(A1:A2)", $csv);

        // and the round trip (export -> import, every cell left exactly as exported) reads every value back unchanged
        $result = $this->roundTrip([$this->decided($rows[0])]);
        $this->assertSame(1, $result['outcomes']['unchanged']);
        $this->assertSame(0, $result['outcomes']['rejected']);
    }

    // -----------------------------------------------------------------
    // Acceptance 1: an untouched re-upload of the current queue reports every row UNCHANGED,
    // with no write and no audit entry for any row.
    // -----------------------------------------------------------------

    public function test_an_untouched_reupload_is_a_true_noop(): void
    {
        $this->held('GL_04a:3');
        $this->held('GL_04a:4', ['candidate_contract_id' => '104450000048']);
        $rows = array_map(fn ($r) => $this->decided($r), $this->service()->exportRows());

        $before = AuditLog::count();
        $result = $this->roundTrip($rows);

        $this->assertSame(2, $result['outcomes']['unchanged']);
        $this->assertSame(0, $result['outcomes']['tied'] + $result['outcomes']['candidate_rejected'] + $result['outcomes']['deferred'] + $result['outcomes']['rejected']);
        $this->assertSame(2, DB::table('fee_gl_unassigned')->count(), 'nothing was tied or removed');
        // one upload-level entry only; no per-row entry for a no-op
        $this->assertSame($before + 1, AuditLog::count());
        $this->assertSame('Held Fee Import Processed', AuditLog::latest('id')->first()->action);
    }

    // -----------------------------------------------------------------
    // Acceptance 2: TIE moves the row into contract_fees, with an audit entry, and it is gone
    // from the next export. Acceptance 10: editing the display-only customer_name has no effect.
    // -----------------------------------------------------------------

    public function test_tie_resolves_the_posting_and_display_only_columns_are_never_read(): void
    {
        $this->contract('104430000072');
        $h = $this->held('GL_04a:5', ['candidate_contract_id' => '104430000072']);
        $row = $this->service()->exportRows()[0];
        $row['candidate_customer_name'] = 'SOMEONE ELSE ENTIRELY'; // edited in the spreadsheet: must have no effect
        $decided = $this->decided($row, 'TIE', '104430000072', 'Confirmed against the drawdown narration and date.');

        $result = $this->roundTrip([$decided]);
        $this->assertSame(['unchanged' => 0, 'tied' => 1, 'candidate_rejected' => 0, 'deferred' => 0, 'rejected' => 0, 'already_resolved' => 0, 'stale_evidence' => 0, 'not_currently_held' => 0, 'conflict' => 0], $result['outcomes']);
        $this->assertSame(0, DB::table('fee_gl_unassigned')->count());
        $this->assertSame(1, DB::table('contract_fees')->where('contract_id', '104430000072')->where('external_transaction_id', 'GL_04a:5')->count());
        $this->assertSame([], $this->service()->exportRows(), 'gone from the next export');

        // the audit entry assignHeld() already writes carries the bulk-import channel and the upload's id
        $tie = AuditLog::where('action', 'GL Fee Tied To Loan')->first();
        $this->assertSame('bulk_import', $tie->meta['channel']);
        $this->assertSame($result['audit_id'], $tie->meta['import_batch_id']);
    }

    /** Acceptance 18: the screen's own tie carries no channel key at all, proving the two are distinguishable. */
    public function test_a_screen_driven_tie_carries_no_channel_key(): void
    {
        $this->contract('104430000072');
        $h = $this->held('GL_04a:6', ['candidate_contract_id' => '104430000072']);
        app(GlFeeBuildService::class)->assignHeld($h, '104430000072', 9, 'Confirmed from the screen directly.');
        $tie = AuditLog::where('action', 'GL Fee Tied To Loan')->first();
        $this->assertArrayNotHasKey('channel', $tie->meta);
    }

    // -----------------------------------------------------------------
    // Acceptance 3 and 4: CANDIDATE_REJECT leaves the posting held and records the dismissal;
    // a contract_id that does not match the current candidate is REJECTED, never substituted.
    // Acceptance 19: the import's own dismissal audit entry carries the channel too.
    // -----------------------------------------------------------------

    public function test_candidate_reject_leaves_the_posting_held_and_records_the_dismissal(): void
    {
        $h = $this->held('GL_04a:7', ['candidate_contract_id' => '104430000072']);
        $row = $this->service()->exportRows()[0];

        $result = $this->roundTrip([$this->decided($row, 'CANDIDATE_REJECT', '104430000072', 'The narration names a different borrower.')]);
        $this->assertSame(1, $result['outcomes']['candidate_rejected']);
        $this->assertSame(1, DB::table('fee_gl_unassigned')->count(), 'the posting stays held');
        $this->assertSame(1, DB::table('fee_gl_candidate_dismissals')->where('external_transaction_id', 'GL_04a:7')->count());
        $reject = AuditLog::where('action', 'Held Fee Candidate Dismissed')->first();
        $this->assertSame('bulk_import', $reject->meta['channel']);

        // the next export shows this exact candidate as already dismissed
        $next = $this->service()->exportRows()[0];
        $this->assertSame('true', $next['candidate_previously_dismissed']);

        // a mismatched contract_id is rejected, not substituted onto the real candidate
        $mismatch = $this->roundTrip([$this->decided($next, 'CANDIDATE_REJECT', '104430000099', 'Wrong account typed in error.')]);
        $this->assertSame(1, $mismatch['outcomes']['rejected']);
        $this->assertStringContainsString('does not match', $mismatch['rows'][0]['detail']);
    }

    // -----------------------------------------------------------------
    // Acceptance 5: DEFER writes a durable note that a rebuild of fee_gl_unassigned does not erase.
    // -----------------------------------------------------------------

    public function test_defer_writes_a_note_that_survives_a_rebuild(): void
    {
        $h = $this->held('GL_04a:8');
        $row = $this->service()->exportRows()[0];
        $result = $this->roundTrip([$this->decided($row, 'DEFER', '', 'Waiting on Finance to confirm the batch narration.')]);
        $this->assertSame(1, $result['outcomes']['deferred']);
        $this->assertSame(1, DB::table('fee_gl_unassigned')->count(), 'the posting stays held');

        // simulate a rebuild: the held row is deleted and reinserted with a new numeric id, same external id
        DB::table('fee_gl_unassigned')->where('id', $h)->delete();
        $newId = $this->held('GL_04a:8');
        $this->assertNotEquals($h, $newId);

        $after = $this->service()->exportRows()[0];
        $this->assertSame('Waiting on Finance to confirm the batch narration.', $after['deferral_note']);
    }

    // -----------------------------------------------------------------
    // Acceptance 6: a rebuild between export and re-import (new held_id, same external id) is
    // resolved correctly by external_transaction_id.
    // -----------------------------------------------------------------

    public function test_a_rebuild_between_export_and_reimport_is_resolved_by_external_id_not_held_id(): void
    {
        $this->contract('104430000072');
        $h = $this->held('GL_04a:9', ['candidate_contract_id' => '104430000072']);
        $original = $this->decided($this->service()->exportRows()[0], 'TIE', '104430000072', 'Confirmed before the rebuild ran.');

        // the rebuild: same posting, new numeric id, nothing upstream changed
        DB::table('fee_gl_unassigned')->where('id', $h)->delete();
        $rebuiltId = $this->held('GL_04a:9', ['candidate_contract_id' => '104430000072']);
        $this->assertNotEquals($h, $rebuiltId);

        $result = $this->roundTrip([$original]);
        $this->assertSame(1, $result['outcomes']['tied'], json_encode($result['rows']));
        $this->assertSame(0, DB::table('fee_gl_unassigned')->count());
    }

    // -----------------------------------------------------------------
    // Acceptance 7: a rebuild that changes the candidate under a pending decision is stale evidence,
    // refused, never applied against evidence nobody actually looked at.
    // -----------------------------------------------------------------

    public function test_a_changed_candidate_since_export_is_stale_evidence_not_guessed(): void
    {
        $h = $this->held('GL_04a:10', ['candidate_contract_id' => '104430000072']);
        $this->contract('104430000072');
        $this->contract('104450000048');
        $original = $this->decided($this->service()->exportRows()[0], 'CANDIDATE_REJECT', '104430000072', 'Confirmed before the rebuild changed the candidate.');

        DB::table('fee_gl_unassigned')->where('id', $h)->update(['candidate_contract_id' => '104450000048']);

        $result = $this->roundTrip([$original]);
        $this->assertSame(1, $result['outcomes']['stale_evidence']);
        $this->assertSame(1, DB::table('fee_gl_unassigned')->count(), 'untouched');
        $this->assertNull(DB::table('fee_gl_candidate_dismissals')->where('external_transaction_id', 'GL_04a:10')->first());
    }

    // -----------------------------------------------------------------
    // Acceptance 8 and 9: a row already resolved (by the screen, or a previous import) reports
    // already_resolved for the same account, and conflict for a different one; a row genuinely
    // gone reports not_currently_held.
    // -----------------------------------------------------------------

    public function test_already_resolved_and_conflict_are_reported_distinctly_from_a_fresh_tie(): void
    {
        $this->contract('104430000072');
        $this->contract('104450000048');
        $h = $this->held('GL_04a:11', ['candidate_contract_id' => '104430000072']);
        $row = $this->decided($this->service()->exportRows()[0], 'TIE', '104430000072', 'First decision, from the screen.');

        // resolved by the screen between export and import
        app(GlFeeBuildService::class)->assignHeld($h, '104430000072', 1, 'Tied from the screen while the file was out.');

        $sameAccount = $this->roundTrip([$row]);
        $this->assertSame(1, $sameAccount['outcomes']['already_resolved']);
        $this->assertSame(0, $sameAccount['outcomes']['tied']);

        $differentAccount = $this->roundTrip([$this->decided($row, 'TIE', '104450000048', 'Second opinion, different loan entirely.')]);
        $this->assertSame(1, $differentAccount['outcomes']['conflict']);
        $this->assertStringContainsString('104430000072', $differentAccount['rows'][0]['detail']);

        // a posting genuinely gone (reversed upstream, never resolved anywhere) is distinct from both
        $gone = $this->roundTrip([$this->decided(['external_transaction_id' => 'GL_04a:NEVER', 'held_id' => '0', 'transaction_date' => '2026-08-01', 'batch' => 'TRF 1', 'gl_code' => '4873', 'amount' => '10.00', 'narration' => '', 'reason' => '', 'loans_in_batch' => '', 'candidate_contract_id' => '', 'candidate_customer_name' => '', 'candidate_as_of_period' => '', 'candidate_evidence' => '', 'candidate_previously_dismissed' => 'false', 'deferral_note' => '', 'deferral_note_by' => '', 'deferral_note_at' => ''], 'TIE', '104430000072', 'This posting does not exist.')]);
        $this->assertSame(1, $gone['outcomes']['not_currently_held']);
    }

    // -----------------------------------------------------------------
    // Acceptance 11: invalid rows are each REJECTED with a specific reason, none blocking another row.
    // Acceptance 12: duplicate external_transaction_id rejects every row sharing it.
    // Acceptance 13: a single mixed file processes every row independently.
    // -----------------------------------------------------------------

    public function test_invalid_rows_are_rejected_with_a_specific_reason_and_never_block_another_row(): void
    {
        $this->contract('104430000072');
        $h1 = $this->held('GL_04a:12', ['candidate_contract_id' => '104430000072']);
        $h2 = $this->held('GL_04a:13');
        $h3 = $this->held('GL_04a:14');
        $h4 = $this->held('GL_04a:15');
        $rows = collect($this->service()->exportRows())->keyBy('external_transaction_id');

        $tie = $this->decided($rows['GL_04a:12'], 'TIE', '104430000072', 'A clean, valid tie for comparison.');
        $shortReason = $this->decided($rows['GL_04a:13'], 'DEFER', '', 'short');
        $unknownDecision = $this->decided($rows['GL_04a:14'], 'MAYBE', '', 'A reason long enough to pass the length check.');
        $inconsistent = $this->decided($rows['GL_04a:15'], '', '104430000072', 'Filled contract_id but decision left blank.');

        $result = $this->roundTrip([$tie, $shortReason, $unknownDecision, $inconsistent]);
        $byId = collect($result['rows'])->keyBy('external_transaction_id');
        $this->assertSame('tied', $byId['GL_04a:12']['outcome']);
        $this->assertSame('rejected', $byId['GL_04a:13']['outcome']);
        $this->assertStringContainsString('10 characters', $byId['GL_04a:13']['detail']);
        $this->assertSame('rejected', $byId['GL_04a:14']['outcome']);
        $this->assertStringContainsString('Unrecognised', $byId['GL_04a:14']['detail']);
        $this->assertSame('rejected', $byId['GL_04a:15']['outcome']);
    }

    public function test_duplicate_external_transaction_id_rejects_every_row_sharing_it(): void
    {
        $this->held('GL_04a:16');
        $row = $this->service()->exportRows()[0];
        $a = $this->decided($row, 'DEFER', '', 'First copy of a duplicated row in the file.');
        $b = $this->decided($row, 'DEFER', '', 'Second copy of a duplicated row in the file.');

        $result = $this->roundTrip([$a, $b]);
        $this->assertSame(2, $result['outcomes']['rejected']);
        $this->assertSame(0, $result['outcomes']['deferred']);
        foreach ($result['rows'] as $r) {
            $this->assertStringContainsString('Duplicate', $r['detail']);
        }
    }

    // -----------------------------------------------------------------
    // Acceptance 17: one upload-level audit entry per import, matching the per-row result exactly,
    // including rows that mutated nothing.
    // -----------------------------------------------------------------

    public function test_one_upload_level_audit_entry_matches_the_returned_outcome_counts(): void
    {
        $this->contract('104430000072');
        $this->contract('104450000048');
        $h1 = $this->held('GL_04a:17', ['candidate_contract_id' => '104430000072']);
        $h2 = $this->held('GL_04a:18', ['candidate_contract_id' => '104450000048']);
        app(GlFeeBuildService::class)->assignHeld($h2, '104450000048', 1, 'Tied from the screen before this import ran.');
        $rows = collect($this->service()->exportRows())->keyBy('external_transaction_id');
        // GL_04a:18 is gone from the export now (resolved), so build its row by hand to test the conflict path
        $resolvedRow = $this->decided(['external_transaction_id' => 'GL_04a:18', 'held_id' => (string) $h2, 'transaction_date' => '2026-08-15', 'batch' => 'TRF 1', 'gl_code' => '4873', 'amount' => '25.00', 'narration' => '', 'reason' => '', 'loans_in_batch' => '', 'candidate_contract_id' => '104450000048', 'candidate_customer_name' => '', 'candidate_as_of_period' => '', 'candidate_evidence' => '', 'candidate_previously_dismissed' => 'false', 'deferral_note' => '', 'deferral_note_by' => '', 'deferral_note_at' => ''], 'TIE', '104430000072', 'Wrong account, should conflict.');

        $tie = $this->decided($rows['GL_04a:17'], 'TIE', '104430000072', 'A clean valid tie for the audit-matching test.');
        $invalid = $this->decided($rows['GL_04a:17'], '', '', ''); // duplicate of 17's external id, but blank: becomes a duplicate rejection, not unchanged

        $result = $this->roundTrip([$tie, $resolvedRow, $invalid]);
        $entry = AuditLog::find($result['audit_log_id']);
        $this->assertSame('Held Fee Import Processed', $entry->action);
        $this->assertSame($result['outcomes'], $entry->new_values['outcomes']);
        $this->assertCount(3, $entry->new_values['rows']);
        $this->assertSame($result['outcomes']['conflict'] ?? 0, 1);
        $this->assertSame($result['outcomes']['rejected'], 2); // the duplicate pair
    }
}
