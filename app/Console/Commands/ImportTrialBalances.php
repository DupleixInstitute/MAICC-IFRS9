<?php

namespace App\Console\Commands;

use App\Models\GlTrialBalanceLine;
use App\Services\Eir\TrialBalanceImportService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Ingests the monthly trial-balance corpus (spec §3.4, Phase 2.8).
 *
 * The files live outside the repo — client data, unanonymised (open item #15) —
 * so the directory is a parameter rather than a fixture path.
 *
 *   php artisan eir:import-trial-balances "…/Dupleix 2026"
 *   php artisan eir:import-trial-balances "…/Dupleix 2026" --afs="…/AFS Final TB….xlsx"
 *
 * December needs the --afs flag to be complete: the standalone monthly file is
 * post-closing and carries no income statement at all (§3.4.2). Without it the
 * corpus loads happily and December silently reports zero income, which is why
 * the command says so at the end rather than leaving it to be discovered.
 */
class ImportTrialBalances extends Command
{
    protected $signature = 'eir:import-trial-balances
                            {directory : Folder holding the Trial Balance_*.xls files}
                            {--afs= : AFS bridge workbook, for the pre-closing December sheet}
                            {--sheet=Final E-Banker TB Dec 2025 : Sheet name inside the AFS workbook}
                            {--period=2025-12-01 : Period the AFS sheet belongs to}';

    protected $description = 'Import MAIIC monthly trial balances into the GL side of the EIR reconciliation';

    public function handle(TrialBalanceImportService $importer): int
    {
        $directory = rtrim((string) $this->argument('directory'), '/\\');
        if (! is_dir($directory)) {
            $this->error("Not a directory: {$directory}");

            return self::FAILURE;
        }

        $files = glob($directory . '/*.xls') ?: [];
        sort($files);

        if ($files === []) {
            $this->error("No .xls trial balances found in {$directory}");

            return self::FAILURE;
        }

        $rows = [];
        $failures = [];

        foreach ($files as $file) {
            try {
                $result = $importer->import($file);
                $rows[] = [
                    $result['period'],
                    basename($file),
                    $result['lines'],
                    number_format($result['debit'], 2),
                    $result['imported'] . ' new / ' . $result['updated'] . ' updated',
                ];
            } catch (Throwable $e) {
                $failures[] = basename($file) . ' — ' . $e->getMessage();
            }
        }

        if ($afs = $this->option('afs')) {
            try {
                $result = $importer->import(
                    (string) $afs,
                    GlTrialBalanceLine::BASIS_PRECLOSING,
                    (string) $this->option('period'),
                    (string) $this->option('sheet')
                );
                $rows[] = [
                    $result['period'] . ' (pre-closing)',
                    basename((string) $afs),
                    $result['lines'],
                    number_format($result['debit'], 2),
                    $result['imported'] . ' new / ' . $result['updated'] . ' updated',
                ];
            } catch (Throwable $e) {
                $failures[] = basename((string) $afs) . ' — ' . $e->getMessage();
            }
        }

        $this->table(['Period', 'File', 'GL lines', 'Total (Dr = Cr)', 'Written'], $rows);

        foreach ($failures as $failure) {
            // A file that does not tie is refused, not imported with a warning:
            // a partially-correct ledger reconciles to something, so nobody
            // goes looking for what is wrong with it.
            $this->error('REJECTED  ' . $failure);
        }

        if (! $this->option('afs')) {
            $this->warn(
                'December 2025 was imported post-closing and carries no income statement (§3.4.2). '
                . 'Re-run with --afs=… to load the pre-closing sheet, or December income reads as zero.'
            );
        }

        $this->info(sprintf('%d file(s) imported, %d rejected.', count($rows), count($failures)));

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }
}
