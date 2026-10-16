<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Services\ApprovalDocumentService;
use App\Services\AuditLoggerService;
use App\Services\Ebanker\GlFeeBuildService;
use App\Support\Approvals;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Finance's answers on the fee reconciliation workbook, round trip (16 October 2026; spec v4 section 7.8.9).
 *
 * Nothing changes on upload. The uploaded workbook is kept as the supporting document of the upload (approval
 * workflow 'fee-workbook', ApprovalDocumentService), and every answer row becomes a PROPOSED item proposed by
 * the person who uploaded it. A different person approves or rejects each one, or approves all of a sheet with
 * one reason. Approval applies the answer through the services that already own each decision, never by
 * writing their tables:
 *
 *  - 2. Held GL postings: the loan the narration points to, or another loan Finance names, is tied with
 *    GlFeeBuildService::assignHeld; "not a loan fee", "reversal" and "Mega Farm" dismiss the candidate loan
 *    with HeldFeeEvidence::dismiss, or are recorded as not actionable where no loan was named;
 *  - 3. Overlaps: FeeSourcePrecedence::decide, SAME or SEPARATE;
 *  - 5. Lines to classify: FeeClassificationService, classified by the proposer and reviewed by the approver
 *    (the approval is the review; the proposer can never review);
 *  - 4b. Fees never posted: the answer is recorded per fee line (fee_unposted_confirmations), which the
 *    setting los_unposted_fee_basis reads (UnpostedFeeBasis);
 *  - 1, 4, 6, 7, 8 and the Read me confirmations: the answer is recorded; one that implies a change the system
 *    cannot make safely (a wrong amount, a wrong loan) is left as a visible follow-up and, on a locked EIR,
 *    flagged for review (EirLockReviewService::raise).
 *
 * A locked EIR is never reopened or changed: where an approved answer changes the fee lines of a locked EIR,
 * it is flagged for review, naming the answer. Every step is audit-logged.
 */
class FeeWorkbookAnswerService
{
    public const WORKFLOW = 'fee-workbook';
    public const STATUSES = ['PROPOSED', 'APPROVED', 'REJECTED', 'NOT_ACTIONABLE', 'WITHDRAWN'];

    public function __construct(private readonly FeeWorkbookReader $reader)
    {
    }

    // ------------------------------------------------------------------ upload

