<?php

namespace App\Console\Commands;

use App\Services\Ebanker\TrialBalanceLandingService;
use Illuminate\Console\Command;

/**
 * Ingests the monthly trial-balance corpus (spec §3.4, Phase 2.8) through the
 * landing zone (spec v4 section 6.3; system audit of 9 October 2026,
 * finding M1): every file becomes a load with its hash and gate results, its
 * GL lines are landed as raw rows under TB_01, and gl_trial_balance_lines is
 * derived from the landed rows. The output shape is what the direct importer
 * produced, so the December 2025 ties read the same table.
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
                            {--period=2025-12-01 : Period the AFS sheet belongs to}
                            {--user= : The user the loads are recorded against}';

    protected $description = 'Land MAIIC monthly trial balances in the landing zone and derive the GL side of the EIR reconciliation';

    public function handle(TrialBalanceLandingService $landing): int
    {
        $directory = rtrim((string) $this->argument('directory'), '/\\');
        if (! is_dir($directory)) {
            $this->error("Not a directory: {$directory}");

            return self::FAILURE;
        }
        if ((glob($directory . '/*.xls') ?: []) === []) {
            $this->error("No .xls trial balances found in {$directory}");

            return self::FAILURE;
        }

        $user = $this->option('user') !== null ? (int) $this->option('user') : null;
        $afs = $this->option('afs') ? (string) $this->option('afs') : null;
        $r = $landing->landDirectory($directory, $user, $afs, (string) $this->option('sheet'), (string) $this->option('period'));

        $rows = [];
        foreach ($r['files'] as $f) {
            if ($f['status'] === 'QUARANTINED') {
                continue;
            }
            $rows[] = [
                $f['period'] . ($f['basis'] === 'PRECLOSING' ? ' (pre-closing)' : ''),
                $f['file'],
                $f['lines'],
                number_format($f['debit'], 2),
                $f['status'] === 'ALREADY_LANDED' ? 'already landed (load ' . $f['load_id'] . ')' : $f['new'] . ' new / ' . $f['versioned'] . ' versioned / ' . $f['unchanged'] . ' unchanged (load ' . $f['load_id'] . ')',
            ];
        }
        $this->table(['Period', 'File', 'GL lines', 'Total (Dr = Cr)', 'Landed'], $rows);

        foreach ($r['failures'] as $failure) {
            // A file that does not tie is refused, not imported with a warning:
            // a partially-correct ledger reconciles to something, so nobody
            // goes looking for what is wrong with it. Its rows sit in
            // quarantine against the load so the file can be opened.
            $this->error('QUARANTINED  ' . $failure);
        }

        $d = $r['derived'];
        $this->info(sprintf('gl_trial_balance_lines derived from the landing zone: %d lines over %d periods (%d new, %d updated, %d removed).', $d['lines'], count($d['periods']), $d['imported'], $d['updated'], $d['removed']));

        if (! $afs) {
            $this->warn(
                'December 2025 was landed post-closing and carries no income statement (§3.4.2). '
                . 'Re-run with --afs=… to land the pre-closing sheet, or December income reads as zero.'
            );
        }

        $this->info(sprintf('%d file(s) landed, %d quarantined.', count($rows), count($r['failures'])));

        return $r['failures'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
