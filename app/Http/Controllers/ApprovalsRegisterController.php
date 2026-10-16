<?php

namespace App\Http\Controllers;

use App\Exports\Ifrs9ReportExport;
use App\Models\SupportingDocument;
use App\Services\ApprovalDocumentService;
use App\Services\ApprovalsRegisterService;
use App\Services\AuditLoggerService;
use App\Support\Approvals;
use App\Support\ReportDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use InvalidArgumentException;
use LogicException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The register of approvals (Staging Module Specification v1, section 8.7;
 * acceptance 15): everything awaiting a decision and everything decided,
 * across the Governance Centre and the approval workflows, filtered by
 * workflow, person, status and date, with its reconciliation to the audit
 * log, and exported to Excel and PDF. A document already in the store is
 * linked here to another pending decision it supports.
 */
class ApprovalsRegisterController extends Controller
{
    private const PER_PAGE = 100;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:eir.view|eir.govern|settings')->only(['index', 'export']);
        $this->middleware('permission:eir.govern|settings')->only(['link']);
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'workflow' => ['nullable', Rule::in(array_keys(ApprovalsRegisterService::workflows()))],
            'person' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:30'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return array_filter(array_intersect_key($data, array_flip(['workflow', 'person', 'status', 'from', 'to'])), fn ($v) => $v !== null && $v !== '');
    }

    public function index(Request $request, ApprovalsRegisterService $register)
    {
        $filters = $this->filters($request);
        $rows = $register->rows($filters);
        $page = max(1, (int) $request->input('page', 1));
        $all = $register->rows();

        return Inertia::render('Approvals/Register', [
            'rows' => array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'total' => count($rows), 'page' => $page, 'perPage' => self::PER_PAGE,
            'pending' => count(array_filter($rows, fn ($r) => $r['pending'])),
            'filters' => (object) $filters,
            'workflows' => ApprovalsRegisterService::workflows(),
            'statuses' => array_values(array_unique(array_column($all, 'status'))),
            'people' => DB::table('users')->orderBy('name')->get(['id', 'name']),
            'reconciliation' => $register->reconcile($all),
            'canLink' => (bool) $request->user()?->can('eir.govern') || (bool) $request->user()?->can('settings'),
        ]);
    }

    public function export(Request $request, string $format, ApprovalsRegisterService $register)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $filters = $this->filters($request);
        $rows = $register->rows($filters);
        $rec = $register->reconcile();
        $filename = 'Approvals register ' . now()->format('Y-m-d');
        AuditLoggerService::log('Approvals Register Exported', 'approvals_register', null, ['new_values' => ['format' => $format, 'filters' => $filters, 'items' => count($rows)],
            'meta' => ['user' => $request->user()?->id]]);
        $report = [
            'company' => ReportDownload::company(),
            'title' => 'Register of approvals',
            'subtitle' => 'Everything awaiting a decision and everything decided, across the Governance Centre and the approval workflows (Staging Module Specification v1, section 8.7)'
                . ($filters !== [] ? '. Filtered: ' . json_encode($filters) : ''),
            'period' => null,
            'generated_at' => now()->format('d M Y H:i'),
            'generated_by' => optional($request->user())->name,
            'kpis' => [
                ['label' => 'Items', 'value' => (string) count($rows), 'tone' => 'maiic'],
                ['label' => 'Awaiting a decision', 'value' => (string) count(array_filter($rows, fn ($r) => $r['pending'])), 'tone' => 'amber'],
                ['label' => 'Reconciled to the audit log', 'value' => $rec['reconciled'] ? 'Yes' : 'No: ' . (count($rec['decided_without_entry']) + count($rec['entries_without_item'])) . ' named', 'tone' => $rec['reconciled'] ? 'emerald' : 'rose'],
            ],
            'sections' => [
                ['heading' => 'Approvals', 'columns' => ApprovalsRegisterService::EXPORT_HEADINGS, 'align' => array_fill(0, count(ApprovalsRegisterService::EXPORT_HEADINGS), 'l'),
                    'rows' => array_map(fn ($r) => array_map(fn ($v) => (string) ($v ?? ''), $r), ApprovalsRegisterService::exportRows($rows))],
                ['heading' => 'Reconciliation with the audit log', 'columns' => ['Finding', 'Workflow', 'Item', 'Detail'], 'align' => ['l', 'l', 'l', 'l'],
                    'rows' => array_merge(
                        array_map(fn ($w) => ['Decided, with no audit entry', $w['workflow'], (string) $w['id'], $w['title'] . ' ' . $w['status']], $rec['decided_without_entry']),
                        array_map(fn ($o) => ['Audit entry for an item not on record', $o['workflow'], (string) $o['id'], 'entry ' . $o['entry'] . ': ' . $o['action'] . ' ' . $o['at']], $rec['entries_without_item']),
                        $rec['reconciled'] ? [['Every decision has an audit entry and every decision entry names an item', '', '', '']] : [])],
            ],
        ];
        if ($format === 'pdf') {
            return ReportDownload::pdf($report, $filename);
        }

        return Excel::download(new Ifrs9ReportExport($report), $filename . '.xlsx');
    }

    /** Link a document already in the store to another pending decision (section 8.7). */
    public function link(Request $request, ApprovalDocumentService $documents)
    {
        $data = $request->validate([
            'target' => ['required', Rule::in(array_merge(['governance'], array_keys(Approvals::WORKFLOWS)))],
            'id' => ['required', 'integer'],
            'document_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $doc = SupportingDocument::withTrashed()->find($data['document_id']);
        if ($doc === null) {
            return back()->with('error', "No document {$data['document_id']} in the store.");
        }
        try {
            $documents->link($doc, $data['target'], (int) $data['id'], (int) $request->user()->id, $data['reason']);
        } catch (InvalidArgumentException|LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Document {$doc->id} ({$doc->original_name}) is linked; it now counts as evidence for this decision too.");
    }
}
