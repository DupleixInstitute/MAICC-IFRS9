<?php

namespace App\Console\Commands;

use App\Services\ReperformanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Re-perform a month (Staging Module Specification v1, section 8.8, item 6;
 * acceptance 19): check that the figures its run left can be reproduced from
 * what the run froze, loan by loan, and name every difference.
 *
 *   php artisan run-records:reperform 2026-09 --user=1
 *       in place: the inputs against the snapshot, every loan staged again
 *       under the frozen rules, every loan's figures against the run's;
 *       nothing is written but the re-performance's record
 *   php artisan run-records:reperform 2026-09 --user=1 --workspace=maiic_ifrs9_2026_09_reperform
 *       the engines run again on a copy of the database, then every loan's
 *       figures in the copy against the run's; the copy is dropped unless --keep
 */
class ReperformMonth extends Command
{
    protected $signature = 'run-records:reperform {period : YYYY-MM} {--run= : The run to re-perform (default: the month\'s run of record, else its latest month-end run)}
        {--user= : The person re-performing it} {--workspace= : Re-run the engines in a throwaway copy of the database named ..._reperform} {--keep : Keep the workspace copy afterwards}';

    protected $description = 'Re-perform a month from its run record, in place or in a workspace copy, and name every difference loan by loan';

    public function handle(ReperformanceService $service): int
    {
        $period = (string) $this->argument('period');
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('The month is YYYY-MM.');

            return self::FAILURE;
        }
        $user = $this->option('user') !== null ? (int) $this->option('user') : null;
        try {
            $run = $service->runFor($period, $this->option('run') !== null ? (int) $this->option('run') : null);
            $id = $this->option('workspace')
                ? $service->inWorkspace($run, (string) $this->option('workspace'), $user, (bool) $this->option('keep'), fn ($m) => $this->line($m))
                : $service->inPlace($run, $user);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $r = DB::table('reperformances')->find($id);
        $summary = json_decode((string) $r->summary, true) ?: [];
        $this->info("Re-performance {$id} of run {$run->id} ({$period}, " . strtolower(str_replace('_', ' ', $r->mode)) . "): {$r->status}; "
            . ($summary['loans'] ?? 0) . ' loans checked, ' . ($summary['differences'] ?? 0) . ' difference(s) ' . json_encode($summary['by_check'] ?? []));
        foreach (DB::table('reperformance_rows')->where('reperformance_id', $id)->limit(20)->get() as $row) {
            $this->line("  {$row->check} " . ($row->contract_id ?? '') . " {$row->item}: {$row->stored} -> {$row->reperformed}" . ($row->note ? " ({$row->note})" : ''));
        }

        return $r->status === 'REPRODUCED' ? self::SUCCESS : self::FAILURE;
    }
}
