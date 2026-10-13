<?php

namespace App\Http\Controllers;

use App\Models\GlTrialBalanceLine;
use App\Services\Ebanker\TrialBalanceLandingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Finance's monthly trial balance, loaded from the screen (13 October 2026). Until
 * now a trial balance could only be landed with eir:import-trial-balances or the
 * bootstrap. The file goes through the same door: TrialBalanceLandingService lands
 * it with its hash and gates (period known, debits equal credits, the file's own
 * grand total), keeps the GL lines as raw rows in the landing zone and derives
 * gl_trial_balance_lines, which the GL reconciliation and the AFS bridge read.
 * A file already landed is recognised by its hash and not loaded twice.
 */
class EirTrialBalanceController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:eir.view']);
        $this->middleware('permission:eir.govern')->only(['upload']);
    }

    public function index()
    {
        // the periods held, newest first, one row per period and basis
        $periods = DB::table('gl_trial_balance_lines')
            ->selectRaw('period, basis, COUNT(*) gl_lines, SUM(debit) debit, SUM(credit) credit, MAX(source_file) source_file, MAX(updated_at) updated_at')
            ->groupBy('period', 'basis')->orderByDesc('period')->orderBy('basis')->get()
            ->map(fn ($p) => ['period' => substr((string) $p->period, 0, 7), 'basis' => $p->basis, 'lines' => (int) $p->gl_lines,
                'debit' => (float) $p->debit, 'credit' => (float) $p->credit, 'difference' => round((float) $p->debit - (float) $p->credit, 2),
                'source_file' => $p->source_file, 'updated_at' => $p->updated_at]);

        $loads = DB::table('ebanker_loads as l')->leftJoin('users as u', 'u.id', '=', 'l.loaded_by')
            ->where('l.route', TrialBalanceLandingService::ROUTE)->orderByDesc('l.id')->limit(50)
            ->get(['l.id', 'l.pack_name', 'l.period', 'l.status', 'l.gates', 'l.manifest', 'l.loaded_at', 'l.created_at', 'u.name as loaded_by'])
            ->map(function ($l) {
                $gates = json_decode($l->gates ?? '', true) ?? [];
                $m = json_decode($l->manifest ?? '', true) ?? [];

                return ['id' => $l->id, 'file' => $m['file'] ?? preg_replace('/^Trial balance /', '', (string) $l->pack_name), 'period' => $l->period, 'basis' => $m['basis'] ?? null,
                    'status' => $l->status, 'rows' => (int) ($m['rows'] ?? 0), 'loaded_at' => $l->loaded_at ?? $l->created_at, 'loaded_by' => $l->loaded_by,
                    'gates' => collect($gates)->filter(fn ($g) => is_array($g) && isset($g['result']))
                        ->map(fn ($g, $name) => ['gate' => $name, 'result' => $g['result'], 'detail' => $g['detail'] ?? null, 'failures' => $g['failures'] ?? []])->values()->all()];
            });

        $latest = $periods->firstWhere('basis', GlTrialBalanceLine::BASIS_POSTCLOSING);

        return Inertia::render('Eir/TrialBalances/Index', [
            'periods' => $periods->values(),
            'loads' => $loads,
            'summary' => [
                'months' => $periods->where('basis', GlTrialBalanceLine::BASIS_POSTCLOSING)->count(),
                'first' => $periods->where('basis', GlTrialBalanceLine::BASIS_POSTCLOSING)->last()['period'] ?? null,
                'last' => $latest['period'] ?? null,
                'refused' => $loads->where('status', 'QUARANTINED')->count(),
            ],
            'canUpload' => (bool) (auth()->user()?->can('eir.govern') ?? false),
        ]);
    }

    /** One trial balance file, through the landing zone's gates, then the derived GL lines. */
    public function upload(Request $request, TrialBalanceLandingService $service)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xls,xlsx', 'max:51200'],
            'basis' => ['required', 'in:' . GlTrialBalanceLine::BASIS_POSTCLOSING . ',' . GlTrialBalanceLine::BASIS_PRECLOSING],
            'period' => ['nullable', 'date_format:Y-m'],
            'sheet' => ['nullable', 'string', 'max:100'],
        ]);
        $dir = storage_path('app/trial-balances/' . now()->format('Ymd-His'));
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        // keep the file's own name: the importer reads the period from it where the sheet carries no stamp
        $name = preg_replace('/[^A-Za-z0-9 ._()-]/', '_', $request->file('file')->getClientOriginalName());
        $path = $request->file('file')->move($dir, $name)->getPathname();

        try {
            $r = $service->landFile($path, $data['basis'], $data['period'] ? $data['period'] . '-01' : null, $data['sheet'] ?: null, $request->user()->id);
            $derived = $r['status'] === 'QUARANTINED' ? null : $service->derive();
        } catch (Throwable $e) {
            return back()->with('error', 'The trial balance could not be read: ' . $e->getMessage());
        }

        if ($r['status'] === 'QUARANTINED') {
            return back()->with('error', "Trial balance refused (load {$r['load_id']}): " . implode('; ', $r['failures']));
        }
        $period = $r['period'] ? substr((string) $r['period'], 0, 7) : 'the month';
        $msg = $r['status'] === 'ALREADY_LANDED'
            ? "This file was already loaded (load {$r['load_id']}); nothing changed."
            : "Trial balance for {$period} loaded (load {$r['load_id']}): {$r['lines']} GL lines, debits " . number_format($r['debit'], 2) . ', credits ' . number_format($r['credit'], 2)
                . '; ' . ($derived['lines'] ?? 0) . ' GL lines now held.';

        return back()->with('success', $msg);
    }
}
