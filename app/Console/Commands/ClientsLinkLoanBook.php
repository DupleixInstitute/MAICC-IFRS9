<?php

namespace App\Console\Commands;

use App\Services\ClientLinkService;
use Illuminate\Console\Command;

/**
 * One client per E-Banker customer number, and every loan linked to it
 * (see ClientLinkService).
 *
 *   php artisan clients:link-loan-book --user=1
 */
class ClientsLinkLoanBook extends Command
{
    protected $signature = 'clients:link-loan-book {--user=}';

    protected $description = 'Create a client for every loan-book customer and link every loan to its client';

    public function handle(ClientLinkService $service): int
    {
        $r = $service->link($this->option('user') !== null ? (int) $this->option('user') : null);
        $this->info("{$r['customers']} customers in the loan book: {$r['created']} new clients, {$r['existing']} already held; {$r['loans_linked']} loans linked; {$r['loans_without_customer']} loans have no customer number.");

        return self::SUCCESS;
    }
}
