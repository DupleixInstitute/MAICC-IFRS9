<?php

declare(strict_types=1);

namespace App\Support\Fli;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * The macro and credit-loss series as they stood at the end of a reporting
 * period. IFRS 9 5.5.17(c) and B5.5.49 to B5.5.51 measure expected credit
 * losses on the information reasonably available at the reporting date, so
 * a correlation, a fit, a base value or a scenario path for period P may only
 * read what was knowable by the end of P. Every FLI engine reads the series
 * through this class; none reads macro_series or credit_loss_series raw.
 *
 * When a row became knowable ("known from", a YYYYMM):
 *  - an actual or estimate: its observation period plus its publication lag
 *    (macro_series.lag_months, months after the period before the figure is
 *    published; 0 when the source states none);
 *  - an interpolated month (the bridge interpolates between two annual
 *    observations): the month the LATER of the two observations became
 *    knowable, because the interpolated value depends on it. With no later
 *    observation the row cannot be reproduced and is never knowable;
 *  - a forecast: the date carried in its vintage (a YYYY-MM-DD in the
 *    vintage text). A forecast with no dated vintage is never knowable for a
 *    past period (fail closed: an undated forecast cannot be shown to have
 *    been published by the reporting date).
 * A credit-loss proxy is derived from the period's own staged loan book, so
 * it is knowable at the end of its observation period.
 */
final class AsAtSeries
{
    public const INTERPOLATED_SOURCE = 'interpolated from the annual observations';

    /** 'YYYY-MM' or 'YYYYMM' to 'YYYYMM'; anything else is refused. */
    public static function ym(string $period): string
    {
        $p = trim($period);
        if (! preg_match('/^(\d{4})-?(\d{2})$/', $p, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
            throw new InvalidArgumentException("Reporting period '{$period}' is not YYYY-MM or YYYYMM: the as-at cut cannot be set.");
        }

        return $m[1] . $m[2];
    }

    /** The number of calendar months from $from to $to (both YYYYMM); negative when $to is earlier. */
    public static function monthsBetween(string $from, string $to): int
    {
        return ((int) substr($to, 0, 4) * 12 + (int) substr($to, 4, 2)) - ((int) substr($from, 0, 4) * 12 + (int) substr($from, 4, 2));
    }

    /**
     * Every macro row knowable at the end of $asOf, per code and period, with
     * its type and the month it became knowable.
     *
     * @param  array<int,string>  $types  value types to return (actual, estimate, forecast)
     * @param  array<int,string>|null  $codes  restrict to these statistic codes
     * @return array<string,array<string,array{value:float,type:string,known_from:string,interpolated:bool}>>
     */
    public static function macroRows(?string $connection, string $asOf, array $types = ['actual'], ?array $codes = null): array
    {
        $p = self::ym($asOf);
        $conn = $connection ?? DB::getDefaultConnection();
        $cols = ['statistic_code', 'observation_period', 'value', 'value_type'];
        foreach (['source', 'lag_months', 'vintage'] as $c) {
            if (self::hasColumn($conn, 'macro_series', $c)) {
                $cols[] = $c;
            }
        }
        $q = DB::connection($conn)->table('macro_series');
        if ($codes !== null) {
            $q->whereIn('statistic_code', $codes);
        }
        // every type is read, so an interpolated row can find the observation it hangs on
        $rows = $q->orderBy('statistic_code')->orderBy('observation_period')->get($cols);

        $byCode = [];
        foreach ($rows as $r) {
            $byCode[(string) $r->statistic_code][] = $r;
        }
        $out = [];
        foreach ($byCode as $code => $list) {
            $knownFrom = [];
            $nextAnchor = null; // known-from of the next non-interpolated row, walking backwards
            for ($i = count($list) - 1; $i >= 0; $i--) {
                $r = $list[$i];
                $interpolated = isset($r->source) && (string) $r->source === self::INTERPOLATED_SOURCE;
                if ($interpolated) {
                    $knownFrom[$i] = $nextAnchor; // null: no later observation, never knowable
                } else {
                    $knownFrom[$i] = self::ownKnownFrom($r);
                    $nextAnchor = $knownFrom[$i];
                }
            }
            foreach ($list as $i => $r) {
                $kf = $knownFrom[$i];
                if ($kf === null || $kf > $p || ! in_array((string) $r->value_type, $types, true)) {
                    continue;
                }
                $out[$code][(string) $r->observation_period] = [
                    'value' => (float) $r->value,
                    'type' => (string) $r->value_type,
                    'known_from' => $kf,
                    'interpolated' => isset($r->source) && (string) $r->source === self::INTERPOLATED_SOURCE,
                ];
            }
        }

        return $out;
    }

    /**
     * The macro values knowable at the end of $asOf: [code => [YYYYMM => value]].
     *
     * @param  array<int,string>  $types
     * @param  array<int,string>|null  $codes
     * @return array<string,array<string,float>>
     */
    public static function macro(?string $connection, string $asOf, array $types = ['actual'], ?array $codes = null): array
    {
        $out = [];
        foreach (self::macroRows($connection, $asOf, $types, $codes) as $code => $byPeriod) {
            foreach ($byPeriod as $period => $row) {
                $out[$code][$period] = $row['value'];
            }
        }

        return $out;
    }

    /**
     * The credit-loss proxies observed by the end of $asOf: [code => [YYYYMM => value]].
     *
     * @return array<string,array<string,float>>
     */
    public static function proxies(?string $connection, string $asOf): array
    {
        $p = self::ym($asOf);
        $rows = DB::connection($connection ?? DB::getDefaultConnection())->table('credit_loss_series')
            ->where('observation_period', '<=', $p)
            ->orderBy('proxy_code')->orderBy('observation_period')
            ->get(['proxy_code', 'observation_period', 'value']);
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->proxy_code][(string) $r->observation_period] = (float) $r->value;
        }

        return $out;
    }

    /** Known-from of a row that is not interpolated. */
    private static function ownKnownFrom(object $r): ?string
    {
        $period = (string) $r->observation_period;
        if ((string) $r->value_type === 'forecast') {
            if (! isset($r->vintage) || ! preg_match('/(\d{4})-(\d{2})-\d{2}/', (string) $r->vintage, $m)) {
                return null;
            }

            return $m[1] . $m[2];
        }
        $lag = isset($r->lag_months) ? max(0, (int) $r->lag_months) : 0;

        return $lag === 0 ? $period : SeriesAligner::shiftPeriod($period, -$lag);
    }

    private static function hasColumn(string $connection, string $table, string $column): bool
    {
        return Schema::connection($connection)->hasColumn($table, $column);
    }
}
