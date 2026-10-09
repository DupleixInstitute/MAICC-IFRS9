<?php

namespace App\Services\Scenario;

use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * The scenario set as a governed object (spec v4 sections 15.4 to 15.8).
 *
 * One set per reporting period, versioned: draft, proposed, approved by a
 * second person, locked when the period's ECL is locked; a change after
 * that is a new version with a reason and the locked version stays attached
 * to the period. The rules are Governance Centre settings: the minimum
 * number of scenarios, the floor on the base weight, the ceiling on any
 * weight, whether a downside needs a calibration note, whether an overlay
 * needs an approved set. Every scenario but the base is a set of shocks on
 * the base path (percentage, absolute, replacement, multiplier), so a
 * downside is an auditable transformation of the base. The back-test and
 * the sensitivity are computed and stored with the set. The proposer edits
 * the set (weights, scenarios, shocks) until it is approved; after that a
 * change is a new version. The manual-overlay register is tied to the set:
 * approval records the overlays in force and the lock refuses while one is
 * still proposed (system audit of 9 October 2026, findings M4 and M5).
 */
class ScenarioSetService
{
    public const BOOTSTRAP_LABEL = 'System Bootstrap (automated data-readiness, not a MAIIC approval)';

    public function __construct(private GovernanceService $governance)
    {
    }

    // ----- the rules --------------------------------------------------------

    /** @return array{min_count:int,base_floor:float,single_ceiling:float,note_required:bool,overlay_needs_set:bool,weighting:string} */
    public function rules(?string $period = null): array
    {
        $asOf = $period ? CarbonImmutable::parse($period . '-01')->endOfMonth() : null;
        $g = function (string $key, string $default) use ($asOf) {
            try {
                return $this->governance->get($key, $asOf);
            } catch (Throwable) {
                return $default;
            }
        };
        $num = fn (string $v, float $d) => preg_match('/(\d+(?:\.\d+)?)/', $v, $m) ? (float) $m[1] : $d;
        $bounds = $g('scenario_weight_bounds', 'Base at least 40 percent; any single weight at most 60');
        preg_match_all('/(\d+(?:\.\d+)?)/', $bounds, $bm);

        return [
            'min_count' => (int) $num($g('scenario_minimum_count', '3'), 3),
            'base_floor' => (float) ($bm[1][0] ?? 40), 'single_ceiling' => (float) ($bm[1][1] ?? 60),
            'note_required' => stripos($g('scenario_calibration_note', 'Required'), 'required') !== false && stripos($g('scenario_calibration_note', 'Required'), 'not') === false,
            'overlay_needs_set' => stripos($g('overlay_requires_approved_set', 'Required'), 'required') !== false,
            'weighting' => $g('scenario_weighting_method', 'Weight the ECL across scenarios'),
        ];
    }

    /** @return array{ok:bool,problems:list<string>,weights_sum:float} */
    public function validate(int $setId): array
    {
        $set = DB::table('governed_scenario_sets')->where('id', $setId)->first() ?? throw new RuntimeException("No scenario set {$setId}.");
        $rules = $this->rules($set->reporting_period);
        $scenarios = DB::table('governed_scenarios')->where('set_id', $setId)->orderBy('order_position')->get();
        $problems = [];
        $sum = round((float) $scenarios->sum('weight'), 2);
        if ($scenarios->count() < $rules['min_count']) {
            $problems[] = "{$scenarios->count()} scenarios; the rule asks for at least {$rules['min_count']}";
        }
        if (abs($sum - 100) > 0.005) {
            $problems[] = "weights sum to {$sum}, not 100";
        }
        $base = $scenarios->firstWhere('is_base', 1);
        if ($base === null) {
            $problems[] = 'no base scenario';
        } elseif ((float) $base->weight < $rules['base_floor']) {
            $problems[] = "base weight {$base->weight} is below the floor of {$rules['base_floor']}";
        }
        foreach ($scenarios as $s) {
            if ((float) $s->weight > $rules['single_ceiling']) {
                $problems[] = "{$s->name} weight {$s->weight} is above the ceiling of {$rules['single_ceiling']}";
            }
            if ($rules['note_required'] && ! $s->is_base && (float) ($s->pd_multiplier ?? 1) >= 1 && trim((string) $s->calibration_note) === '') {
                $problems[] = "{$s->name} has no calibration note";
            }
            if (! $s->is_base && ! DB::table('scenario_shocks')->where('scenario_id', $s->id)->exists()) {
                $problems[] = "{$s->name} has no shock on the base: it would be the base under another name";
            }
        }
        // The overlay tie of spec 15.7: overlays of the period still proposed
        // do not stop a proposal or an approval, but lock() refuses while any
        // remain (system audit of 9 October 2026, finding M4). Their ids are
        // recorded here so the screen can say what is holding the lock.
        $pending = $this->proposedOverlayIds($set->reporting_period);
        $result = ['ok' => $problems === [], 'problems' => $problems, 'weights_sum' => $sum, 'rules' => $rules, 'overlays_pending' => $pending];
        DB::table('governed_scenario_sets')->where('id', $setId)->update(['validation' => json_encode($result), 'updated_at' => now()]);

        return $result;
    }

