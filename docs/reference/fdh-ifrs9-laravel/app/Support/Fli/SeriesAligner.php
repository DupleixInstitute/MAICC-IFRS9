<?php

declare(strict_types=1);

namespace App\Support\Fli;

/**
 * Aligns a macro driver X and a credit-loss proxy Y on their overlapping
 * observation window, honouring a lag: the driver enters at period (P - lag)
 * to explain the proxy at period P (macro effects on default are not
 * contemporaneous - FLI_AND_PD_METHODOLOGY.md sections 8, 12). Periods are the
 * CHAR(6) YYYYMM keys used throughout the schema.
 *
 * Nothing is interpolated or back-filled: a proxy period with no matching
 * lagged driver observation is simply dropped from the overlap (fail-closed).
 */
final class SeriesAligner
{
    /**
     * Shift a YYYYMM period backwards by $lagMonths calendar months.
     */
    public static function shiftPeriod(string $yyyymm, int $lagMonths): string
    {
        $year = (int) substr($yyyymm, 0, 4);
        $month = (int) substr($yyyymm, 4, 2);
        $total = $year * 12 + ($month - 1) - $lagMonths;
        $newYear = intdiv($total, 12);
        $newMonth = ($total % 12) + 1;
        if ($newMonth <= 0) { // guard for negative modulo edge
            $newMonth += 12;
            $newYear -= 1;
        }

        return sprintf('%04d%02d', $newYear, $newMonth);
    }

    /**
     * @param array<string,float> $xByPeriod driver value keyed by YYYYMM
     * @param array<string,float> $yByPeriod proxy value keyed by YYYYMM
     * @return array{x:array<int,float>,y:array<int,float>,periods:array<int,string>,n:int,overlap_start:?string,overlap_end:?string}
     */
    public static function align(array $xByPeriod, array $yByPeriod, int $lagMonths): array
    {
        $x = [];
        $y = [];
        $periods = [];
        $yPeriods = array_keys($yByPeriod);
        sort($yPeriods);
        foreach ($yPeriods as $p) {
            $xp = self::shiftPeriod((string) $p, $lagMonths);
            if (! array_key_exists($xp, $xByPeriod)) {
                continue;
            }
            $x[] = (float) $xByPeriod[$xp];
            $y[] = (float) $yByPeriod[$p];
            $periods[] = (string) $p;
        }

        return [
            'x' => $x,
            'y' => $y,
            'periods' => $periods,
            'n' => count($periods),
            'overlap_start' => $periods === [] ? null : $periods[0],
            'overlap_end' => $periods === [] ? null : $periods[count($periods) - 1],
        ];
    }
}
