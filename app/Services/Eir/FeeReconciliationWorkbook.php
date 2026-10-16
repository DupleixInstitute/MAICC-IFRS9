<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Services\AuditLoggerService;
use App\Services\Ebanker\AccountExclusionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The fee reconciliation workbook for Finance, generated from the live data (16 October 2026; spec v4 section
 * 7.8.9). It has the sheets, columns, dropdown answers, colours and wording of the workbook Dupleix built by
 * script for Finance on 16 October 2026 (version 2), with two additions so that the answers can come back:
 *
 *  - every answer row carries a stable key in a hidden last column ("Key (do not change)"): the fee line id,
 *    the held posting's external id, the pair of fee lines of a source difference, the loan account, the
 *    classification event of a correction, so an upload maps each answer to exactly one record;
 *  - the Read me sheet carries, in its hidden column Z, the workbook's fingerprint: when and from which
 *    database it was generated and a SHA-256 of all the keys. Each download is recorded in the audit log with
 *    the fingerprint, so an upload can prove it came from this system and that no keyed row was lost.
 *
 * "Leaving the books" comes from the account exclusion register (AccountExclusionService), not from notes.
 * The workbook only reads; nothing is written except the audit entry of the download.
 */
class FeeReconciliationWorkbook
{
    public const GENERATOR = 'maiic-fee-reconciliation';
    public const VERSION = 1;
    public const AUDIT_ACTION = 'Fee Reconciliation Workbook Downloaded';
    public const KEY_HEADER = 'Key (do not change)';
    public const FINGERPRINT_LABEL = 'MAIIC fee workbook fingerprint';
    public const FINGERPRINT_COLUMN = 'Z';

    public const NAVY = '1F3864';
    public const ANSWER_HEAD = 'BF8F00';
    public const ANSWER = 'FFF2CC';
    public const DONE = 'E2EFDA';
    public const OPEN = 'FCE4D6';
    public const WAIT = 'EDEDED';
    public const LEAVING = 'D9D9D9';
    public const MONEY = '#,##0.00;[Red]-#,##0.00';

    /** The source labels Finance reads, by source_system. */
    public const SOURCE = [
        'MAIIC Finance EIR assessment 2025' => 'Finance 2025 workbook',
        FeeSourcePrecedence::SOURCE_GL => 'GL posting (E-Banker)',
        FeeSourcePrecedence::SOURCE_LOS => 'LOS charge (E-Banker)',
        FeeSourcePrecedence::SOURCE_TAKEON => 'Take-on workbook',
    ];

    public const REASON = [
        'FEE_HELD_FOR_LOAN' => 'A GL fee posting with no loan on it looks like this loan\'s (see "Held GL postings")',
        'FEE_SOURCE_OVERLAP' => 'Finance and E-Banker each have a fee line the other source does not (see "Overlaps")',
        'FEE_CLASSIFICATION_PENDING' => 'A fee line is not yet reviewed (see "Lines to classify")',
        'TAKEON_NOT_RECOMPUTED' => 'Take-on loan: its schedule could not be recomputed from the start (see "Take-on loans")',
        'TAKEON_SOLVE_REFUSED' => 'Take-on loan: its schedule could not be recomputed from the start (see "Take-on loans")',
        'ORIGINAL_SCHEDULE_MISSING' => 'No original repayment schedule in E-Banker',
        'DRAWN_AMOUNT_MISSING' => 'No drawn amount',
        'SCHEDULE_NOT_APPROVED' => null, // follows from the missing schedule; not shown on its own
    ];

    /** The blockers only Finance can answer. */
    public const YOURS = ['FEE_HELD_FOR_LOAN', 'FEE_SOURCE_OVERLAP', 'FEE_CLASSIFICATION_PENDING', 'TAKEON_NOT_RECOMPUTED', 'TAKEON_SOLVE_REFUSED'];

    public const ST_LEAVING = 'Leaving the books (exclusion register)';
    public const ST_LOCKED = 'Locked: please confirm';
    public const ST_MIXED = 'Waiting on E-Banker data; one fee line for you';
    public const ST_YOURS = 'Needs your answer';
    public const ST_WAITING = 'Waiting on E-Banker data';
    public const ST_READY = 'Ready to calculate';
    public const ST_OTHER = 'Not one of the EIR loans';

    /**
     * The sheets as the upload reads them: the title, the answer column's header, the dropdown options, the
     * other answer fields (header => field) and the record type of its keys.
     *
     * @var array<string, array{answer:string, options:list<string>, fields:array<string,string>, type:string}>
     */
    public const SHEETS = [
        '1. Loan status' => ['answer' => 'Your answer', 'options' => ['Agree', 'Disagree - see comment', 'Answered on another sheet'], 'fields' => ['Comment' => 'comment'], 'type' => 'loan'],
        '2. Held GL postings' => ['answer' => 'Your answer', 'options' => ['Fee of the loan the narration points to', 'Fee of another loan - give account', 'Not a loan fee', 'Reversal or reallocation - nets off', 'Mega Farm - outside the EIR', 'Do not know'],
            'fields' => ['Loan account it belongs to' => 'loan_account_given', 'Comment' => 'comment'], 'type' => 'held'],
        '3. Overlaps' => ['answer' => 'Your answer', 'options' => ['Same fee as the Finance line', 'A separate fee of its own', 'Not charged - ignore'], 'fields' => ['Comment' => 'comment'], 'type' => 'overlap'],
        '4. Source differences' => ['answer' => 'Your answer', 'options' => ['Amount used is right', 'Other amount is right', 'Neither - give the correct amount', 'Two separate fees'],
            'fields' => ['Correct amount (if neither)' => 'correct_amount', 'Comment' => 'comment'], 'type' => 'difference'],
        '4b. Fees never posted' => ['answer' => 'Was it charged?', 'options' => ['Yes - deducted from the drawdown', 'Yes - paid by the borrower', 'Yes - posted under another account', 'No - never charged', 'Do not know'],
            'fields' => ['Where it was posted or received (GL, date)' => 'detail', 'Comment' => 'comment'], 'type' => 'unposted'],
        '5. Lines to classify' => ['answer' => 'Part of the loan\'s yield?', 'options' => ['Yes - integral to the loan', 'No - a charge for a separate service', 'Never charged to the customer', 'Do not know'],
            'fields' => ['What is this charge?' => 'detail', 'Comment' => 'comment'], 'type' => 'classify'],
        '6. Take-on loans' => ['answer' => 'Is the block the right one for this loan?', 'options' => ['Yes', 'No - see comment', 'Do not know'], 'fields' => ['Comment (and where the right schedule is)' => 'comment'], 'type' => 'takeon'],
        '7. Corrections made' => ['answer' => 'Your answer', 'options' => ['Agree', 'Disagree - see comment'], 'fields' => ['Comment' => 'comment'], 'type' => 'move'],
        '8. Fee lines' => ['answer' => 'Your answer', 'options' => ['Correct', 'Wrong amount - see comment', 'Wrong loan - see comment', 'Not a fee of this loan', 'Not integral', 'Duplicate of another line'], 'fields' => ['Comment' => 'comment'], 'type' => 'fee'],
    ];

