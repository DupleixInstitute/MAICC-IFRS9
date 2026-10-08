<?php

namespace App\Console\Commands;

use App\Services\Ebanker\LoanBookBuildService;
use App\Services\Eir\StagingService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Stage built loan books under the governed thresholds and the
 * missed-instalment trigger (spec v4 section 3.6, decision D31).
 *
 *   php artisan eir:stage 2024-07 2026-08 --user=1
 */
class StageLoanBooks extends Command
{
    protected $signature = 'eir:stage {from : YYYY-MM} {to? : YYYY-MM} {--user=} {--dry-run}';

    protected $description = 'Stage the loan book of each period: days past due against the governed thresholds, then the missed-instalment trigger';

    public function handle(StagingService $service, LoanBookBuildService $build): int
    {
        $from = (string) $this->argument('from');
        $to = (string) ($this->argument('to') ?? $from);
        $user = $this->option('user') !== null ? (int) $this->option('user') : null;
        try {
            $periods = $build->periods($from, $to);
            $locked = $build->lockedAmong($periods);
            if ($locked !== []) {
                $this->error('Locked period(s) are never restaged: ' . implode(', ', $locked));
                return self::FAILURE;
            }
            $rows = [];
            foreach ($periods as $p) {
                $c = $service->stage($p, $user, (bool) $this->option('dry-run'));
                $rows[] = [$p, $c['rows'], $c['stage1'], $c['stage2'], $c['stage3'], $c['by_dpd'], $c['by_instalments'], $c['by_sicr'], $c['by_bucket']];
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->table(['Period', 'Rows', 'Stage 1', 'Stage 2', 'Stage 3', 'by DPD', 'by instalments', 'by SICR', 'DPD from buckets'], $rows);
        if ($this->option('dry-run')) {
            $this->warn('Dry run: nothing written.');
        }

        return self::SUCCESS;
    }
}
