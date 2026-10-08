<?php

namespace App\Console\Commands;

use App\Services\Ebanker\LoanBookBuildService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Build the monthly loan book from the landing zone (spec v4 section 6.6).
 *
 *   php artisan eir:build-loan-books 2024-07 2026-08 --user=1 --approve-by=2
 *   php artisan eir:build-loan-books 2025-12 2025-12 --method=bootstrap --dry-run
 *   php artisan eir:build-loan-books 2026-09 2026-09 --method=report --file="Loan Book Report Sep 2026.csv" --user=1 --approve-by=2
 *   php artisan eir:build-loan-books --approve=7 --user=2         approve a proposed build
 *
 * Without --approve-by or --bootstrap the build is proposed and previewed,
 * nothing is written; a second person approves it. --bootstrap approves
 * under the automated label, which the screen shows is not a MAIIC approval.
 */
class BuildLoanBooks extends Command
{
    protected $signature = 'eir:build-loan-books
        {from? : First period, YYYY-MM}
        {to? : Last period, YYYY-MM (defaults to from)}
        {--method= : A|bootstrap, B|derive or C|report; default is the method in force on each period end}
        {--file= : Method C only: the printed Loan Book Report as CSV}
        {--user= : Id of the person requesting the build}
        {--approve-by= : Id of the second person approving it}
        {--bootstrap : Approve under the bootstrap label (automated data-readiness, not a MAIIC approval)}
        {--approve= : Id of a proposed build to approve (with --user as the approver)}
        {--retire-stale : Also remove rows of the period that no build wrote and this build does not produce (old test imports); out-of-scope GLs are kept}
        {--dry-run : Preview the differences; write nothing}';

    protected $description = 'Build loan_books for a range of months from the landing zone by method A, B or C, under maker-checker';

    public function handle(LoanBookBuildService $service): int
    {
        $user = $this->option('user') !== null ? (int) $this->option('user') : null;
        $approver = $this->option('approve-by') !== null ? (int) $this->option('approve-by') : null;
        $label = $this->option('bootstrap') ? LoanBookBuildService::BOOTSTRAP_LABEL : null;
        try {
            if ($this->option('approve') !== null) {
                if ($user === null) {
                    $this->error('--user is the approver and is required.');
                    return self::FAILURE;
                }
                $r = $service->approve((int) $this->option('approve'), $user);
            } elseif (in_array(strtolower((string) $this->option('method')), ['c', 'report'], true)) {
                if ($this->option('file') === null || $this->argument('from') === null) {
                    $this->error('Method C needs --file and the period.');
                    return self::FAILURE;
                }
                $r = $service->buildFromReport((string) $this->option('file'), (string) $this->argument('from'), $user, $approver, $label, (bool) $this->option('dry-run'));
                if (($r['gates']['failures'] ?? []) !== []) {
                    $this->error('Report QUARANTINED: ' . implode('; ', $r['gates']['failures']));
                    return self::FAILURE;
                }
            } else {
                if ($this->argument('from') === null) {
                    $this->error('Give the first period (YYYY-MM).');
                    return self::FAILURE;
                }
                $from = (string) $this->argument('from');
                $to = (string) ($this->argument('to') ?? $from);
                $r = $service->build($from, $to, $this->option('method'), $user, $approver, $label, (bool) $this->option('dry-run'), (bool) $this->option('retire-stale'));
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Build {$r['status']}" . ($r['build_id'] ? " (build {$r['build_id']})" : ''));
        $rows = [];
        foreach ($r['periods'] as $period => $p) {
            $rows[] = [$period, $p['method'], $p['rows'], $p['flagged'] ?? 0, $p['new'] ?? '', $p['changed'] ?? '', $p['unchanged'] ?? '', $p['not_in_build'] ?? '', isset($p['stale']) ? count($p['stale']) . ' / ' . ($p['retired'] ?? 0) : ''];
        }
        if ($rows !== []) {
            $this->table(['Period', 'Method', 'Rows', 'Flagged', 'New', 'Changed', 'Unchanged', 'Not in build', 'Stale / retired'], $rows);
        }
        foreach ($r['periods'] as $period => $p) {
            foreach (array_slice($p['differences'] ?? [], 0, 10) as $d) {
                $this->line(sprintf('  %s %s: %s -> %s (%s -> %s)', $period, $d['contract_id'], number_format($d['was'], 2), number_format($d['now'], 2), $d['was_method'] ?? '-', $d['now_method']));
            }
            if (count($p['differences'] ?? []) > 10) {
                $this->line('  ... ' . (count($p['differences']) - 10) . ' more differences in this period; the full list is on the build record');
            }
        }
        if ($r['status'] === 'PROPOSED') {
            $this->warn('Proposed and previewed; nothing written. A second person approves with --approve=' . $r['build_id'] . ' --user=<their id>.');
        }

        return self::SUCCESS;
    }
}
