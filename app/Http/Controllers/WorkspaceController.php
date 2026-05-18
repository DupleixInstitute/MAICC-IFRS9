<?php

namespace App\Http\Controllers;

use App\Models\PeriodWorkspaceTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * IFRS 9 period-close workspace. Shows the logged-in user the checklist for a
 * reporting period (import → segmentation → models → ECL → reports → sign-off),
 * with progress. Marking tasks is role-gated to administrators; everyone else
 * sees a live, read-only view.
 */
class WorkspaceController extends Controller
{
    /** The canonical IFRS 9 close checklist. route = deep link to the screen. */
    private const TASKS = [
        ['key' => 'import_loanbook',  'label' => 'Import the loan book for the period',        'route' => 'imports.index'],
        ['key' => 'segmentation',     'label' => 'Review portfolio segmentation & sector tags', 'route' => 'portfolios.index'],
        ['key' => 'staging',          'label' => 'Confirm staging & SICR rules',                'route' => 'stageing-rules.index'],
        ['key' => 'pd_model',         'label' => 'Run / review the PD model',                   'route' => 'transition-matrices.index'],
        ['key' => 'lgd_model',        'label' => 'Run / review the LGD model',                  'route' => 'loss-given-default.index'],
        ['key' => 'fli',              'label' => 'Apply forward-looking (macro) adjustment',    'route' => 'macro-forecast-weighted.index'],
        ['key' => 'run_ecl',          'label' => 'Run the ECL calculation',                     'route' => 'expected-credit-loss.index'],
        ['key' => 'review_reports',   'label' => 'Review IFRS 9 reports & data quality',        'route' => 'ifrs9-reports.index'],
        ['key' => 'stress_test',      'label' => 'Run stress / sensitivity scenarios',          'route' => 'stress-testing.index'],
        ['key' => 'signoff',          'label' => 'Management sign-off for the period',          'route' => null],
    ];

    private function isAdmin(?\App\Models\User $u): bool
    {
        if (! $u) {
            return false;
        }
        try {
            return method_exists($u, 'hasRole') ? (bool) $u->hasRole('admin') : false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function index(Request $request)
    {
        $periods = DB::table('loan_books')->whereNotNull('reporting_period')
            ->distinct()->orderByDesc('reporting_period')->pluck('reporting_period');

        $period = $request->query('period');
        if (! $period || ! $periods->contains($period)) {
            $period = $periods->first();
        }

        $state = PeriodWorkspaceTask::where('reporting_period', $period)
            ->get()->keyBy('task_key');

        $tasks = collect(self::TASKS)->map(function ($t) use ($state) {
            $row = $state->get($t['key']);
            return array_merge($t, [
                'status'       => $row->status ?? 'pending',
                'completed_by' => $row->completed_by ?? null,
                'completed_at' => optional($row->completed_at ?? null)?->format('d M Y H:i'),
                'href'         => $t['route'] ? route($t['route']) : null,
            ]);
        });

        $done = $tasks->where('status', 'done')->count();
        $user = $request->user();

        return Inertia::render('Workspace/Index', [
            'periods'   => $periods,
            'period'    => $period,
            'tasks'     => $tasks->values(),
            'progress'  => [
                'done'    => $done,
                'total'   => $tasks->count(),
                'percent' => $tasks->count() ? round($done * 100 / $tasks->count()) : 0,
            ],
            'me'        => ['name' => optional($user)->name, 'email' => optional($user)->email],
            'is_admin'  => $this->isAdmin($user),
        ]);
    }

    public function toggle(Request $request)
    {
        abort_unless($this->isAdmin($request->user()), 403, 'Only administrators can update the workspace.');

        $v = $request->validate([
            'period'   => 'required|string',
            'task_key' => 'required|string',
        ]);

        $task = PeriodWorkspaceTask::firstOrNew([
            'reporting_period' => $v['period'],
            'task_key'         => $v['task_key'],
        ]);

        if ($task->status === 'done') {
            $task->status = 'pending';
            $task->completed_by = null;
            $task->completed_at = null;
        } else {
            $task->status = 'done';
            $task->completed_by = optional($request->user())->name ?? 'admin';
            $task->completed_at = now();
        }
        $task->save();

        return back();
    }
}
