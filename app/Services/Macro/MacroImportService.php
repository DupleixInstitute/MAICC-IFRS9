<?php

namespace App\Services\Macro;

use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\DB;

/**
 * Commits a source's rows to macro_statistics_data as a batch (spec v4
 * section 13.3, MS-3): every commit records the source, the address, when
 * it was fetched, who committed it and how many rows; every observation
 * points to its batch. Observations are keyed on series, period and
 * scenario, so a refresh updates the value and keeps the key. An API
 * import writes to the base scenario, created on first use.
 */
class MacroImportService
{
    /** @return array{batch_id:int,rows:int,new:int,updated:int,unchanged:int} */
    public function commit(array $preview, ?int $userId, string $sourceKey = 'world_bank', ?string $fileSha = null, ?string $note = null): array
    {
        $series = DB::table('macro_statistics')->where('statistic_code', $preview['series_code'])->first();
        if ($series === null || $preview['rows'] === []) {
            return ['batch_id' => 0, 'rows' => 0, 'new' => 0, 'updated' => 0, 'unchanged' => 0];
        }
        [$profileId, $scenarioId] = $this->baseScenario($userId);

        return DB::transaction(function () use ($preview, $series, $userId, $sourceKey, $fileSha, $note, $profileId, $scenarioId) {
            $batchId = (int) DB::table('macro_source_import_batches')->insertGetId([
                'source' => $sourceKey, 'address' => $preview['address'] ?? null, 'country' => $preview['country'] ?? 'MWI', 'file_sha256' => $fileSha,
                'fetched_at' => $preview['fetched_at'] ?? now(), 'committed_by' => $userId, 'rows' => count($preview['rows']),
                'series' => json_encode([['code' => $series->statistic_code, 'indicator' => $preview['indicator_code'] ?? null, 'rows' => count($preview['rows']), 'first' => $preview['rows'][0]['period'], 'last' => end($preview['rows'])['period']]]),
                'note' => $note, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $new = $updated = $unchanged = 0;
            foreach ($preview['rows'] as $r) {
                $existing = DB::table('macro_statistics_data')->where('macro_stat_definition_id', $series->id)->where('period', $r['period'])->where('scenario_id', $scenarioId)->first();
                $values = ['value' => $r['value'], 'is_forecast' => $r['value_type'] === 'forecast' ? 1 : 0, 'actual_value' => $r['value_type'] === 'actual' ? $r['value'] : null,
                    'source' => $preview['source'] ?? $sourceKey, 'source_import_batch_id' => $batchId, 'updated_at' => now()];
                if ($existing === null) {
                    DB::table('macro_statistics_data')->insert($values + ['macro_stat_definition_id' => $series->id, 'scenario_profile_id' => $profileId, 'scenario_id' => $scenarioId, 'period' => $r['period'], 'created_by' => $userId ?? 1, 'created_at' => now()]);
                    $new++;
                } elseif (abs((float) $existing->value - round((float) $r['value'], 4)) > 0.00005 || (int) $existing->is_forecast !== $values['is_forecast']) { // the column holds four decimals
                    DB::table('macro_statistics_data')->where('id', $existing->id)->update($values);
                    $updated++;
                } else {
                    DB::table('macro_statistics_data')->where('id', $existing->id)->update(['source_import_batch_id' => $batchId]);
                    $unchanged++;
                }
            }
            AuditLoggerService::log('Macro Series Imported', 'macro_source_import_batches', $batchId, ['new_values' => ['series' => $series->statistic_code, 'source' => $sourceKey, 'rows' => count($preview['rows']), 'new' => $new, 'updated' => $updated], 'meta' => ['user' => $userId, 'address' => $preview['address'] ?? null]]);

            return ['batch_id' => $batchId, 'rows' => count($preview['rows']), 'new' => $new, 'updated' => $updated, 'unchanged' => $unchanged];
        });
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