    /** How the Read me counts the accounts leaving the books, by exclusion category (the wording of 16 October 2026). */
    public const CATEGORY_PHRASE = [
        'UAT_TEST_ACCOUNT' => 'test accounts',
        'CLOSED_BEFORE_WINDOW' => 'real loans closed with nothing held for them in the months the system covers',
        'DUPLICATE_APPROVAL' => 'approval records never drawn whose loans run on other accounts',
    ];

    /** The two confirmations on the Read me sheet. */
    public const CONFIRM_OPTIONS = ['Agree', 'Disagree - see comment'];

    private array $data = [];
    private array $keys = [];

    public function __construct(private readonly EirReadinessService $readiness, private readonly GovernanceService $governance)
    {
    }

    // ------------------------------------------------------------------ the data

    /** Everything the sheets show, read from the live tables (the port of the 16 October extract). */
    public function data(): array
    {
        $users = DB::table('users')->pluck('name', 'id')->all();
        $exclusions = Schema::hasTable('account_exclusions')
            ? DB::table('account_exclusions')->whereIn('status', ['PROPOSED', 'APPROVED'])->get(['contract_id', 'category', 'reason', 'status'])->keyBy(fn ($e) => ltrim((string) $e->contract_id, '0'))
            : collect();
        $loans = [];
        foreach (ContractEir::query()->orderBy('contract_id')->get() as $c) {
            $issues = $c->locked_at ? [] : array_values(array_filter($this->readiness->assessContract($c)['issues'] ?? [], fn ($i) => ($i['code'] ?? '') !== 'EIR_LOCKED'));
            $id = (string) $c->contract_id;
            $loans[$id] = ['contract_id' => $id, 'customer' => trim((string) $c->customer_name), 'portfolio' => $c->portfolio, 'locked' => (bool) $c->locked_at,
                'eir' => $c->eir_effective_annual !== null ? (float) $c->eir_effective_annual : null, 'exclusion' => $exclusions->get(ltrim($id, '0')),
                'issues' => array_map(fn ($i) => ['code' => $i['code'] ?? '', 'message' => $i['message'] ?? ''], $issues)];
        }
        $fees = DB::table('contract_fees')->orderBy('contract_id')->orderBy('transaction_date')->orderBy('id')->get()->map(fn ($f) => [
            'id' => (int) $f->id, 'contract_id' => (string) $f->contract_id, 'fee_type' => $f->fee_type, 'description' => $f->description, 'amount' => (float) $f->amount,
            'date' => $f->transaction_date ? substr((string) $f->transaction_date, 0, 10) : null, 'source' => $f->source_system, 'source_reference' => $f->source_reference,
            'gl' => $f->gl_account_ref, 'integral' => $f->integral === null ? null : (bool) $f->integral, 'status' => $f->classification_status,
            'classified_by' => $users[$f->classified_by] ?? null, 'reviewed_by' => $users[$f->reviewed_by] ?? null,
            'superseded_by' => $f->superseded_by_source ?? null, 'overlap' => $f->source_overlap ?? null,
        ])->all();
        $held = [];
        if (Schema::hasTable('fee_gl_unassigned')) {
            $dismissed = Schema::hasTable('fee_gl_candidate_dismissals')
                ? DB::table('fee_gl_candidate_dismissals')->get()->keyBy(fn ($d) => $d->external_transaction_id . '|' . $d->contract_id) : collect();
            $held = DB::table('fee_gl_unassigned')->orderBy('transaction_date')->orderBy('id')->get()->map(fn ($h) => [
                'id' => (int) $h->id, 'external_id' => $h->external_transaction_id, 'date' => $h->transaction_date ? substr((string) $h->transaction_date, 0, 10) : null, 'batch' => $h->batch,
                'gl' => (string) $h->gl_code, 'amount' => (float) $h->amount, 'narration' => $h->narration, 'reason' => (string) $h->reason,
                'candidate' => $h->candidate_contract_id ? ltrim((string) $h->candidate_contract_id, '0') : null,
                'dismissed' => $h->candidate_contract_id ? ($dismissed->get($h->external_transaction_id . '|' . ltrim((string) $h->candidate_contract_id, '0'))?->reason) : null,
            ])->all();
        }
        $differences = Schema::hasTable('fee_source_differences')
            ? DB::table('fee_source_differences')->orderBy('contract_id')->orderBy('fee_type')->orderBy('id')->get()->map(fn ($d) => (array) $d)->all() : [];
        $takeonIds = array_keys(array_filter($loans, fn ($l) => array_intersect(['TAKEON_NOT_RECOMPUTED', 'TAKEON_SOLVE_REFUSED'], array_column($l['issues'], 'code')) !== []));
        $takeon = $takeonIds !== [] && Schema::hasTable('contract_takeon')
            ? DB::table('contract_takeon')->whereIn('contract_id', $takeonIds)->orderBy('contract_id')->get()->map(fn ($t) => (array) $t)->all() : [];
        $moves = DB::table('eir_fee_classification_events')->where('reason', 'like', 'Moved from loan%')->orderBy('id')->get(['id', 'contract_fee_id', 'reason', 'created_at'])->map(fn ($e) => (array) $e)->all();
        $precedence = DB::table('governance_settings')->where('key', 'fee_source_precedence')->where('status', 'APPROVED')->orderByDesc('effective_from')->orderByDesc('id')->first();

        return $this->data = ['database' => DB::connection()->getDatabaseName(), 'as_at' => now()->toDateTimeString(), 'loans' => $loans, 'fees' => $fees, 'held' => $held,
            'differences' => $differences, 'takeon' => $takeon, 'moves' => $moves, 'users' => $users,
            'precedence' => $precedence ? ['value' => $precedence->value, 'effective_from' => substr((string) $precedence->effective_from, 0, 10), 'approver' => $users[$precedence->approved_by] ?? null] : null,
            'excluded_left' => Schema::hasTable('account_exclusions') ? DB::table('account_exclusions')->where('status', 'APPROVED')->count() : 0];
    }

