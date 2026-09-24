<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Eir\DisbursementImportService;
use App\Services\Imports\MappedFileReader;
use Illuminate\Console\Command;
use Throwable;

/**
 * Loads the per-drawdown extract from the command line, through the same reader
 * and service as the intake screen. Header aliases apply, so a file spooled
 * from E-Banker loads with no mapping; a saved intake template for
 * disbursements is honoured as well.
 */
class ImportDisbursements extends Command
{
    protected $signature = 'eir:import-disbursements
        {file : Path to the CSV or spreadsheet holding the drawdowns}
        {--user= : The id of the person loading the file; recorded on every row and in the audit log}';

    protected $description = 'Import the per-drawdown extract (one row per tranche) with ISO-date and positive-amount checks';

    public function handle(MappedFileReader $reader, DisbursementImportService $service): int
    {
        $file = (string) $this->argument('file');
        if (! is_readable($file)) {
            $this->error("File not readable: {$file}");

            return self::INVALID;
        }

        $userId = trim((string) $this->option('user'));
        if ($userId === '' || ! ctype_digit($userId) || User::query()->whereKey((int) $userId)->doesntExist()) {
            $this->error('Give --user=<id> of an existing user: a drawdown is audited evidence and every load is recorded against a person.');

            return self::INVALID;
        }
        auth()->onceUsingId((int) $userId);

        try {
            // No transforms: the disbursement date must reach the service exactly
            // as the file wrote it, so a dd/mm cell is refused and not re-read.
            $read = $reader->read($file, 'disbursements');
            $result = $service->import($read['rows'], null, (int) $userId);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Drawdowns loaded from {$file}.");
        $this->table(
            ['Rows in file', 'Loaded', 'Already stored', 'Duplicates in file', 'Facilities', 'Total amount', 'Skipped'],
            [[
                $result['source_rows'], $result['loaded_rows'], $result['already_stored'],
                $result['duplicate_source_rows'], $result['contracts'],
                number_format($result['total_amount'], 2), count($result['skipped']),
            ]]
        );
        $this->line(sprintf(
            'Drawdowns in the file run from %s to %s.',
            $result['first_date'] ?? '-', $result['last_date'] ?? '-'
        ));

        if ($result['skipped'] !== []) {
            $this->newLine();
            $this->warn(count($result['skipped']) . ' row(s) were not loaded:');
            $this->table(['Row', 'Reason'], array_map(null, array_keys($result['skipped']), array_values($result['skipped'])));
        }

        if ($result['notes'] !== [] && $this->getOutput()->isVerbose()) {
            $this->newLine();
            foreach ($result['notes'] as $note) {
                $this->line('  ' . $note);
            }
        }

        return self::SUCCESS;
    }
}
