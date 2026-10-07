<?php

declare(strict_types=1);

namespace App\Services\Fli;

use Illuminate\Support\Facades\DB;

/**
 * MacroModule - extracts the macro driver ACTUALS from the read-only golden
 * source (fdh_real.macro_statistics_data) into the normalized macro_series
 * table with value_type / source / vintage, and records a macro_import_logs
 * row per statistic.
 *
 * Fail-closed / no-fabrication rules:
 *   - Only rows the source marks as historical actuals (data_type Actual /
 *     historical) land in macro_series as value_type='actual'; rows marked
 *     estimate land as 'estimate'.
 *   - Scenario forecast paths (basecase / upside / downside*) are NOT written
 *     to macro_series: they are scenario projections that belong in
 *     fli_driver_forecasts, and they would collide on the (statistic_code,
 *     observation_period) natural key. They are counted and reported, never
 *     silently coerced into a single "actual".
 *   - The World Bank / IMF auto-connect is a documented STUB in this build
 *     (no live external calls) - importFromExternal() returns not_configured.
 *
 * Grounded in REVISED_SCHEMA.md sections 4 + 9.2 and FLI_AND_PD_METHODOLOGY.md
 * section 15.3.
 */
final class MacroModule
{
    private string $connection;

    private string $goldenConnection;

    public function __construct(?string $connection = null, string $goldenConnection = 'golden')
    {
        $this->connection = $connection ?? config('database.default');
        $this->goldenConnection = $goldenConnection;
    }

    private function db()
    {
        return DB::connection($this->connection);
    }

    /**
     * Map the golden data_type to a macro_series value_type, or null when the
     * row is a scenario forecast path (which does not belong in macro_series).
     */
    public static function classifyValueType(string $dataType): ?string
    {
        $t = strtolower(trim($dataType));
        if (in_array($t, ['actual', 'historical', 'history'], true)) {
            return 'actual';
        }
        if ($t === 'estimate') {
            return 'estimate';
        }

        // basecase / upside / downside1 / downside2 / ... => scenario forecast
        return null;
    }

    /**
     * Import macro actuals from golden.macro_statistics_data.
     *
     * @return array{loaded:int,skipped_forecast:int,by_statistic:array<string,int>,source:string,periods:array<string,array{min:string,max:string}>,note:string}
     */
    public function importFromGolden(): array
    {
        $golden = DB::connection($this->goldenConnection);

        // Statistic display names from the definitions catalog (names are blank
        // on the observation rows themselves).
        $names = [];
        try {
            foreach ($golden->table('macro_statistics')->get(['statistic_code', 'statistic_name']) as $d) {
                $names[(string) $d->statistic_code] = (string) $d->statistic_name;
            }
        } catch (\Throwable $e) {
            // definitions catalog optional; names fall back to the code
        }

        $rows = $golden->table('macro_statistics_data')
            ->orderBy('statistic_code')
            ->orderBy('reporting_period')
            ->get(['statistic_code', 'statistic_name', 'reporting_period', 'periodic_value', 'data_type', 'data_source']);

        $now = now();
        $toUpsert = [];
        $byStat = [];
        $skippedForecast = 0;
        $periods = [];

        foreach ($rows as $r) {
            $valueType = self::classifyValueType((string) $r->data_type);
            $code = mb_substr((string) $r->statistic_code, 0, 32);
            if ($valueType === null) {
                $skippedForecast++;
                continue;
            }
            $period = str_pad((string) $r->reporting_period, 6, '0', STR_PAD_LEFT);
            $name = trim((string) $r->statistic_name) !== ''
                ? (string) $r->statistic_name
                : ($names[$code] ?? $code);

            $toUpsert[$code . '|' . $period] = [
                'statistic_code' => $code,
                'statistic_name' => mb_substr($name, 0, 128),
                'observation_period' => $period,
                'value' => (float) $r->periodic_value,
                'source' => 'fdh_real:macro_statistics_data',
                'lag_months' => 0,
                'value_type' => $valueType,
                'vintage' => mb_substr((string) ($r->data_source ?? ''), 0, 32) ?: null,
                'imported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $byStat[$code] = ($byStat[$code] ?? 0) + 1;
            if (! isset($periods[$code])) {
                $periods[$code] = ['min' => $period, 'max' => $period];
            } else {
                $periods[$code]['min'] = min($periods[$code]['min'], $period);
                $periods[$code]['max'] = max($periods[$code]['max'], $period);
            }
        }

        $loaded = 0;
        if ($toUpsert !== []) {
            $chunks = array_chunk(array_values($toUpsert), 500);
            foreach ($chunks as $chunk) {
                $this->db()->table('macro_series')->upsert(
                    $chunk,
                    ['statistic_code', 'observation_period'],
                    ['statistic_name', 'value', 'source', 'value_type', 'vintage', 'imported_at', 'updated_at']
                );
            }
            $loaded = count($toUpsert);

            // One macro_import_logs row per statistic for provenance.
            foreach ($byStat as $code => $n) {
                $this->db()->table('macro_import_logs')->insert([
                    'source' => 'manual', // golden DB extract (operator-driven), not a live worldbank/imf pull
                    'statistic_code' => $code,
                    'rows' => $n,
                    'status' => 'import',
                    'payload_hash' => hash('sha256', $this->goldenConnection . ':macro_statistics_data:' . $code . ':' . $n),
                    'fetched_at' => $now,
                ]);
            }
        }

        return [
            'loaded' => $loaded,
            'skipped_forecast' => $skippedForecast,
            'by_statistic' => $byStat,
            'source' => $this->goldenConnection . '.macro_statistics_data',
            'periods' => $periods,
            'note' => $skippedForecast > 0
                ? "{$skippedForecast} scenario forecast rows (basecase/upside/downside) were NOT loaded into macro_series - they are scenario projections for fli_driver_forecasts."
                : 'no scenario forecast rows encountered',
        ];
    }

    /**
     * World Bank / IMF auto-connect - documented STUB. This build performs NO
     * live external calls; the method fails closed so a caller can never treat
     * an unconfigured connector as a successful fetch.
     *
     * @return array{status:string,source:string,note:string,rows:int}
     */
    public function importFromExternal(string $source): array
    {
        return [
            'status' => 'not_configured',
            'source' => $source,
            'rows' => 0,
            'note' => 'External macro auto-connect (World Bank / IMF WEO) is a documented stub in this build. '
                . 'No live HTTP calls are made. Configure the connector and re-run to fetch a real vintage; '
                . 'until then use importFromGolden() or a governed CSV import.',
        ];
    }
}