    /** The status Finance reads for a loan. */
    public static function status(array $loan): string
    {
        if ($loan['exclusion'] ?? null) {
            return self::ST_LEAVING;
        }
        if ($loan['locked']) {
            return self::ST_LOCKED;
        }
        $codes = array_unique(array_column($loan['issues'], 'code'));
        $yours = array_intersect($codes, self::YOURS);
        $other = array_diff($codes, self::YOURS, ['SCHEDULE_NOT_APPROVED']);
        if ($yours !== [] && $other !== []) {
            return self::ST_MIXED;
        }
        if ($yours !== []) {
            return self::ST_YOURS;
        }

        return $codes !== [] ? self::ST_WAITING : self::ST_READY;
    }

    /** A fee line used in an EIR: of the source of record, no overlap, reviewed and integral. */
    private static function used(array $f): bool
    {
        return $f['superseded_by'] === null && ! $f['overlap'] && $f['status'] === 'REVIEWED' && $f['integral'] !== false;
    }

    /** The LOS charges never posted that sheet 4b asks about: the source of record, not excluded, not decided non-integral or rejected. */
    public static function neverPosted(array $fees, array $loans): array
    {
        $never = array_values(array_filter($fees, fn ($f) => $f['source'] === FeeSourcePrecedence::SOURCE_LOS && $f['superseded_by'] === null && ($f['gl'] === null || $f['gl'] === '')
            && in_array($f['status'], ['REVIEWED', 'PENDING'], true) && $f['integral'] !== false && ! (($loans[$f['contract_id']]['exclusion'] ?? null)) && abs($f['amount']) > 0));
        usort($never, fn ($a, $b) => [! ($loans[$a['contract_id']]['locked'] ?? false), $a['contract_id'], $a['id']] <=> [! ($loans[$b['contract_id']]['locked'] ?? false), $b['contract_id'], $b['id']]);

        return $never;
    }

    // ------------------------------------------------------------------ the keys

    public static function keysHash(array $keys): string
    {
        $keys = array_values(array_unique($keys));
        sort($keys, SORT_STRING);

        return hash('sha256', implode("\n", $keys));
    }

    // ------------------------------------------------------------------ the workbook

