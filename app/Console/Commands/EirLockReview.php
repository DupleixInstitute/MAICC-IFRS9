<?php

namespace App\Console\Commands;

use App\Services\Eir\EirLockReviewService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Review flags on locked EIRs (EirLockReviewService, 15 October 2026).
 *
 *   php artisan eir:lock-review --detect
 *       raise the flags the system can see (held postings naming the loan, fees recorded after the lock)
 *   php artisan eir:lock-review --raise=104430000071 --reason="Finance's 2025 fee workbook ..." --evidence="..."
 *       raise a flag by hand, where the evidence is outside the system
 *
 * A flag never reopens an EIR or changes a figure.
 */
class EirLockReview extends Command
{
    protected $signature = 'eir:lock-review {--detect : Raise the automatic flags} {--raise= : A loan whose locked EIR to flag} {--reason= : Why it needs review} {--evidence= : Where the evidence is} {--user=1 : The user recorded as raising it}';

    protected $description = 'Flag locked EIRs whose fee evidence was not in the inputs they were locked on; never reopens anything';

    public function handle(EirLockReviewService $reviews): int
    {
        try {
            if ($this->option('detect')) {
                $this->info($reviews->detect((int) $this->option('user')) . ' new automatic flag(s).');
            }
            if ($this->option('raise')) {
                $id = $reviews->raise((string) $this->option('raise'), (string) $this->option('reason'), ['evidence' => (string) $this->option('evidence')], (int) $this->option('user'));
                $this->info("Flag {$id} raised on {$this->option('raise')}.");
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->line(count($reviews->open()) . ' open flag(s).');

        return self::SUCCESS;
    }
}
