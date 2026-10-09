<?php

namespace App\Http\Controllers;

use App\Services\Scenario\ScenarioSetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Governance Centre, Scenario Sets (spec v4 section 15.9): the sets of each
 * period with their versions, rules, paths, back-test and sensitivity;
 * propose, approve (a second person), lock, new version.
 */
class ScenarioSetController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:eir.govern')->only(['seed', 'propose', 'approve', 'lock', 'version', 'update', 'addScenario', 'removeScenario']);
    }

    /** The shape of one scenario as the editor posts it (system audit of 9 October 2026, finding M5). */
    private const SCENARIO_RULES = [
        'name' => ['required', 'string', 'max:40'], 'weight' => ['required', 'numeric', 'min:0', 'max:100'], 'is_base' => ['nullable', 'boolean'],
        'anchored_to' => ['nullable', 'string', 'max:255'], 'calibration_note' => ['nullable', 'string', 'max:2000'], 'narrative' => ['nullable', 'string', 'max:4000'],
        'pd_multiplier' => ['nullable', 'numeric', 'gt:0', 'lte:10'],
        'shocks' => ['nullable', 'array'], 'shocks.*.statistic_code' => ['required', 'string', 'max:32'], 'shocks.*.year_offset' => ['nullable', 'integer', 'min:0', 'max:5'],
        'shocks.*.kind' => ['required', 'in:pct,abs,replace,mult'], 'shocks.*.value' => ['required', 'numeric'], 'shocks.*.note' => ['nullable', 'string', 'max:255'],
    ];

    public function index(Request $request, ScenarioSetService $service)
    {
        $period = (string) $request->query('period', DB::table('governed_scenario_sets')->max('reporting_period') ?? DB::table('loan_books')->max('reporting_period') ?? now()->format('Y-m'));
        $sets = DB::table('governed_scenario_sets as s')->leftJoin('users as p', 'p.id', '=', 's.proposed_by')->leftJoin('users as a', 'a.id', '=', 's.approved_by')
            ->where('s.reporting_period', $period)->orderByDesc('s.version')
            ->get(['s.*', 'p.name as proposer', 'a.name as approver'])->map(function ($s) use ($service) {
                $scenarios = DB::table('governed_scenarios')->where('set_id', $s->id)->orderBy('order_position')->get()->map(fn ($x) => (array) $x + ['shocks' => DB::table('scenario_shocks')->where('scenario_id', $x->id)->get()->map(fn ($sh) => (array) $sh)->all()])->all();
                try {
                    $paths = $service->paths((int) $s->id);
                } catch (Throwable) {
                    $paths = ['base' => [], 'scenarios' => []];
                }

                $userId = auth()->id();

                return ['id' => $s->id, 'version' => $s->version, 'name' => $s->name, 'status' => $s->status, 'narrative' => $s->narrative, 'source_vintage' => $s->source_vintage,
                    'proposer' => $s->proposer, 'proposed_at' => $s->proposed_at, 'approver' => $s->approver ?? $s->approver_label, 'approved_at' => $s->approved_at, 'locked_at' => $s->locked_at,
                    'supersedes_id' => $s->supersedes_id, 'version_reason' => $s->version_reason, 'validation' => json_decode($s->validation ?? '', true), 'backtest' => json_decode($s->backtest ?? '', true),
                    'sensitivity' => json_decode($s->sensitivity ?? '', true), 'scenarios' => $scenarios, 'paths' => $paths,
                    'overlays_at_approval' => json_decode($s->overlays_at_approval ?? '', true),
                    // the editor opens for a draft, or for a proposed set to its proposer (audit M5)
                    'editable' => $s->status === 'DRAFT' || ($s->status === 'PROPOSED' && ($s->proposed_by === null || (int) $s->proposed_by === (int) $userId))];
            });
        $overlays = DB::getSchemaBuilder()->hasTable('fli_overlays')
            ? DB::table('fli_overlays')->where('reporting_period', $period)->whereIn('status', ['PROPOSED', 'APPROVED'])->orderBy('id')->get(['id', 'scope', 'scope_value', 'adjustment', 'reason', 'status', 'expiry_period'])
            : collect();

        return Inertia::render('Governance/ScenarioSets', [
            'period' => $period, 'sets' => $sets, 'rules' => $service->rules($period), 'overlays' => $overlays,
            'periods' => DB::table('governed_scenario_sets')->distinct()->orderByDesc('reporting_period')->pluck('reporting_period'),
            'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false),
        ]);
    }

    /** The editor saves the head and every scenario with its shocks at once; scenarios without an id are added, ids in `remove` are dropped. */
    public function update(Request $request, int $set, ScenarioSetService $service)
    {
        $rules = ['name' => ['nullable', 'string', 'max:255'], 'narrative' => ['nullable', 'string', 'max:4000'], 'source_vintage' => ['nullable', 'string', 'max:255'],
            'scenarios' => ['nullable', 'array'], 'scenarios.*.id' => ['nullable', 'integer'], 'remove' => ['nullable', 'array'], 'remove.*' => ['integer']];
        foreach (self::SCENARIO_RULES as $k => $r) {
            $rules["scenarios.*.{$k}"] = $r;
        }
        $data = $request->validate($rules);

        try {
            $v = $service->updateProposed($set, $data, $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($v['ok'] ? 'success' : 'error', $v['ok'] ? "Set {$set} saved; it passes its rules." : "Set {$set} saved as a draft that does not yet pass its rules: " . implode('; ', $v['problems']));
    }

    public function addScenario(Request $request, int $set, ScenarioSetService $service)
    {
        $data = $request->validate(self::SCENARIO_RULES);

        return $this->act(fn () => $service->addScenario($set, $data, $request->user()->id), "Scenario '{$data['name']}' added to set {$set}.");
    }

    public function removeScenario(Request $request, int $set, int $scenario, ScenarioSetService $service)
    {
        return $this->act(fn () => $service->removeScenario($set, $scenario, $request->user()->id), "Scenario {$scenario} removed from set {$set}.");
    }

    public function seed(Request $request, ScenarioSetService $service)
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        try {
            $id = $service->seedFirstSet($data['period'], $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('scenario-sets.index', ['period' => $data['period']])->with('success', "Set {$id} proposed for {$data['period']}: the first set of spec 15.8, for Dr Thom to set the weights.");
    }

    public function propose(Request $request, int $set, ScenarioSetService $service)
    {
        return $this->act(fn () => $service->propose($set, $request->user()->id), "Set {$set} proposed.");
    }

    public function approve(Request $request, int $set, ScenarioSetService $service)
    {
        return $this->act(fn () => $service->approve($set, $request->user()->id), "Set {$set} approved; the back-test and sensitivity are stored with it.");
    }

    public function lock(Request $request, int $set, ScenarioSetService $service)
    {
        return $this->act(fn () => $service->lock($set, $request->user()->id), "Set {$set} locked.");
    }

    public function version(Request $request, int $set, ScenarioSetService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return $this->act(fn () => $service->newVersion($set, $data['reason'], $request->user()->id), "A new version of set {$set} was created as a draft.");
    }

    private function act(callable $fn, string $ok)
    {
        try {
            $fn();
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $ok);
    }
}
