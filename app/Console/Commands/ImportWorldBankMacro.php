<?php

namespace App\Console\Commands;

use App\Services\Macro\MacroImportService;
use App\Services\Macro\WorldBankFetcherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import every macro series that carries a World Bank code (spec v4 section
 * 13.3): non-fatal per series, for the bootstrap and the monthly scheduler.
 * When the source cannot be reached, or --offline is passed, the committed
 * snapshot under docs/bootstrap/macro/ is used, so a server without
 * internet still bootstraps; --snapshot writes a fresh snapshot after a
 * live fetch, for committing.
 *
 *   php artisan macro:import-worldbank
 *   php artisan macro:import-worldbank --code=GDP_GROWTH --from=2000
 *   php artisan macro:import-worldbank --offline
 *   php artisan macro:import-worldbank --snapshot
 */
class ImportWorldBankMacro extends Command
{
    protected $signature = 'macro:import-worldbank {--code= : One series code} {--country= : ISO3 (default MWI)} {--from=} {--to=} {--user=1} {--offline : Use the committed snapshot} {--snapshot : Write docs/bootstrap/macro/worldbank-MWI.json after fetching}';

    protected $description = 'Import the World Bank macro series for Malawi into the base scenario, with provenance; falls back to the committed snapshot';

    public function handle(WorldBankFetcherService $fetcher, MacroImportService $importer): int
    {
        $user = (int) $this->option('user');
        $country = $fetcher->country($this->option('country'));
        $snapshotPath = base_path('docs/bootstrap/macro/worldbank-' . $country . '.json');
        $snapshot = is_file($snapshotPath) ? (json_decode(file_get_contents($snapshotPath), true) ?: []) : [];
        $q = DB::table('macro_statistics')->whereNotNull('external_codes')->where('external_codes', 'like', '%world_bank%');
        if ($this->option('code')) {
            $q->where('statistic_code', $this->option('code'));
        }
        $series = $q->orderBy('statistic_code')->get();
        if ($series->isEmpty()) {
            $this->error('No series with a World Bank code; run MacroSeriesSeeder first.');
            return self::FAILURE;
        }
        $rows = [];
        $fresh = [];
        $live = 0; $fromSnapshot = 0; $skipped = 0;
        foreach ($series as $s) {
            $preview = null;
            if (! $this->option('offline')) {
                $preview = $fetcher->fetch($s, $country, $this->option('from') ? (int) $this->option('from') : null, $this->option('to') ? (int) $this->option('to') : null);
                if ($preview['rows'] !== []) {
                    $live++;
                    $fresh[$s->statistic_code] = $preview;
                }
            }
            if (($preview === null || $preview['rows'] === []) && isset($snapshot[$s->statistic_code]) && ($snapshot[$s->statistic_code]['rows'] ?? []) !== []) {
                $preview = $snapshot[$s->statistic_code] + ['source' => 'World Bank Open Data (committed snapshot)', 'note' => 'from the committed snapshot of ' . ($snapshot[$s->statistic_code]['fetched_at'] ?? '?')];
                $fromSnapshot++;
            }
            if ($preview === null || $preview['rows'] === []) {
                $skipped++;
                $rows[] = [$s->statistic_code, $preview['indicator_code'] ?? '-', 0, '-', '-', 'SKIPPED: ' . ($preview['note'] ?? 'no code')];
                continue;
            }
            $r = $importer->commit($preview, $user, str_contains($preview['source'], 'snapshot') ? 'snapshot' : 'world_bank', null, $preview['note'] ?? null);
            $rows[] = [$s->statistic_code, $preview['indicator_code'] ?? '-', count($preview['rows']), $preview['rows'][0]['year'], end($preview['rows'])['year'], "batch {$r['batch_id']}: {$r['new']} new, {$r['updated']} updated, {$r['unchanged']} unchanged" . (str_contains($preview['source'], 'snapshot') ? ' (snapshot)' : '')];
        }
        $this->table(['Series', 'Indicator', 'Rows', 'First', 'Last', 'Result'], $rows);
        $this->info("{$live} series fetched live, {$fromSnapshot} from the committed snapshot, {$skipped} skipped.");
        if ($this->option('snapshot') && $fresh !== []) {
            if (! is_dir(dirname($snapshotPath))) {
                mkdir(dirname($snapshotPath), 0775, true);
            }
            $merged = $snapshot;
            foreach ($fresh as $code => $p) {
                $merged[$code] = array_intersect_key($p, array_flip(['series_code', 'indicator_code', 'country', 'address', 'fetched_at', 'rows']));
            }
            ksort($merged);
            file_put_contents($snapshotPath, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Snapshot written: ' . $snapshotPath . ' (' . count($merged) . ' series).');
        }

        return self::SUCCESS;
    }
}
