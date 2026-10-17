<?php

namespace App\Console\Commands;

use App\Services\Eir\LoanGrossAmountOverlayService;
use Illuminate\Console\Command;

/**
 * Reads a completed Finance gross-amount-overlay CSV (LoanGrossAmountOverlayService::
 * exportTemplate's own output, filled in) and records it. Never changes a loan's booked
 * carrying amount — this is Finance's own position kept beside the system's own rebuilt
 * figure (MAIIC Finance Overlay Importers Specification v1, section 4.2).
 *
 *   php artisan eir:import-gross-amount-overlay "docs/bootstrap/models/Finance Gross Amount Overlay - December 2025.csv" --user=1
 */
class ImportLoanGrossAmountOverlay extends Command
{
    protected $signature = 'eir:import-gross-amount-overlay {file} {--user= : Id of the person importing it}';

    protected $description = "Import Finance's completed gross-amount-overlay CSV; records it beside the system's own figures, changes nothing booked";

    public function handle(LoanGrossAmountOverlayService $service): int
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
