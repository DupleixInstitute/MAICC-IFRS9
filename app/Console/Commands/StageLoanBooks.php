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

    protected $description = 'Stage the loan book of each period: days past due against the governed thresholds, then the missed-instalment trigger and the approved qualitative SICR triggers';

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
            // a month with no loan book has nothing to stage: refused by name, never reported as a
            // success with 0 rows (final check of 14 October 2026)
            foreach ($periods as $p) {
                if (! \Illuminate\Support\Facades\DB::table('loan_books')->where('reporting_period', $p)->exists()) {
                    $this->error(\App\Http\Controllers\MaintenanceController::noLoanBookMessage($p, 'Staging'));

                    return self::FAILURE;
                }
            }
            $rows = [];
            foreach ($periods as $p) {
                $c = $service->stage($p, $user, (bool) $this->option('dry-run'));
                if ((int) $c['rows'] === 0) {
                    $this->error("Staging is refused for {$p}: the loan book holds no row to stage.");

                    return self::FAILURE;
                }
                $rows[] = [$p, $c['rows'], $c['stage1'], $c['stage2'], $c['stage3'], $c['by_dpd'], $c['by_instalments'], $c['by_sicr'], $c['by_credit_impaired'], $c['by_bucket'], $c['held_by_cure']];
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->table(['Period', 'Rows', 'Stage 1', 'Stage 2', 'Stage 3', 'by DPD', 'by instalments', 'by SICR trigger', 'by credit-impaired trigger', 'DPD from buckets', 'held by cure'], $rows);
        if ($this->option('dry-run')) {
            $this->warn('Dry run: nothing written.');
        }

        return self::SUCCESS;
    }
}
