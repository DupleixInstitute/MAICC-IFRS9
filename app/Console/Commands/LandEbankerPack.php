<?php

namespace App\Console\Commands;

use App\Services\Ebanker\PackLandingService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Route 1 of the E-Banker feed (spec v4 section 6.5): land a pack, a folder
 * of extract files plus manifest.json, into the landing zone through the
 * gates of section 6.4.
 *
 *   php artisan eir:land-pack docs/bootstrap/ebanker-pack-2026-10-07 --user=1
 *   php artisan eir:land-pack <folder> --dry-run        gates only, nothing written
 */
class LandEbankerPack extends Command
{
    protected $signature = 'eir:land-pack
        {folder : The pack folder holding the CSV files and manifest.json}
        {--route=ROUTE_1_MANUAL : The route the pack came by}
        {--user= : Id of the person loading the pack; recorded on the load and in the audit log}
        {--dry-run : Run the gates and report; write nothing}';

    protected $description = 'Land an E-Banker pack (extract files plus manifest) into the landing zone through its gates';

    public function handle(PackLandingService $service): int
    {
        $folder = (string) $this->argument('folder');
        if (! is_dir($folder)) {
            $this->error("Not a folder: {$folder}");
            return self::FAILURE;
        }
        try {
            $r = $service->land($folder, $this->option('user') !== null ? (int) $this->option('user') : null, (string) $this->option('route'), (bool) $this->option('dry-run'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->info("Pack {$r['status']} (load {$r['load_id']})");
        $rows = [];
        foreach ($r['gates']['files'] ?? [] as $file => $g) {
            $res = $r['files'][$file] ?? null;
            $landed = is_array($res) && array_key_exists('new', $res) ? "{$res['new']} new, {$res['versioned']} versioned, {$res['unchanged']} unchanged" : ($g['skipped'] ?? '');
            $rows[] = [
                substr($file, 0, 60), $g['query_id'] ?? '', $g['rows'] ?? '',
                $landed,
                ($g['failures'] ?? []) === [] ? 'pass' : implode('; ', array_slice($g['failures'], 0, 2)),
            ];
        }
        $this->table(['File', 'Query', 'Rows', 'Landed', 'Gates'], $rows);
        if ($r['watermarks'] !== []) {
            $this->line('Watermarks: ' . json_encode($r['watermarks']));
        }
        foreach ($r['gates']['pack']['accepted_exceptions'] ?? [] as $e) {
            $this->line('Accepted exception: ' . $e);
        }

        return str_starts_with($r['status'], 'QUARANTINED') || str_ends_with($r['status'], 'QUARANTINED') ? self::FAILURE : self::SUCCESS;
    }
}
