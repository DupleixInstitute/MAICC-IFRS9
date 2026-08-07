<?php

namespace App\Http\Controllers;

use App\Models\PeriodWorkspaceTask;
use App\Models\User;
use App\Notifications\SystemEventNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * IFRS 9 period-close workspace.
 *
 * The checklist mixes two kinds of steps:
 *  - AUTO steps the system verifies itself against the database for the
 *    selected period (loan book imported, stages allocated, PD/LGD/FLI
 *    populated, ECL calculated, stress run). They can never be ticked by
 *    hand and always reflect the real data state.
 *  - MANUAL steps that need human judgement (report review, sign-off),
 *    tickable by administrators; ticking notifies the other admins.
 */
class WorkspaceController extends Controller
{
    /**
     * The canonical IFRS 9 close checklist.
     * auto = system-verified; manual rows are stored in period_workspace_tasks.
     */
    private const TASKS = [
        ['key' => 'import_loanbook', 'label' => 'Import the loan book for the period',         'route' => 'imports.index',                  'auto' => true],
        ['key' => 'segmentation',    'label' => 'Portfolio segmentation & sector tags',        'route' => 'portfolios.index',               'auto' => true],
        ['key' => 'staging',         'label' => 'Stage allocation (SICR rules applied)',       'route' => 'stageing-rules.index',           'auto' => true],
        ['key' => 'pd_model',        'label' => 'PD model applied to the loan book',           'route' => 'transition-matrices.index',      'auto' => true],
        ['key' => 'lgd_model',       'label' => 'LGD model applied to the loan book',          'route' => 'loss-given-default.index',       'auto' => true],
        ['key' => 'fli',             'label' => 'Forward-looking (macro) adjustment applied',  'route' => 'macro-forecast-weighted.index',  'auto' => true],
        ['key' => 'run_ecl',         'label' => 'ECL calculation run',                          'route' => 'expected-credit-loss.index',     'auto' => true],
        ['key' => 'review_reports',  'label' => 'Review IFRS 9 reports & data quality',        'route' => 'ifrs9-reports.index',            'auto' => false],
        ['key' => 'stress_test',     'label' => 'Stress / sensitivity scenarios run',           'route' => 'stress-testing.index',           'auto' => true],
        ['key' => 'signoff',         'label' => 'Management sign-off for the period',           'route' => null,                             'auto' => false],
    ];

    private function isAdmin(?User $u): bool
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

    /**
     * System verification for the auto steps, computed from the database for
     * the period. Returns [key => ['done' => bool, 'detail' => string]].
     */
    private function autoChecks(string $period): array
    {
        $lb = DB::table('loan_books')
            ->where('reporting_period', $period)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN loan_portfolio_id IS NOT NULL THEN 1 ELSE 0 END) as with_portfolio')
            ->selectRaw('SUM(CASE WHEN ifrs9stage_post_qualitative IS NOT NULL THEN 1 ELSE 0 END) as with_stage')
            ->selectRaw('SUM(CASE WHEN pd_value IS NOT NULL THEN 1 ELSE 0 END) as with_pd')
            ->selectRaw('SUM(CASE WHEN lgd_value IS NOT NULL THEN 1 ELSE 0 END) as with_lgd')
            ->selectRaw('SUM(CASE WHEN pd_post_fli IS NOT NULL THEN 1 ELSE 0 END) as with_fli')
            ->first();

        $eclRows = DB::table('expected_credit_loss')->where('reporting_period', $period)->count();
        $stressRuns = DB::table('stress_scenarios')->where('reporting_period', $period)->count();

        $total = (int) ($lb->total ?? 0);
        $n = fn ($v) => number_format((int) $v);

        return [
            'import_loanbook' => [
                'done' => $total > 0,
                'detail' => $total > 0 ? $n($total) . ' loans imported' : 'No loan book rows for this period',
            ],
            'segmentation' => [
                'done' => $total > 0 && (int) $lb->with_portfolio === $total,
                'detail' => $total > 0 ? $n($lb->with_portfolio) . ' of ' . $n($total) . ' loans assigned to a portfolio' : 'Awaiting loan book',
            ],
            'staging' => [
                'done' => $total > 0 && (int) $lb->with_stage === $total,
                'detail' => $total > 0 ? $n($lb->with_stage) . ' of ' . $n($total) . ' loans staged' : 'Awaiting loan book',
            ],
            'pd_model' => [
                'done' => $total > 0 && (int) $lb->with_pd > 0,
                'detail' => $total > 0 ? $n($lb->with_pd) . ' loans carry a PD' : 'Awaiting loan book',
            ],
            'lgd_model' => [
                'done' => $total > 0 && (int) $lb->with_lgd > 0,
                'detail' => $total > 0 ? $n($lb->with_lgd) . ' loans carry an LGD' : 'Awaiting loan book',
            ],
            'fli' => [
                'done' => $total > 0 && (int) $lb->with_fli > 0,
                'detail' => $total > 0 ? $n($lb->with_fli) . ' loans carry a post-FLI PD' : 'Awaiting loan book',
            ],
            'run_ecl' => [
                'done' => $eclRows > 0,
                'detail' => $eclRows > 0 ? $n($eclRows) . ' ECL result rows' : 'ECL not calculated for this period',
            ],
            'stress_test' => [
                'done' => $stressRuns > 0,
                'detail' => $stressRuns > 0 ? $n($stressRuns) . ' saved scenario run(s)' : 'No saved stress scenarios',
            ],
        ];
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
        $checks = $period ? $this->autoChecks($period) : [];