    /**
     * Build the workbook and record the download in the audit log.
     *
     * @return array{path:string, name:string, fingerprint:array}
     */
    public function download(int $userId): array
    {
        $book = $this->build();
        $name = 'MAIIC fee reconciliation for Finance - ' . now()->format('Y-m-d His') . '.xlsx';
        $path = storage_path('app/fee-workbooks/' . uniqid('download-', true) . '.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        (new Xlsx($book['spreadsheet']))->save($path);
        AuditLoggerService::log(self::AUDIT_ACTION, 'fee_workbook_downloads', null, [
            'new_values' => $book['fingerprint'] + ['file' => $name, 'sha256' => hash_file('sha256', $path), 'rows_by_sheet' => $book['counts']],
            'meta' => ['user' => $userId],
        ]);

        return ['path' => $path, 'name' => $name, 'fingerprint' => $book['fingerprint']];
    }

    /** @return array{spreadsheet:Spreadsheet, fingerprint:array, counts:array<string,int>, keys:list<string>} */
    public function build(): array
    {
        $data = $this->data ?: $this->data();
        $this->keys = [];
        $loans = $data['loans'];
        $name = fn ($c) => $c === null ? '' : (($loans[(string) $c]['customer'] ?? '') ?: '');

        $byLoan = [];
        foreach ($data['fees'] as $f) {
            $s = self::SOURCE[$f['source']] ?? (string) $f['source'];
            $byLoan[$f['contract_id']][$s] = ($byLoan[$f['contract_id']][$s] ?? 0) + $f['amount'];
            if (self::used($f)) {
                $byLoan[$f['contract_id']]['used'] = ($byLoan[$f['contract_id']]['used'] ?? 0) + $f['amount'];
            }
        }
        $heldByLoan = [];
        foreach ($data['held'] as $h) {
            if ($h['candidate'] && ! $h['dismissed']) {
                $heldByLoan[$h['candidate']] = ($heldByLoan[$h['candidate']] ?? 0) + $h['amount'];
            }
        }
        $counts = [];
        foreach ($loans as $l) {
            $s = self::status($l);
            $counts[$s] = ($counts[$s] ?? 0) + 1;
        }
        $nofee = count(array_filter($loans, fn ($l) => $l['locked'] && empty($byLoan[$l['contract_id']]['used'])));
        $never = self::neverPosted($data['fees'], $loans);
        $neverLocked = array_values(array_unique(array_map(fn ($f) => $f['contract_id'], array_filter($never, fn ($f) => $loans[$f['contract_id']]['locked'] ?? false))));
        sort($neverLocked);
        $neverOpen = array_values(array_diff(array_unique(array_column($never, 'contract_id')), $neverLocked));
        $neverLockedTotal = array_sum(array_map(fn ($f) => in_array($f['contract_id'], $neverLocked, true) ? $f['amount'] : 0, $never));
        // an account number is a numeric string, which PHP turns into an integer array key: read the keys back as text
        $others = array_values(array_diff(array_map('strval', array_keys($byLoan)), array_map('strval', array_keys($loans))));
        sort($others);
        $overlaps = array_values(array_filter($data['fees'], fn ($f) => $f['overlap'] && $f['superseded_by'] === null));
        $pending = array_values(array_filter($data['fees'], fn ($f) => $f['status'] === 'PENDING' && $f['superseded_by'] === null));

        // text stays text (an account number, a date as E-Banker gives it), numbers stay numbers
        $binder = \PhpOffice\PhpSpreadsheet\Cell\Cell::getValueBinder();
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder((new \PhpOffice\PhpSpreadsheet\Cell\StringValueBinder())
            ->setNullConversion(false)->setBooleanConversion(false)->setNumericConversion(false)->setFormulaConversion(false));
        try {
            return $this->compose($data, $loans, $name, $byLoan, $heldByLoan, $counts, $nofee, $never, $neverLocked, $neverOpen, $neverLockedTotal, $others, $overlaps, $pending);
        } finally {
            \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder($binder);
        }
    }

    private function compose(array $data, array $loans, \Closure $name, array $byLoan, array $heldByLoan, array $counts, int $nofee, array $never, array $neverLocked, array $neverOpen, float $neverLockedTotal, array $others, array $overlaps, array $pending): array
    {
        $book = new Spreadsheet();
        $book->getProperties()->setCreator('MAIIC IFRS 9 system')->setTitle('MAIIC loan fees: reconciliation for Finance');

        // ------------------------------------------------------------ Read me
        $ws = $book->getActiveSheet();
        $ws->setTitle('Read me');
        $ws->getTabColor()->setRGB(self::NAVY);
        $ws->getColumnDimension('A')->setWidth(4);
        $ws->getColumnDimension('B')->setWidth(120);
        $basis = app(GovernanceService::class)->get(UnpostedFeeBasis::KEY);
        $leaving = array_filter($loans, fn ($l) => $l['exclusion'] ?? null);
        $categories = [];
        foreach (array_keys(self::CATEGORY_PHRASE) as $code) {
            $n = count(array_filter($leaving, fn ($l) => $l['exclusion']->category === $code));
            if ($n > 0) {
                $categories[] = $n . ' ' . self::CATEGORY_PHRASE[$code];
            }
        }
        foreach ($leaving as $l) {
            if (! isset(self::CATEGORY_PHRASE[$l['exclusion']->category])) {
                $categories[] = '1 ' . lcfirst(AccountExclusionService::CATEGORIES[$l['exclusion']->category] ?? $l['exclusion']->category);
            }
        }
        $categoryText = count($categories) > 1 ? implode(', ', array_slice($categories, 0, -1)) . ' and ' . end($categories) : implode('', $categories);
        $neverNames = implode(', ', array_map(fn ($c) => $name($c) ?: $c, $neverLocked));
        $lines = [
            ['MAIIC loan fees: reconciliation for Finance', 'title'],
            ['Prepared for MAIIC Finance from the IFRS 9 system. Figures as in the system on ' . substr($data['as_at'], 0, 10) . ' (' . date('j F Y', strtotime($data['as_at'])) . ').', null],
            ['', null],
            ['Why you have this', 'h'],
            ['Each loan\'s effective interest rate (EIR) takes in the fees that are part of the loan\'s yield. The system takes each fee from one source, in the order of the Governance Centre setting "Which source a contract\'s fees come from" (now: ' . ($data['precedence']['value'] ?? 'not set') . '). '
                . 'This workbook shows you every fee line the system holds, which ones it used for each loan, and where the sources do not agree, so you can tell us what is right.', null],
            ['This workbook was generated by the system. Each row carries a hidden key, so that the answers you return are read back against exactly the record you answered; please don\'t delete rows, and don\'t change the hidden columns.', null],
            ['', null],
            ['Where things stand', 'h'],
            [count($loans) . ' loans are in scope for the EIR.', null],
            ['  ' . ($counts[self::ST_LOCKED] ?? 0) . ' have a locked EIR. Their fees reconcile across the sources. Please look over them and tell us if anything is wrong; ' . $nofee . ' of them carry no fee at all, and those are worth a second look.', null],
        ];
        if ($leaving !== []) {
            $lines[] = ['  ' . count($leaving) . ' are leaving the books (account exclusion register): ' . $categoryText . '. Nothing to answer for them; their fee lines are on sheet 8 for reference.', null];
        }
        if ($data['excluded_left'] > 0) {
            $lines[] = ['  ' . $data['excluded_left'] . ' accounts have left the books (account exclusion register, approved) and are not in this workbook.', null];
        }
        $lines[] = ['  ' . ($counts[self::ST_YOURS] ?? 0) . ' are blocked on a question only Finance can answer: a held GL posting, a fee in one source but not the other, a fee line to classify, or a take-on loan.', null];
        $lines[] = ['  ' . (($counts[self::ST_WAITING] ?? 0) + ($counts[self::ST_MIXED] ?? 0)) . ' are waiting on E-Banker data (no original schedule or drawn amount, for example). ' . ($counts[self::ST_MIXED] ?? 0)
            . ' of them also have one fee line for you on "Lines to classify". Most of these loans show an approved amount but were never drawn; if you know that, a comment saves a lot of time.', null];
        if (($counts[self::ST_READY] ?? 0) > 0) {
            $lines[] = ['  ' . $counts[self::ST_READY] . ' are ready to calculate.', null];
        }
        $lines[] = ['  ' . count($others) . ' other accounts carry LOS fee charges but are not among the EIR loans; they are at the bottom of "Loan status".', null];
        $lines[] = ['', null];
        $lines[] = ['How to answer', 'h'];
        $lines[] = ['The yellow columns are yours. Most have a dropdown: pick one answer, and use the comment column for anything else (an account number, an amount, the reason). Please don\'t change the white columns or delete rows; '
            . 'if a fee is missing altogether, add it at the bottom of "Fee lines" and say so in the comment.', null];
        $lines[] = ['Your answers don\'t change anything by themselves. A MAIIC user uploads this workbook on Fee Classification ("Upload Finance\'s answers"); it is kept as the supporting document, every answer waits as a proposal, and a second person approves or rejects each one. '
            . 'You can also answer straight in the system with your own login if you prefer.', null];
        $lines[] = ['', null];
        $lines[] = ['The sheets, in the order to do them', 'h'];
        $lines[] = ['1. Loan status: one row per loan, the fees by source, what the system used, and what is blocking it. Start here.', null];
        $lines[] = ['2. Held GL postings: GL fee postings with no loan on them. Tell us whose fee each one is. This is where most of the open items are.', null];
        $lines[] = ['3. Overlaps: ' . count($overlaps) . ' fee lines that one source has and the source of record does not, on loans the source of record also carries. Same fee or a fee of its own?', null];
        $lines[] = ['4b. Fees never posted: ' . count($never) . ' fee lines from the E-Banker loan system (LOS) that were never posted to the ledger: no batch and no income GL. '
            . ($basis === UnpostedFeeBasis::AS_BUILT ? 'The system has used them where neither your workbook nor the GL has the fee. ' : 'Under the setting in force the system uses one only where you have confirmed it was charged. ')
            . count($neverLocked) . ' locked EIRs include ' . number_format($neverLockedTotal, 0) . ' of them' . ($neverLocked !== [] ? ' (' . $neverNames . ')' : '') . '; ' . count($neverOpen) . ' loans not yet locked carry the rest. '
            . 'Was each one charged? Your answer decides whether the system may count it (Governance Centre setting "LOS charges never posted to the ledger"); a locked EIR that used one is flagged for review, never changed by itself.', null];
        $lines[] = ['4. Source differences: where your workbook and E-Banker carry a different amount for the same fee. The system used the source of record; please confirm.', null];
        $lines[] = ['5. Lines to classify: fee lines still to review, mostly "Other Fee (%)". What is it, and is it part of the loan\'s yield?', null];
        $lines[] = ['6. Take-on loans: the take-on loans whose take-on schedules could not be used.', null];
        $lines[] = ['7. Corrections made: fee lines moved from one loan to another (' . count($data['moves']) . ' lines). Please confirm.', null];
        $lines[] = ['8. Fee lines: every fee line the system holds, whichever source, with whether it was used. The full file for reference; answer only where something is wrong.', null];
        $lines[] = ['', null];
        $lines[] = ['Two things to confirm', 'h'];
        $r = 0;
        foreach ($lines as [$text, $kind]) {
            $r++;
            $ws->setCellValue('B' . $r, $text);
            $ws->getStyle('B' . $r)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            if ($kind === 'title') {
                $ws->getStyle('B' . $r)->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::NAVY);
            } elseif ($kind === 'h') {
                $ws->getStyle('B' . $r)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::NAVY);
            }
            if (mb_strlen($text) > 120) {
                $ws->getRowDimension($r)->setRowHeight(15 * (intdiv(mb_strlen($text), 115) + 1));
            }
        }
        $prec = $data['precedence'];
        $confirm = [
            'A' => 'A. The order of the fee sources. Since ' . ($prec['effective_from'] ?? 'its seeding') . ' the system takes the fees in this order: ' . ($prec['value'] ?? 'not set') . ' (approved by ' . ($prec['approver'] ?? 'a MAIIC approver') . '). Do you agree it should stay that way for go-live?',
            'B' => 'B. Corrections made. ' . count($data['moves']) . ' fee lines were moved from one loan to another, details on "Corrections made". Do you agree with the corrections?',
        ];
        $r++;
        foreach ($confirm as $letter => $text) {
            $r++;
            $ws->setCellValue('B' . $r, $text);
            $ws->getStyle('B' . $r)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $r++;
            $this->fill($ws, 'B' . $r, self::ANSWER);
            $this->box($ws, 'B' . $r);
            $this->dropdown($ws, 'B' . $r, self::CONFIRM_OPTIONS);
            $this->key($ws, self::FINGERPRINT_COLUMN . $r, 'confirm:' . $letter);
            $r++;
            $ws->setCellValue('B' . $r, 'Comment:');
            $this->fill($ws, 'B' . $r, self::ANSWER);
            $this->box($ws, 'B' . $r);
            $r++;
        }

