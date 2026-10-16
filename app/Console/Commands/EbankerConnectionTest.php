<?php

namespace App\Console\Commands;

use App\Services\Ebanker\DirectRead\EbankerSource;
use App\Services\Ebanker\DirectRead\OracleConnectionSource;
use App\Services\Ebanker\DirectRead\RegisteredStatements;
use Illuminate\Console\Command;
use Throwable;

/**
 * The route 4 connection test (data access request of 14 October 2026, section 6, step 6a, and 6.1):
 * who the system connected as, to which database, from where and when, then the scope check (the
 * MAIIC loan accounts it can see). With --statements, each registered statement is run wrapped so
 * that it returns no row: the grants and the columns are proved without reading data.
 *
 *   php artisan eir:ebanker-connection-test
 *   php artisan eir:ebanker-connection-test --statements
 */
class EbankerConnectionTest extends Command
{
    public const WHO = "SELECT USER AS connected_as, SYS_CONTEXT('USERENV','DB_NAME') AS database_name, SYS_CONTEXT('USERENV','SERVICE_NAME') AS service_name, SYS_CONTEXT('USERENV','IP_ADDRESS') AS connected_from, TO_CHAR(SYSDATE, 'YYYY-MM-DD HH24:MI:SS') AS database_time FROM dual";

    public const SCOPE = 'SELECT COUNT(*) AS maiic_loan_accounts, MAX(a.account_open_date) AS latest_account_opened FROM acmaster a WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145)';

    protected $signature = 'eir:ebanker-connection-test {--statements : Also run each registered statement so that it returns no row, proving its grants and columns}';

    protected $description = 'Route 4: show who the E-Banker connection logs in as, to which database and from where';

    public function handle(EbankerSource $source): int
    {
        try {
            $this->line('Connecting to ' . $source->describe());
            $this->showRow('Who, where and when', $source->select(self::WHO));
            $this->showRow('What it can see', $source->select(self::SCOPE));
        } catch (Throwable $e) {
            $this->error('Connection test failed: ' . OracleConnectionSource::oracleError($e));

            return self::FAILURE;
        }
        $this->warn('Check that database_name is the one intended: the test copy for .env.bootstrap, production or the replica for .env.');
        if (! $this->option('statements')) {
            return self::SUCCESS;
        }
        $rows = []; $failed = 0;
        foreach (RegisteredStatements::runnable() as $q) {
            $binds = [];
            foreach (RegisteredStatements::bindsIn($q->sql) as $b) {
                $binds[$b] = $b === 'watermark' ? 0 : now()->startOfMonth()->toDateString();
            }
            try {
                foreach ($source->select('SELECT * FROM (' . $q->sql . ') WHERE 1 = 0', $binds) as $ignored) {
                }
                $rows[] = [$q->query_id, 'ok'];
            } catch (Throwable $e) {
                $failed++;
                $rows[] = [$q->query_id, OracleConnectionSource::oracleError($e)];
            }
        }
        $this->table(['Statement', 'Result'], $rows);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function showRow(string $title, iterable $rows): void
    {
        foreach ($rows as $row) {
            $this->info($title);
            $this->table(array_keys($row), [array_values($row)]);

            return;
        }
        $this->warn("{$title}: no row returned.");
    }
}
