<?php

namespace App\Console\Commands;

use App\Services\Ebanker\FeedRoute;
use App\Services\Ebanker\FeedZip;
use App\Services\Ebanker\PackLandingService;
use App\Services\Eir\GovernanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use ZipArchive;

/**
 * Route 2 of the E-Banker feed (spec v4 section 6.5): a scheduled export at
 * MAIIC writes the pack to a shared folder or SFTP mount; the system polls
 * the folder and lands every pack it has not yet landed through the same
 * door and gates as route 1. The folder is EBANKER_FEED_FOLDER in the
 * environment; a pack is a sub-folder (or the folder itself) holding
 * manifest.json, or a zip of one, which is unpacked beside itself with every
 * entry name checked first: an entry that climbs out of the pack folder is
 * skipped and logged (system audit of 9 October 2026, finding M14). A pack
 * already landed (same hash) is skipped; a pack that fails its gates is
 * quarantined and reported, and the folder is left as it is so that nothing
 * is lost.
 *
 * The governed route is enforced (14 October 2026): the folder is polled only
 * while route 2 is in force, and the API inbox (--also-route-3) only while
 * route 3 is, unless the setting opens routes 1 to 3 together (FeedRoute);
 * otherwise the poller refuses with the reason and lands nothing. A missing
 * value refuses too; it used to be read as route 1. The API inbox is the
 * database's own (DatabaseScope).
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
        // the governed route in force decides which of the two doors may land (FeedRoute)
        $route2 = FeedRoute::check(2, $governance);
        $route3 = $this->option('also-route-3') ? FeedRoute::check(3, $governance) : null;
        $inForce = $route2['in_force'] ?? 'none approved';
        if (! $route2['allowed'] && ($route3 === null || ! $route3['allowed'])) {
            $this->error('Refused: ' . $route2['reason'] . ($route3 !== null && $route3['reason'] !== $route2['reason'] ? ' ' . $route3['reason'] : ''));

            return self::FAILURE;
        }
        $candidates = [];
        if ($route2['allowed']) {
            if ($folder === '' || ! is_dir($folder)) {
                $this->warn('No feed folder: set EBANKER_FEED_FOLDER or pass the folder. Nothing landed from a folder.');
            } else {
                $this->unpackZips($folder);
                if (is_file($folder . DIRECTORY_SEPARATOR . 'manifest.json')) {
                    $candidates[] = $folder;
                }
                foreach (glob($folder . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $sub) {
                    if (is_file($sub . DIRECTORY_SEPARATOR . 'manifest.json')) {
                        $candidates[] = $sub;
                    }
                }
            }
        } else {
            $this->warn('The feed folder is not polled: ' . $route2['reason']);
        }
        $inbox = \App\Support\DatabaseScope::storagePath('ebanker-inbox');
        if ($route3 !== null && $route3['allowed']) {
            foreach (glob($inbox . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $sub) {
                if (is_file($sub . DIRECTORY_SEPARATOR . 'manifest.json')) {
                    $candidates[] = $sub;
                }
            }
        } elseif ($route3 !== null) {
            $this->warn('The API inbox is not landed: ' . $route3['reason']);
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
                $route = str_starts_with($dir, $inbox) ? PackLandingService::ROUTE_API : PackLandingService::ROUTE_FOLDER;
                $r = $service->land($dir, (int) $this->option('user'), $route);
                $failed = array_filter($r['gates']['files'] ?? [], fn ($f) => ($f['failures'] ?? []) !== []);
                $rows[] = [basename($dir), $r['status'], "load {$r['load_id']}" . ($failed !== [] ? '; ' . implode(' | ', array_map(fn ($n, $f) => $n . ': ' . implode('; ', $f['failures']), array_keys($failed), $failed)) : '')];
            } catch (Throwable $e) {
                $rows[] = [basename($dir), 'ERROR', substr($e->getMessage(), 0, 160)];
            }
        }
        $this->info(($route2['allowed'] ? "Feed folder {$folder}" : 'API inbox') . ': ' . count($candidates) . ' pack(s) found; route in force: ' . $inForce . '.');
        if ($rows !== []) {
            $this->table(['Pack', 'Result', 'Detail'], $rows);
        }

        return in_array('ERROR', array_column($rows, 1), true) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * A zip in the feed folder is unpacked into a folder named after it (so
     * a zip already unpacked is not unpacked again), through the entry-name
     * guard: an unsafe entry is skipped with a log line and a warning here,
     * and the rest of the pack goes on to the gates.
     */
    private function unpackZips(string $folder): void
    {
        foreach (glob($folder . DIRECTORY_SEPARATOR . '*.zip') ?: [] as $path) {
            $dir = $folder . DIRECTORY_SEPARATOR . pathinfo($path, PATHINFO_FILENAME) . '-' . substr((string) hash_file('sha256', $path), 0, 8);
            if (is_dir($dir)) {
                continue;
            }
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) {
                $this->warn('Not a readable zip, left as it is: ' . basename($path));
                Log::warning('E-Banker feed folder: not a readable zip, left as it is: ' . $path);
                continue;
            }
            mkdir($dir, 0775, true);
            $r = FeedZip::extract($zip, $dir, 'feed folder');
            $zip->close();
            foreach ($r['skipped'] as $name) {
                $this->warn(sprintf('%s: entry "%s" skipped, its name climbs out of the pack folder (audit finding M14)', basename($path), $name));
            }
            $this->line(sprintf('Unpacked %s: %d entries%s.', basename($path), $r['extracted'], $r['skipped'] !== [] ? ', ' . count($r['skipped']) . ' skipped' : ''));
        }
    }
}