        // ------------------------------------------------------------ 1. Loan status
        $order = [self::ST_YOURS => 0, self::ST_MIXED => 1, self::ST_WAITING => 1, self::ST_READY => 2, self::ST_LOCKED => 3, self::ST_LEAVING => 4];
        $rows = [];
        $fills = [];
        $sorted = array_values($loans);
        usort($sorted, fn ($a, $b) => [$order[self::status($a)], $a['contract_id']] <=> [$order[self::status($b)], $b['contract_id']]);
        foreach ($sorted as $l) {
            $t = $byLoan[$l['contract_id']] ?? [];
            $st = self::status($l);
            $why = $l['exclusion'] ? ((AccountExclusionService::CATEGORIES[$l['exclusion']->category] ?? $l['exclusion']->category) . ': ' . trim((string) $l['exclusion']->reason) . ($l['exclusion']->status === 'PROPOSED' ? ' (proposed, awaiting approval)' : ''))
                : implode('; ', array_values(array_unique(array_filter(array_map(fn ($i) => array_key_exists($i['code'], self::REASON) ? self::REASON[$i['code']] : $i['message'], $l['issues'])))));
            if ($l['locked'] && empty($t['used']) && ! empty($heldByLoan[$l['contract_id']])) {
                $why = 'Locked with no fee, but held GL postings name this customer (see "Held GL postings"). If they are its fees, the locked EIR is flagged for review and recalculated only once its reopening is approved.';
            } elseif ($l['locked'] && empty($t['used'])) {
                $why = 'Nothing is blocking it, but it is locked with no fee at all. Was there really no fee on this loan?';
            }
            $rows[] = [$l['contract_id'], $l['customer'], $l['portfolio'], $st, $l['locked'] ? $l['eir'] : null,
                ($t['Finance 2025 workbook'] ?? 0) ?: null, ($t['GL posting (E-Banker)'] ?? 0) ?: null, ($t['LOS charge (E-Banker)'] ?? 0) ?: null, ($t['Take-on workbook'] ?? 0) ?: null,
                $t['used'] ?? 0, ($heldByLoan[$l['contract_id']] ?? 0) ?: null, $why ?: null, null, null, 'loan:' . $l['contract_id']];
            $fills[count($rows) + 1] = str_starts_with($st, 'Locked') ? self::DONE : ($st === self::ST_YOURS ? self::OPEN : ($st === self::ST_LEAVING ? self::LEAVING : self::WAIT));
        }
        foreach ($others as $c) {
            $t = $byLoan[$c];
            $c = (string) $c;
            $rows[] = [$c, null, null, self::ST_OTHER, null, ($t['Finance 2025 workbook'] ?? 0) ?: null, ($t['GL posting (E-Banker)'] ?? 0) ?: null, ($t['LOS charge (E-Banker)'] ?? 0) ?: null,
                ($t['Take-on workbook'] ?? 0) ?: null, null, null, 'E-Banker holds LOS fee charges on this account, but it is not one of the term loans the EIR covers. Which loan are these fees for, if any?', null, null, 'account:' . $c];
            $fills[count($rows) + 1] = self::OPEN;
        }
        $ws = $this->sheet($book, '1. Loan status', ['Loan account', 'Customer', 'Portfolio', 'Status', 'EIR (annual, locked)', 'Finance 2025 workbook', 'GL postings', 'LOS charges', 'Take-on workbook',
            'Fees used in the EIR', 'Held GL postings that look like this loan\'s', 'What is blocking it', 'Your answer', 'Comment'], [15, 34, 18, 26, 12, 16, 16, 16, 16, 16, 18, 60, 22, 40], 13, '2F5597', $rows);
        $this->percent($ws, 5);
        $this->finish($ws, [6, 7, 8, 9, 10, 11], 13, [12], $fills, [13 => self::SHEETS['1. Loan status']['options']]);

