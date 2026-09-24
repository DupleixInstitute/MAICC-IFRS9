<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Eir\ReferenceRateImportService;
use App\Services\Imports\MappedFileReader;
use Illuminate\Console\Command;
use Throwable;

/**
 * Loads the reference-rate series (File C) from the command line, through
 * the same reader and service as the intake screen. Header aliases apply,
 * so the repaired PLR file loads with no mapping; a saved intake template
 * for reference_rates is honoured as well.
 */
class ImportReferenceRates extends Command
{
    protected $signature = 'eir:import-reference-rates
        {file : Path to the CSV or spreadsheet holding the series}
        {--index=PLR : The index the rows belong to when the file carries none}
        {--user= : The id of the person loading the file; recorded on every row and in the audit log}';

    protected $description = 'Import the reference-rate series (the Reserve Bank prime lending rate) with ISO-date and ordering checks';

    public function handle(MappedFileReader $reader, ReferenceRateImportService $service): int
    {
        $file = (string) $this->argument('file');
        if (! is_readable($file)) {
            $this->error("File not readable: {$file}");

            return self::INVALID;
        }

        $userId = trim((string) $this->option('user'));
        if ($userId === '' || ! ctype_digit($userId) || User::query()->whereKey((int) $userId)->doesntExist()) {
            $this->error('Give --user=<id> of an existing user: the series is audited evidence and every load is recorded against a person.');

            return self::INVALID;
        }
        auth()->onceUsingId((int) $userId);

        try {
            // No transforms: the effective date must reach the service exactly
            // as the file wrote it (decision D19).
            $read = $reader->read($file, 'reference_rates');
            $result = $service->import($read['rows'], (string) $this->option('index'), null, (int) $userId);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Reference rates loaded from {$file}.");
        $this->table(
            ['Index', 'Rows in file', 'Loaded', 'Already stored', 'Duplicates in file', 'Rate changes in file', 'Repeated rates', 'Skipped'],
            [[
                $result['index_code'], $result['source_rows'], $result['loaded_rows'], $result['unchanged'],
                $result['duplicate_source_rows'], $result['rate_changes'], $result['repeated_rate_rows'], count($result['skipped']),
            ]]
        );

        $series = $result['series'];
        $this->line(sprintf(
            'Stored %s series: %d rows, %d rate changes, %s to %s, current rate %s%%.',
            $series['index_code'], $series['rows'], $series['changes'],
            $series['first_date'] ?? '-', $series['last_date'] ?? '-',
            $series['current_rate'] === null ? '-' : number_format($series['current_rate'], 2)
        ));

        if ($result['skipped'] !== []) {
            $this->newLine();
            $this->warn(count($result['skipped']) . ' row(s) were not loaded:');
            $this->table(['Index @ date', 'Reason'], array_map(null, array_keys($result['skipped']), array_values($result['skipped'])));
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
