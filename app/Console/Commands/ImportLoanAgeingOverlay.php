<?php

namespace App\Console\Commands;

use App\Services\Eir\LoanAgeingOverlayService;
use Illuminate\Console\Command;

/**
 * Reads a completed Finance ageing-overlay CSV (LoanAgeingOverlayService::exportTemplate's own
 * output, filled in) and records it. Never changes a loan's booked figures, staging or ECL —
 * this is Finance's own position kept beside the system's, pending the governance decision on
 * whether the staging policy should ever prefer a schedule-corrected count over the raw report
 * bucket (December 2025 Loan Book Investigation Findings v1, section 10).
 *
 *   php artisan eir:import-ageing-overlay "docs/bootstrap/models/Finance Ageing Overlay - December 2025.csv" --user=1
 */
class ImportLoanAgeingOverlay extends Command
{
    protected $signature = 'eir:import-ageing-overlay {file} {--user= : Id of the person importing it}';

    protected $description = "Import Finance's completed ageing-overlay CSV; records it beside the system's own figures, changes nothing booked";

    public function handle(LoanAgeingOverlayService $service): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path)) {
            $this->error("No file at {$path}.");

            return self::FAILURE;
        }
        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;
        $result = $service->import($path, $userId);
        $this->info("{$result['imported']} row(s) recorded from " . basename($path) . '.');

        return self::SUCCESS;
    }
}