        // ------------------------------------------------------------ 2. Held GL postings
        $why = ['NO_LOAN_IN_BATCH' => 'No loan account is a leg of the posting batch, so the system cannot tell whose fee it is',
            'MEGA_FARM' => 'The only loan in the batch is a Mega Farm programme account (outside the EIR)'];
        $blocked = array_map('strval', array_keys(array_filter($loans, fn ($l) => in_array('FEE_HELD_FOR_LOAN', array_column($l['issues'], 'code'), true))));
        $rows = [];
        foreach ($data['held'] as $h) {
            $cand = $h['candidate'];
            $rows[] = [$h['date'], $h['batch'], $h['gl'], $h['amount'], $h['narration'], $why[explode(':', $h['reason'])[0]] ?? $h['reason'],
                $cand, $cand ? $name($cand) : null, $cand ? (in_array($cand, $blocked, true) ? 'Yes' : 'No') : null, null, null, null, self::heldKey($h)];
        }
        $ws = $this->sheet($book, '2. Held GL postings', ['Posting date', 'Batch', 'GL', 'Amount', 'Narration', 'Why it is held', 'Loan the narration points to', 'Customer of that loan', 'Loan blocked by it?',
            'Your answer', 'Loan account it belongs to', 'Comment'], [12, 18, 8, 16, 46, 40, 16, 30, 12, 30, 18, 40], 10, 'C00000', $rows);
        $this->finish($ws, [4], 10, [5, 6], [], [10 => self::SHEETS['2. Held GL postings']['options']]);
        $last = $ws->getHighestRow();
        $ws->setCellValue('B' . ($last + 2), 'Total');
        $ws->setCellValue('D' . ($last + 2), '=SUM(D2:D' . max($last, 2) . ')');
        $ws->getStyle('D' . ($last + 2))->getNumberFormat()->setFormatCode(self::MONEY);
        $ws->getStyle('B' . ($last + 2))->getFont()->setBold(true);
        $ws->freezePane('A2');

        // ------------------------------------------------------------ 3. Overlaps
        $rows = array_map(fn ($f) => [$f['contract_id'], $name($f['contract_id']), $f['fee_type'], $f['amount'], self::SOURCE[$f['source']] ?? $f['source'], $f['date'], $f['overlap'], null, null, 'overlap:' . $f['id']], $overlaps);
        $ws = $this->sheet($book, '3. Overlaps', ['Loan account', 'Customer', 'Fee type', 'Amount', 'Source', 'Date', 'What the system found', 'Your answer', 'Comment'], [15, 30, 12, 16, 22, 12, 80, 30, 40], 8, 'C00000', $rows);
        $this->finish($ws, [4], 8, [7], [], [8 => self::SHEETS['3. Overlaps']['options']]);

        // ------------------------------------------------------------ 4. Source differences
        $rows = array_map(fn ($d) => [(string) $d['contract_id'], $name($d['contract_id']), $d['fee_type'], $d['source_of_record'], (float) $d['amount_of_record'], $d['other_source'],
            (float) $d['other_amount'], (float) $d['difference'], null, null, null, 'diff:' . $d['record_fee_id'] . ':' . $d['other_fee_id']], $data['differences']);
        $ws = $this->sheet($book, '4. Source differences', ['Loan account', 'Customer', 'Fee type', 'Source used', 'Amount used', 'Other source', 'Other amount', 'Difference (other less used)', 'Your answer', 'Correct amount (if neither)', 'Comment'],
            [15, 30, 12, 18, 16, 16, 16, 16, 30, 18, 40], 9, 'BF8F00', $rows);
        $this->finish($ws, [5, 7, 8, 10], 9, [], [], [9 => self::SHEETS['4. Source differences']['options']]);

