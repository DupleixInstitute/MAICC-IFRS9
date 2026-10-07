<?php

declare(strict_types=1);

namespace App\Services\Fli;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Enforces the IFRS 9 scenario-weight invariants (MAIIC's hard rule, adopted
 * for FDH) and the outcome-weighting principle:
 *
 *   - a scenario set has AT LEAST TWO scenarios (B5.5.42);
 *   - the weights form a probability distribution: SUM = 1.0000 (+/- tolerance),
 *     hard-blocked on save/approval (not a warning);
 *   - weighting is applied to the ECL OUTCOMES, ECL = SUM_s w_s * ECL_s, NOT to
 *     a blended macro input first - because E[ECL(x)] != ECL(E[x]) whenever the
 *     loss function is convex in the drivers (Jensen's inequality). blendInputs()
 *     exists ONLY to demonstrate the (biased-low) alternative a test asserts against.
 *
 * FLI_AND_PD_METHODOLOGY.md sections 2 (principles 2-3) and 3.
 */
final class ScenarioWeightValidator
{
    public const TOLERANCE = 0.0001;

    /**
     * @param array<int,array{name?:string,probability:float|int}> $scenarios
     * @return array{valid:bool,sum:float,count:int,errors:array<int,string>}
     */
    public function validate(array $scenarios, float $tolerance = self::TOLERANCE): array
    {
        $errors = [];
        $count = count($scenarios);
        if ($count < 2) {
            $errors[] = sprintf('a scenario set needs at least 2 scenarios (has %d) - IFRS 9 B5.5.42', $count);
        }
        $sum = 0.0;
        foreach ($scenarios as $i => $s) {
            $w = (float) $s['probability'];
            if ($w < 0.0 || $w > 1.0) {
                $errors[] = sprintf('scenario %s weight %.4f is outside [0,1]', $s['name'] ?? (string) $i, $w);
            }
            $sum += $w;
        }
        if (abs($sum - 1.0) > $tolerance) {
            $errors[] = sprintf('scenario weights sum to %.4f, not 1.0000 (tolerance %.4f) - blocked', $sum, $tolerance);
        }

        return ['valid' => $errors === [], 'sum' => $sum, 'count' => $count, 'errors' => $errors];
    }

    /**
     * Fail-closed assertion for the save/approve path.
     *
     * @param array<int,array{name?:string,probability:float|int}> $scenarios
     */
    public function assertValid(array $scenarios, float $tolerance = self::TOLERANCE): void
    {
        $r = $this->validate($scenarios, $tolerance);
        if (! $r['valid']) {
            throw new RuntimeException('Scenario set rejected: ' . implode('; ', $r['errors']));
        }
    }

    /**
     * One-click normalise helper: scale weights so they sum to exactly 1.
     *
     * @param array<int,array{name?:string,probability:float|int}> $scenarios
     * @return array<int,array{name?:string,probability:float}>
     */
    public function normalise(array $scenarios): array
    {
        $sum = 0.0;
        foreach ($scenarios as $s) {
            $sum += (float) $s['probability'];
        }
        if ($sum <= 0.0) {
            throw new RuntimeException('cannot normalise: weights sum to zero');
        }
        $out = [];
        foreach ($scenarios as $s) {
            $s['probability'] = (float) $s['probability'] / $sum;
            $out[] = $s;
        }

        return $out;
    }

    /**
     * Correct transmission: weight the OUTCOMES. ECL = SUM_s w_s * ECL_s.
     *
     * @param array<int,float> $outcomes ECL_s per scenario
     * @param array<int,float> $weights  w_s per scenario (same order)
     */
    public function weightedOutcome(array $outcomes, array $weights): float
    {
        $sum = 0.0;
        foreach ($outcomes as $i => $o) {
            $sum += (float) $o * (float) ($weights[$i] ?? 0.0);
        }

        return $sum;
    }

    /**
     * INCORRECT (biased-low) alternative: blend the inputs first. Provided only
     * so a test can assert the engine does NOT use it. E[x] = SUM_s w_s * x_s.
     *
     * @param array<int,float> $inputs  macro input per scenario
     * @param array<int,float> $weights w_s per scenario
     */
    public function blendInput(array $inputs, array $weights): float
    {
        $sum = 0.0;
        foreach ($inputs as $i => $x) {
            $sum += (float) $x * (float) ($weights[$i] ?? 0.0);
        }

        return $sum;
    }

    /**
     * Validate a persisted forecast set's scenarios (fli_scenarios) fail-closed.
     *
     * @return array{valid:bool,sum:float,count:int,errors:array<int,string>}
     */
    public function validateForecastSet(string $connection, int $forecastSetId, float $tolerance = self::TOLERANCE): array
    {
        $rows = DB::connection($connection)->table('fli_scenarios')
            ->where('forecast_set_id', $forecastSetId)
            ->get(['scenario_name', 'probability']);
        $scenarios = [];
        foreach ($rows as $r) {
            $scenarios[] = ['name' => (string) $r->scenario_name, 'probability' => (float) $r->probability];
        }

        return $this->validate($scenarios, $tolerance);
    }
}
