<?php

namespace App\Http\Controllers;

use App\Exports\Ifrs9ReportExport;
use App\Services\AuditLoggerService;
use App\Services\RunRecordService;
use App\Support\ReportDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The record of what each month ran on (Staging Module Specification v1,
 * section 8.8, items 1, 3 and 5): the runs of each month with their steps,
 * frozen snapshot and fingerprint, the run of record of a locked month,
 * "Settings in force" for a month and "What changed" between two, exported
 * to Excel and PDF.
 */
class RunRecordsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:eir.view|eir.govern|settings');
        $this->middleware('permission:eir.govern|settings')->only(['reperform']);
    }

    public function index(Request $request, RunRecordService $runs)
    {
        $data = $request->validate([
            'period' => ['nullable', 'regex:/^\d{4}-\d{2}$/'], 'kind' => ['nullable', Rule::in([RunRecordService::MONTH_END, RunRecordService::BOOTSTRAP, RunRecordService::SCREEN])],
            'from' => ['nullable', 'regex:/^\d{4}-\d{2}$/'], 'to' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $names = DB::table('users')->pluck('name', 'id');
        $list = RunRecordService::available() ? DB::table('run_records')->when($data['period'] ?? null, fn ($q, $p) => $q->where('reporting_period', $p))
            ->when($data['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))->orderByDesc('id')->limit(300)
            ->get(['id', 'reporting_period', 'kind', 'status', 'label', 'started_by', 'started_at', 'finished_at', 'code_version', 'fingerprint', 'of_record_at', 'of_record_until', 'superseded_by_run', 'steps'])
            ->map(fn ($r) => ['id' => $r->id, 'period' => $r->reporting_period, 'kind' => $r->kind, 'status' => $r->status, 'label' => $r->label,
                'by' => $r->started_by ? ($names[$r->started_by] ?? "user {$r->started_by}") : null, 'started_at' => $r->started_at, 'finished_at' => $r->finished_at,
                'code' => substr((string) $r->code_version, 0, 10), 'fingerprint' => substr((string) $r->fingerprint, 0, 12),
                'of_record' => $r->of_record_at !== null && $r->of_record_until === null, 'was_of_record' => $r->of_record_until !== null, 'superseded_by' => $r->superseded_by_run,
                'steps' => count(json_decode((string) $r->steps, true) ?: [])]) : collect();
        $compare = null;
        if (($data['from'] ?? null) && ($data['to'] ?? null)) {
            $compare = $this->compare($runs, $data['from'], $data['to']);
        }

        return Inertia::render('RunRecords/Index', [
            'runs' => $list->values(), 'filters' => (object) array_filter($data), 'compare' => $compare,
            'months' => RunRecordService::available() ? DB::table('run_records')->distinct()->orderByDesc('reporting_period')->pluck('reporting_period') : [],
        ]);
    }

    public function show(int $run, RunRecordService $runs)
    {
        $r = RunRecordService::available() ? DB::table('run_records')->where('id', $run)->first() : null;
        abort_if($r === null, 404);
        $snapshot = json_decode((string) $r->snapshot, true) ?: [];
        $names = DB::table('users')->pluck('name', 'id');

        return Inertia::render('RunRecords/Show', [
            'run' => ['id' => $r->id, 'period' => $r->reporting_period, 'kind' => $r->kind, 'status' => $r->status, 'label' => $r->label,
                'by' => $r->started_by ? ($names[$r->started_by] ?? "user {$r->started_by}") : null, 'started_at' => $r->started_at, 'finished_at' => $r->finished_at,
                'code_version' => $r->code_version, 'fingerprint' => $r->fingerprint, 'error' => $r->error, 'superseded_by' => $r->superseded_by_run,
                'of_record_at' => $r->of_record_at, 'of_record_until' => $r->of_record_until, 'of_record_by' => $r->of_record_by ? ($names[$r->of_record_by] ?? null) : null],
            'verifies' => $r->snapshot === null ? null : $runs->verifies($r),
            'steps' => json_decode((string) $r->steps, true) ?: [],
            'results' => json_decode((string) $r->results, true) ?: [],
            'sections' => $snapshot === [] ? [] : RunRecordService::flatten($snapshot),
            // the re-performances of this run (section 8.8, item 6), the latest with its differences
            'reperformances' => \Illuminate\Support\Facades\Schema::hasTable('reperformances') ? DB::table('reperformances')->where('run_record_id', $r->id)->orderByDesc('id')->get()
                ->map(fn ($p) => ['id' => $p->id, 'mode' => $p->mode, 'workspace' => $p->workspace, 'status' => $p->status, 'started_at' => $p->started_at, 'finished_at' => $p->finished_at,
                    'by' => $p->started_by ? ($names[$p->started_by] ?? null) : null, 'summary' => json_decode((string) $p->summary, true), 'error' => $p->error]) : [],
            'differences' => \Illuminate\Support\Facades\Schema::hasTable('reperformance_rows') && ($latest = DB::table('reperformances')->where('run_record_id', $r->id)->max('id'))
                ? DB::table('reperformance_rows')->where('reperformance_id', $latest)->orderBy('id')->limit(200)->get(['check', 'contract_id', 'item', 'stored', 'reperformed', 'note']) : [],
            'canReperform' => (bool) request()->user()?->can('eir.govern') || (bool) request()->user()?->can('settings'),
            'rowsStamped' => collect(RunRecordService::STAMP)->keys()->filter(fn ($t) => \Illuminate\Support\Facades\Schema::hasTable($t) && \Illuminate\Support\Facades\Schema::hasColumn($t, 'run_record_id'))
                ->mapWithKeys(fn ($t) => [$t => DB::table($t)->where('run_record_id', $r->id)->count()])->filter()->all(),
        ]);
    }

    /** Re-perform the run in place (section 8.8, item 6): from its frozen snapshot, nothing written but the record. */
    public function reperform(Request $request, int $run, \App\Services\ReperformanceService $service)
    {
        try {
            $r = $service->runFor((string) DB::table('run_records')->where('id', $run)->value('reporting_period'), $run);
            $id = $service->inPlace($r, (int) $request->user()->id);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $p = DB::table('reperformances')->find($id);
        $s = json_decode((string) $p->summary, true) ?: [];

        return back()->with($p->status === 'REPRODUCED' ? 'success' : 'error', "Re-performance {$id}: " . ($p->status === 'REPRODUCED'
            ? 'every one of the ' . ($s['loans'] ?? 0) . ' loans reproduced, and the inputs match the snapshot.'
            : ($s['differences'] ?? 0) . ' difference(s) named below ' . json_encode($s['by_check'] ?? []) . '.'));
    }

    /** A re-performance and every difference it named, in Excel or PDF. */
    public function reperformance(Request $request, int $reperformance, string $format)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $p = DB::table('reperformances')->find($reperformance);
        abort_if($p === null, 404);
        $s = json_decode((string) $p->summary, true) ?: [];
        $rows = DB::table('reperformance_rows')->where('reperformance_id', $p->id)->orderBy('id')->get()
            ->map(fn ($r) => [$r->check, (string) $r->contract_id, $r->item, (string) $r->stored, (string) $r->reperformed, (string) $r->note])->all();

        return $this->download($request, $format, "Re-performance {$p->reporting_period} (run {$p->run_record_id})", 'Re-performed ' . strtolower(str_replace('_', ' ', $p->mode))
            . ($p->workspace ? " in {$p->workspace}" : '') . " on {$p->finished_at}: {$p->status}, " . ($s['loans'] ?? 0) . ' loans checked', $p->reporting_period, [
                ['heading' => 'Differences', 'columns' => ['Check', 'Loan', 'Item', 'Stored', 'Re-performed', 'Note'], 'align' => ['l', 'l', 'l', 'r', 'r', 'l'],
                    'rows' => $rows ?: [['None: every loan reproduced and every input matches the snapshot', '', '', '', '', '']]],
            ], ['reperformance' => $p->id]);
    }

    private function compare(RunRecordService $runs, string $from, string $to): array
    {
        $a = $runs->monthRun($from);
        $b = $runs->monthRun($to);
        $diff = $a && $b ? RunRecordService::differences(json_decode((string) $a->snapshot, true) ?: [], json_decode((string) $b->snapshot, true) ?: []) : [];

        return ['from' => $from, 'to' => $to, 'from_run' => $a?->id, 'to_run' => $b?->id, 'rows' => $diff,
            'results' => ['from' => json_decode((string) ($a->results ?? 'null'), true), 'to' => json_decode((string) ($b->results ?? 'null'), true)]];
    }

    /** "Settings in force" for a month: everything in the record of the run that stands for it. */
    public function settingsInForce(Request $request, string $period, string $format, RunRecordService $runs)
    {
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $period) === 1 && in_array($format, ['xlsx', 'pdf'], true), 404);
        $run = $runs->monthRun($period);
        if ($run === null) {
            return back()->with('error', "No completed run of {$period} is on record yet.");
        }
        $sections = [];
        foreach (RunRecordService::flatten(json_decode((string) $run->snapshot, true) ?: []) as $heading => $items) {
            $sections[] = ['heading' => $heading, 'columns' => ['Item', 'In force'], 'align' => ['l', 'l'], 'rows' => array_map(fn ($k, $v) => [(string) $k, $v], array_keys($items), $items)];
        }

        return $this->download($request, $format, "Settings in force {$period}", "What {$period} ran on: run {$run->id} ({$run->kind}" . ($run->of_record_at && ! $run->of_record_until ? ', the run of record' : '')
            . "), fingerprint {$run->fingerprint}", $period, $sections, ['run' => $run->id]);
    }

    /** "What changed" between two months: every setting, rule, model, data load and code change. */
    public function whatChanged(Request $request, string $from, string $to, string $format, RunRecordService $runs)
    {
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $from) === 1 && preg_match('/^\d{4}-\d{2}$/', $to) === 1 && in_array($format, ['xlsx', 'pdf'], true), 404);
        $c = $this->compare($runs, $from, $to);
        if ($c['from_run'] === null || $c['to_run'] === null) {
            return back()->with('error', 'Both months need a completed run on record to be compared.');
        }
        $results = array_map(fn ($k) => [$k, json_encode($c['results']['from'][$k] ?? null), json_encode($c['results']['to'][$k] ?? null)],
            array_unique(array_merge(array_keys((array) $c['results']['from']), array_keys((array) $c['results']['to']))));

        return $this->download($request, $format, "What changed {$from} to {$to}", "Run {$c['from_run']} of {$from} against run {$c['to_run']} of {$to}: every input that differs", $to, [
            ['heading' => 'Inputs that differ', 'columns' => ['Section', 'Item', $from, $to], 'align' => ['l', 'l', 'l', 'l'], 'rows' => $c['rows'] ?: [['No input differs', '', '', '']]],
            ['heading' => 'Headline results', 'columns' => ['Figure', $from, $to], 'align' => ['l', 'r', 'r'], 'rows' => array_values($results)],
        ], ['from_run' => $c['from_run'], 'to_run' => $c['to_run'], 'differences' => count($c['rows'])]);
    }

    private function download(Request $request, string $format, string $title, string $subtitle, string $period, array $sections, array $meta)
    {
        AuditLoggerService::log('Run Record Report Exported', 'run_records', null, ['reporting_period' => $period, 'new_values' => ['report' => $title, 'format' => $format] + $meta,
            'meta' => ['user' => $request->user()?->id]]);
        $report = ['company' => ReportDownload::company(), 'title' => $title, 'subtitle' => $subtitle, 'period' => $period, 'generated_at' => now()->format('d M Y H:i'),
            'generated_by' => optional($request->user())->name, 'kpis' => [], 'sections' => $sections];

        return $format === 'pdf' ? ReportDownload::pdf($report, $title) : Excel::download(new Ifrs9ReportExport($report), $title . '.xlsx');
    }
}