        // ------------------------------------------------------------ 4b. Fees never posted
        $rows = array_map(function ($f) use ($loans, $name) {
            $l = $loans[$f['contract_id']] ?? null;

            return [$f['contract_id'], $name($f['contract_id']), $l ? ($l['locked'] ? 'Locked' : 'Not locked') : 'Not an EIR loan', $l && $l['locked'] ? $l['eir'] : null,
                $f['description'], $f['fee_type'], $f['amount'], ucfirst(strtolower((string) $f['status'])), null, null, null, 'unposted:' . $f['id']];
        }, $never);
        $ws = $this->sheet($book, '4b. Fees never posted', ['Loan account', 'Customer', 'EIR', 'Locked EIR (annual)', 'Fee name', 'Fee type', 'Amount', 'Review status', 'Was it charged?', 'Where it was posted or received (GL, date)', 'Comment'],
            [15, 30, 12, 12, 24, 12, 16, 13, 34, 34, 40], 9, 'C00000', $rows);
        $this->percent($ws, 4);
        $this->finish($ws, [7], 9, [], [], [9 => self::SHEETS['4b. Fees never posted']['options']]);

        // ------------------------------------------------------------ 5. Lines to classify
        $rows = array_map(fn ($f) => [$f['contract_id'], $name($f['contract_id']), $f['description'], $f['fee_type'], $f['amount'], self::SOURCE[$f['source']] ?? $f['source'],
            isset($loans[$f['contract_id']]) ? self::status($loans[$f['contract_id']]) : 'Not in EIR scope', null, null, null, 'classify:' . $f['id']], $pending);
        $ws = $this->sheet($book, '5. Lines to classify', ['Loan account', 'Customer', 'Fee name', 'Fee type', 'Amount', 'Source', 'Loan status', 'What is this charge?', 'Part of the loan\'s yield?', 'Comment'],
            [15, 30, 26, 12, 16, 22, 24, 34, 30, 40], 8, 'BF8F00', $rows);
        $this->finish($ws, [5], 8, [], [], [9 => self::SHEETS['5. Lines to classify']['options']]);

        // ------------------------------------------------------------ 6. Take-on loans
        $rows = array_map(function ($t) use ($name) {
            $flags = json_decode((string) ($t['flags'] ?? ''), true) ?: [];
            $also = array_values(array_filter($flags, fn ($f) => ! str_starts_with((string) $f, 'take-on EIR refused')));

            return [(string) $t['contract_id'], $name($t['contract_id']), $t['block_id'] ?? null, isset($t['origination_date']) ? substr((string) $t['origination_date'], 0, 10) : null, (float) ($t['original_principal'] ?? 0),
                ((float) ($t['contractual_rate'] ?? 0)) / 100, (float) ($t['fees_total'] ?? 0), (float) ($t['takeon_balance'] ?? 0), (float) ($t['schedule_balance_at_takeon'] ?? 0),
                trim(($t['eir_check_reason'] ?? '') . ($also !== [] ? "\nAlso: " . implode('; ', $also) : '')) ?: null, null, null, 'takeon:' . $t['contract_id']];
        }, $data['takeon']);
        $ws = $this->sheet($book, '6. Take-on loans', ['Loan account', 'Customer', 'Take-on block (your workbook)', 'Value date', 'Original principal', 'Rate', 'Fees', 'Balance at take-on (E-Banker)', 'Balance at take-on (schedule)',
            'Why the schedule could not be used', 'Is the block the right one for this loan?', 'Comment (and where the right schedule is)'], [15, 26, 12, 12, 16, 8, 14, 18, 18, 80, 24, 50], 11, 'BF8F00', $rows);
        $this->percent($ws, 6);
        for ($r = 2; $r <= $ws->getHighestRow(); $r++) {
            $ws->getRowDimension($r)->setRowHeight(150);
        }
        $this->finish($ws, [5, 7, 8, 9], 11, [10], [], [11 => self::SHEETS['6. Take-on loans']['options']]);

        // ------------------------------------------------------------ 7. Corrections made
        $feeById = array_column($data['fees'], null, 'id');
        $rows = array_map(function ($m) use ($feeById, $name) {
            $f = $feeById[(int) $m['contract_fee_id']] ?? [];

            return [(int) $m['contract_fee_id'], $f['contract_id'] ?? null, $name($f['contract_id'] ?? null), $f['fee_type'] ?? null, $f['amount'] ?? null,
                self::SOURCE[$f['source'] ?? ''] ?? ($f['source'] ?? null), $m['reason'], null, null, 'move:' . $m['id']];
        }, $data['moves']);
        $ws = $this->sheet($book, '7. Corrections made', ['Fee line', 'Now on loan', 'Customer', 'Fee type', 'Amount', 'Source', 'What was done', 'Your answer', 'Comment'], [10, 15, 26, 12, 16, 22, 90, 22, 40], 8, '548235', $rows);
        $this->finish($ws, [5], 8, [7], [], [8 => self::SHEETS['7. Corrections made']['options']]);

        // ------------------------------------------------------------ 8. Fee lines
        $rows = array_map(function ($f) use ($name) {
            $use = match (true) {
                $f['superseded_by'] !== null => 'No - the ' . $f['superseded_by'] . ' line is used instead',
                (bool) $f['overlap'] => 'Waiting: see "Overlaps"',
                $f['status'] === 'PENDING' => 'Not yet: see "Lines to classify"',
                $f['integral'] === false => 'No - not integral',
                default => 'Yes',
            };

            return [$f['id'], $f['contract_id'], $name($f['contract_id']), $f['date'], $f['fee_type'], $f['description'], $f['amount'], self::SOURCE[$f['source']] ?? $f['source'],
                $f['source_reference'], $f['gl'], $f['integral'] === null ? '' : ($f['integral'] ? 'Yes' : 'No'), $use, $f['status'] ? ucfirst(strtolower((string) $f['status'])) : null,
                $f['classified_by'], $f['reviewed_by'], null, null, 'fee:' . $f['id']];
        }, $data['fees']);
        $ws = $this->sheet($book, '8. Fee lines', ['Line', 'Loan account', 'Customer', 'Date', 'Fee type', 'Fee name / narration', 'Amount', 'Source', 'Source reference', 'GL', 'Integral',
            'Used in the EIR?', 'Review status', 'Classified by', 'Reviewed by', 'Your answer', 'Comment'], [7, 15, 28, 11, 12, 34, 16, 22, 18, 9, 9, 34, 12, 18, 18, 26, 40], 16, '7F7F7F', $rows);
        $this->finish($ws, [7], 16, [], [], [16 => self::SHEETS['8. Fee lines']['options']]);
        $ws->freezePane('D2');

