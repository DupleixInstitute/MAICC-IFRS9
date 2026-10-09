<?php

namespace App\Services\Macro;

use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceService;
use Illuminate\Support\Facades\DB;

/**
 * Commits a source's rows to macro_statistics_data as a batch (spec v4
 * section 13.3, MS-3): every commit records the source, the address, when
 * it was fetched, who committed it and how many rows; every observation
 * points to its batch. Observations are keyed on series, period and
 * scenario, so a refresh updates the value and keeps the key. An API
 * import writes to the base scenario, created on first use.
 *
 * Where two sources carry the same series and period, the governed order
 * of macro_source_precedence decides which wins (section 13.5; system audit
 * of 9 October 2026, finding M12: the order was written into this file and
 * the setting read by nothing). The order is read from the option text the
 * Governance Centre holds, which names the sources in order and, where it
 * says so, the kind of observation each is trusted for: "World Bank
 * actuals, IMF forecasts, RBM rates" trusts the World Bank first for an
 * actual, the IMF first for a forecast and the Reserve Bank file first for
 * a rate series; "IMF for everything it carries" puts the IMF first for
 * every kind; "Manual entry first, then the sources" puts a hand entry
 * above every source, so no import replaces it. Under every option a manual
 * entry overrides what is held, since it carries a reason, and an actual
 * already held is never replaced by a forecast from a source.
 */
class MacroImportService
{
    /** The source keys the batches carry, by the word the option text uses for them. */
    private const SOURCE_WORDS = ['world bank' => 'world_bank', 'imf' => 'imf_weo', 'rbm' => 'rbm_file', 'reserve bank' => 'rbm_file', 'manual' => 'manual'];

    /** The kinds of observation the option text can scope a source to. */
    private const KIND_WORDS = ['actual' => 'actual', 'forecast' => 'forecast', 'rate' => 'rate'];

    public function __construct(private ?GovernanceService $governance = null)
    {
        $this->governance ??= new GovernanceService();
    }