    /** @return array{upload_id:int, answers:int, refused:list<array>, basis:string} */
    public function upload(UploadedFile $file, int $userId): array
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
            throw new InvalidArgumentException('Upload the workbook as an Excel file (.xlsx), as it was downloaded.');
        }
        $read = $this->reader->read($file->getRealPath());
        if ($read['answers'] === []) {
            throw new InvalidArgumentException('No answer could be read from the workbook' . ($read['refused'] !== [] ? ': ' . count($read['refused']) . ' row(s) were refused, for example ' . $read['refused'][0]['sheet'] . ', ' . $read['refused'][0]['reason'] : '; every yellow column is empty') . '. Nothing was stored.');
        }
        $sha = hash_file('sha256', $file->getRealPath());

        return DB::transaction(function () use ($file, $userId, $read, $sha) {
            $now = now();
            $id = DB::table('fee_workbook_uploads')->insertGetId([
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'basis' => $read['basis'], 'sha256' => $sha,
                'fingerprint_hash' => $read['fingerprint']['keys_hash'] ?? null, 'generated_at' => $read['fingerprint']['generated_at'] ?? null,
                'source_database' => $read['fingerprint']['database'] ?? null, 'status' => 'OPEN', 'answer_count' => count($read['answers']),
                'refused' => json_encode($read['refused']), 'uploaded_by' => $userId, 'uploaded_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach (array_chunk($read['answers'], 200) as $chunk) {
                DB::table('fee_workbook_answers')->insert(array_map(fn ($a) => [
                    'upload_id' => $id, 'sheet' => $a['sheet'], 'row_number' => $a['row_number'], 'record_type' => $a['record_type'], 'record_key' => $a['record_key'],
                    'contract_id' => $a['contract_id'] ?? null, 'answer' => $a['answer'] ?? null, 'loan_account_given' => $a['loan_account_given'] ?? null,
                    'correct_amount' => $a['correct_amount'] ?? null, 'detail' => isset($a['detail']) ? mb_substr((string) $a['detail'], 0, 500) : null,
                    'comment' => $a['comment'] ?? null, 'context' => json_encode($a['context'] ?? []), 'proposed_by' => $userId, 'status' => 'PROPOSED',
                    'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }
            AuditLoggerService::log('Fee Workbook Answers Uploaded', 'fee_workbook_uploads', $id, [
                'new_values' => ['file' => $file->getClientOriginalName(), 'sha256' => $sha, 'basis' => $read['basis'], 'answers' => count($read['answers']),
                    'refused' => count($read['refused']), 'fingerprint' => $read['fingerprint']],
                'meta' => ['user' => $userId],
            ]);
            // the workbook Finance answered is the supporting document of every answer it carries
            app(ApprovalDocumentService::class)->attach(self::WORKFLOW, $id, $file, 'correspondence', 'Finance\'s answers on the fee reconciliation workbook', $userId);

            return ['upload_id' => $id, 'answers' => count($read['answers']), 'refused' => $read['refused'], 'basis' => $read['basis']];
        });
    }

    // ------------------------------------------------------------------ decisions

    /**
     * A second person decides one answer: approve (applied through its service), reject, or record it as not
     * actionable, each with the reason.
     *
     * @return array{status:string, outcome:?string, follow_up:?string, lock_review_id:?int}
     */
    public function decide(int $answerId, string $action, string $reason, int $userId): array
    {
        $reason = trim($reason);
        if (! in_array($action, ['approve', 'reject', 'not_actionable'], true)) {
            throw new InvalidArgumentException("'{$action}' is not approve, reject or not_actionable.");
        }
        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('Give the reason for the decision (at least 10 characters).');
        }
        $answer = DB::table('fee_workbook_answers')->where('id', $answerId)->first() ?? throw new InvalidArgumentException("No answer {$answerId}.");
        if ($answer->status !== 'PROPOSED') {
            throw new LogicException("Answer {$answerId} is already " . strtolower($answer->status) . '.');
        }
        // maker-checker: the person who entered Finance's answers never decides them, with no override
        if ((int) $answer->proposed_by === $userId) {
            throw new LogicException('The person who uploaded Finance\'s answers cannot decide them; a second person approves or rejects each one.');
        }
        Approvals::assertEvidence(self::WORKFLOW, (int) $answer->upload_id);

        $result = match ($action) {
            'reject' => ['status' => 'REJECTED', 'outcome' => 'Rejected: nothing was applied.', 'follow_up' => null, 'lock_review_id' => null],
            'not_actionable' => ['status' => 'NOT_ACTIONABLE', 'outcome' => 'Recorded as not actionable by the approver: nothing was applied.', 'follow_up' => null, 'lock_review_id' => null],
            'approve' => $this->approve($answer, $reason, $userId),
        };
        DB::table('fee_workbook_answers')->where('id', $answerId)->update([
            'status' => $result['status'], 'decided_by' => $userId, 'decided_at' => now(), 'decision_reason' => mb_substr($reason, 0, 2000),
            'outcome' => $result['outcome'] === null ? null : mb_substr($result['outcome'], 0, 4000), 'follow_up' => $result['follow_up'] === null ? null : mb_substr($result['follow_up'], 0, 500),
            'lock_review_id' => $result['lock_review_id'], 'updated_at' => now(),
        ]);
        AuditLoggerService::log('Fee Workbook Answer ' . ucwords(strtolower(str_replace('_', ' ', $result['status']))), 'fee_workbook_answers', $answerId, [
            'old_values' => ['status' => 'PROPOSED'],
            'new_values' => ['status' => $result['status'], 'sheet' => $answer->sheet, 'row' => $answer->row_number, 'record' => $answer->record_key, 'answer' => $answer->answer,
                'reason' => $reason, 'outcome' => $result['outcome'], 'follow_up' => $result['follow_up'], 'lock_review_id' => $result['lock_review_id']],
            'meta' => ['user' => $userId, 'proposed_by' => (int) $answer->proposed_by, 'upload_id' => (int) $answer->upload_id],
        ]);
        $this->closeIfDecided((int) $answer->upload_id, $userId);

        return $result;
    }

    /**
     * Approve every proposed answer of one sheet of an upload with one reason. Each is applied on its own; one
     * that cannot be applied stays proposed and is named.
     *
     * @return array{approved:int, not_actionable:int, failed:list<string>}
     */
    public function approveSheet(int $uploadId, string $sheet, string $reason, int $userId): array
    {
        $ids = DB::table('fee_workbook_answers')->where('upload_id', $uploadId)->where('sheet', $sheet)->where('status', 'PROPOSED')->orderBy('id')->pluck('id');
        if ($ids->isEmpty()) {
            throw new InvalidArgumentException("No answer of \"{$sheet}\" in upload {$uploadId} waits for a decision.");
        }
        // the maker-checker and the evidence rule hold for the whole sheet: checked once, before anything is applied
        if (DB::table('fee_workbook_answers')->whereIn('id', $ids)->where('proposed_by', $userId)->exists()) {
            throw new LogicException('The person who uploaded Finance\'s answers cannot decide them; a second person approves or rejects each one.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new InvalidArgumentException('Give the reason for the decision (at least 10 characters).');
        }
        Approvals::assertEvidence(self::WORKFLOW, $uploadId);
        $out = ['approved' => 0, 'not_actionable' => 0, 'failed' => []];
        foreach ($ids as $id) {
            try {
                $r = $this->decide((int) $id, 'approve', $reason, $userId);
                $r['status'] === 'NOT_ACTIONABLE' ? $out['not_actionable']++ : $out['approved']++;
            } catch (\Throwable $e) {
                $out['failed'][] = "answer {$id}: " . $e->getMessage();
            }
        }
        AuditLoggerService::log('Fee Workbook Sheet Approved', 'fee_workbook_uploads', $uploadId, [
            'new_values' => ['sheet' => $sheet, 'reason' => $reason] + $out, 'meta' => ['user' => $userId]]);

        return $out;
    }

    /** Reject (a second person) or withdraw (the person who uploaded) every answer still proposed (Approvals::decide). */
    public function close(int $uploadId, int $userId, string $action, string $reason): void
    {
        $upload = DB::table('fee_workbook_uploads')->where('id', $uploadId)->first() ?? throw new InvalidArgumentException("No upload {$uploadId}.");
        if ($upload->status !== 'OPEN') {
            throw new LogicException("Fee workbook upload {$uploadId} is not waiting for a decision.");
        }
        if ($action === 'withdraw' && (int) $upload->uploaded_by !== $userId) {
            throw new LogicException("Only the person who uploaded fee workbook {$uploadId} can withdraw it; a second person rejects it instead.");
        }
        if ($action === 'reject' && (int) $upload->uploaded_by === $userId) {
            throw new LogicException("The person who uploaded fee workbook {$uploadId} withdraws it rather than rejecting it.");
        }
        $status = $action === 'withdraw' ? 'WITHDRAWN' : 'REJECTED';
        DB::transaction(function () use ($uploadId, $userId, $reason, $status) {
            $n = DB::table('fee_workbook_answers')->where('upload_id', $uploadId)->where('status', 'PROPOSED')->update(['status' => $status, 'decided_by' => $userId, 'decided_at' => now(),
                'decision_reason' => mb_substr($reason, 0, 2000), 'outcome' => ucfirst(strtolower($status)) . ' with the whole upload: nothing was applied.', 'updated_at' => now()]);
            DB::table('fee_workbook_uploads')->where('id', $uploadId)->update(['status' => $status, 'closed_by' => $userId, 'closed_at' => now(), 'close_reason' => $reason, 'updated_at' => now()]);
            AuditLoggerService::log('Fee workbook ' . ($status === 'WITHDRAWN' ? 'Withdrawn' : 'Rejected'), 'fee_workbook_uploads', $uploadId, [
                'new_values' => ['status' => $status, 'reason' => $reason, 'answers' => $n], 'meta' => ['user' => $userId]]);
        });
    }

    private function closeIfDecided(int $uploadId, int $userId): void
    {
        if (DB::table('fee_workbook_answers')->where('upload_id', $uploadId)->where('status', 'PROPOSED')->exists()) {
            return;
        }
        $n = DB::table('fee_workbook_uploads')->where('id', $uploadId)->where('status', 'OPEN')
            ->update(['status' => 'DECIDED', 'closed_by' => $userId, 'closed_at' => now(), 'close_reason' => 'Every answer decided.', 'updated_at' => now()]);
        if ($n > 0) {
            AuditLoggerService::log('Fee workbook Closed', 'fee_workbook_uploads', $uploadId, ['new_values' => ['status' => 'DECIDED'], 'meta' => ['user' => $userId]]);
        }
    }

    // ------------------------------------------------------------------ applying an approved answer

    /** @return array{status:string, outcome:?string, follow_up:?string, lock_review_id:?int} */
    private function approve(object $answer, string $reason, int $userId): array
    {
        $names = DB::table('users')->whereIn('id', [$answer->proposed_by, $userId])->pluck('name', 'id');
        $why = "Finance's answer on the fee reconciliation workbook (upload {$answer->upload_id}, {$answer->sheet}, row {$answer->row_number}): \"" . ($answer->answer ?? 'no answer picked') . '"'
            . ($answer->comment ? ' - ' . trim($answer->comment) : '') . '; entered by ' . ($names[$answer->proposed_by] ?? "user {$answer->proposed_by}") . ', approved by ' . ($names[$userId] ?? "user {$userId}") . ': ' . $reason;
        $before = $this->lockedState();

        $result = DB::transaction(fn () => $this->apply($answer, $why, $userId));

        // an approved answer never changes a locked EIR: where it changed one's fee lines, the EIR is flagged for review
        $flag = $result['flag'] ?? null;
        $changed = array_keys(array_diff_assoc($this->lockedState(), $before) + array_diff_key($before, $this->lockedState()));
        $flagged = [];
        foreach (array_unique(array_merge($changed, $flag ? [$flag[0]] : [])) as $contract) {
            $text = $flag && $flag[0] === (string) $contract ? $flag[1] : 'An approved answer changed the fee lines of this locked EIR: ' . $result['outcome'];
            $flagged[] = app(EirLockReviewService::class)->raise((string) $contract, mb_substr($text . ' (' . $why . ')', 0, 990), ['workbook_answer_id' => (int) $answer->id, 'upload_id' => (int) $answer->upload_id,
                'sheet' => $answer->sheet, 'record' => $answer->record_key, 'answer' => $answer->answer], $userId, 'workbook:' . $answer->id);
        }
        if ($flagged !== []) {
            $result['outcome'] .= ' Locked EIR flagged for review (flag ' . implode(', ', $flagged) . '); it keeps its figures until a reopening is approved.';
        }

        return ['status' => $result['status'], 'outcome' => $result['outcome'], 'follow_up' => $result['follow_up'] ?? null, 'lock_review_id' => $flagged[0] ?? null];
    }

    /** @return array{status:string, outcome:string, follow_up?:?string, flag?:array{0:string,1:string}} */
    private function apply(object $a, string $why, int $userId): array
    {
        $done = fn (string $outcome, ?string $followUp = null, ?array $flag = null) => ['status' => 'APPROVED', 'outcome' => $outcome, 'follow_up' => $followUp, 'flag' => $flag];
        $none = fn (string $outcome, ?string $followUp = null) => ['status' => 'NOT_ACTIONABLE', 'outcome' => $outcome, 'follow_up' => $followUp];
        $comment = $a->comment ? trim($a->comment) : null;
        if ($a->answer === null) {
            return $done('No answer was picked; the comment is recorded.', $comment);
        }
        $locked = fn (?string $c) => $c !== null && ContractEir::where('contract_id', $c)->whereNotNull('locked_at')->exists();
        $id = (int) (explode(':', $a->record_key)[1] ?? 0);

        switch ($a->record_type) {
            case 'held':
                $held = str_starts_with($a->record_key, 'held#')
                    ? DB::table('fee_gl_unassigned')->where('id', (int) substr($a->record_key, 5))->first()
                    : DB::table('fee_gl_unassigned')->where('external_transaction_id', substr($a->record_key, 5))->first();
                if ($held === null) {
                    throw new LogicException('The held posting is no longer held (it was tied or the GL fee build no longer holds it): reject the answer or record it as not actionable.');
                }
                $candidate = $held->candidate_contract_id ? ltrim((string) $held->candidate_contract_id, '0') : null;
                switch ($a->answer) {
                    case 'Fee of the loan the narration points to':
                    case 'Fee of another loan - give account':
                        $loan = $a->answer === 'Fee of another loan - give account' ? \App\Support\ContractId::normalise($a->loan_account_given) : $candidate;
                        if ($loan === null) {
                            throw new LogicException($a->answer === 'Fee of another loan - give account'
                                ? 'Finance gave no loan account in "Loan account it belongs to": reject the answer, or record it as not actionable.'
                                : 'The system names no loan for this posting, so "the loan the narration points to" is none: reject the answer, or record it as not actionable.');
                        }
                        app(GlFeeBuildService::class)->assignHeld((int) $held->id, $loan, $userId, mb_substr($why, 0, 500));

                        return $done("Tied to loan {$loan} through GlFeeBuildService::assignHeld: the fee line now waits for its classification and review.");
                    case 'Not a loan fee':
                    case 'Reversal or reallocation - nets off':
                    case 'Mega Farm - outside the EIR':
                        if ($candidate === null) {
                            return $none('Recorded: no loan is named for this posting, so there is no candidate to dismiss; the posting stays held.', "Finance: {$a->answer}" . ($comment ? " ({$comment})" : '') . '. The posting stays on Held GL fee lines.');
                        }
                        HeldFeeEvidence::dismiss((int) $held->id, $candidate, mb_substr($why, 0, 500), $userId);

                        return $done("Recorded through HeldFeeEvidence::dismiss that the posting is not loan {$candidate}'s fee; it stays held and no longer stops that loan's EIR.");
                    default:
                        return $none('Finance does not know whose fee it is: nothing to apply; the posting stays held.', $comment);
                }

            case 'overlap':
                if ($a->answer === 'Not charged - ignore') {
                    return $none('No action records "not charged" for an overlap: the line still waits on Overlaps to decide.', 'Finance says this fee was not charged' . ($comment ? " ({$comment})" : '') . ': decide it on Overlaps to decide.');
                }
                $decision = $a->answer === 'Same fee as the Finance line' ? 'SAME' : 'SEPARATE';
                try {
                    app(FeeSourcePrecedence::class)->decide($id, $decision, mb_substr($why, 0, 500), $userId);
                } catch (InvalidArgumentException $e) {
                    throw new LogicException($e->getMessage());
                }

                return $done("Decided {$decision} through FeeSourcePrecedence::decide" . ($decision === 'SAME' ? ': the line is superseded.' : ': the line stands for classification and review.'));

            case 'classify':
                $fee = DB::table('contract_fees')->where('id', $id)->first();
                if ($fee === null || ! in_array($fee->classification_status, ['PENDING', 'CLASSIFIED'], true)) {
                    throw new LogicException("Fee line {$id} is " . ($fee ? strtolower($fee->classification_status) : 'gone') . ', not waiting for its classification.');
                }
                if ($a->answer === 'Do not know') {
                    return $none('Finance does not know: the line stays pending.', $comment);
                }
                $integral = $a->answer === 'Yes - integral to the loan';
                $text = mb_substr($why . ($a->detail ? ' What the charge is: ' . $a->detail . '.' : '') . ($a->answer === 'Never charged to the customer' ? ' Never charged to the customer, so it stays out of the EIR.' : ''), 0, 2000);
                $service = app(FeeClassificationService::class);
                // the proposer classifies and the approver reviews: the approval is the review, and the proposer never reviews
                $service->classify([$id], false, $integral, $text, (int) $a->proposed_by, null, null, ['workbook_answer_id' => (int) $a->id]);
                try {
                    $service->review([$id], $userId, false, ['workbook_answer_id' => (int) $a->id]);
                } catch (InvalidArgumentException $e) {
                    throw new LogicException($e->getMessage()); // rolls back the classification as well
                }

                return $done('Classified ' . ($integral ? 'integral' : 'non-integral') . ' (by the person who entered the answer) and reviewed by the approver, through FeeClassificationService.', null,
                    $locked($fee->contract_id) ? [(string) $fee->contract_id, "Finance classified fee line {$id} of this locked EIR " . ($integral ? 'integral' : 'non-integral') . '.'] : null);

            case 'unposted':
                $fee = DB::table('contract_fees')->where('id', $id)->first() ?? throw new LogicException("Fee line {$id} is gone.");
                $charged = str_starts_with($a->answer, 'Yes') ? true : ($a->answer === 'No - never charged' ? false : null);
                DB::table(UnpostedFeeBasis::TABLE)->updateOrInsert(['contract_fee_id' => $id], [
                    'contract_id' => $fee->contract_id, 'charged' => $charged, 'answer' => $a->answer, 'how' => $a->detail, 'comment' => $comment, 'workbook_answer_id' => (int) $a->id,
                    'proposed_by' => (int) $a->proposed_by, 'approved_by' => $userId, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                AuditLoggerService::log('Unposted Fee Confirmed', 'contract_fees', $id, ['new_values' => ['charged' => $charged, 'answer' => $a->answer, 'how' => $a->detail, 'comment' => $comment],
                    'meta' => ['user' => $userId, 'workbook_answer_id' => (int) $a->id]]);
                $basis = app(GovernanceService::class)->get(UnpostedFeeBasis::KEY);
                $counted = $fee->classification_status === 'REVIEWED' && (bool) $fee->integral;
                $outcome = 'Recorded for the setting "LOS charges never posted to the ledger": ' . ($charged === true ? 'charged' : ($charged === false ? 'never charged' : 'Finance does not know')) . '. ';
                $outcome .= $basis === UnpostedFeeBasis::AS_BUILT ? 'Under the setting in force (as built) the line counts whatever the answer.' : ($charged === true ? 'Under the setting in force it now counts.' : 'Under the setting in force it stays out of the EIR inputs.');
                // as built a line Finance says was never charged still counts: a locked EIR that used it is flagged, never changed
                $flag = $charged === false && $basis === UnpostedFeeBasis::AS_BUILT && $counted && $locked($fee->contract_id)
                    ? [(string) $fee->contract_id, "Finance says LOS fee line {$id} (" . number_format((float) $fee->amount, 2) . ') was never charged, and the locked EIR includes it.'] : null;

                return $done($outcome, $charged === false && $basis === UnpostedFeeBasis::AS_BUILT ? "Finance says fee line {$id} was never charged; as built it still counts (setting los_unposted_fee_basis)." : null, $flag);

            case 'confirmation':
                return $done("Recorded: {$a->answer}.", $a->answer === 'Agree' ? null : 'Finance disagrees: ' . ($comment ?? 'no comment given') . '.');

            case 'loan':
            case 'account':
            case 'difference':
            case 'takeon':
            case 'move':
            case 'fee':
                $agree = in_array($a->answer, ['Agree', 'Answered on another sheet', 'Amount used is right', 'Yes', 'Correct'], true);
                if ($agree) {
                    return $done("Recorded: {$a->answer}.", $comment && $a->record_type === 'account' ? $comment : null);
                }
                $what = "Finance: {$a->answer}" . ($a->correct_amount !== null ? ', correct amount ' . number_format((float) $a->correct_amount, 2) : '') . ($a->loan_account_given ? ', loan ' . $a->loan_account_given : '') . ($comment ? " ({$comment})" : '');
                $contract = $a->contract_id;

                return $done('Recorded. The system does not change a figure, an amount or a loan by itself: the answer is left as a follow-up for a person to act on.', $what,
                    $locked($contract) ? [(string) $contract, "Finance's answer on {$a->sheet} implies a change to this locked EIR: {$what}."] : null);

            case 'missing_fee':
                return $none('A fee Finance says is missing is not created from the workbook.', 'Finance says a fee is missing' . ($comment ? ": {$comment}" : '') . '. Load it through EIR Data Intake (fees), where it is proved against the GL.');
        }

        throw new LogicException("An answer on {$a->sheet} ({$a->record_type}) has no way to be applied.");
    }

    /** @return array<string,string> a fingerprint of the fee lines of each locked EIR, by contract */
    private function lockedState(): array
    {
        $locked = ContractEir::whereNotNull('locked_at')->pluck('contract_id')->map(fn ($c) => (string) $c)->all();
        if ($locked === []) {
            return [];
        }
        $q = DB::table('contract_fees')->whereIn('contract_id', $locked)->orderBy('id');
        $state = array_fill_keys($locked, '');
        $out = array_flip(UnpostedFeeBasis::leftOut());
        foreach ($q->get(['id', 'contract_id', 'amount', 'classification_status', 'integral', 'superseded_by_source']) as $f) {
            $state[(string) $f->contract_id] .= implode('|', [$f->id, round((float) $f->amount, 2), $f->classification_status, (int) $f->integral, $f->superseded_by_source, isset($out[(int) $f->id]) ? 'out' : 'in']) . ';';
        }

        return array_map('md5', $state);
    }

    // ------------------------------------------------------------------ the screen

    /** The answers of the tab, filtered by sheet and status, fifteen a page. */
    public function page(?string $sheet, ?string $status, int $perPage = 15)
    {
        $users = DB::table('users')->pluck('name', 'id');

        return DB::table('fee_workbook_answers')
            ->when($sheet, fn ($q) => $q->where('sheet', $sheet))
            ->when($status === 'FOLLOW_UP', fn ($q) => $q->whereNotNull('follow_up'), fn ($q) => $q->when($status, fn ($q) => $q->where('status', $status)))
            ->orderByRaw("CASE status WHEN 'PROPOSED' THEN 0 ELSE 1 END")->orderBy('upload_id')->orderBy('sheet')->orderBy('row_number')
            ->paginate($perPage, ['*'], 'answers_page')->withQueryString()
            ->through(fn ($a) => array_merge((array) $a, ['context' => json_decode((string) $a->context, true) ?: [], 'proposer' => $users[$a->proposed_by] ?? null, 'decider' => $a->decided_by ? ($users[$a->decided_by] ?? null) : null]));
    }

    /** The uploads, newest first, with what waits. */
    public function uploads(): array
    {
        $users = DB::table('users')->pluck('name', 'id');
        $waiting = DB::table('fee_workbook_answers')->where('status', 'PROPOSED')->select('upload_id', 'sheet', DB::raw('COUNT(*) as n'))->groupBy('upload_id', 'sheet')->get()->groupBy('upload_id');

        return DB::table('fee_workbook_uploads')->orderByDesc('id')->limit(15)->get()->map(fn ($u) => array_merge((array) $u, [
            'uploader' => $users[$u->uploaded_by] ?? null, 'refused' => json_decode((string) $u->refused, true) ?: [],
            'waiting' => collect($waiting->get($u->id, []))->mapWithKeys(fn ($w) => [$w->sheet => (int) $w->n])->all(),
            'documents' => \App\Support\SupportingDocuments::forItem('fee_workbook_uploads', (int) $u->id)->where('removed', false)->values()->all(),
        ]))->all();
    }
}
