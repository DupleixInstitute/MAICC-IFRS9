<?php

namespace App\Services\Fli;

use App\Services\Reports\EclBuildUpService;
use App\Services\Scenario\ScenarioSetService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Proposed against approved (FLI module specification v1, section 4, Impact
 * Analysis). For a scenario set that is proposed, or any set of the month,
 * the ECL each of its scenarios would give and its probability-weighted ECL,
 * measured exactly as the route and the ECL engine would measure them under
 * the model version in force, and set beside the ECL booked today. Nothing
 * is written: the figure becomes the booked one only when the set is
 * approved and the route is applied.
 */
class FliImpactService
{
    public function __construct(private FliRouteService $route, private TransmissionMethodCatalogue $methods, private EclBuildUpService $buildUp)
    {
    }

    /** @return array<string,mixed> */
    public function simulate(string $period, int $setId): array
    {
        $set = DB::table('governed_scenario_sets')->where('id', $setId)->first() ?? throw new RuntimeException("No scenario set {$setId}.");
        if ($set->reporting_period !== $period) {
            throw new RuntimeException("Set {$setId} is for {$set->reporting_period}, not {$period}.");
        }
        $fit = $this->route->approvedFit($period) ?? throw new RuntimeException("No model version is in force for {$period}, so no scenario can be measured.");
        [$base, $scenarios] = $this->route->scenarioAdjustments($period, $fit, $setId);
        $method = $this->methods->inForce();

        $per = array_fill_keys(array_keys($scenarios), 0.0);
        $booked = 0.0;
        $measured = 0;
        foreach ($this->buildUp->split($this->buildUp->loans($period), $period)[1] as $l) { // the MAIIC engine's loans; the Mega Farm programme is measured by its own engine (D30)
            $x = EclBuildUpService::lineage($l);
            if ($x['ecl'] === null || ! $x['ties']) {
                continue;
            }
            $measured++;
            $booked += (float) $x['ecl'];
            $shape = (object) ['stage' => (string) ($x['stage'] ?? ''), 'remaining_tenor' => $l->remaining_tenor];
            foreach ($scenarios as $name => $s) {
                if ((string) $x['stage'] === '3' || $x['horizon_pd_post'] <= 0 || $l->pd_prefli === null) {
                    $per[$name] += (float) $x['ecl'];
                    continue;
                }
                $pd = $this->methods->apply($method, (float) $l->pd_prefli, ['adjustment' => $s['adjustment'], 'segment_adjustment' => $s['adjustment']]);
                $pd = max(0.0, min(1.0, (float) ($pd ?? $l->pd_prefli)));
                $per[$name] += (float) $x['ecl'] * ScenarioSetService::stagePd($shape, $pd) / $x['horizon_pd_post'];
            }
        }
        $rows = [];
        $weighted = 0.0;
        foreach ($scenarios as $name => $s) {
            $weighted += $per[$name] * $s['weight'] / 100;
            $rows[] = ['scenario' => $name, 'weight' => (float) $s['weight'], 'driver' => $s['driver'], 'adjustment' => $s['adjustment'], 'ecl' => round($per[$name], 2), 'contribution' => round($per[$name] * $s['weight'] / 100, 2)];
        }

        return ['set' => ['id' => (int) $set->id, 'name' => $set->name, 'version' => (int) $set->version, 'status' => $set->status], 'model_version' => $fit->version,
            'base' => $base, 'scenarios' => $rows, 'weighted' => round($weighted, 2), 'booked' => round($booked, 2), 'difference' => round($weighted - $booked, 2), 'loans' => $measured,
            'note' => 'Measured on the loans whose booked ECL ties to their saved PD, LGD and EAD, without the overlays; Stage 3 does not move with the scenarios.'];
    }
}
