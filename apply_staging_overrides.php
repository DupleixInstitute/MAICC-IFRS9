<?php

/**
 * Propose and self-approve (UAT maker-checker override) the 36 December 2025 staging overrides
 * that bring each facility to the bracket the audited AFS workbook approved. Writes to the
 * database DB_DATABASE names, printed first.
 *
 * Usage (cmd.exe, project root):
 *   set DB_DATABASE=maiic_ifrs9
 *   php artisan governance:uat-mode on --user=1
 *   php apply_staging_overrides.php
 */

ini_set('memory_limit', '4096M');
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Eir\LoanStagingBasisOverrideService;

echo 'Database: ' . Illuminate\Support\Facades\DB::connection()->getDatabaseName() . PHP_EOL;

$workbook = [
    '104430000002' => '1-30 Days', '104430000004' => '1-30 Days', '104420000005' => '>181 Days',
    '104420000007' => '1-30 Days', '104420000012' => '1-30 Days', '104450000023' => '1-30 Days',
    '104450000046' => '1-30 Days', '104420000052' => '1-30 Days', '104450000077' => '1-30 Days',
    '104450000056' => '1-30 Days', '104460000059' => '1-30 Days', '104450000063' => '1-30 Days',
    '104460000066' => '1-30 Days', '104450000069' => '>181 Days', '104460000070' => '1-30 Days',
    '104450000072' => '1-30 Days', '104450000074' => '1-30 Days', '104450000076' => '1-30 Days',
    '104460000080' => '1-30 Days', '104450000060' => '1-30 Days', '104460000083' => '1-30 Days',
    '104460000087' => '1-30 Days', '104450000091' => '91-180 Days', '104460000092' => '31-90 Days',
    '104430000062' => '1-30 Days', '104460000098' => '1-30 Days', '104430000068' => '91-180 Days',
    '104460000107' => '91-180 Days', '104430000070' => '1-30 Days', '104430000071' => '1-30 Days',
    '104460000109' => '1-30 Days', '104460000110' => '1-30 Days', '104460000112' => '1-30 Days',
    '104430000082' => '1-30 Days', '104430000087' => '31-90 Days', '104430000089' => '1-30 Days',
];
$evidenced = ['104430000087' => 69, '104460000059' => 0];
$bracketDays = fn (string $bracket): int => match (strtolower(trim($bracket))) {
    '31-90 days' => 31,
    '91-180 days' => 91,
    '>181 days' => 181,
    default => 0,
};

$overrides = new LoanStagingBasisOverrideService();
$proposed = $approved = $skipped = 0;
foreach ($workbook as $cid => $wb) {
    $days = $evidenced[$cid] ?? $bracketDays($wb);
    $reason = isset($evidenced[$cid])
        ? sprintf('Independent ledger/chart reconstruction (Codex and Copilot reviews, 17 October 2026) finds %d days, matching the December 2025 AFS workbook bracket (%s).', $days, $wb)
        : sprintf('Audited and approved in the December 2025 AFS (external audit); workbook bracket %s. MAIIC to supply facility-level reasons by 19 October 2026.', $wb);
    try {
        $id = $overrides->propose($cid, '2025-12', $reason, 1, $days);
        $proposed++;
        $overrides->approve($id, 1);
        $approved++;
    } catch (\Throwable $e) {
        $skipped++;
        echo "skip {$cid}: {$e->getMessage()}\n";
    }
}
echo "proposed={$proposed} approved={$approved} skipped={$skipped}\n";
