<?php

namespace App\Console\Commands;

use App\Services\Ebanker\DirectRead\DirectReadRunner;
use App\Services\Ebanker\DirectRead\EbankerSource;
use App\Services\Ebanker\FeedRoute;
use App\Services\Eir\GovernanceService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Route 4 of the E-Banker feed (spec v4 section 6.5): read E-Banker directly with the registered
 * statements and land the read through the gates (DirectReadRunner). Refused unless route 4 is the
 * governed route in force (Governance Centre, ebanker_feed_route), as routes 2 and 3 are.
 *
 *   php artisan eir:direct-read 2026-11 --user=1
 *   php artisan eir:direct-read 2026-11 --dry-run      read and run the gates; land nothing
 */
class DirectReadEbanker extends Command
{
    protected $signature = 'eir:direct-read
        {period : The month-end being read, YYYY-MM}
        {--user=1 : Id of the person the load is recorded against}
        {--dry-run : Read and run the gates; write nothing to the landing zone}';

    protected $description = 'Read E-Banker directly (route 4) with the registered statements and land the read through the gates';

    public function handle(DirectReadRunner $runner, EbankerSource $source, GovernanceService $governance): int
    {
        $route = FeedRoute::check(4, $governance);
        if (! $route['allowed']) {
            $this->error('Refused: ' . $route['reason']);

            return self::FAILURE;
        }
        try {
            $r = $runner->run($source, (string) $this->argument('period'), (int) $this->option('user'), (bool) $this->option('dry-run'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $rows = [];
        foreach ($r['reads'] as $queryId => $read) {
            $landed = $r['landing']['files'] ?? [];
            $res = null;
            foreach ($landed as $file => $f) {
                if (str_starts_with($file, $queryId . '_')) {
                    $res = $f;
                }
            }
            $rows[] = [$queryId, $read['rows'], isset($read['binds']['watermark']) ? '> ' . $read['binds']['watermark'] : 'whole', $read['ms'] . ' ms',
                is_array($res) && array_key_exists('new', $res) ? "{$res['new']} new, {$res['versioned']} versioned, {$res['unchanged']} unchanged" : (($res['failures'] ?? []) !== [] ? implode('; ', array_slice($res['failures'], 0, 2)) : '')];
        }
        $this->table(['Query', 'Rows read', 'Read', 'Time', 'Landed'], $rows);
        $this->info("Pack {$r['landing']['status']} (load {$r['landing']['load_id']}), kept at {$r['pack_dir']}");

        return str_contains($r['landing']['status'], 'QUARANTINED') ? self::FAILURE : self::SUCCESS;
    }
}
