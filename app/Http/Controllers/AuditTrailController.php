<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Unified audit trail (contract Schedule 1 component: "Audit trail - logging
 * of key actions and changes").
 *
 * The platform writes to two stores:
 *  - activity_log  (spatie/laravel-activitylog) - model-level CRUD activity
 *    from ~18 models (clients, loans, users, tickets, ...).
 *  - audit_logs    (App\Services\AuditLoggerService) - module-level actions
 *    with old/new values (EIR rules & classification, internal grading,
 *    transition matrices, LGD).
 *
 * This page merges both into one filterable timeline via a UNION with
 * normalised columns, so nothing needs to be re-logged.
 */
class AuditTrailController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:settings']);
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:190'],
            'source' => ['nullable', 'in:activity,module'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        // Normalised shape: source, action, entity, entity_id, details,
        // user_id, created_at.
        $activity = DB::table('activity_log')->select([
            DB::raw("'activity' as source"),
            DB::raw('description as action'),
            DB::raw("COALESCE(subject_type, '') as entity"),
            DB::raw('subject_id as entity_id'),
            DB::raw('properties as details'),
            DB::raw('causer_id as user_id'),
            'created_at',
        ]);

        $module = DB::table('audit_logs')->select([
            DB::raw("'module' as source"),
            'action',
            DB::raw('entity_type as entity'),
            'entity_id',
            DB::raw("JSON_OBJECT('scope', scope, 'reporting_period', reporting_period, 'rows_affected', rows_affected, 'old', old_values, 'new', new_values) as details"),
            'user_id',
            'created_at',
        ]);

        $applyFilters = function ($query, bool $isActivity) use ($filters) {
            if (! empty($filters['search'])) {
                $s = '%' . $filters['search'] . '%';
                $query->where(function ($q) use ($s, $isActivity) {
                    if ($isActivity) {
                        $q->where('description', 'like', $s)->orWhere('subject_type', 'like', $s);
                    } else {
                        $q->where('action', 'like', $s)->orWhere('entity_type', 'like', $s);
                    }
                });
            }
            if (! empty($filters['user_id'])) {
                $query->where($isActivity ? 'causer_id' : 'user_id', $filters['user_id']);
            }
            if (! empty($filters['from'])) {
                $query->whereDate('created_at', '>=', $filters['from']);
            }
            if (! empty($filters['to'])) {
                $query->whereDate('created_at', '<=', $filters['to']);
            }

            return $query;
        };

        $activity = $applyFilters($activity, true);
        $module = $applyFilters($module, false);

        $source = $filters['source'] ?? null;
        if ($source === 'activity') {
            $union = $activity;
        } elseif ($source === 'module') {
            $union = $module;
        } else {
            $union = $activity->unionAll($module);
        }

        $perPage = 25;
        $page = max(1, (int) $request->input('page', 1));

        $rows = DB::query()->fromSub($union, 'trail')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        // Resolve user names in one query.
        $userIds = collect($rows->items())->pluck('user_id')->filter()->unique();
        $users = DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');

        $entries = collect($rows->items())->map(function ($row) use ($users) {
            return [
                'source' => $row->source,
                'action' => $row->action,
                'entity' => class_basename((string) $row->entity),
                'entity_id' => $row->entity_id,
                'details' => $row->details ? json_decode($row->details, true) : null,
                'user' => $row->user_id ? ($users[$row->user_id] ?? 'User #' . $row->user_id) : 'System',
                'created_at' => $row->created_at,
            ];
        });

        return Inertia::render('AuditTrail/Index', [
            'filters' => array_merge([
                'search' => null, 'source' => null, 'user_id' => null, 'from' => null, 'to' => null,
            ], $filters),
            'entries' => $entries,
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
                'per_page' => $rows->perPage(),
            ],
            'users' => DB::table('users')->orderBy('name')->get(['id', 'name']),
            'counts' => [
                'activity' => DB::table('activity_log')->count(),
                'module' => DB::table('audit_logs')->count(),
            ],
        ]);
    }
}
