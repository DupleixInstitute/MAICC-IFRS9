<?php

namespace App\Console\Commands;

use App\Services\Eir\EirLedgerCashService;
use Illuminate\Console\Command;

/**
 * Copies the actual cash of every loan from the landed E-Banker ledger into
 * the EIR revenue engine's actual-cash table (see EirLedgerCashService).
 *
 *   php artisan eir:load-ledger-cash
 *   php artisan eir:load-ledger-cash --to=2026-08-31 --user=1
 */
class EirLoadLedgerCash extends Command
{
    protected $signature = 'eir:load-ledger-cash {--to= : Last posting date to read (default: the last loaded)} {--user=}';

    protected $description = 'Load the actual cash of every loan from the landed E-Banker ledger for the EIR revenue engine';

    public function handle(EirLedgerCashService $service): int
    {
        $r = $service->load($this->option('to') ?: null, $this->option('user') !== null ? (int) $this->option('user') : null);
        $this->info("Ledger to {$r['last_date']}: {$r['accounts']} loan accounts, {$r['postings']} cash postings read, {$r['loaded']} new rows loaded.");
        foreach ($r['by_type'] as $type => $n) {
            $this->line("  {$type}: {$n}");
        }
        $rv = $r['reversals'];
        $this->line("Reversals (306, 343, 901): {$rv['reversals']}, {$rv['paired']} paired to the posting they reverse, {$rv['unpaired']} unpaired" . ($rv['unpaired_ids'] !== [] ? ' (vouchers ' . implode(', ', $rv['unpaired_ids']) . ')' : '') . '.');

        return self::SUCCESS;
    }
}