        // ------------------------------------------------------------ the fingerprint
        $fingerprint = ['generator' => self::GENERATOR, 'version' => self::VERSION, 'generated_at' => $data['as_at'], 'database' => $data['database'],
            'keys' => count(array_unique($this->keys)), 'keys_hash' => self::keysHash($this->keys)];
        $readme = $book->getSheetByName('Read me');
        $readme->setCellValue(self::FINGERPRINT_COLUMN . '1', self::FINGERPRINT_LABEL);
        $readme->setCellValueExplicit(self::FINGERPRINT_COLUMN . '2', json_encode($fingerprint), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $readme->getColumnDimension(self::FINGERPRINT_COLUMN)->setVisible(false);
        $book->setActiveSheetIndex(0);
        $counts = [];
        foreach ($book->getWorksheetIterator() as $s) {
            $counts[$s->getTitle()] = $s->getTitle() === 'Read me' ? 2 : max(0, $s->getHighestDataRow('A') - 1);
        }

        return ['spreadsheet' => $book, 'fingerprint' => $fingerprint, 'counts' => $counts, 'keys' => $this->keys];
    }

    /** The key of a held posting: its external id (stable across the GL fee build's rewrite of the held list). */
    public static function heldKey(array $h): string
    {
        return $h['external_id'] !== null && $h['external_id'] !== '' ? 'held:' . $h['external_id'] : 'held#' . $h['id'];
    }

    // ------------------------------------------------------------------ sheet helpers

    /** A sheet with its header row and rows; the last value of each row is its key, written to the hidden last column. */
    private function sheet(Spreadsheet $book, string $title, array $headers, array $widths, int $answerFrom, string $tab, array $rows): Worksheet
    {
        $ws = $book->createSheet();
        $ws->setTitle($title);
        $ws->getTabColor()->setRGB($tab);
        $all = array_merge($headers, [self::KEY_HEADER]);
        $ws->fromArray($all, null, 'A1', true);
        foreach ($all as $i => $h) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $cell = $col . '1';
            $ws->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $this->fill($ws, $cell, ($i + 1) >= $answerFrom && $i < count($headers) ? self::ANSWER_HEAD : self::NAVY);
            $ws->getStyle($cell)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
            $this->box($ws, $cell);
            $ws->getColumnDimension($col)->setWidth($widths[$i] ?? 20);
        }
        $keyCol = Coordinate::stringFromColumnIndex(count($all));
        $ws->getColumnDimension($keyCol)->setVisible(false);
        $ws->getRowDimension(1)->setRowHeight(42);
        $ws->freezePane('C2');
        $r = 2;
        foreach ($rows as $row) {
            $key = array_pop($row);
            $ws->fromArray(array_merge($row, [$key]), null, 'A' . $r, true);
            // an account number or a fee line id is text in E-Banker: kept as text so a leading zero or a long number survives
            $ws->setCellValueExplicit($keyCol . $r, $key, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $this->keys[] = $key;
            $r++;
        }

        return $ws;
    }

    private function finish(Worksheet $ws, array $moneyCols, int $answerFrom, array $wrapCols, array $fills, array $lists): void
    {
        $n = $ws->getHighestRow();
        $width = Coordinate::columnIndexFromString($ws->getHighestColumn()) - 1; // the key column is not styled
        $lastCol = Coordinate::stringFromColumnIndex($width);
        if ($n >= 2) {
            $ws->getStyle("A2:{$lastCol}{$n}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BFBFBF');
            $ws->getStyle("A2:{$lastCol}{$n}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            foreach ($wrapCols as $c) {
                $col = Coordinate::stringFromColumnIndex($c);
                $ws->getStyle("{$col}2:{$col}{$n}")->getAlignment()->setWrapText(true);
            }
            foreach ($moneyCols as $c) {
                $col = Coordinate::stringFromColumnIndex($c);
                $ws->getStyle("{$col}2:{$col}{$n}")->getNumberFormat()->setFormatCode(self::MONEY);
            }
            foreach ($fills as $r => $colour) {
                if ($answerFrom > 1) {
                    $this->fill($ws, 'A' . $r . ':' . Coordinate::stringFromColumnIndex($answerFrom - 1) . $r, $colour);
                }
            }
            $this->fill($ws, Coordinate::stringFromColumnIndex($answerFrom) . '2:' . $lastCol . $n, self::ANSWER);
        }
        $ws->setAutoFilter('A1:' . $lastCol . max($n, 2));
        foreach ($lists as $c => $options) {
            $col = Coordinate::stringFromColumnIndex($c);
            $this->dropdown($ws, "{$col}2:{$col}" . max($n, 2), $options, true);
        }
    }

    private function dropdown(Worksheet $ws, string $range, array $options, bool $withError = false): void
    {
        $dv = new DataValidation();
        $dv->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)->setFormula1('"' . implode(',', $options) . '"');
        if ($withError) {
            $dv->setShowErrorMessage(true)->setErrorTitle('Pick from the list')->setError('Please pick one of the answers in the list, and use the comment column for anything else.');
        }
        $ws->setDataValidation($range, $dv);
    }

    private function percent(Worksheet $ws, int $col): void
    {
        $c = Coordinate::stringFromColumnIndex($col);
        $n = max($ws->getHighestRow(), 2);
        $ws->getStyle("{$c}2:{$c}{$n}")->getNumberFormat()->setFormatCode('0.00%');
    }

    private function fill(Worksheet $ws, string $range, string $rgb): void
    {
        $ws->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
    }

    private function box(Worksheet $ws, string $range): void
    {
        $ws->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BFBFBF');
    }

    private function key(Worksheet $ws, string $cell, string $key): void
    {
        $ws->setCellValueExplicit($cell, $key, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $this->keys[] = $key;
    }
}
