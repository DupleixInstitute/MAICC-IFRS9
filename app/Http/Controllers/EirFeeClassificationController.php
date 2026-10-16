<?php

namespace App\Http\Controllers;

use App\Models\ContractFee;
use App\Models\EirAccountingRule;
use App\Models\EirFeeClassificationEvent;
use App\Services\AuditLoggerService;
use App\Services\Eir\FeeRuleMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EirFeeClassificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // the list and the fee GL check are readable with eir.fees.import; classifying and reviewing stay with settings
        // the workbook for Finance and Finance's answers go with the fee intake (eir.fees.import), the permission
        // Finance's users hold for this screen; eir.view does not open Fee Classification at all. The held-fee
        // export is the same read as the on-screen queue, so it takes the same grant explicitly (it does not
        // inherit it from index) — Held Fee Classification Export/Reimport Specification v1, section 5.
        $this->middleware('permission:settings|eir.fees.import')->only(['index', 'downloadWorkbook', 'uploadAnswers', 'downloadHeldExport']);
        $this->middleware('permission:settings')->except(['index', 'tieHeld', 'dismissCandidate', 'decideOverlap', 'moveFee', 'downloadWorkbook', 'uploadAnswers', 'decideAnswer', 'approveSheet', 'downloadHeldExport', 'uploadHeldImport']);
        // tying a held GL fee line to a loan is a governed, audit-logged act (spec 7.8.4), and so is deciding
        // Finance's answers, which tie, decide and classify through the same services; the held-fee re-import
        // is a bulk front end to the same two governed operations (spec section 6), so it takes the same grant
        $this->middleware('permission:eir.govern')->only(['tieHeld', 'dismissCandidate', 'decideOverlap', 'moveFee', 'decideAnswer', 'approveSheet', 'uploadHeldImport']);
    }

    /**
     * The GL fee legs the batch could not place on one loan (fee_gl_unassigned),
     * read only: date, batch, amount, GL, narration, why it is held and the loan
     * the evidence points to, if any. A person ties it with tieHeld().
     */
    private function heldGlFees(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('fee_gl_unassigned')) {
            return [];
        }

        // a candidate a person has said is not the loan's (HeldFeeEvidence::dismiss) is shown as such
        $dismissed = \Illuminate\Support\Facades\Schema::hasTable('fee_gl_candidate_dismissals')
            ? DB::table('fee_gl_candidate_dismissals')->get(['external_transaction_id', 'contract_id', 'reason'])->keyBy(fn ($d) => $d->external_transaction_id . '|' . $d->contract_id)
            : collect();

        return DB::table('fee_gl_unassigned')->orderBy('transaction_date')->orderBy('id')
            ->get(['id', 'external_transaction_id', 'transaction_date', 'batch', 'gl_code', 'amount', 'narration', 'reason', 'loans_in_batch', 'candidate_contract_id', 'candidate_evidence'])
            ->map(fn ($h) => (array) $h + ['loans' => json_decode((string) $h->loans_in_batch, true) ?: [],
                'candidate_dismissed' => $dismissed->get($h->external_transaction_id . '|' . ltrim((string) $h->candidate_contract_id, '0'))?->reason])->all();
    }

    /**
     * Where a lower fee source carries a different amount from the source of
     * record for the same contract and fee type (spec 7.8.5), read from the
     * superseded markers the precedence wrote; nothing is written here.
     */
    private function sourceDifferences(): array
    {
        // stored by FeeSourcePrecedence::apply per fee event (R03, 15 October 2026); nothing is recomputed here
        if (! \Illuminate\Support\Facades\Schema::hasTable('fee_source_differences')) {
            return [];
        }

        return DB::table('fee_source_differences')->orderBy('contract_id')->orderBy('fee_type')
            ->get(['contract_id', 'fee_type', 'source_of_record', 'amount_of_record', 'other_source', 'other_amount', 'difference'])
            ->map(fn ($d) => ['contract_id' => (string) $d->contract_id, 'fee_type' => $d->fee_type, 'source_of_record' => $d->source_of_record,
                'amount_of_record' => (float) $d->amount_of_record, 'other_source' => $d->other_source, 'other_amount' => (float) $d->other_amount, 'difference' => (float) $d->difference])
            ->all();
    }

    /** Lower-source fee lines the source of record has no event for (R03): each waits for a person's decision. */
    private function sourceOverlaps(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('contract_fees', 'source_overlap')) {
            return [];
        }

        return DB::table('contract_fees')->whereNotNull('source_overlap')->whereNull('superseded_by_source')->orderBy('contract_id')->orderBy('id')
            ->get(['id', 'contract_id', 'fee_type', 'amount', 'source_system', 'transaction_date', 'source_overlap'])->map(fn ($f) => (array) $f)->all();
    }

    /** A person decides an overlap: a fee of its own (SEPARATE) or the same fee as the source of record's (SAME), with the reason. */
    public function decideOverlap(Request $request, int $fee, \App\Services\Eir\FeeSourcePrecedence $precedence)
    {
        $data = $request->validate(['decision' => ['required', 'in:SEPARATE,SAME'], 'reason' => ['required', 'string', 'min:10', 'max:500']]);
        try {
            $precedence->decide($fee, $data['decision'], $data['reason'], (int) $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['decision'] === 'SEPARATE' ? 'Recorded as a fee of its own: it stands for classification and review.' : 'Recorded as the same fee as the source of record: it is superseded.');
    }

    /** A person records, with the reason, that a held posting is not the fee of the loan the system named (HeldFeeEvidence::dismiss). */
    public function dismissCandidate(Request $request, int $held)
    {
        $data = $request->validate(['contract_id' => ['required', 'string', 'max:60'], 'reason' => ['required', 'string', 'min:10', 'max:500']]);
        try {
            \App\Services\Eir\HeldFeeEvidence::dismiss($held, $data['contract_id'], $data['reason'], (int) $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Recorded: the posting is not the fee of loan ' . ltrim(trim($data['contract_id']), '0') . '. It stays held, and no longer stops that loan\'s EIR.');
    }

    /** Tie a held GL fee line to its loan, with the reason (GlFeeBuildService::assignHeld audit-logs it). */
    public function tieHeld(Request $request, int $held, \App\Services\Ebanker\GlFeeBuildService $service)
    {
        $data = $request->validate(['contract_id' => ['required', 'string', 'max:60'], 'reason' => ['required', 'string', 'min:10', 'max:500']]);
        \App\Support\LedgerReadLimits::raise(); // the precedence and the fee build read the ledger
        try {
            $service->assignHeld($held, $data['contract_id'], (int) $request->user()->id, $data['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'The fee line is tied to loan ' . ltrim(trim($data['contract_id']), '0') . '. It now waits on the list below for its classification and review.');
    }

    /**
     * Move a fee line loaded against the wrong loan to the right one (for example a source file whose rows carried
     * each other's fees), with the reason and, where the file had it wrong too, the right date. Neither loan's EIR
     * may be locked: a locked EIR is reopened first, since its fees are part of it. The line goes back to PENDING,
     * so it is classified and reviewed again on its new loan; the move is an event on the line and an audit entry,
     * and the fee source precedence is applied again for both loans.
     */
    public function moveFee(Request $request, ContractFee $fee)
    {
        $data = $request->validate([
            'contract_id' => ['required', 'string', 'max:60'],
            'transaction_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $target = \App\Support\ContractId::normalise($data['contract_id']);
        if ($target === null || ! DB::table('contract_eir')->where('contract_id', $target)->exists()) {
            return back()->withErrors(['contract_id' => "No loan {$data['contract_id']} in the contract master."]);
        }
        if ($target === $fee->contract_id && empty($data['transaction_date'])) {
            return back()->withErrors(['contract_id' => 'The line is already on that loan.']);
        }
        $locked = DB::table('contract_eir')->whereIn('contract_id', [$fee->contract_id, $target])->whereNotNull('locked_at')->pluck('contract_id')->all();
        if ($locked !== []) {
            return back()->withErrors(['contract_id' => 'The EIR of ' . implode(' and ', $locked) . ' is locked, so its fee lines are part of it: request and approve its reopening first.']);
        }
        $from = $fee->contract_id;
        $fromDate = $fee->transaction_date ? substr((string) $fee->transaction_date, 0, 10) : null;
        $toDate = $data['transaction_date'] ?? $fromDate;
        $reason = 'Moved from loan ' . $from . ' to loan ' . $target . ($toDate !== $fromDate ? ", dated {$toDate} (was " . ($fromDate ?? 'undated') . ')' : '') . ': ' . trim($data['reason']);
        DB::transaction(function () use ($fee, $target, $toDate, $reason, $request) {
            $fee->update(['contract_id' => $target, 'transaction_date' => $toDate, 'classification_status' => 'PENDING', 'integral' => null,
                'classified_by' => null, 'classified_at' => null, 'reviewed_by' => null, 'reviewed_at' => null]);
            EirFeeClassificationEvent::create(['contract_fee_id' => $fee->id, 'action' => 'REOPENED', 'integral' => null, // reopened for classification on its new loan
                'reason' => mb_substr($reason, 0, 2000), 'performed_by' => (int) $request->user()->id]);
        });
        AuditLoggerService::log('EIR Fee Line Moved To Another Loan', ContractFee::class, $fee->id, [
            'old_values' => ['contract_id' => $from, 'transaction_date' => $fromDate], 'new_values' => ['contract_id' => $target, 'transaction_date' => $toDate], 'meta' => ['reason' => trim($data['reason'])]]);
        \App\Support\LedgerReadLimits::raise();
        app(\App\Services\Eir\FeeSourcePrecedence::class)->apply((int) $request->user()->id);

        return back()->with('success', "The fee line is moved from loan {$from} to loan {$target}. It is pending again: classify it and have a second person review it.");
    }

    /** What the setting for the LOS charges never posted would do to the locked EIRs (read only); null when it cannot be read. */
    private function unpostedPreview(): ?array
    {
        try {
            return \App\Services\Eir\UnpostedFeeBasis::preview();
        } catch (\App\Exceptions\GovernanceSettingMissingException) {
            return null;
        }
    }

    /**
     * The fee reconciliation workbook for Finance, from the live data: the sheets, columns, dropdowns and colours
     * Finance knows, each answer row with its hidden key and the Read me with the fingerprint; the download is
     * recorded in the audit log (FeeReconciliationWorkbook).
     */
    public function downloadWorkbook(Request $request, \App\Services\Eir\FeeReconciliationWorkbook $workbook)
    {
        try {
            $file = $workbook->download((int) $request->user()->id);
        } catch (\App\Exceptions\GovernanceSettingMissingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return response()->download($file['path'], $file['name'], ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /**
     * Finance's answers: a workbook from the download (mapped by its keys, its fingerprint checked) or the
     * offline workbook of 16 October 2026 (mapped by the columns that name each record). Nothing changes: each
     * answer is proposed, the file is kept as the supporting document, and a second person decides.
     */
    public function uploadAnswers(Request $request, \App\Services\Eir\FeeWorkbookAnswerService $answers)
    {
        $request->validate(['workbook' => ['required', 'file', 'max:20480']]);
        try {
            $r = $answers->upload($request->file('workbook'), (int) $request->user()->id);
        } catch (\InvalidArgumentException|\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
        $message = "{$r['answers']} answer(s) read from the workbook (" . ($r['basis'] === \App\Services\Eir\FeeWorkbookReader::KEYED ? 'matched by their keys' : 'a workbook with no keys, matched by loan account, fee type and amount')
            . "), each waiting for a second person's decision. Nothing has changed yet.";
        if ($r['refused'] !== []) {
            $message .= ' ' . count($r['refused']) . ' row(s) were refused: ' . implode(' ', array_map(fn ($x) => $x['sheet'] . ': ' . $x['reason'], array_slice($r['refused'], 0, 5))) . (count($r['refused']) > 5 ? ' ...' : '');
        }

        return redirect()->route('eir-fee-classification.index', ['tab' => 'answers'])->with('success', $message);
    }

    /** A second person approves, rejects or records as not actionable one of Finance's answers, with the reason. */
    public function decideAnswer(Request $request, int $answer, \App\Services\Eir\FeeWorkbookAnswerService $answers)
    {
        $data = $request->validate(['action' => ['required', 'in:approve,reject,not_actionable'], 'reason' => ['required', 'string', 'min:10', 'max:2000']]);
        \App\Support\LedgerReadLimits::raise(); // tying a held posting applies the precedence, which reads the ledger
        try {
            $r = $answers->decide($answer, $data['action'], $data['reason'], (int) $request->user()->id);
        } catch (\InvalidArgumentException|\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', match ($r['status']) { 'APPROVED' => 'Approved: ', 'REJECTED' => 'Rejected: ', default => 'Not actionable: ' } . $r['outcome']);
    }

    /** Approve every answer of one sheet of an upload with one reason; one that cannot be applied stays proposed and is named. */
    public function approveSheet(Request $request, \App\Services\Eir\FeeWorkbookAnswerService $answers)
    {
        $data = $request->validate(['upload_id' => ['required', 'integer'], 'sheet' => ['required', 'string', 'max:40'], 'reason' => ['required', 'string', 'min:10', 'max:2000']]);
        \App\Support\LedgerReadLimits::raise();
        try {
            $r = $answers->approveSheet((int) $data['upload_id'], $data['sheet'], $data['reason'], (int) $request->user()->id);
        } catch (\InvalidArgumentException|\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
        $message = "{$data['sheet']}: {$r['approved']} answer(s) approved and applied" . ($r['not_actionable'] ? ", {$r['not_actionable']} recorded as not actionable" : '') . '.';
        if ($r['failed'] !== []) {
            return back()->with('error', $message . ' ' . count($r['failed']) . ' could not be applied and wait: ' . implode(' ', array_slice($r['failed'], 0, 5)));
        }

        return back()->with('success', $message);
    }

    /** The fee lines the screen's filters select (status, fee or cost type, GL, contract). */
    private function filtered(Request $request)
    {
        $query = ContractFee::query();
        if ($request->filled('status')) $query->where('classification_status', $request->input('status'));
        if ($request->filled('fee_type')) $query->where('fee_type', $request->input('fee_type'));
        if ($request->filled('gl')) $query->where('gl_account_ref', $request->input('gl'));
        if ($request->filled('contract_id')) $query->where('contract_id', 'like', '%' . $request->input('contract_id') . '%');
        // a superseded line never enters the EIR and needs no decision: shown only when asked for
        if (! $request->boolean('superseded')) $query->whereNull('superseded_by_source');

        return $query;
    }

    /**
     * Every line the filters select, for "Select all lines matching these filters": the ids, so the
     * existing classify, review and return actions act on them unchanged. A superseded line is left
     * out (it never enters the EIR), and so is a reviewed line unless the filter asks for reviewed lines.
     */
    public function matchingIds(Request $request)
    {
        $query = $this->filtered($request)->whereNull('superseded_by_source');
        if ($request->input('status') !== 'REVIEWED') {
            $query->where('classification_status', '!=', 'REVIEWED');
        }
        $ids = $query->orderBy('id')->limit(5000)->pluck('id');

        return response()->json(['ids' => $ids, 'count' => $ids->count()]);
    }

    public function index(Request $request)
    {
        return Inertia::render('Eir/FeeClassification', $this->indexProps($request));
    }

    private function indexProps(Request $request, ?array $heldImportResult = null): array
    {
        $query = $this->filtered($request)->with('suggestedRule:id,name,proposed_integral');

        return [
            'fees' => $query->orderByRaw("CASE classification_status WHEN 'PENDING' THEN 1 WHEN 'CLASSIFIED' THEN 2 ELSE 3 END")
                ->orderByDesc('id')->paginate(15)->withQueryString(),
            'rules' => EirAccountingRule::where('active', true)->where('status', EirAccountingRule::STATUS_APPROVED)->whereNotNull('approved_at')->orderBy('priority')->get(['id', 'name', 'proposed_integral']),
            'filters' => $request->only(['status', 'fee_type', 'gl', 'contract_id', 'superseded']),
            'summary' => ContractFee::whereNull('superseded_by_source')->select('classification_status', DB::raw('COUNT(*) as line_count'), DB::raw('SUM(amount) as total_amount'))
                ->groupBy('classification_status')->get()->keyBy('classification_status'),
            'supersededCount' => ContractFee::whereNotNull('superseded_by_source')->count(),
            // Drives the re-apply prompt: an operator needs to see that pending
            // lines carry no suggestion before being told to use rule mode.
            'glReconciliation' => app(\App\Services\Eir\FeeGlReconciliationService::class)->report(),
            'canClassify' => (bool) $request->user()?->can('settings'),
            'canGovern' => (bool) $request->user()?->can('eir.govern'),
            'heldGlFees' => $this->heldGlFees(),
            // Finance's answers on the fee reconciliation workbook (spec v4 section 7.8.9)
            'answers' => \Illuminate\Support\Facades\Schema::hasTable('fee_workbook_answers') ? app(\App\Services\Eir\FeeWorkbookAnswerService::class)->page($request->input('answer_sheet'), $request->input('answer_status')) : null,
            'answerUploads' => \Illuminate\Support\Facades\Schema::hasTable('fee_workbook_uploads') ? app(\App\Services\Eir\FeeWorkbookAnswerService::class)->uploads() : [],
            'answerFilters' => ['sheet' => $request->input('answer_sheet'), 'status' => $request->input('answer_status')],
            'answerCounts' => \Illuminate\Support\Facades\Schema::hasTable('fee_workbook_answers') ? DB::table('fee_workbook_answers')->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status') : [],
            'answerSheets' => array_merge(['Read me'], array_keys(\App\Services\Eir\FeeReconciliationWorkbook::SHEETS)),
            'unpostedBasis' => $this->unpostedPreview(),
            'canDecideAnswers' => (bool) $request->user()?->can('eir.govern'),
            'sourceDifferences' => $this->sourceDifferences(),
            'sourceOverlaps' => $this->sourceOverlaps(),
            'ruleCoverage' => [
                'pending' => ContractFee::whereNull('superseded_by_source')->where('classification_status', 'PENDING')->count(),
                'pending_matched' => ContractFee::whereNull('superseded_by_source')->where('classification_status', 'PENDING')->whereNotNull('suggested_rule_id')->count(),
                'approved_rules' => EirAccountingRule::where('active', true)->where('status', EirAccountingRule::STATUS_APPROVED)->whereNotNull('approved_at')->count(),
                'draft_rules' => EirAccountingRule::where('active', true)->where('status', EirAccountingRule::STATUS_PROPOSED)->count(),
            ],
            'heldImportResult' => $heldImportResult,
        ];
    }

    /**
     * The held queue as a CSV, identified by external_transaction_id (Held Fee Classification
     * Export/Reimport Specification v1, section 5). Read only: nothing is written or audit-logged by
     * a download.
     */
    public function downloadHeldExport(\App\Services\Eir\HeldFeeClassificationImportService $service)
    {
        $csv = $service->toCsv($service->exportRows());

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="held-fee-classification-' . now()->format('Y-m-d-His') . '.csv"',
        ]);
    }

    /**
     * Finance's decisions on the held queue, from the downloaded (and possibly edited) file. Every
     * accepted row calls assignHeld() or HeldFeeEvidence::dismiss() exactly as the screen does
     * (section 6); nothing silent, every row accounted for in the result shown on the same screen.
     */
    public function uploadHeldImport(Request $request, \App\Services\Eir\HeldFeeClassificationImportService $service)
    {
        $request->validate(['file' => ['required', 'file', 'max:20480']]);
        \App\Support\LedgerReadLimits::raise(); // a TIE applies the fee source precedence, which reads the ledger
        try {
            $r = $service->import($request->file('file')->get(), $request->file('file')->getClientOriginalName(), (int) $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
        $o = $r['outcomes'];
        $message = "{$o['tied']} tied, {$o['candidate_rejected']} candidate(s) rejected (posting stays held), {$o['deferred']} deferred, {$o['unchanged']} unchanged; "
            . "{$o['already_resolved']} already resolved, {$o['stale_evidence']} stale evidence (export again), {$o['not_currently_held']} not currently held, "
            . "{$o['conflict']} conflict(s), {$o['rejected']} rejected.";
        session()->flash('success', $message);

        return Inertia::render('Eir/FeeClassification', $this->indexProps($request, $r));
    }

    /**
     * Re-applies the approved rulebook to PENDING lines already on file.
     *
     * Deliberately a separate action rather than a side effect of approving a
     * rule: approving one rule should not silently re-tag thousands of fee
     * lines. The operator triggers it and sees what moved.
     */
    public function rematch(FeeRuleMatcher $matcher)
    {
        $result = $matcher->sweepPending();

        AuditLoggerService::log('EIR Fee Rules Re-applied', ContractFee::class, null, ['meta' => $result]);

        if ($result['examined'] === 0) {
            return back()->with('success', 'No pending fee lines to re-match.');
        }

        $message = "Re-applied the rulebook to {$result['examined']} pending line(s): "
            . "{$result['matched']} matched, {$result['unmatched']} still unmatched, "
            . "{$result['changed']} suggestion(s) updated.";
        if ($result['left_alone'] > 0) {
            $message .= " {$result['left_alone']} already-classified line(s) were left untouched.";
        }

        return back()->with('success', $message);
    }

    /**
     * Two modes.
     *
     * MANUAL applies one treatment to every selected line — correct when a
     * human has looked at the selection and judged it as a set.
     *
     * RULE applies each line's OWN matched rule, which is what makes the
     * rulebook worth authoring: a mixed selection of arrangement fees and
     * default charges resolves correctly in a single action instead of
     * everything inheriting whichever boolean was clicked. A line with no
     * matched rule is refused rather than defaulted — there is no rule to
     * apply, and guessing is what the rulebook exists to stop. That refusal is
     * absolute and not subject to the admin override, because it is a data
     * question rather than a segregation-of-duties one.
     */
    public function classify(Request $request)
    {
        $data = $request->validate([
            'fee_ids' => ['required', 'array', 'min:1'],
            'fee_ids.*' => ['integer', 'exists:contract_fees,id'],
            'mode' => ['nullable', 'in:manual,rule'],
            'integral' => ['required_if:mode,manual', 'nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:2000'],
            'accounting_rule_id' => ['nullable', 'exists:eir_accounting_rules,id'],
            // a line loaded without a direction (the LOS charges carry none) cannot
            // enter the EIR; the classifier states it, and only fills a blank
            'cashflow_direction' => ['nullable', 'in:RECEIVED,PAID'],
        ]);

        $byRule = ($data['mode'] ?? 'manual') === 'rule';
        // one path for a person on this screen and for Finance's workbook answers (FeeClassificationService)
        ['applied' => $applied, 'refused' => $refused] = app(\App\Services\Eir\FeeClassificationService::class)->classify(
            $data['fee_ids'], $byRule, $byRule ? null : (bool) $data['integral'], $data['reason'], (int) auth()->id(),
            $byRule ? null : ($data['accounting_rule_id'] ?? null), $data['cashflow_direction'] ?? null);

        if ($applied === 0 && $refused !== []) {
            return back()->withErrors(['fee_ids' => 'No line could be classified: none of the selected lines has a matching rule. Re-apply the rulebook, or classify them manually.']);
        }

        $message = $applied . ' fee/cost line(s) classified' . ($byRule ? ' using their matched rules.' : '.');
        if ($refused !== []) {
            $message .= ' ' . count($refused) . ' line(s) were skipped for having no matching rule: '
                . implode(', ', array_slice($refused, 0, 5)) . (count($refused) > 5 ? ' ...' : '');
        }

        return back()->with('success', $message);
    }

    public function review(Request $request)
    {
        $data = $request->validate(['fee_ids' => ['required', 'array', 'min:1'], 'fee_ids.*' => ['integer', 'exists:contract_fees,id']]);
        try {
            $result = app(\App\Services\Eir\FeeClassificationService::class)->review($data['fee_ids'], (int) auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['fee_ids' => $e->getMessage()]);
        }

        return back()->with('success', count($result['reviewed']) . ' classification(s) approved.');
    }

    /**
     * Send classified or reviewed lines back to classification, with the
     * reason (maker-checker): a reviewed line found to be missing a required
     * field, such as its cash-flow direction, or classified on a wrong basis,
     * is classified again and reviewed again by a second person. A line of a
     * contract whose original EIR is locked has entered that EIR and is not
     * reopened here; the EIR is reopened first (EIR Calculations, Reopen).
     */
    public function returnToClassification(Request $request)
    {
        $data = $request->validate([
            'fee_ids' => ['required', 'array', 'min:1'],
            'fee_ids.*' => ['integer', 'exists:contract_fees,id'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $fees = ContractFee::whereIn('id', $data['fee_ids'])->whereIn('classification_status', ['CLASSIFIED', 'REVIEWED'])->get();
        $locked = DB::table('contract_eir')->whereIn('contract_id', $fees->pluck('contract_id')->unique())->whereNotNull('locked_at')->pluck('contract_id')->all();
        $blocked = $fees->whereIn('contract_id', $locked);
        if ($blocked->isNotEmpty()) {
            return back()->withErrors(['fee_ids' => 'The original EIR of ' . implode(', ', array_slice($locked, 0, 5)) . ' is locked, so its fee lines are part of it: reopen the EIR first. Nothing was returned.']);
        }
        if ($fees->isEmpty()) {
            return back()->withErrors(['fee_ids' => 'Only classified or reviewed lines can be returned to classification.']);
        }
        DB::transaction(function () use ($fees, $data) {
            foreach ($fees as $fee) {
                $was = $fee->classification_status;
                $fee->update(['classification_status' => 'PENDING', 'classified_by' => null, 'classified_at' => null, 'reviewed_by' => null, 'reviewed_at' => null]);
                EirFeeClassificationEvent::create(['contract_fee_id' => $fee->id, 'action' => 'REOPENED', 'integral' => $fee->integral,
                    'reason' => 'Returned to classification (was ' . strtolower($was) . '): ' . trim($data['reason']), 'performed_by' => auth()->id()]);
            }
        });
        AuditLoggerService::log('EIR Fee Classifications Returned', ContractFee::class, null, ['meta' => ['fee_ids' => $fees->pluck('id')->all(), 'reason' => trim($data['reason'])]]);

        return back()->with('success', $fees->count() . ' line(s) returned to classification. Classify them again; a second person then reviews them.');
    }
}
