<?php

namespace App\Http\Controllers;

use App\Models\SupportingDocument;
use App\Services\ApprovalDocumentService;
use App\Services\Eir\GovernanceDocumentService;
use App\Support\Approvals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * One item of an approval workflow (Staging Module Specification v1, section
 * 8.6): its history from the audit log, its supporting documents, and reject
 * or withdraw with a reason. Approving stays on the workflow's own screen,
 * which now refuses an approval without the evidence the workflow requires.
 */
class ApprovalRecordController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:eir.view|eir.govern|settings')->only(['show', 'openDocument']);
        $this->middleware('permission:eir.govern|settings')->only(['decide', 'attachDocument', 'removeDocument']);
    }

    public function show(string $workflow, int $id)
    {
        abort_unless(isset(Approvals::WORKFLOWS[$workflow]), 404);
        $w = Approvals::workflow($workflow);
        try {
            $row = Approvals::record($workflow, $id);
        } catch (InvalidArgumentException) {
            abort(404);
        }
        $names = DB::table('users')->pluck('name', 'id');
        $events = DB::table('audit_logs')->whereIn('entity_type', $w['entities'])->where('entity_id', $id)->orderByDesc('id')
            ->get(['id', 'action', 'new_values', 'meta', 'created_at'])
            ->map(function ($e) use ($names) {
                $meta = json_decode((string) $e->meta, true) ?: [];
                $by = $meta['user'] ?? $meta['approved_by'] ?? $meta['proposed_by'] ?? $meta['rejected_by'] ?? null;
                $values = json_decode((string) $e->new_values, true) ?: [];

                return ['id' => $e->id, 'action' => $e->action, 'at' => (string) $e->created_at, 'by' => $by !== null ? ($names[$by] ?? "user {$by}") : ($meta['label'] ?? null),
                    'reason' => $values['reason'] ?? $meta['reason'] ?? null];
            });
        // attached here or linked from another decision (section 8.7)
        $documents = \App\Support\SupportingDocuments::forItem($w['table'], $id);
        $maker = $w['maker'] !== null && isset($row->{$w['maker']}) ? (int) $row->{$w['maker']} : null;

        return Inertia::render('Approvals/Record', [
            'workflow' => $workflow, 'label' => $w['label'], 'id' => $id, 'status' => (string) ($row->{$w['status']} ?? ''),
            'pending' => Approvals::pending($workflow, $row), 'evidence' => $w['evidence'], 'maker' => $maker !== null ? ($names[$maker] ?? "user {$maker}") : null,
            'isMaker' => $maker !== null && $maker === (int) auth()->id(),
            'events' => $events->values(), 'documents' => $documents->values(), 'documentTypes' => GovernanceDocumentService::TYPES,
        ]);
    }

    public function decide(Request $request, string $workflow, int $id)
    {
        abort_unless(isset(Approvals::WORKFLOWS[$workflow]), 404);
        $data = $request->validate(['action' => ['required', 'in:reject,withdraw'], 'reason' => ['required', 'string', 'min:10', 'max:5000']]);
        try {
            Approvals::decide($workflow, $id, $data['action'], (int) auth()->id(), $data['reason']);
        } catch (InvalidArgumentException|LogicException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', Approvals::workflow($workflow)['label'] . " {$id} " . ($data['action'] === 'reject' ? 'rejected' : 'withdrawn') . '; it stays on record with the reason.');
    }

    public function attachDocument(Request $request, string $workflow, int $id, ApprovalDocumentService $documents)
    {
        abort_unless(isset(Approvals::WORKFLOWS[$workflow]), 404);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:' . (GovernanceDocumentService::MAX_BYTES / 1024)],
            'document_type' => ['required', 'in:' . implode(',', GovernanceDocumentService::TYPES)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            $documents->attach($workflow, $id, $request->file('file'), $data['document_type'], $data['description'] ?? null, (int) auth()->id());
        } catch (InvalidArgumentException|LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Document attached.');
    }

    public function removeDocument(Request $request, SupportingDocument $document, ApprovalDocumentService $documents)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        try {
            $documents->remove($document, (int) auth()->id(), $data['reason']);
        } catch (InvalidArgumentException|LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Document removed; it is kept on record, marked removed.');
    }

    public function openDocument(int $document, ApprovalDocumentService $documents)
    {
        $doc = SupportingDocument::withTrashed()->findOrFail($document);
        try {
            // a Governance Centre document linked to a workflow item opens by the Centre's own rules
            $file = $doc->documentable_type === \App\Models\GovernanceSetting::class
                ? app(GovernanceDocumentService::class)->open($doc, (int) auth()->id()) : $documents->open($doc, (int) auth()->id());
        } catch (InvalidArgumentException) {
            abort(404);
        } catch (LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
        if (! $file['intact']) {
            return back()->with('error', "Document \"{$file['name']}\" has been altered on disk since it was attached: its fingerprint no longer matches. It is not opened; the alteration is recorded in the audit log.");
        }

        return response()->download($file['path'], $file['name'], $file['mime'] ? ['Content-Type' => $file['mime']] : []);
    }
}
