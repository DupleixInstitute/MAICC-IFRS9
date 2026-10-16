<?php

namespace App\Console\Commands;

use App\Support\AuditChain;
use Illuminate\Console\Command;

/**
 * Recompute the audit log's chain (Staging Module Specification v1, section
 * 8.5) and name the first entry that does not follow: an entry changed,
 * removed or inserted outside the system. Also says whether the database
 * guard that refuses an update or delete is installed.
 *
 *   php artisan audit:verify-chain
 */
class VerifyAuditChain extends Command
{
    protected $signature = 'audit:verify-chain';

    protected $description = 'Check that the audit log is an unbroken chain and say whether its append-only guard is installed';

    public function handle(): int
    {
        $r = AuditChain::verify();
        $guard = $r['guard'] ? 'the append-only guard is installed' : 'the append-only guard is NOT installed (the chain still detects a change)';
        if ($r['intact']) {
            $this->info("Audit chain intact: {$r['entries']} chained entries; {$guard}." . ($r['reason'] ? " ({$r['reason']})" : ''));

            return self::SUCCESS;
        }
        $this->error("Audit chain BROKEN at entry {$r['first_broken']}: {$r['reason']}. {$r['entries']} entries checked before it; {$guard}.");

        return self::FAILURE;
    }
}
