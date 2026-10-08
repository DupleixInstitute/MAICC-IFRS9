<?php

namespace App\Console\Commands;

use App\Services\Ebanker\PackLandingService;
use App\Services\Eir\GovernanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Route 2 of the E-Banker feed (spec v4 section 6.5): a scheduled export at
 * MAIIC writes the pack to a shared folder or SFTP mount; the system polls
 * the folder and lands every pack it has not yet landed through the same
 * door and gates as route 1. The folder is EBANKER_FEED_FOLDER in the
 * environment; a pack is a sub-folder (or the folder itself) holding
 * manifest.json. A pack already landed (same hash) is skipped; a pack that
 * fails its gates is quarantined and reported, and the folder is left as it
 * is so that nothing is lost.
 *
 *   php artisan eir:poll-feed-folder                 the configured folder
 *   php artisan eir:poll-feed-folder "D:\feeds\ebanker" --user=1
 */
class PollFeedFolder extends Command
{
    protected $signature = 'eir:poll-feed-folder {folder? : The folder to poll (default: EBANKER_FEED_FOLDER)} {--user=1} {--also-route-3 : Also land packs posted by the API into its inbox}';

    protected $description = 'Land every new E-Banker pack found in the feed folder (route 2) through the gates';

    public function handle(PackLandingService $service, GovernanceService $governance): int
    {
        $folder = (string) ($this->argument('folder') ?? config('services.ebanker_feed.folder'));
        if ($folder === '' || ! is_dir($folder)) {
            $this->warn('No feed folder: set EBANKER_FEED_FOLDER or pass the folder. Nothing landed.');
            return self::SUCCESS;
        }
        try {
            $inForce = $governance->get('ebanker_feed_route');
        } catch (Throwable) {
            $inForce = 'Route 1';
        }
        $candidates = [];
        if (is_file($folder . DIRECTORY_SEPARATOR . 'manifest.json')) {
            $candidates[] = $folder;
        }
        foreach (glob($folder . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $sub) {
            if (is_file($sub . DIRECTORY_SEPARATOR . 'manifest.json')) {
                $candidates[] = $sub;
            }
        }
        if ($this->option('also-route-3')) {
            foreach (glob(storage_path('app/ebanker-inbox/*'), GLOB_ONLYDIR) ?: [] as $sub) {
                if (is_file($sub . DIRECTORY_SEPARATOR . 'manifest.json')) {
                    $candidates[] = $sub;
                }
            }
        }
        $rows = [];
        foreach ($candidates as $dir) {
            $hash = hash('sha256', (string) file_get_contents($dir . DIRECTORY_SEPARATOR . 'manifest.json'));
            $existing = DB::table('ebanker_loads')->where('pack_hash', $hash)->first();
            if ($existing && $existing->status === 'LANDED') {
                $rows[] = [basename($dir), 'already landed', "load {$existing->id}"];
                continue;
            }
            try {
                $route = str_starts_with($dir, storage_path('app/ebanker-inbox')) ? PackLandingService::ROUTE_API : PackLandingService::ROUTE_FOLDER;
                $r = $service->land($dir, (int) $this->option('user'), $route);
                $failed = array_filter($r['gates']['files'] ?? [], fn ($f) => ($f['failures'] ?? []) !== []);
                $rows[] = [basename($dir), $r['status'], "load {$r['load_id']}" . ($failed !== [] ? '; ' . implode(' | ', array_map(fn ($n, $f) => $n . ': ' . implode('; ', $f['failures']), array_keys($failed), $failed)) : '')];
            } catch (Throwable $e) {
                $rows[] = [basename($dir), 'ERROR', substr($e->getMessage(), 0, 160)];
            }
        }
        $this->info("Feed folder {$folder}: " . count($candidates) . ' pack(s) found; route in force: ' . $inForce . '.');
        if ($rows !== []) {
            $this->table(['Pack', 'Result', 'Detail'], $rows);
        }

        return in_array('ERROR', array_column($rows, 1), true) ? self::FAILURE : self::SUCCESS;
    }
}
