<?php

namespace App\Console\Commands;

use App\Services\Ifrs9\SpBasisEclService;
use Illuminate\Console\Command;
use Throwable;

/**
 * The S&P basis for one or more months: the December 2025 model's method as an alternative ECL, for
 * comparison only (spec v4 section 17). It changes no booked figure.
 *
 *   php artisan ifrs9:sp-basis 2025-12 2026-09
 */
class SpBasisEcl extends Command
{
    protected $signature = 'ifrs9:sp-basis {periods* : Months, YYYY-MM} {--user=1 : The user the run is recorded against}';

    protected $description = 'Run the S&P basis (the December 2025 model\'s PD method) beside the system\'s ECL, for comparison only';

    public function handle(SpBasisEclService $service): int
    {
        $failed = 0;
        foreach ($this->argument('periods') as $period) {
            try {
                $r = $service->run($period, (int) $this->option('user'));
                $this->info(sprintf('%s: run %d, %d loans, S&P basis ECL %s against the system\'s %s (undiscounted)', $period, $r['run_id'], $r['loans'],
                    number_format($r['ecl'], 2), number_format($r['system_ecl'], 2)));
            } catch (Throwable $e) {
                $failed++;
                $this->error("{$period}: {$e->getMessage()}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
