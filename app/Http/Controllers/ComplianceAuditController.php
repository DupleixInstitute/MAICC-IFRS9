<?php

namespace App\Http\Controllers;

use App\Services\Compliance\ComplianceAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Governance Centre, Compliance Audits (spec v4 section 12.5): one card per
 * workbook with its status counts and the three downloads; the rows, where
 * a reviewer sets a status and signs, a second person approves, and the
 * audit log records both. Column 8 renders as a link to the screen.
 */
class ComplianceAuditController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:eir.govern')->only(['sign', 'approve', 'reload']);
    }

    public function index(ComplianceAuditService $service)
    {
        $audits = DB::table('compliance_audits')->orderBy('id')->get()->map(fn ($a) => [
            'id' => $a->id, 'key' => $a->key, 'short' => $a->short, 'title' => $a->title, 'reviewer' => $a->reviewer, 'loaded_at' => $a->loaded_at, 'file_stem' => $a->file_stem,
            'counts' => $service->counts($a->id), 'rows' => DB::table('compliance_audit_rows')->where('audit_id', $a->id)->count(), 'findings' => DB::table('compliance_findings')->where('audit_id', $a->id)->where('status', '!=', 'Closed')->count(),
            'files' => collect(['xlsx', 'pdf', 'md'])->filter(fn ($ext) => is_file(base_path("docs/compliance/{$a->file_stem}.{$ext}")))->values()->all(),
        ]);

        return Inertia::render('Governance/Compliance/Index', ['audits' => $audits, 'statuses' => ComplianceAuditService::STATUSES, 'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false)]);
    }

    public function show(int $audit, ComplianceAuditService $service)
    {
        $a = DB::table('compliance_audits')->where('id', $audit)->first() ?? abort(404);
        $rows = DB::table('compliance_audit_rows as r')->leftJoin('users as s', 's.id', '=', 'r.signed_by')->leftJoin('users as ap', 'ap.id', '=', 'r.approved_by')->where('r.audit_id', $audit)->orderBy('r.ordering')
            ->get(['r.*', 's.name as signer', 'ap.name as approver'])->map(function ($r) {
                $arr = (array) $r;
                // a route named in column 8 becomes a link
                $arr['links'] = [];
                foreach (array_filter(array_map('trim', preg_split('/[;,]/', (string) $r->where_to_see))) as $part) {
                    if (preg_match('#(/[a-z0-9\-/]+)#', $part, $m)) {
                        $arr['links'][] = ['label' => $part, 'href' => $m[1]];
                    }
                }

                return $arr;
            });

        return Inertia::render('Governance/Compliance/Show', [
            'audit' => (array) $a, 'rows' => $rows, 'counts' => $service->counts($audit), 'statuses' => ComplianceAuditService::STATUSES,
            'findings' => DB::table('compliance_findings')->where('audit_id', $audit)->orderBy('number')->get(), 'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false),
        ]);
    }

    public function download(int $audit, string $ext): BinaryFileResponse
    {
        $a = DB::table('compliance_audits')->where('id', $audit)->first() ?? abort(404);
        abort_unless(in_array($ext, ['xlsx', 'pdf', 'md'], true), 404);
        $path = base_path("docs/compliance/{$a->file_stem}.{$ext}");
        abort_unless(is_file($path), 404);

        return response()->download($path);
    }

    public function sign(Request $request, int $row, ComplianceAuditService $service)
    {
        $data = $request->validate(['status' => ['required', 'string'], 'note' => ['nullable', 'string', 'max:255']]);
        try {
            $service->sign($row, $data['status'], $request->user()->id, $data['note'] ?? null);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Row signed; a second person approves the status.');
    }

    public function approve(Request $request, int $row, ComplianceAuditService $service)
    {
        try {
            $service->approve($row, $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Status approved and recorded.');
    }

    public function reload(ComplianceAuditService $service)
    {
        $r = $service->load();

        return back()->with('success', 'Register reloaded from the workbooks: ' . collect($r)->map(fn ($n, $k) => "{$k} {$n} rows")->implode(', ') . '.');
    }
}
