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
        $this->middleware('permission:eir.govern')->only(['seed', 'propose', 'approve', 'lock', 'version']);
    }

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

                return ['id' => $s->id, 'version' => $s->version, 'name' => $s->name, 'status' => $s->status, 'narrative' => $s->narrative, 'source_vintage' => $s->source_vintage,
                    'proposer' => $s->proposer, 'proposed_at' => $s->proposed_at, 'approver' => $s->approver ?? $s->approver_label, 'approved_at' => $s->approved_at, 'locked_at' => $s->locked_at,
                    'supersedes_id' => $s->supersedes_id, 'version_reason' => $s->version_reason, 'validation' => json_decode($s->validation ?? '', true), 'backtest' => json_decode($s->backtest ?? '', true),
                    'sensitivity' => json_decode($s->sensitivity ?? '', true), 'scenarios' => $scenarios, 'paths' => $paths];
            });

        return Inertia::render('Governance/ScenarioSets', [
            'period' => $period, 'sets' => $sets, 'rules' => $service->rules($period),
            'periods' => DB::table('governed_scenario_sets')->distinct()->orderByDesc('reporting_period')->pluck('reporting_period'),
            'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false),
        ]);
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