    /** @return array{batch_id:int,rows:int,new:int,updated:int,unchanged:int,kept_actual:int,kept_precedence:int,precedence:string} */
    public function commit(array $preview, ?int $userId, string $sourceKey = 'world_bank', ?string $fileSha = null, ?string $note = null): array
    {
        $series = DB::table('macro_statistics')->where('statistic_code', $preview['series_code'])->first();
        $precedence = $this->governance->get('macro_source_precedence');
        if ($series === null || $preview['rows'] === []) {
            return ['batch_id' => 0, 'rows' => 0, 'new' => 0, 'updated' => 0, 'unchanged' => 0, 'kept_actual' => 0, 'kept_precedence' => 0, 'precedence' => $precedence];
        }
        [$profileId, $scenarioId] = $this->baseScenario($userId);
        $order = self::precedenceFromOption($precedence);
        $rateSeries = array_key_exists('rbm', json_decode($series->external_codes ?? '', true) ?? []);

        return DB::transaction(function () use ($preview, $series, $userId, $sourceKey, $fileSha, $note, $profileId, $scenarioId, $precedence, $order, $rateSeries) {
            $batchId = (int) DB::table('macro_source_import_batches')->insertGetId([
                'source' => $sourceKey, 'address' => $preview['address'] ?? null, 'country' => $preview['country'] ?? 'MWI', 'file_sha256' => $fileSha,
                'fetched_at' => $preview['fetched_at'] ?? now(), 'committed_by' => $userId, 'rows' => count($preview['rows']),
                'series' => json_encode([['code' => $series->statistic_code, 'indicator' => $preview['indicator_code'] ?? null, 'rows' => count($preview['rows']), 'first' => $preview['rows'][0]['period'], 'last' => end($preview['rows'])['period'], 'precedence' => $precedence]]),
                'note' => $note, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $new = $updated = $unchanged = $kept = $keptPrecedence = 0;
            foreach ($preview['rows'] as $r) {
                $existing = DB::table('macro_statistics_data as d')->leftJoin('macro_source_import_batches as b', 'b.id', '=', 'd.source_import_batch_id')
                    ->where('d.macro_stat_definition_id', $series->id)->where('d.period', $r['period'])->where('d.scenario_id', $scenarioId)
                    ->first(['d.id', 'd.value', 'd.is_forecast', 'b.source as held_source']);
                $values = ['value' => $r['value'], 'is_forecast' => $r['value_type'] === 'forecast' ? 1 : 0, 'actual_value' => $r['value_type'] === 'actual' ? $r['value'] : null,
                    'source' => $preview['source'] ?? $sourceKey, 'source_import_batch_id' => $batchId, 'updated_at' => now()];
                if ($existing === null) {
                    DB::table('macro_statistics_data')->insert($values + ['macro_stat_definition_id' => $series->id, 'scenario_profile_id' => $profileId, 'scenario_id' => $scenarioId, 'period' => $r['period'], 'created_by' => $userId ?? 1, 'created_at' => now()]);
                    $new++;
                } elseif ($r['value_type'] === 'forecast' && (int) $existing->is_forecast === 0 && $sourceKey !== 'manual') {
                    // precedence (13.5): an actual already held is never overwritten by a forecast; the forecast is kept in the batch history
                    $kept++;
                    continue;
                } elseif ($sourceKey !== 'manual' && $existing->held_source !== null && $existing->held_source !== $sourceKey
                    && self::rank($sourceKey, $r['value_type'], $rateSeries, $order) > self::rank((string) $existing->held_source, (int) $existing->is_forecast ? 'forecast' : 'actual', $rateSeries, $order)) {
                    // the governed order: the source already held ranks above the one arriving for this kind of observation; the row keeps its value
                    $keptPrecedence++;
                    continue;
                } elseif (abs((float) $existing->value - round((float) $r['value'], 4)) > 0.00005 || (int) $existing->is_forecast !== $values['is_forecast']) { // the column holds four decimals
                    DB::table('macro_statistics_data')->where('id', $existing->id)->update($values);
                    $updated++;
                } else {
                    DB::table('macro_statistics_data')->where('id', $existing->id)->update(['source_import_batch_id' => $batchId]);
                    $unchanged++;
                }
            }
            AuditLoggerService::log('Macro Series Imported', 'macro_source_import_batches', $batchId, ['new_values' => ['series' => $series->statistic_code, 'source' => $sourceKey, 'rows' => count($preview['rows']), 'new' => $new, 'updated' => $updated, 'kept_actual' => $kept, 'kept_precedence' => $keptPrecedence],
                'meta' => ['user' => $userId, 'address' => $preview['address'] ?? null, 'precedence' => $precedence]]);

            return ['batch_id' => $batchId, 'rows' => count($preview['rows']), 'new' => $new, 'updated' => $updated, 'unchanged' => $unchanged, 'kept_actual' => $kept, 'kept_precedence' => $keptPrecedence, 'precedence' => $precedence];
        });
    }

    /**
     * The order of sources named by an option of macro_source_precedence, read
     * from its text: each clause names a source and, where it says so, the kind
     * of observation it is trusted for (actuals, forecasts, rates); a source
     * named without a kind is trusted for every kind. The option text is the
     * governed object, so a new option in the catalogue needs no change here.
     *
     * @return list<array{source:string,kind:string}> in order of trust; kind is actual, forecast, rate or all
     */
    public static function precedenceFromOption(string $option): array
    {
        $out = [];
        foreach (preg_split('/,|\bthen\b/i', $option) ?: [] as $clause) {
            $clause = strtolower(trim($clause));
            if ($clause === '') {
                continue;
            }
            $source = null;
            foreach (self::SOURCE_WORDS as $word => $key) {
                if (str_contains($clause, $word)) {
                    $source = $key;
                    break;
                }
            }
            if ($source === null) {
                // "the sources": the remaining sources in their usual order, each for every kind
                if (str_contains($clause, 'source')) {
                    foreach (array_unique(array_values(self::SOURCE_WORDS)) as $key) {
                        if ($key !== 'manual' && ! in_array($key, array_column($out, 'source'), true)) {
                            $out[] = ['source' => $key, 'kind' => 'all'];
                        }
                    }
                }
                continue;
            }
            $kind = 'all';
            foreach (self::KIND_WORDS as $word => $k) {
                if (str_contains($clause, $word)) {
                    $kind = $k;
                    break;
                }
            }
            $out[] = ['source' => $source, 'kind' => $kind];
        }

        return $out;
    }

    /**
     * Where a source stands for one kind of observation under the order: its
     * place among the entries scoped to that kind (or to every kind), after
     * them its place among the entries scoped to another kind, and last of all
     * a source the option does not name. Lower wins; equal ranks let the
     * arriving row refresh the held one.
     *
     * @param list<array{source:string,kind:string}> $order
     */
    public static function rank(string $source, string $valueType, bool $rateSeries, array $order): int
    {
        $source = $source === 'snapshot' ? 'world_bank' : $source;   // the committed snapshot is the World Bank's data, fetched earlier
        $kind = $rateSeries ? 'rate' : ($valueType === 'forecast' ? 'forecast' : 'actual');
        $scoped = 1000;
        $unscoped = 1000;
        foreach ($order as $i => $entry) {
            if ($entry['source'] !== $source) {
                continue;
            }
            if ($entry['kind'] === $kind || $entry['kind'] === 'all') {
                $scoped = min($scoped, $i);
            } else {
                $unscoped = min($unscoped, 100 + $i);
            }
        }

        return min($scoped, $unscoped);
    }

    /** The base scenario (profile and scenario) actuals are written to; created on first use. */
    public function baseScenario(?int $userId): array
    {
        $profile = DB::table('scenario_profiles')->where('profile_code', 'BASE')->first();
        $profileId = $profile?->id ?? (int) DB::table('scenario_profiles')->insertGetId(['name' => 'Base', 'profile_code' => 'BASE', 'description' => 'The base path: actuals from the sources, forecasts from the IMF WEO', 'created_by' => $userId ?? 1, 'created_at' => now(), 'updated_at' => now()]);
        $scenario = DB::table('scenarios')->where('profile_id', $profileId)->where('is_base_case', 1)->first();
        $scenarioId = $scenario?->id ?? (int) DB::table('scenarios')->insertGetId(['profile_id' => $profileId, 'name' => 'Base case', 'description' => 'Actuals and the central forecast', 'probability' => 1, 'is_base_case' => 1, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return [$profileId, $scenarioId];
    }
}