        $tasks = collect(self::TASKS)->map(function ($t) use ($state, $checks) {
            if ($t['auto']) {
                $check = $checks[$t['key']] ?? ['done' => false, 'detail' => null];

                return array_merge($t, [
                    'status' => $check['done'] ? 'done' : 'pending',
                    'detail' => $check['detail'],
                    'completed_by' => 'System verified',
                    'completed_at' => null,
                    'href' => $t['route'] ? route($t['route']) : null,
                ]);
            }

            $row = $state->get($t['key']);

            return array_merge($t, [
                'status' => $row->status ?? 'pending',
                'detail' => null,
                'completed_by' => $row->completed_by ?? null,
                'completed_at' => optional($row->completed_at ?? null)?->format('d M Y H:i'),
                'href' => $t['route'] ? route($t['route']) : null,
            ]);
        });

        $done = $tasks->where('status', 'done')->count();
        $user = $request->user();

        $messages = \App\Models\WorkspaceMessage::query()
            ->where(fn ($q) => $q->where('reporting_period', $period)->orWhereNull('reporting_period'))
            ->orderByDesc('id')->limit(50)->get()
            ->map(fn ($m) => [
                'user_name' => $m->user_name,
                'body' => $m->body,
                'when' => $m->created_at?->diffForHumans(),
                'mine' => $m->user_id === optional($user)->id,
            ])->reverse()->values();

        return Inertia::render('Workspace/Index', [
            'periods' => $periods,
            'period' => $period,
            'tasks' => $tasks->values(),
            'progress' => [
                'done' => $done,
                'total' => $tasks->count(),
                'percent' => $tasks->count() ? round($done * 100 / $tasks->count()) : 0,
            ],
            'outstanding' => $tasks->where('status', 'pending')->pluck('label')->values(),
            'me' => ['name' => optional($user)->name, 'email' => optional($user)->email],
            'is_admin' => $this->isAdmin($user),
            'messages' => $messages,
        ]);
    }

    public function postMessage(Request $request)
    {
        $v = $request->validate([
            'period' => 'nullable|string',
            'body' => 'required|string|max:2000',
        ]);

        \App\Models\WorkspaceMessage::create([
            'reporting_period' => $v['period'] ?? null,
            'user_id' => optional($request->user())->id,
            'user_name' => optional($request->user())->name ?? 'User',
            'body' => trim($v['body']),
        ]);

        return back();
    }

    public function toggle(Request $request)
    {
        abort_unless($this->isAdmin($request->user()), 403, 'Only administrators can update the workspace.');

        $v = $request->validate([
            'period' => 'required|string',
            'task_key' => 'required|string',
        ]);

        $definition = collect(self::TASKS)->firstWhere('key', $v['task_key']);
        abort_unless((bool) $definition, 422, 'Unknown checklist step.');
        // System-verified steps reflect the database and cannot be hand-ticked.
        abort_if($definition['auto'], 422, 'This step is verified automatically by the system.');

        $task = PeriodWorkspaceTask::firstOrNew([
            'reporting_period' => $v['period'],
            'task_key' => $v['task_key'],
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

        // Tell the other administrators through the bell.
        $actor = $request->user();
        $verb = $task->status === 'done' ? 'completed' : 'reopened';
        User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->where('id', '!=', optional($actor)->id)
            ->get()
            ->each(fn ($admin) => $admin->notify(new SystemEventNotification(
                'workspace',
                ($actor->name ?? 'An administrator') . ' ' . $verb . ' "' . $definition['label'] . '" for ' . $v['period'] . '.',
                route('workspace.index', ['period' => $v['period']])
            )));

        return back()->with('success', 'Step "' . $definition['label'] . '" ' . $verb . ' for ' . $v['period'] . '.');
    }
}
