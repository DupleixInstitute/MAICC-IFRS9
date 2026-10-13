<?php

namespace App\Http\Controllers;

use App\Services\AuditLoggerService;
use App\Services\Scenario\ScenarioSetService;
use App\Support\Fli\FliPlainLanguage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Weighted Forecast (FLI module specification v1): the probability-weighted
 * path of every driver under a governed scenario set, year by year, beside
 * each scenario's own path and the spread between them.
 *
 * IFRS 9 weights the loss, not the economy (5.5.17(a), B5.5.42): the booked
 * ECL is the weighted ECL of the scenarios. The weighted path is shown for
 * reading the set (where the scenarios sit around the base, how wide the
 * range is) and is the input of the "weight the macro path" method that the
 * Governance Centre keeps for reproducing earlier periods. Calculating it
 * stores the path against the set, so a reviewer sees what was weighted.
 */
class MacroForecastWeightedController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:eir.govern')->only(['calculate', 'destroy']);
    }

    public function index(Request $request, ScenarioSetService $sets)
    {
        $periods = DB::table('governed_scenario_sets')->distinct()->orderByDesc('reporting_period')->pluck('reporting_period');
        $period = (string) $request->query('period', $periods->first() ?? now()->format('Y-m'));
        $setsOfPeriod = DB::table('governed_scenario_sets')->where('reporting_period', $period)->orderByDesc('version')->get(['id', 'name', 'version', 'status']);
        $setId = (int) $request->query('set', $setsOfPeriod->firstWhere('status', 'APPROVED')->id ?? $setsOfPeriod->firstWhere('status', 'LOCKED')->id ?? ($setsOfPeriod->first()->id ?? 0));
        $rows = [];
        $paths = null;
        $error = null;
        if ($setId) {
            try {
                $paths = $sets->paths($setId);
                $rows = $this->weighted($paths);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $plain = new FliPlainLanguage();
        $saved = DB::table('macro_forecast_weighted')->where('governed_set_id', $setId)->where('is_current', 1)->orderBy('statistic_code')->orderBy('year_offset')->get();

        return Inertia::render('FLI/ForecastWeighted/Index', [
            'period' => $period, 'periods' => $periods, 'sets' => $setsOfPeriod, 'setId' => $setId,
            'rows' => array_map(fn ($r) => $r + ['name' => $plain->driver($r['statistic_code'])], $rows),
            'scenarios' => $paths ? array_map(fn ($n, $s) => ['name' => $n, 'weight' => $s['weight'], 'is_base' => $s['is_base']], array_keys($paths['scenarios']), $paths['scenarios']) : [],
            'year' => (int) substr($period, 0, 4), 'asOf' => isset($paths['as_of']) ? substr((string) $paths['as_of'], 0, 4) . '-' . substr((string) $paths['as_of'], 4, 2) : null, 'error' => $error,
            'saved' => ['count' => $saved->count(), 'at' => $saved->max('updated_at'), 'revision' => $saved->max('revision')],
            'canGovern' => (bool) $request->user()?->can('eir.govern'),
        ]);
    }

    /** Store the weighted path of a set (a new revision; the previous one is kept, not current). */
    public function calculate(Request $request, ScenarioSetService $sets)
    {
        $data = $request->validate(['set_id' => ['required', 'integer', 'exists:governed_scenario_sets,id']]);
        try {
            $set = DB::table('governed_scenario_sets')->where('id', $data['set_id'])->first();
            $rows = $this->weighted($sets->paths((int) $set->id));
            $year = (int) substr($set->reporting_period, 0, 4);
            $revision = (int) DB::table('macro_forecast_weighted')->where('governed_set_id', $set->id)->max('revision') + 1;
            DB::transaction(function () use ($rows, $set, $year, $revision) {
                DB::table('macro_forecast_weighted')->where('governed_set_id', $set->id)->update(['is_current' => 0, 'updated_at' => now()]);
                foreach ($rows as $r) {
                    foreach ($r['years'] as $o => $y) {
                        if ($y['weighted'] === null) {
                            continue;
                        }
                        DB::table('macro_forecast_weighted')->insert(['governed_set_id' => $set->id, 'reporting_period' => $set->reporting_period, 'statistic_code' => $r['statistic_code'],
                            'year_offset' => $o, 'start_period' => ($year + $o) . '-01-01', 'end_period' => ($year + $o) . '-12-31', 'weighted_value' => round($y['weighted'], 4),
                            'standard_deviation' => round($y['spread'], 4), 'scenario_values' => json_encode($y['scenarios']), 'revision' => $revision, 'is_current' => 1,
                            'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            });
            AuditLoggerService::log('FLI Weighted Forecast Calculated', 'macro_forecast_weighted', (int) $set->id, ['reporting_period' => $set->reporting_period, 'new_values' => ['set' => (int) $set->id, 'revision' => $revision, 'series' => count($rows)], 'meta' => ['user' => $request->user()->id]]);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Weighted path stored for set {$data['set_id']} (revision {$revision}).");
    }

    public function destroy(Request $request, $id)
    {
        DB::table('macro_forecast_weighted')->where('governed_set_id', (int) $id)->delete();

        return back()->with('success', 'The stored weighted path of the set is removed; the set itself is unchanged.');
    }

    /**
     * Per driver and forecast year: the base, each scenario, the weighted value
     * and the weighted spread (standard deviation) of the scenarios around it.
     *
     * @return list<array{statistic_code:string,years:array<int,array{base:?float,weighted:?float,spread:?float,scenarios:array<string,?float>,source:?string}>}>
     */
    private function weighted(array $paths): array
    {
        $out = [];
        foreach ($paths['base'] as $code => $years) {
            $row = ['statistic_code' => $code, 'years' => []];
            $moved = false;
            foreach ($years as $o => $baseValue) {
                $vals = [];
                $sum = 0.0; $wsum = 0.0;
                foreach ($paths['scenarios'] as $name => $s) {
                    $v = $s['path'][$code][$o] ?? null;
                    $vals[$name] = $v === null ? null : round((float) $v, 4);
                    if ($v !== null) {
                        $sum += (float) $v * $s['weight'];
                        $wsum += $s['weight'];
                    }
                    $moved = $moved || ($v !== null && $baseValue !== null && abs((float) $v - (float) $baseValue) > 1e-9);
                }
                $w = $wsum > 0 ? $sum / $wsum : null;
                $var = 0.0;
                if ($w !== null) {
                    foreach ($paths['scenarios'] as $name => $s) {
                        if ($vals[$name] !== null) {
                            $var += $s['weight'] / $wsum * ((float) $vals[$name] - $w) ** 2;
                        }
                    }
                }
                $row['years'][$o] = ['base' => $baseValue === null ? null : round((float) $baseValue, 4), 'weighted' => $w === null ? null : round($w, 4), 'spread' => $w === null ? null : round(sqrt($var), 4),
                    'scenarios' => $vals, 'source' => $paths['base_source'][$code][$o] ?? null];
            }
            $row['shocked'] = $moved;
            $out[] = $row;
        }
        usort($out, fn ($a, $b) => [$b['shocked'], $a['statistic_code']] <=> [$a['shocked'], $b['statistic_code']]);

        return $out;
    }
}
