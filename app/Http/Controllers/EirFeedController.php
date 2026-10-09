<?php

namespace App\Http\Controllers;

use App\Services\Ebanker\LoanBookBuildService;
use App\Services\Ebanker\PackLandingService;
use App\Services\Eir\GovernanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Data Foundation, E-Banker Feed (spec v4 section 6.8): the queries, the
 * load history with its gate results, the watermarks, the quarantine, and
 * the Build action with its approval. Every derived loan-book row links back
 * to its raw rows and its pack.
 */
class EirFeedController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:eir.view']);
        $this->middleware('permission:eir.govern')->only(['land', 'build', 'approve']);
    }

    public function index(GovernanceService $governance, LoanBookBuildService $build)
    {
        $loads = DB::table('ebanker_loads as l')->leftJoin('users as u', 'u.id', '=', 'l.loaded_by')
            ->orderByDesc('l.id')->limit(50)
            ->get(['l.id', 'l.pack_name', 'l.route', 'l.period', 'l.status', 'l.loaded_at', 'l.created_at', 'l.gates', 'l.watermarks', 'l.pack_hash', 'u.name as loaded_by'])
            ->map(function ($l) {
                $gates = json_decode($l->gates ?? '', true) ?? [];
                $files = $gates['files'] ?? [];
                $failed = array_filter($files, fn ($f) => ($f['failures'] ?? []) !== []);
                // the named gates of a pack (accounts in the master, the balance history tie, the month-end rows, ISO dates),
                // and for a single-file load (a trial balance, the take-on workbook) the gates recorded at its top level
                $named = array_filter($gates['pack'] ?? $gates, fn ($g) => is_array($g) && isset($g['result']));
                return [
                    'id' => $l->id, 'pack' => $l->pack_name, 'route' => $l->route, 'period' => $l->period, 'status' => $l->status,
                    'loaded_at' => $l->loaded_at ?? $l->created_at, 'loaded_by' => $l->loaded_by, 'hash' => substr($l->pack_hash, 0, 12),
                    'files' => count($files), 'failed' => count($failed),
                    'failures' => array_map(fn ($name, $f) => ['file' => $name, 'failures' => $f['failures']], array_keys($failed), $failed),
                    'gates' => array_map(fn ($name, $g) => ['gate' => $name, 'level' => $g['level'] ?? null, 'result' => $g['result'], 'detail' => $g['detail'] ?? null, 'failures' => $g['failures'] ?? []], array_keys($named), $named),
                    'accepted_exceptions' => $gates['pack']['accepted_exceptions'] ?? [],
                    'watermarks' => json_decode($l->watermarks ?? '', true) ?? [],
                ];
            });
        $queries = DB::table('ebanker_queries')->orderBy('query_id')->get(['query_id', 'title', 'source_table', 'key_column', 'date_column', 'version', 'incremental']);
        // only rows of landed loads count: a quarantined load keeps its rows but no reader sees them
        $rowsByQuery = DB::table('ebanker_raw_rows')->whereNull('superseded_at')->whereIn('load_id', \App\Services\Ebanker\LandingZoneReader::landedLoadIds())
            ->selectRaw('query_id, count(*) n, max(row_date) last_date')->groupBy('query_id')->get()->keyBy('query_id');
        $builds = DB::table('loan_book_builds as b')->leftJoin('users as r', 'r.id', '=', 'b.requested_by')->leftJoin('users as a', 'a.id', '=', 'b.approved_by')
            ->orderByDesc('b.id')->limit(20)
            ->get(['b.id', 'b.method', 'b.period_from', 'b.period_to', 'b.status', 'b.approver_label', 'b.result', 'b.created_at', 'b.built_at', 'r.name as requested_by', 'a.name as approved_by'])
            ->map(fn ($b) => ['id' => $b->id, 'method' => $b->method, 'from' => $b->period_from, 'to' => $b->period_to, 'status' => $b->status, 'approver' => $b->approved_by ?? $b->approver_label,
                'requested_by' => $b->requested_by, 'created_at' => $b->created_at, 'built_at' => $b->built_at,
                'summary' => collect(json_decode($b->result ?? '', true) ?? [])->map(fn ($p, $period) => ['period' => $period, 'method' => $p['method'] ?? '', 'rows' => $p['rows'] ?? 0, 'flagged' => $p['flagged'] ?? 0, 'changed' => $p['changed'] ?? 0, 'new' => $p['new'] ?? 0])->values()->all()]);
        $periods = DB::table('loan_books')->whereNotNull('build_method')->selectRaw('reporting_period, build_method, count(*) n, sum(build_flag is not null) flagged')->groupBy('reporting_period', 'build_method')->orderBy('reporting_period')->get();
        try {
            $route = $governance->get('ebanker_feed_route');
            $method = $governance->get('loan_book_build_method');
        } catch (Throwable) {
            $route = 'Route 1: manual pack'; $method = 'B: derived from the ledger';
        }

        return Inertia::render('Eir/Feed/Index', [
            'loads' => $loads, 'queries' => $queries->map(fn ($q) => (array) $q + ['rows' => (int) ($rowsByQuery[$q->query_id]->n ?? 0), 'last_date' => $rowsByQuery[$q->query_id]->last_date ?? null]),
            'builds' => $builds, 'periods' => $periods, 'routeInForce' => $route, 'methodInForce' => $method,
            'locks' => DB::table('reporting_period_locks')->orderBy('reporting_period')->pluck('reporting_period'),
            'lastLedgerDate' => (new \App\Services\Ebanker\LandingZoneReader())->lastLedgerDate(),
            'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false),
        ]);
    }

    /** Route 1: a pack uploaded as a zip of CSV files plus manifest.json. */
    public function land(Request $request, PackLandingService $service)
    {
        $request->validate(['pack' => ['required', 'file', 'mimes:zip']]);
        $dir = storage_path('app/ebanker-packs/' . now()->format('Ymd-His') . '-' . substr(md5((string) $request->user()->id), 0, 6));
        mkdir($dir, 0775, true);
        $zip = new \ZipArchive();
        if ($zip->open($request->file('pack')->getRealPath()) !== true) {
            return back()->with('error', 'The file is not a readable zip.');
        }
        $zip->extractTo($dir);
        $zip->close();
        $packDir = is_file($dir . '/manifest.json') ? $dir : (glob($dir . '/*/manifest.json') ? dirname(glob($dir . '/*/manifest.json')[0]) : null);
        if ($packDir === null) {
            return back()->with('error', 'The zip holds no manifest.json.');
        }
        try {
            $r = $service->land($packDir, $request->user()->id, PackLandingService::ROUTE_MANUAL);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($r['status'] === 'QUARANTINED' ? 'error' : 'success', "Pack {$r['status']} (load {$r['load_id']}).");
    }

    /** Propose a build; a second person approves it. */
    public function build(Request $request, LoanBookBuildService $service)
    {
        $data = $request->validate(['from' => ['required', 'regex:/^\d{4}-\d{2}$/'], 'to' => ['required', 'regex:/^\d{4}-\d{2}$/'], 'method' => ['nullable', 'in:A,B,C'], 'retire_stale' => ['nullable', 'boolean']]);
        try {
            $r = $service->build($data['from'], $data['to'], $data['method'] ?? null, $request->user()->id, null, null, false, (bool) ($data['retire_stale'] ?? false));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Build {$r['build_id']} proposed for {$data['from']} to {$data['to']}; a second person approves it. " . collect($r['periods'])->sum('changed') . ' changed, ' . collect($r['periods'])->sum('new') . ' new rows in the preview.');
    }

    public function approve(Request $request, int $build, LoanBookBuildService $service)
    {
        try {
            $r = $service->approve($build, $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Build {$r['build_id']} approved and built: " . collect($r['periods'])->sum('rows') . ' rows over ' . count($r['periods']) . ' periods.');
    }

    /** The queries, as the file Barry runs. */
    public function queries()
    {
        $files = glob(base_path('docs/bootstrap/queries/*.sql')) ?: [];
        $out = '';
        foreach ($files as $f) {
            $out .= "-- ===== " . basename($f) . " =====\n" . file_get_contents($f) . "\n\n";
        }

        return response($out, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Content-Disposition' => 'attachment; filename="MAIIC E-Banker queries ' . CarbonImmutable::today()->toDateString() . '.sql"']);
    }
}
