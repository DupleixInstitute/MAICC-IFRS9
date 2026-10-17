<?php

/**
 * Propose and self-approve (UAT maker-checker override) the gross amount overrides backfilled
 * from the December 2025 audited workbook, for every facility in the overlay CSV whose finance
 * amount differs from the system amount by more than a trivial rounding difference (currently
 * only MUSECO, contract 104420000005: system K138,135,680.80, workbook K0 — confirmed with the
 * user on 17 October 2026 before running).
 *
 * Usage (cmd.exe, from the project root, after `set DB_DATABASE=maiic_ifrs9_candidate_20261017`
 * and `php artisan governance:uat-mode on --user=1`):
 *   php apply_gross_amount_overrides.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Eir\LoanGrossAmountOverrideService;

$csvPath = __DIR__ . '/docs/bootstrap/models/Finance Gross Amount Overlay - December 2025.csv';
$userId = 1;
$service = new LoanGrossAmountOverrideService();

$rows = array_map('str_getcsv', file($csvPath));
$header = array_shift($rows);

$proposed = $approved = $skipped = 0;
foreach ($rows as $row) {
    if (count($row) < count($header)) {
        continue;
    }
    $r = array_combine($header, $row);
    $system = (float) $r['system_amount'];
    $reference = $r['reference_amount'] !== '' ? (float) $r['reference_amount'] : null;
    if ($reference === null || abs($system - $reference) <= 1000.0) {
        $skipped++;
        continue;
    }
    $reason = sprintf(
        '%s: audited and approved in the December 2025 AFS at %s; MAIIC to supply facility-level reasons by 19 October 2026.',
        $r['customer_name'], number_format($reference, 2)
    );
    try {
        $id = $service->propose($r['contract_id'], $r['reporting_period'], $reason, $userId, $reference);
        $proposed++;
        $service->approve($id, $userId);
        $approved++;
        echo "{$r['contract_id']} ({$r['customer_name']}): proposed+approved #{$id}, {$system} -> {$reference}\n";
    } catch (\Throwable $e) {
        echo "{$r['contract_id']}: SKIPPED - {$e->getMessage()}\n";
        $skipped++;
    }
}

echo "proposed={$proposed} approved={$approved} skipped={$skipped}\n";