    // ----- the lifecycle ------------------------------------------------------

    public function create(string $period, string $name, array $scenarios, ?int $userId, ?string $narrative = null, ?string $sourceVintage = null): int
    {
        return DB::transaction(function () use ($period, $name, $scenarios, $userId, $narrative, $sourceVintage) {
            $version = (int) DB::table('governed_scenario_sets')->where('reporting_period', $period)->max('version') + 1;
            $setId = (int) DB::table('governed_scenario_sets')->insertGetId(['reporting_period' => $period, 'name' => $name, 'version' => $version, 'status' => 'DRAFT', 'narrative' => $narrative, 'source_vintage' => $sourceVintage, 'created_at' => now(), 'updated_at' => now()]);
            foreach (array_values($scenarios) as $i => $s) {
                $scenarioId = (int) DB::table('governed_scenarios')->insertGetId(['set_id' => $setId, 'name' => $s['name'], 'weight' => $s['weight'], 'is_base' => (bool) ($s['is_base'] ?? false), 'narrative' => $s['narrative'] ?? null,
                    'anchored_to' => $s['anchored_to'] ?? null, 'calibration_note' => $s['calibration_note'] ?? null, 'pd_multiplier' => $s['pd_multiplier'] ?? null, 'order_position' => $i, 'created_at' => now(), 'updated_at' => now()]);
                foreach ($s['shocks'] ?? [] as $sh) {
                    DB::table('scenario_shocks')->insert(['scenario_id' => $scenarioId, 'statistic_code' => $sh['statistic_code'], 'year_offset' => $sh['year_offset'] ?? 0, 'kind' => $sh['kind'], 'value' => $sh['value'], 'note' => $sh['note'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            AuditLoggerService::log('Scenario Set Created', 'governed_scenario_sets', $setId, ['new_values' => ['period' => $period, 'name' => $name, 'version' => $version, 'scenarios' => count($scenarios)], 'meta' => ['user' => $userId]]);

            return $setId;
        });
    }

    public function propose(int $setId, ?int $userId): array
    {
        $v = $this->validate($setId);
        if (! $v['ok']) {
            throw new RuntimeException('The set cannot be proposed: ' . implode('; ', $v['problems']));
        }
        DB::table('governed_scenario_sets')->where('id', $setId)->whereIn('status', ['DRAFT', 'PROPOSED'])->update(['status' => 'PROPOSED', 'proposed_by' => $userId, 'proposed_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('Scenario Set Proposed', 'governed_scenario_sets', $setId, ['meta' => ['user' => $userId]]);

        return $v;
    }

    public function approve(int $setId, ?int $approverId, ?string $label = null): void
    {
        $set = DB::table('governed_scenario_sets')->where('id', $setId)->first() ?? throw new RuntimeException("No scenario set {$setId}.");
        if ($set->status !== 'PROPOSED') {
            throw new RuntimeException('Only a proposed set can be approved.');
        }
        if ($label !== \App\Services\Ebanker\LoanBookBuildService::BOOTSTRAP_LABEL && $approverId !== null && (int) $set->proposed_by === $approverId) {
            throw new RuntimeException('Maker-checker: the approver must be a different person from the proposer.');
        }
        $v = $this->validate($setId);
        if (! $v['ok']) {
            throw new RuntimeException('The set no longer passes its rules: ' . implode('; ', $v['problems']));
        }
        // The overlays in force at the moment of approval are written on the
        // set (spec 15.7: an overlay sits on an approved set and is shown
        // beside it; system audit of 9 October 2026, finding M4).
        $overlays = $this->overlaysInForce($set->reporting_period);
        DB::transaction(function () use ($setId, $set, $approverId, $label, $overlays) {
            DB::table('governed_scenario_sets')->where('reporting_period', $set->reporting_period)->where('id', '!=', $setId)->whereIn('status', ['APPROVED'])->update(['status' => 'SUPERSEDED', 'updated_at' => now()]);
            $update = ['status' => 'APPROVED', 'approved_by' => $approverId, 'approver_label' => $label, 'approved_at' => now(), 'updated_at' => now()];
            if (DB::getSchemaBuilder()->hasColumn('governed_scenario_sets', 'overlays_at_approval')) {
                $update['overlays_at_approval'] = json_encode(['approved_at' => now()->toDateTimeString(), 'overlays' => $overlays]);
            }
            DB::table('governed_scenario_sets')->where('id', $setId)->update($update);
            $this->backtest($setId);
            $this->sensitivity($setId);
        });
        AuditLoggerService::log('Scenario Set Approved', 'governed_scenario_sets', $setId, ['meta' => ['approved_by' => $approverId, 'label' => $label, 'overlays_in_force' => array_column($overlays, 'id')]]);
    }

    /**
     * A set locks with the period's ECL. It refuses while any overlay of the
     * period is still proposed (spec 15.7; system audit of 9 October 2026,
     * finding M4): the lock would otherwise fix an ECL that judgement nobody
     * has approved is about to change.
     *
     * @throws OverlayPendingException
     */
    public function lock(int $setId, ?int $userId): void
    {
        $set = DB::table('governed_scenario_sets')->where('id', $setId)->first() ?? throw new RuntimeException("No scenario set {$setId}.");
        if ($set->status !== 'APPROVED') {
            throw new RuntimeException('Only an approved set can be locked.');
        }
        $pending = $this->proposedOverlayIds($set->reporting_period);
        if ($pending !== []) {
            throw OverlayPendingException::forSet($setId, $set->reporting_period, $pending);
        }
        DB::table('governed_scenario_sets')->where('id', $setId)->update(['status' => 'LOCKED', 'locked_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('Scenario Set Locked', 'governed_scenario_sets', $setId, ['meta' => ['user' => $userId]]);
    }

    // ----- the editor (system audit of 9 October 2026, finding M5) -----------

    /**
     * The proposer changes a set that is not yet approved: the name and
     * narrative, the weights, the scenarios' notes and shocks, scenarios
     * added or removed. Nothing in an approved, locked or superseded set
     * can be changed; that is a new version. A proposed set must still pass
     * every rule of spec 15.6 after the change, or the change is refused
     * with the reason and nothing is written; a draft may be left failing,
     * since propose() refuses it until it passes.
     *
     * @param array{name?:string,narrative?:?string,source_vintage?:?string,scenarios?:list<array>,remove?:list<int>} $changes
     *   each scenario: ['id' => int|null, 'name', 'weight', 'is_base', 'anchored_to', 'calibration_note', 'pd_multiplier', 'narrative', 'shocks' => list<array{statistic_code,year_offset,kind,value,note}>];
     *   a scenario without an id is added; a 'shocks' key present replaces that scenario's shock list.
     * @return array the validation result after the change
     */
    public function updateProposed(int $setId, array $changes, ?int $userId): array
    {
        $set = $this->editable($setId, $userId);
        $before = $this->snapshot($setId);
        $result = DB::transaction(function () use ($setId, $set, $changes) {
            $head = array_filter(['name' => $changes['name'] ?? null, 'narrative' => $changes['narrative'] ?? null, 'source_vintage' => $changes['source_vintage'] ?? null], fn ($v) => $v !== null);
            if ($head !== []) {
                if (isset($head['name']) && trim((string) $head['name']) === '') {
                    throw new RuntimeException('The set needs a name.');
                }
                DB::table('governed_scenario_sets')->where('id', $setId)->update($head + ['updated_at' => now()]);
            }
            foreach ($changes['remove'] ?? [] as $removeId) {
                $this->deleteScenario($setId, (int) $removeId);
            }
            foreach ($changes['scenarios'] ?? [] as $s) {
                $this->writeScenario($setId, $s);
            }
            $v = $this->validate($setId);
            if ($set->status === 'PROPOSED' && ! $v['ok']) {
                throw new RuntimeException('The change is refused, the set would no longer pass its rules: ' . implode('; ', $v['problems']));
            }

            return $v;
        });
        AuditLoggerService::log('Scenario Set Edited', 'governed_scenario_sets', $setId, ['reporting_period' => $set->reporting_period, 'old_values' => $before, 'new_values' => $this->snapshot($setId), 'meta' => ['user' => $userId, 'ok' => $result['ok'], 'problems' => $result['problems']]]);

        return $result;
    }

    /** One scenario added to a set not yet approved; the new scenario's id. */
    public function addScenario(int $setId, array $scenario, ?int $userId): int
    {
        unset($scenario['id']);
        $this->updateProposed($setId, ['scenarios' => [$scenario]], $userId);

        return (int) DB::table('governed_scenarios')->where('set_id', $setId)->where('name', $scenario['name'])->orderByDesc('id')->value('id');
    }

    public function removeScenario(int $setId, int $scenarioId, ?int $userId): array
    {
        return $this->updateProposed($setId, ['remove' => [$scenarioId]], $userId);
    }

    private function editable(int $setId, ?int $userId): object
    {
        $set = DB::table('governed_scenario_sets')->where('id', $setId)->first() ?? throw new RuntimeException("No scenario set {$setId}.");
        if (! in_array($set->status, ['DRAFT', 'PROPOSED'], true)) {
            throw new RuntimeException("Set {$setId} is {$set->status} and cannot be changed; a change after approval is a new version with a reason.");
        }
        if ($set->status === 'PROPOSED' && $userId !== null && $set->proposed_by !== null && (int) $set->proposed_by !== $userId) {
            throw new RuntimeException('Only the proposer may change a proposed set; a second person approves it or asks for a new version.');
        }

        return $set;
    }

    private function writeScenario(int $setId, array $s): void
    {
        $id = isset($s['id']) ? (int) $s['id'] : null;
        $existing = $id ? DB::table('governed_scenarios')->where('set_id', $setId)->where('id', $id)->first() : null;
        if ($id && $existing === null) {
            throw new RuntimeException("Scenario {$id} is not in set {$setId}.");
        }
        $fields = [];
        foreach (['name', 'weight', 'is_base', 'narrative', 'anchored_to', 'calibration_note', 'pd_multiplier'] as $k) {
            if (array_key_exists($k, $s)) {
                $fields[$k] = $s[$k];
            }
        }
        if (array_key_exists('name', $fields) && trim((string) $fields['name']) === '') {
            throw new RuntimeException('A scenario needs a name.');
        }
        if (array_key_exists('weight', $fields)) {
            if (! is_numeric($fields['weight']) || (float) $fields['weight'] < 0) {
                throw new RuntimeException('A weight is a percentage from 0 to 100.');
            }
            $fields['weight'] = round((float) $fields['weight'], 2);
        }
        if (array_key_exists('pd_multiplier', $fields) && $fields['pd_multiplier'] !== null && (! is_numeric($fields['pd_multiplier']) || (float) $fields['pd_multiplier'] <= 0)) {
            throw new RuntimeException('A PD multiplier is a positive number.');
        }
        if (array_key_exists('is_base', $fields)) {
            $fields['is_base'] = (bool) $fields['is_base'];
            if ($fields['is_base']) {
                // one base per set: the base is the path the shocks transform
                DB::table('governed_scenarios')->where('set_id', $setId)->when($id, fn ($q) => $q->where('id', '!=', $id))->update(['is_base' => false, 'updated_at' => now()]);
            }
        }
        if ($existing === null) {
            if (! isset($fields['name'])) {
                throw new RuntimeException('A new scenario needs a name.');
            }
            $fields += ['weight' => 0, 'is_base' => false];
            $fields['order_position'] = (int) DB::table('governed_scenarios')->where('set_id', $setId)->max('order_position') + 1;
            $id = (int) DB::table('governed_scenarios')->insertGetId($fields + ['set_id' => $setId, 'created_at' => now(), 'updated_at' => now()]);
        } elseif ($fields !== []) {
            DB::table('governed_scenarios')->where('id', $id)->update($fields + ['updated_at' => now()]);
        }
        if (array_key_exists('shocks', $s)) {
            DB::table('scenario_shocks')->where('scenario_id', $id)->delete();
            $seen = [];
            foreach ((array) $s['shocks'] as $sh) {
                $code = strtoupper(trim((string) ($sh['statistic_code'] ?? '')));
                $kind = (string) ($sh['kind'] ?? '');
                $offset = (int) ($sh['year_offset'] ?? 0);
                if ($code === '' || ! in_array($kind, ['pct', 'abs', 'replace', 'mult'], true) || ! is_numeric($sh['value'] ?? null)) {
                    throw new RuntimeException('A shock names a series, a kind (pct, abs, replace or mult) and a numeric value.');
                }
                if ($offset < 0 || $offset > 5) {
                    throw new RuntimeException('A shock\'s year offset is 0 (the first forecast year) to 5.');
                }
                if (isset($seen["{$code}|{$offset}"])) {
                    throw new RuntimeException("Two shocks on {$code} for year offset {$offset}: one series, one offset, one shock.");
                }
                $seen["{$code}|{$offset}"] = true;
                DB::table('scenario_shocks')->insert(['scenario_id' => $id, 'statistic_code' => $code, 'year_offset' => $offset, 'kind' => $kind, 'value' => (float) $sh['value'], 'note' => isset($sh['note']) && trim((string) $sh['note']) !== '' ? trim((string) $sh['note']) : null, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function deleteScenario(int $setId, int $scenarioId): void
    {
        $s = DB::table('governed_scenarios')->where('set_id', $setId)->where('id', $scenarioId)->first() ?? throw new RuntimeException("Scenario {$scenarioId} is not in set {$setId}.");
        if ($s->is_base) {
            throw new RuntimeException('The base scenario cannot be removed: every other scenario is a shock on it.');
        }
        DB::table('scenario_shocks')->where('scenario_id', $scenarioId)->delete();
        DB::table('governed_scenarios')->where('id', $scenarioId)->delete();
    }

    /** The set's head and scenarios as the audit log records them before and after an edit. */
    private function snapshot(int $setId): array
    {
        $set = DB::table('governed_scenario_sets')->where('id', $setId)->first(['name', 'narrative', 'source_vintage']);
        $scenarios = [];
        foreach (DB::table('governed_scenarios')->where('set_id', $setId)->orderBy('order_position')->get() as $s) {
            $scenarios[$s->name] = ['weight' => (float) $s->weight, 'is_base' => (bool) $s->is_base, 'pd_multiplier' => $s->pd_multiplier, 'calibration_note' => $s->calibration_note,
                'shocks' => DB::table('scenario_shocks')->where('scenario_id', $s->id)->orderBy('statistic_code')->get()->map(fn ($x) => "{$x->statistic_code}[{$x->year_offset}] {$x->kind} {$x->value}")->all()];
        }

        return ['set' => (array) $set, 'scenarios' => $scenarios];
    }

    // ----- the overlay tie (spec 15.7) --------------------------------------------

    /** @return list<int> the ids of the period's overlays still proposed */
    public function proposedOverlayIds(string $period): array
    {
        if (! DB::getSchemaBuilder()->hasTable('fli_overlays')) {
            return [];
        }

        return DB::table('fli_overlays')->where('reporting_period', $period)->where('status', 'PROPOSED')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return list<array> the approved, unexpired overlays for the period, as recorded on the set at approval */
    public function overlaysInForce(string $period): array
    {
        if (! DB::getSchemaBuilder()->hasTable('fli_overlays')) {
            return [];
        }

        return DB::table('fli_overlays')->where('status', 'APPROVED')->where('reporting_period', '<=', $period)->where('expiry_period', '>=', $period)->orderBy('id')
            ->get(['id', 'scope', 'scope_value', 'adjustment', 'reason', 'owner_id', 'expiry_period', 'approved_by', 'approver_label'])->map(fn ($o) => array_merge((array) $o, ['adjustment' => (float) $o->adjustment, 'id' => (int) $o->id]))->all();
    }

    /** A change after lock: a new version, the locked one stays attached to the period. */
    public function newVersion(int $setId, string $reason, ?int $userId): int
    {
        $set = DB::table('governed_scenario_sets')->where('id', $setId)->first() ?? throw new RuntimeException("No scenario set {$setId}.");
        $scenarios = [];
        foreach (DB::table('governed_scenarios')->where('set_id', $setId)->orderBy('order_position')->get() as $s) {
            $scenarios[] = (array) $s + ['shocks' => DB::table('scenario_shocks')->where('scenario_id', $s->id)->get()->map(fn ($x) => (array) $x)->all()];
        }
        $newId = $this->create($set->reporting_period, $set->name, $scenarios, $userId, $set->narrative, $set->source_vintage);
        DB::table('governed_scenario_sets')->where('id', $newId)->update(['supersedes_id' => $setId, 'version_reason' => $reason]);

        return $newId;
    }

    // ----- the paths, the back-test and the sensitivity ----------------------

    /** The base path per series (annual, from macro_series) and each scenario's shocked path. */
    public function paths(int $setId): array
    {
        $set = DB::table('governed_scenario_sets')->where('id', $setId)->first();
        $year = (int) substr($set->reporting_period, 0, 4);
        $codes = DB::table('scenario_shocks')->join('governed_scenarios', 'governed_scenarios.id', '=', 'scenario_shocks.scenario_id')->where('set_id', $setId)->distinct()->pluck('statistic_code')->all();
        $codes = array_values(array_unique(array_merge(['GDP_GROWTH', 'CPI', 'MWK_USD', 'LENDING_RATE', 'PLR', 'AGRI_GROWTH'], $codes)));
        $base = [];
        foreach ($codes as $code) {
            for ($o = 0; $o <= 2; $o++) {
                $v = DB::getSchemaBuilder()->hasTable('macro_series') ? DB::table('macro_series')->where('statistic_code', $code)->where('observation_period', 'like', ($year + $o) . '%')->orderByDesc('observation_period')->value('value') : null;
                $v ??= DB::getSchemaBuilder()->hasTable('macro_series') ? DB::table('macro_series')->where('statistic_code', $code)->orderByDesc('observation_period')->value('value') : null;
                $base[$code][$o] = $v !== null ? (float) $v : null;
            }
        }
        $out = ['base' => $base, 'scenarios' => []];
        foreach (DB::table('governed_scenarios')->where('set_id', $setId)->orderBy('order_position')->get() as $s) {
            $path = $base;
            foreach (DB::table('scenario_shocks')->where('scenario_id', $s->id)->get() as $sh) {
                $b = $path[$sh->statistic_code][$sh->year_offset] ?? null;
                if ($b === null && $sh->kind !== 'replace') {
                    continue;
                }
                $path[$sh->statistic_code][$sh->year_offset] = match ($sh->kind) {
                    'pct' => $b * (1 + (float) $sh->value / 100), 'abs' => $b + (float) $sh->value, 'replace' => (float) $sh->value, 'mult' => $b * (float) $sh->value, default => $b,
                };
            }
            $out['scenarios'][$s->name] = ['weight' => (float) $s->weight, 'is_base' => (bool) $s->is_base, 'path' => $path];
        }

        return $out;
    }

    /** The previous set's base path for the period against the actual now known, per series. */
    public function backtest(int $setId): array
    {
        $set = DB::table('governed_scenario_sets')->where('id', $setId)->first();
        $prev = DB::table('governed_scenario_sets')->where('reporting_period', '<', $set->reporting_period)->whereIn('status', ['APPROVED', 'LOCKED', 'SUPERSEDED'])->orderByDesc('reporting_period')->orderByDesc('version')->first();
        $rows = [];
        $flag = false;
        if ($prev !== null) {
            $prevPaths = $this->paths((int) $prev->id);
            $year = (int) substr($set->reporting_period, 0, 4);
            foreach ($prevPaths['base'] as $code => $offsets) {
                $predicted = $offsets[0] ?? null;
                $actual = DB::table('macro_series')->where('statistic_code', $code)->where('value_type', 'actual')->where('observation_period', 'like', $year . '%')->orderByDesc('observation_period')->value('value');
                if ($predicted === null || $actual === null) {
                    continue;
                }
                $range = array_map(fn ($s) => $s['path'][$code][0] ?? $predicted, $prevPaths['scenarios']);
                $inRange = (float) $actual >= min($range) - 1e-9 && (float) $actual <= max($range) + 1e-9;
                $flag = $flag || ! $inRange;
                $rows[] = ['series' => $code, 'predicted_base' => round($predicted, 4), 'actual' => round((float) $actual, 4), 'miss' => round((float) $actual - $predicted, 4), 'scenario_range' => [round(min($range), 4), round(max($range), 4)], 'within_range' => $inRange];
            }
        }
        $result = ['previous_set' => $prev?->id, 'rows' => $rows, 'review_weights' => $flag, 'note' => $prev === null ? 'no earlier set to back-test against' : ($flag ? 'an actual fell outside the previous set\'s scenario range: review the weights before the next set' : 'every actual fell within the previous set\'s scenario range')];
        DB::table('governed_scenario_sets')->where('id', $setId)->update(['backtest' => json_encode($result), 'updated_at' => now()]);

        return $result;
    }

    /**
     * The stage PD the ECL engine measures from a twelve-month PD: Stage 1
     * over the shorter of twelve months and the remaining life, Stage 2 over
     * the lifetime, Stage 3 at 1. The sensitivity and the overlay register's
     * ECL line both read it, so one rule moves the PD in both places. The
     * row needs `stage` and `remaining_tenor` (months).
     */
    public static function stagePd(object $l, float $pd12): float
    {
        $pd12 = max(0.0, min(1.0, $pd12));
        $months = $l->remaining_tenor === null || (float) $l->remaining_tenor < 1 ? 12.0 : (float) $l->remaining_tenor;

        return match ((string) $l->stage) {
            '3' => 1.0,
            '2' => 1 - pow(1 - $pd12, $months / 12),
            default => 1 - pow(1 - $pd12, min(12.0, $months) / 12),
        };
    }

    /**
     * The ECL under each scenario, with 100 percent weight on each, and with ten
     * points moved from the base to the downside and to the upside. Until the
     * chain runs per scenario, each scenario's PD is the pre-FLI PD times its
     * multiplier, floored and capped; Stage 3 is 100 percent.
     */
    public function sensitivity(int $setId): array
    {
        $set = DB::table('governed_scenario_sets')->where('id', $setId)->first();
        $scenarios = DB::table('governed_scenarios')->where('set_id', $setId)->orderBy('order_position')->get();
        // The ECL per scenario, from the per-scenario PD the route wrote on the
        // loan (fli_by_scenario) when the chain has run for this set, else the
        // typed multiplier on the pre-FLI PD; on the ECL engine's own basis:
        // the stage the staging engine measured, lifetime PD for Stage 2 over
        // the remaining months, Stage 3 at 1, EAD = carrying + commitments x
        // utilisation. One weighting, the set's (spec 15.5; audit H3, M9).
        $loans = DB::table('loan_books')->where('reporting_period', $set->reporting_period)
            ->selectRaw('coalesce(ifrs9stage_post_qualitative, calculated_ifrs9_stage, ifrs9stage_pre_qualitative) as stage, coalesce(pd_prefli, pd_value, `12m_pd`) as pd_prefli, pd_post_fli, ecl_value, lgd_value, remaining_tenor, carrying_amount, commitments, facility_utilisation_rate, fli_by_scenario, fli_set_id')
            ->whereRaw('coalesce(pd_prefli, pd_value, `12m_pd`) is not null')->get();
        $names = $scenarios->pluck('name')->all();
        $fromChain = $loans->isNotEmpty() && $loans->every(function ($l) use ($setId, $names) {
            $by = $l->fli_by_scenario !== null ? (json_decode((string) $l->fli_by_scenario, true) ?: []) : [];
            return (int) $l->fli_set_id === $setId && ($by === [] ? (string) $l->stage === '3' : array_diff($names, array_keys($by)) === []);
        });
        $stagePd = fn (object $l, float $pd12): float => self::stagePd($l, $pd12);
        // Each loan's booked ECL (ecl_value, whichever engine wrote it) scaled by
        // the ratio of the scenario's stage PD to the booked PD's stage PD, so the
        // base scenario reconciles to the allowance exactly and the others move
        // with the PD alone; a loan with no booked ECL is measured directly.
        $eclUnder = function (string $name, float $mult) use ($loans, $fromChain, $stagePd): float {
            $ecl = 0.0;
            foreach ($loans as $l) {
                $ead = (float) ($l->carrying_amount ?? 0) + (float) ($l->commitments ?? 0) * (float) ($l->facility_utilisation_rate ?? 1);
                $lgd = $l->lgd_value !== null ? (float) $l->lgd_value : 0.45;
                $by = $fromChain ? (json_decode((string) $l->fli_by_scenario, true) ?: []) : [];
                $booked12 = (float) ($l->pd_post_fli ?? $l->pd_prefli);
                $pd12 = isset($by[$name]['pd']) ? (float) $by[$name]['pd'] : (float) $l->pd_prefli * $mult;
                $bookedStage = $stagePd($l, $booked12);
                if ($l->ecl_value !== null && $bookedStage > 0) {
                    $ecl += (float) $l->ecl_value * $stagePd($l, $pd12) / $bookedStage;
                } else {
                    $ecl += $ead * $stagePd($l, $pd12) * $lgd;
                }
            }

            return round($ecl, 2);
        };
        $perScenario = [];
        foreach ($scenarios as $s) {
            $perScenario[$s->name] = ['weight' => (float) $s->weight, 'pd_multiplier' => (float) ($s->pd_multiplier ?? 1), 'ecl' => $eclUnder($s->name, (float) ($s->pd_multiplier ?? 1))];
        }
        $weighted = round(array_sum(array_map(fn ($p) => $p['ecl'] * $p['weight'] / 100, $perScenario)), 2);
        $base = $scenarios->firstWhere('is_base', 1);
        $down = $scenarios->where('is_base', 0)->sortByDesc('pd_multiplier')->first();
        $up = $scenarios->where('is_base', 0)->sortBy('pd_multiplier')->first();
        $shift = function ($to) use ($perScenario, $base) {
            if ($base === null || $to === null) {
                return null;
            }
            $w = array_map(fn ($p) => $p['weight'], $perScenario);
            $w[$base->name] -= 10; $w[$to->name] += 10;

            return round(array_sum(array_map(fn ($name, $p) => $p['ecl'] * $w[$name] / 100, array_keys($perScenario), $perScenario)), 2);
        };
        $result = ['period' => $set->reporting_period, 'loans' => $loans->count(), 'note' => $loans->isEmpty() ? 'no loan of the period carries a PD: run the PD engine, then the sensitivity' : null, 'per_scenario' => $perScenario, 'weighted_ecl' => $weighted,
            'ten_points_to_downside' => $shift($down), 'ten_points_to_upside' => $shift($up),
            'basis' => ($fromChain ? 'the per-scenario PD the route wrote on each loan' : 'the pre-FLI PD x the typed scenario multiplier (the route has not run for this set)') . ', applied to the booked ECL of each loan as the ratio of stage PDs (lifetime for Stage 2, 1 for Stage 3), so the base reconciles to the allowance; weighted once by the set'];
        DB::table('governed_scenario_sets')->where('id', $setId)->update(['sensitivity' => json_encode($result), 'updated_at' => now()]);

        return $result;
    }

    // ----- the first set ----------------------------------------------------

    /** The first set of spec 15.8, as a proposal for Dr Thom, for the period named. */
    public function seedFirstSet(string $period, ?int $userId = null): int
    {
        $existing = DB::table('governed_scenario_sets')->where('reporting_period', $period)->orderByDesc('version')->first();
        if ($existing !== null) {
            return (int) $existing->id;
        }
        $id = $this->create($period, 'First set, proposed for Dr Thom', [
            ['name' => 'Base', 'weight' => 50, 'is_base' => true, 'anchored_to' => 'The IMF World Economic Outlook path, World Bank actuals, the RBM\'s stated policy path', 'pd_multiplier' => 1.0, 'calibration_note' => 'The sources of section 13 as they stand', 'shocks' => []],
            ['name' => 'Upside', 'weight' => 15, 'anchored_to' => 'The 2021 to 2022 recovery: growth and the harvest above the base, inflation below', 'pd_multiplier' => 0.85, 'calibration_note' => '2021: real GDP growth 4.6 percent, agriculture up on a good harvest, inflation 9.3 percent (World Bank)',
                'shocks' => [['statistic_code' => 'GDP_GROWTH', 'kind' => 'abs', 'value' => 2.0, 'note' => 'growth two points above the base'], ['statistic_code' => 'AGRI_GROWTH', 'kind' => 'abs', 'value' => 4.0], ['statistic_code' => 'CPI', 'kind' => 'abs', 'value' => -5.0], ['statistic_code' => 'PLR', 'kind' => 'abs', 'value' => -2.0], ['statistic_code' => 'POLICY_RATE', 'kind' => 'abs', 'value' => -2.0]]],
            ['name' => 'Downside', 'weight' => 25, 'anchored_to' => 'The 2023 devaluation year: the kwacha down by 44 percent, inflation and lending rates up', 'pd_multiplier' => 1.35, 'calibration_note' => 'November 2023: the RBM devalued the kwacha 44 percent; inflation rose to 34.5 percent for 2024; the policy rate reached 26 percent',
                'shocks' => [['statistic_code' => 'MWK_USD', 'kind' => 'pct', 'value' => 44.0, 'note' => 'the November 2023 devaluation'], ['statistic_code' => 'CPI', 'kind' => 'abs', 'value' => 10.0], ['statistic_code' => 'LENDING_RATE', 'kind' => 'abs', 'value' => 5.0], ['statistic_code' => 'PLR', 'kind' => 'abs', 'value' => 5.0, 'note' => 'the PLR follows the policy rate'], ['statistic_code' => 'POLICY_RATE', 'kind' => 'abs', 'value' => 5.0], ['statistic_code' => 'GDP_GROWTH', 'kind' => 'abs', 'value' => -1.5]]],
            ['name' => 'Severe', 'weight' => 10, 'anchored_to' => 'The 2016 drought together with a devaluation: the harvest fails and the currency falls', 'pd_multiplier' => 1.80, 'calibration_note' => '2016: agriculture value added fell on the El Nino drought while growth fell to 2.3 percent; combined here with a 44 percent devaluation',
                'shocks' => [['statistic_code' => 'AGRI_GROWTH', 'kind' => 'abs', 'value' => -15.0, 'note' => 'the 2016 harvest failure'], ['statistic_code' => 'GDP_GROWTH', 'kind' => 'abs', 'value' => -3.0], ['statistic_code' => 'MWK_USD', 'kind' => 'pct', 'value' => 44.0], ['statistic_code' => 'CPI', 'kind' => 'abs', 'value' => 15.0], ['statistic_code' => 'LENDING_RATE', 'kind' => 'abs', 'value' => 8.0], ['statistic_code' => 'PLR', 'kind' => 'abs', 'value' => 8.0], ['statistic_code' => 'POLICY_RATE', 'kind' => 'abs', 'value' => 8.0]]],
        ], $userId, 'The first scenario set of specification v4 section 15.8. The weights are a starting point and are Dr Thom\'s to set; the anchors are the answer to "why these".', 'World Bank actuals to 2025 (fetched 8 October 2026); IMF WEO path to be loaded; RBM policy path to be stated');
        $this->propose($id, $userId);

        return $id;
    }
}
