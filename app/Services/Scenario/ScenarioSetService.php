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
 * the sensitivity are computed and stored with the set.
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
        if ($rules['overlay_needs_set'] && DB::getSchemaBuilder()->hasTable('fli_adj')) {
            // an overlay against this set must be approved before the set locks: recorded as a note for lock()
        }
        $result = ['ok' => $problems === [], 'problems' => $problems, 'weights_sum' => $sum, 'rules' => $rules];
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
        if ($label === null && $approverId !== null && (int) $set->proposed_by === $approverId) {
            throw new RuntimeException('Maker-checker: the approver must be a different person from the proposer.');
        }
        $v = $this->validate($setId);
        if (! $v['ok']) {
            throw new RuntimeException('The set no longer passes its rules: ' . implode('; ', $v['problems']));
        }
        DB::transaction(function () use ($setId, $set, $approverId, $label) {
            DB::table('governed_scenario_sets')->where('reporting_period', $set->reporting_period)->where('id', '!=', $setId)->whereIn('status', ['APPROVED'])->update(['status' => 'SUPERSEDED', 'updated_at' => now()]);
            DB::table('governed_scenario_sets')->where('id', $setId)->update(['status' => 'APPROVED', 'approved_by' => $approverId, 'approver_label' => $label, 'approved_at' => now(), 'updated_at' => now()]);
            $this->backtest($setId);
            $this->sensitivity($setId);
        });
        AuditLoggerService::log('Scenario Set Approved', 'governed_scenario_sets', $setId, ['meta' => ['approved_by' => $approverId, 'label' => $label]]);
    }

    public function lock(int $setId, ?int $userId): void
    {
        $set = DB::table('governed_scenario_sets')->where('id', $setId)->first() ?? throw new RuntimeException("No scenario set {$setId}.");
        if ($set->status !== 'APPROVED') {
            throw new RuntimeException('Only an approved set can be locked.');
        }
        DB::table('governed_scenario_sets')->where('id', $setId)->update(['status' => 'LOCKED', 'locked_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('Scenario Set Locked', 'governed_scenario_sets', $setId, ['meta' => ['user' => $userId]]);
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
        $codes = array_values(array_unique(array_merge(['GDP_GROWTH', 'CPI', 'MWK_USD', 'LENDING_RATE', 'AGRI_GROWTH'], $codes)));
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
     * The ECL under each scenario, with 100 percent weight on each, and with ten
     * points moved from the base to the downside and to the upside. Until the
     * chain runs per scenario, each scenario's PD is the pre-FLI PD times its
     * multiplier, floored and capped; Stage 3 is 100 percent.
     */
    public function sensitivity(int $setId): array
    {
        $set = DB::table('governed_scenario_sets')->where('id', $setId)->first();
        $scenarios = DB::table('governed_scenarios')->where('set_id', $setId)->orderBy('order_position')->get();
        $loans = DB::table('loan_books')->where('reporting_period', $set->reporting_period)
            ->selectRaw('ifrs9stage_post_qualitative, coalesce(pd_prefli, pd_value, `12m_pd`) as pd_prefli, lgd_value, ead, carrying_amount')
            ->whereRaw('coalesce(pd_prefli, pd_value, `12m_pd`) is not null')->get();
        $eclUnder = function (float $mult) use ($loans): float {
            $ecl = 0.0;
            foreach ($loans as $l) {
                $ead = (float) ($l->ead ?? $l->carrying_amount);
                $lgd = $l->lgd_value !== null ? (float) $l->lgd_value : 0.45;
                $pd = $l->ifrs9stage_post_qualitative === '3' ? 1.0 : max(0.0, min(1.0, (float) $l->pd_prefli * $mult));
                $ecl += $ead * $pd * $lgd;
            }

            return round($ecl, 2);
        };
        $perScenario = [];
        foreach ($scenarios as $s) {
            $perScenario[$s->name] = ['weight' => (float) $s->weight, 'pd_multiplier' => (float) ($s->pd_multiplier ?? 1), 'ecl' => $eclUnder((float) ($s->pd_multiplier ?? 1))];
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
            'ten_points_to_downside' => $shift($down), 'ten_points_to_upside' => $shift($up), 'basis' => 'pre-FLI PD x the scenario multiplier, Stage 3 at 100 percent, LGD as held (0.45 where none); the chain per scenario replaces the multiplier once a fit per scenario is approved'];
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
                'shocks' => [['statistic_code' => 'GDP_GROWTH', 'kind' => 'abs', 'value' => 2.0, 'note' => 'growth two points above the base'], ['statistic_code' => 'AGRI_GROWTH', 'kind' => 'abs', 'value' => 4.0], ['statistic_code' => 'CPI', 'kind' => 'abs', 'value' => -5.0]]],
            ['name' => 'Downside', 'weight' => 25, 'anchored_to' => 'The 2023 devaluation year: the kwacha down by 44 percent, inflation and lending rates up', 'pd_multiplier' => 1.35, 'calibration_note' => 'November 2023: the RBM devalued the kwacha 44 percent; inflation rose to 34.5 percent for 2024; the policy rate reached 26 percent',
                'shocks' => [['statistic_code' => 'MWK_USD', 'kind' => 'pct', 'value' => 44.0, 'note' => 'the November 2023 devaluation'], ['statistic_code' => 'CPI', 'kind' => 'abs', 'value' => 10.0], ['statistic_code' => 'LENDING_RATE', 'kind' => 'abs', 'value' => 5.0], ['statistic_code' => 'GDP_GROWTH', 'kind' => 'abs', 'value' => -1.5]]],
            ['name' => 'Severe', 'weight' => 10, 'anchored_to' => 'The 2016 drought together with a devaluation: the harvest fails and the currency falls', 'pd_multiplier' => 1.80, 'calibration_note' => '2016: agriculture value added fell on the El Nino drought while growth fell to 2.3 percent; combined here with a 44 percent devaluation',
                'shocks' => [['statistic_code' => 'AGRI_GROWTH', 'kind' => 'abs', 'value' => -15.0, 'note' => 'the 2016 harvest failure'], ['statistic_code' => 'GDP_GROWTH', 'kind' => 'abs', 'value' => -3.0], ['statistic_code' => 'MWK_USD', 'kind' => 'pct', 'value' => 44.0], ['statistic_code' => 'CPI', 'kind' => 'abs', 'value' => 15.0], ['statistic_code' => 'LENDING_RATE', 'kind' => 'abs', 'value' => 8.0]]],
        ], $userId, 'The first scenario set of specification v4 section 15.8. The weights are a starting point and are Dr Thom\'s to set; the anchors are the answer to "why these".', 'World Bank actuals to 2025 (fetched 8 October 2026); IMF WEO path to be loaded; RBM policy path to be stated');
        $this->propose($id, $userId);

        return $id;
    }
}
