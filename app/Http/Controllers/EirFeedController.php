<?php

namespace App\Http\Controllers;

use App\Services\Ebanker\FeedZip;
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
        $this->middleware('permission:eir.govern')->only(['land', 'build', 'approve', 'lockPeriod', 'approveUnlock', 'closeUnlock']);
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
        $rowsByQuery = \App\Services\Ebanker\LandingZoneReader::onCurrentIndex()->whereNull('superseded_at')->whereIn('load_id', \App\Services\Ebanker\LandingZoneReader::landedLoadIds())
            ->selectRaw('query_id, count(*) n, max(row_date) last_date')->groupBy('query_id')->get()->keyBy('query_id');
        $builds = DB::table('loan_book_builds as b')->leftJoin('users as r', 'r.id', '=', 'b.requested_by')->leftJoin('users as a', 'a.id', '=', 'b.approved_by')
            ->orderByDesc('b.id')->limit(20)
            ->get(['b.id', 'b.method', 'b.period_from', 'b.period_to', 'b.status', 'b.note', 'b.approver_label', 'b.result', 'b.created_at', 'b.built_at', 'b.requested_by as requested_by_id', 'r.name as requested_by', 'a.name as approved_by'])
            ->map(fn ($b) => ['id' => $b->id, 'method' => $b->method, 'from' => $b->period_from, 'to' => $b->period_to, 'status' => $b->status, 'approver' => $b->approved_by ?? $b->approver_label,
                // an approved proposal is closed as superseded by the build it produced, not refused
                'superseded' => $b->status === 'REJECTED' && str_starts_with((string) $b->note, 'Superseded'),
                'requested_by' => $b->requested_by, 'requested_by_id' => $b->requested_by_id === null ? null : (int) $b->requested_by_id, 'created_at' => $b->created_at, 'built_at' => $b->built_at,
                'summary' => collect(json_decode($b->result ?? '', true) ?? [])->map(fn ($p, $period) => ['period' => $period, 'method' => $p['method'] ?? '', 'rows' => $p['rows'] ?? 0, 'flagged' => $p['flagged'] ?? 0, 'changed' => $p['changed'] ?? 0, 'new' => $p['new'] ?? 0])->values()->all()]);
        $periods = DB::table('loan_books')->whereNotNull('build_method')->selectRaw('reporting_period, build_method, count(*) n, sum(build_flag is not null) flagged')->groupBy('reporting_period', 'build_method')->orderBy('reporting_period')->get();
        try {
            $route = $governance->get('ebanker_feed_route');
            $method = $governance->get('loan_book_build_method');
        } catch (Throwable) {
            // no approved value in Governance: say so, never assume one
            $route = null; $method = null;
        }

        return Inertia::render('Eir/Feed/Index', [
            'loads' => $loads, 'queries' => $queries->map(fn ($q) => (array) $q + ['rows' => (int) ($rowsByQuery[$q->query_id]->n ?? 0), 'last_date' => $rowsByQuery[$q->query_id]->last_date ?? null]),
            'builds' => $builds, 'periods' => $periods, 'routeInForce' => $route, 'methodInForce' => $method,
            'locks' => DB::table('reporting_period_locks')->orderBy('reporting_period')->pluck('reporting_period'),
            // whether each open month has a run of record a lock would name (Staging Module Specification v1, section 8.8, item 3)
            'runOfRecord' => $this->runOfRecordStatus($periods->pluck('reporting_period')->unique()->values()->all()),
            // unlocks requested by one person and awaiting a second (PeriodUnlockService)
            'unlockRequests' => app(\App\Services\Eir\PeriodUnlockService::class)->pending(),
            'lastLedgerDate' => (new \App\Services\Ebanker\LandingZoneReader())->lastLedgerDate(),
            'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false),
            'me' => auth()->id(),
            // the view the section tab opened (config/menu.php) and the count on each tab
            'tab' => in_array(request('tab'), ['loads', 'builds', 'periods', 'queries'], true) ? request('tab') : 'loads',
            'tabCounts' => ['loads' => DB::table('ebanker_loads')->count(), 'builds' => DB::table('loan_book_builds')->count(), 'periods' => $periods->count(), 'queries' => $queries->count()],
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
        // an entry whose name climbs out of the pack folder is left out and logged (system audit of 9 October 2026, finding M14)
        $extracted = FeedZip::extract($zip, $dir, 'upload');
        $zip->close();
        $packDir = is_file($dir . '/manifest.json') ? $dir : (glob($dir . '/*/manifest.json') ? dirname(glob($dir . '/*/manifest.json')[0]) : null);
        if ($packDir === null) {
            return back()->with('error', 'The zip holds no manifest.json.' . ($extracted['skipped'] !== [] ? ' ' . count($extracted['skipped']) . ' entry name(s) were refused: ' . implode(', ', $extracted['skipped']) : ''));
        }
        try {
            $r = $service->land($packDir, $request->user()->id, PackLandingService::ROUTE_MANUAL, $request->boolean('dry_run'));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $note = $extracted['skipped'] !== [] ? ' ' . count($extracted['skipped']) . ' entry name(s) were refused and left out: ' . implode(', ', $extracted['skipped']) . '.' : '';
        if (($r['status'] ?? null) === 'ALREADY_LANDED') {
            // the same manifest is already held: say so, for a check as for a load (final check of 14 October
            // 2026: a check of a landed pack said "every check passed; load the pack to keep it")
            return back()->with('success', ($request->boolean('dry_run') ? 'Check only, nothing kept: this' : 'This') . " pack is already loaded (load {$r['load_id']}); loading it again changes nothing.{$note}");
        }
        if ($request->boolean('dry_run')) {
            $failed = array_filter($r['gates']['files'] ?? [], fn ($f) => ($f['failures'] ?? []) !== []);
            $packFails = array_filter($r['gates']['pack'] ?? [], fn ($g) => is_array($g) && ($g['result'] ?? '') === 'FAIL');
            // the failures file by file, as the import screens list a refused file (key ebanker-pack)
            $issues = [];
            foreach ($failed as $file => $f) {
                foreach ($f['failures'] as $m) {
                    $issues[] = ['row' => preg_match('/\brow (\d+)/', (string) $m, $mm) ? (int) $mm[1] : null, 'column' => (string) $file, 'message' => (string) $m, 'fix' => 'Correct the file or the manifest entry and zip the pack again.', 'severity' => 'error'];
                }
            }
            foreach ($packFails as $gate => $g) {
                $issues[] = ['row' => null, 'column' => 'Pack: ' . str_replace('_', ' ', (string) $gate), 'message' => implode('; ', array_slice((array) ($g['failures'] ?? [$g['detail'] ?? 'failed']), 0, 5)), 'fix' => 'See the pack check on the E-Banker Loads tab after landing, or correct the files named.', 'severity' => 'error'];
            }
            $request->session()->flash('import_check', \App\Support\Imports\ImportSpecValidator::fromGates('ebanker-pack', $request->file('pack')->getClientOriginalName(), count($r['gates']['files'] ?? []), $issues));

            return back()->with($failed === [] && $packFails === [] ? 'success' : 'error', 'Check only, nothing kept: '
                . ($failed === [] && $packFails === [] ? 'every check passed; load the pack to keep it.' : count($failed) . ' file(s) and ' . count($packFails) . ' pack check(s) failed: '
                    . implode('; ', array_map(fn ($name, $f) => $name . ' ' . implode(', ', $f['failures']), array_keys($failed), $failed))) . $note);
        }

        // the rows the load added, changed and found already held, so a pack sent twice is seen to change nothing
        $rows = $r['status'] === 'LANDED' ? collect($r['files'] ?? [])->filter(fn ($f) => is_array($f) && isset($f['new'])) : collect();
        $counts = $rows->isNotEmpty() ? ': ' . number_format($rows->sum('new')) . ' new rows, ' . number_format($rows->sum('versioned')) . ' changed (kept as a new version), ' . number_format($rows->sum('unchanged')) . ' already held' : '';

        return back()->with($r['status'] === 'QUARANTINED' ? 'error' : 'success', "Pack {$r['status']} (load {$r['load_id']}){$counts}.{$note}");
    }

    /** Propose a build; a second person approves it. */
    public function build(Request $request, LoanBookBuildService $service)
    {
        $data = $request->validate(['from' => ['required', 'regex:/^\d{4}-\d{2}$/'], 'to' => ['required', 'regex:/^\d{4}-\d{2}$/'], 'method' => ['nullable', 'in:A,B,C'], 'retire_stale' => ['nullable', 'boolean']]);
        \App\Support\LedgerReadLimits::raise();
        try {
            $r = $service->build($data['from'], $data['to'], $data['method'] ?? null, $request->user()->id, null, null, false, (bool) ($data['retire_stale'] ?? false));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Build {$r['build_id']} proposed for {$data['from']} to {$data['to']}; a second person approves it. " . collect($r['periods'])->sum('changed') . ' changed, ' . collect($r['periods'])->sum('new') . ' new rows in the preview.');
    }

    public function approve(Request $request, int $build, LoanBookBuildService $service)
    {
        \App\Support\LedgerReadLimits::raise();
        try {
            $r = $service->approve($build, $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Build {$r['build_id']} approved and built: " . collect($r['periods'])->sum('rows') . ' rows over ' . count($r['periods']) . ' periods.');
    }

    /**
     * Lock a reporting period, or request its unlock, from the screen (spec v4
     * sections 6.6 and 6.11): the same action as `php artisan eir:lock-period`,
     * with the reason written to the audit log. A locked period is never
     * restated by a build, the ECL, staging, the PD, the LGD, the
     * forward-looking route, the revenue roll-forward or a write-off. Locking
     * is one person's act; unlocking is maker-checker (documentation audit of
     * 14 October 2026): this records the request, and a different person
     * approves it (approveUnlock) before the lock is removed.
     */
    public function lockPeriod(Request $request, string $period)
    {
        abort_unless((bool) preg_match('/^\d{4}-\d{2}$/', $period), 404);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'], 'unlock' => ['nullable', 'boolean'],
            // a month computed before run records existed: an explicit choice, never the default
            'without_run_record' => ['nullable', 'boolean'],
        ]);
        $unlock = (bool) ($data['unlock'] ?? false);
        $withoutRun = ! $unlock && (bool) ($data['without_run_record'] ?? false);
        if (! $unlock && ! DB::table('loan_books')->where('reporting_period', $period)->exists()) {
            return back()->with('error', "Period {$period} has no loan book; there is nothing to lock.");
        }
        if ($withoutRun) {
            // only for a month no month-end or bootstrap run ever completed; where one did and a screen run changed
            // the month since, the answer is to run the month-end job again, not to lock past the record
            $status = $this->runOfRecordStatus([$period])[$period];
            if (! $status['pre_run_records']) {
                return back()->with('error', "Period {$period} has a month-end run on record, so it is locked from its run, not without one. " . ($status['message'] ?? ''));
            }
            if (mb_strlen(trim($data['reason'])) < 20) {
                return back()->withErrors(['reason' => 'A lock without a run record needs a reason of at least 20 characters saying why: for example that the month was computed before run records existed.']);
            }
        }
        $args = ['period' => $period, '--user' => $request->user()->id, '--reason' => trim($data['reason'])];
        if ($unlock) {
            $args['--unlock'] = true;
        }
        if ($withoutRun) {
            $args['--without-run-record'] = true;
        }
        $code = \Illuminate\Support\Facades\Artisan::call('eir:lock-period', $args);
        $out = trim(\Illuminate\Support\Facades\Artisan::output());

        return back()->with($code === 0 ? 'success' : 'error', $out !== '' ? $out : ($code === 0 ? "Period {$period} updated." : "Period {$period} could not be updated."));
    }

    /**
     * For each month, whether a lock can name its run of record: ok with the run, or why not, and
     * whether the month predates run records (no completed month-end or bootstrap run at all), the
     * only case a lock without a run record is offered for.
     *
     * @param  list<string>  $periods
     * @return array<string, array{ok: bool, run_id: ?int, pre_run_records: bool, message: ?string}>
     */
    private function runOfRecordStatus(array $periods): array
    {
        $out = [];
        $available = \App\Services\RunRecordService::available();
        $runs = app(\App\Services\RunRecordService::class);
        foreach ($periods as $period) {
            $period = (string) $period;
            if (! $available) {
                // a database not yet migrated locks as it did before run records
                $out[$period] = ['ok' => true, 'run_id' => null, 'pre_run_records' => false, 'message' => null];
                continue;
            }
            try {
                $out[$period] = ['ok' => true, 'run_id' => (int) $runs->runOfRecord($period)->id, 'pre_run_records' => false, 'message' => null];
            } catch (\LogicException $e) {
                $everRun = DB::table('run_records')->where('reporting_period', $period)
                    ->whereIn('kind', [\App\Services\RunRecordService::MONTH_END, \App\Services\RunRecordService::BOOTSTRAP])
                    ->where('status', \App\Services\RunRecordService::COMPLETED)->exists();
                $out[$period] = ['ok' => false, 'run_id' => null, 'pre_run_records' => ! $everRun, 'message' => $e->getMessage()];
            }
        }

        return $out;
    }

    /** A second person approves an unlock request, and the lock is removed (PeriodUnlockService). */
    public function approveUnlock(Request $request, int $unlock, \App\Services\Eir\PeriodUnlockService $unlocks)
    {
        try {
            $period = $unlocks->approve($unlock, (int) $request->user()->id);
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Period {$period} unlocked.");
    }

    /** Reject an unlock request (another person) or withdraw it (the person who asked), with a reason. */
    public function closeUnlock(Request $request, int $unlock, \App\Services\Eir\PeriodUnlockService $unlocks)
    {
        $data = $request->validate(['action' => ['required', 'in:reject,withdraw'], 'reason' => ['required', 'string', 'min:10', 'max:500']]);
        try {
            $unlocks->close($unlock, (int) $request->user()->id, $data['action'], $data['reason']);
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['action'] === 'reject' ? 'Unlock request rejected; the period stays locked.' : 'Unlock request withdrawn; the period stays locked.');
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
