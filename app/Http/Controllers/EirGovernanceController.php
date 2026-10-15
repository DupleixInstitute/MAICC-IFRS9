<?php

namespace App\Http\Controllers;

use App\Models\GovernanceSetting;
use App\Services\Eir\GovernanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;
use LogicException;

/**
 * The Governance Centre (spec v3 section 8): every calculation convention
 * the EIR engine uses, with its options, the value in force, its effective
 * date, who proposed and who approved it, and the history of changes.
 */
class EirGovernanceController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:eir.govern']);
    }

    public function index(GovernanceService $governance)
    {
        return Inertia::render('Eir/Governance', [
            'settings' => $governance->overview(),
            'asOf' => now()->toDateString(),
            'userId' => auth()->id(),
            'adminOverride' => $this->adminOverride(),
            // the transmission-method cards beside their setting (spec v4 section 14.7): what each does, its
            // formula, what it implies, its preconditions checked live, a worked example
            'methodCards' => app(\App\Services\Fli\TransmissionMethodCatalogue::class)->cards(),
        ]);
    }

    public function propose(Request $request, GovernanceService $governance)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'in:' . implode(',', GovernanceService::keys())],
            'value' => ['required', 'string', 'max:255'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        try {
            $setting = $governance->propose($data['key'], $data['value'], $data['effective_from'], $data['reason'], auth()->id());
        } catch (InvalidArgumentException|LogicException|\App\Support\BackDatedChangeException $e) {
            return back()->withErrors(['governance' => $e->getMessage()])->withInput();
        }

        return back()->with('success', "Change to \"{$setting->label}\" proposed. It takes effect from {$setting->effective_from->toDateString()} once a second person approves it.");
    }

    public function approve(GovernanceSetting $setting, GovernanceService $governance)
    {
        try {
            $approved = $governance->approve($setting->id, (int) auth()->id(), $this->adminOverride());
        } catch (LogicException|\App\Support\BackDatedChangeException $e) {
            return back()->withErrors(['governance' => $e->getMessage()]);
        }

        return back()->with('success', "\"{$approved->label}\" is \"{$approved->value}\" from {$approved->effective_from->toDateString()}.");
    }

    /** Reject (a second person) or withdraw (the proposer) a proposal, with a reason (section 8.2). */
    public function review(Request $request, GovernanceSetting $setting, GovernanceService $governance)
    {
        $data = $request->validate(['action' => ['required', 'in:reject,withdraw'], 'reason' => ['required', 'string', 'min:10', 'max:5000']]);
        try {
            $data['action'] === 'reject'
                ? $governance->reject($setting->id, (int) auth()->id(), $data['reason'])
                : $governance->withdraw($setting->id, (int) auth()->id(), $data['reason']);
        } catch (InvalidArgumentException|LogicException $e) {
            return back()->withErrors(['governance' => $e->getMessage()]);
        }

        return back()->with('success', $data['action'] === 'reject' ? "The proposal for \"{$setting->label}\" is rejected; it stays on record." : "The proposal for \"{$setting->label}\" is withdrawn; it stays on record.");
    }

    /** Attach a supporting document to a proposal (section 8.3). */
    public function attachDocument(Request $request, GovernanceSetting $setting, \App\Services\Eir\GovernanceDocumentService $documents)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:' . (\App\Services\Eir\GovernanceDocumentService::MAX_BYTES / 1024)],
            'document_type' => ['required', 'in:' . implode(',', \App\Services\Eir\GovernanceDocumentService::TYPES)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            $documents->attach($setting, $request->file('file'), $data['document_type'], $data['description'] ?? null, (int) auth()->id());
        } catch (InvalidArgumentException|LogicException $e) {
            return back()->withErrors(['governance' => $e->getMessage()]);
        }

        return back()->with('success', 'Document attached to the proposal.');
    }

    public function removeDocument(Request $request, \App\Models\SupportingDocument $document, \App\Services\Eir\GovernanceDocumentService $documents)
    {
        abort_unless($document->documentable_type === GovernanceSetting::class, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        try {
            $documents->remove($document, (int) auth()->id(), $data['reason']);
        } catch (InvalidArgumentException|LogicException $e) {
            return back()->withErrors(['governance' => $e->getMessage()]);
        }

        return back()->with('success', 'Document removed from the proposal; it is kept on record, marked removed.');
    }

    /** Open a document: checked against its fingerprint; an altered file is refused and logged. */
    public function openDocument(int $document, \App\Services\Eir\GovernanceDocumentService $documents)
    {
        $doc = \App\Models\SupportingDocument::withTrashed()->findOrFail($document);
        abort_unless($doc->documentable_type === GovernanceSetting::class, 404);
        try {
            $file = $documents->open($doc, (int) auth()->id());
        } catch (LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
        if (! $file['intact']) {
            return back()->with('error', "Document \"{$file['name']}\" has been altered on disk since it was attached: its fingerprint no longer matches. It is not opened; the alteration is recorded in the audit log.");
        }

        return response()->download($file['path'], $file['name'], $file['mime'] ? ['Content-Type' => $file['mime']] : []);
    }

    /**
     * One setting's history (section 8.4): every value it has held and every
     * proposal, with who proposed and decided, the reasons and the documents,
     * newest first. ?download=csv exports it.
     */
    public function history(Request $request, string $key)
    {
        abort_unless(isset(GovernanceService::catalogue()[$key]), 404);
        $names = \Illuminate\Support\Facades\DB::table('users')->pluck('name', 'id');
        $rows = GovernanceSetting::with('documents')->where('key', $key)->orderByDesc('effective_from')->orderByDesc('id')->get()
            ->map(fn (GovernanceSetting $s) => [
                'id' => $s->id, 'value' => $s->value, 'effective_from' => $s->effective_from->toDateString(), 'status' => $s->status,
                'proposed_by' => $s->set_by ? ($names[$s->set_by] ?? "user {$s->set_by}") : 'installation', 'reason' => $s->reason,
                'approved_by' => $s->approved_by ? ($names[$s->approved_by] ?? "user {$s->approved_by}") : null, 'approved_at' => $s->approved_at?->toDateTimeString(),
                'reviewed_by' => $s->reviewed_by ? ($names[$s->reviewed_by] ?? "user {$s->reviewed_by}") : null, 'reviewed_at' => $s->reviewed_at?->toDateTimeString(), 'review_reason' => $s->review_reason,
                'documents' => $s->documents->map(fn ($d) => ['id' => $d->id, 'name' => $d->original_name, 'type' => $d->document_type, 'description' => $d->description, 'removed' => $d->deleted_at !== null, 'sha256' => $d->hash])->values(),
            ]);
        $events = \Illuminate\Support\Facades\DB::table('audit_logs')->where('entity_type', GovernanceSetting::class)->whereIn('entity_id', $rows->pluck('id'))->orderByDesc('id')
            ->get(['id', 'action', 'entity_id', 'new_values', 'meta', 'created_at'])->map(fn ($e) => ['id' => $e->id, 'action' => $e->action, 'change' => $e->entity_id, 'at' => (string) $e->created_at,
                'detail' => json_decode((string) $e->new_values, true), 'by' => (fn ($m) => isset($m['user']) ? ($names[$m['user']] ?? 'user ' . $m['user']) : ($m['seeded_by'] ?? null))(json_decode((string) $e->meta, true) ?: [])]);
        if ($request->query('download') === 'csv') {
            return response()->streamDownload(function () use ($rows, $key) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ["History of the governed setting {$key}"]);
                fputcsv($out, ['Change', 'Value', 'Effective from', 'Status', 'Proposed by', 'Reason', 'Approved by', 'Approved at', 'Rejected or withdrawn by', 'When', 'Why', 'Documents']);
                foreach ($rows as $r) {
                    fputcsv($out, [$r['id'], $r['value'], $r['effective_from'], $r['status'], $r['proposed_by'], $r['reason'], $r['approved_by'], $r['approved_at'], $r['reviewed_by'], $r['reviewed_at'], $r['review_reason'],
                        collect($r['documents'])->map(fn ($d) => $d['name'] . ($d['removed'] ? ' (removed)' : '') . ' sha256 ' . $d['sha256'])->implode('; ')]);
                }
                fclose($out);
            }, "Governance history {$key} " . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
        }

        return Inertia::render('Eir/GovernanceHistory', [
            'settingKey' => $key, 'label' => GovernanceService::catalogue()[$key]['label'], 'evidenceRequired' => GovernanceService::evidenceRequired($key),
            'rows' => $rows->values(), 'events' => $events->values(), 'documentTypes' => \App\Services\Eir\GovernanceDocumentService::TYPES, 'userId' => auth()->id(),
        ]);
    }

    /**
     * The self-approvals report (Staging Module Specification v1, section 8.9,
     * item 6): every decision made under the maker-checker override, by
     * workflow, person and date, read from the "Maker-Checker Override Used"
     * audit entries; for review at handover. ?download=csv exports it.
     */
    public function selfApprovals(Request $request)
    {
        $rows = \App\Support\MakerChecker::selfApprovals();
        if ($request->query('download') === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['When', 'Workflow', 'Record', 'Person', 'Environment', 'Label']);
                foreach ($rows as $r) {
                    fputcsv($out, [$r['at'], $r['workflow'], is_scalar($r['record']) ? $r['record'] : json_encode($r['record']), $r['name'] ?? ('user ' . $r['person']), $r['environment'], \App\Support\MakerChecker::LABEL]);
                }
                fclose($out);
            }, 'Self-approvals under the maker-checker override ' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
        }

        return Inertia::render('Eir/SelfApprovals', [
            'rows' => $rows->values(),
            'byWorkflow' => $rows->countBy('workflow'),
            'inForce' => \App\Support\MakerChecker::overrideInForce(),
            'environment' => app()->environment(),
        ]);
    }

    /** Administrators may approve their own proposal under UAT mode (MakerChecker), as with the EIR lock. */
    private function adminOverride(): bool
    {
        return app(GovernanceService::class)->adminOverrideAllowed(auth()->user()); // governed (audit H9)
    }
}
