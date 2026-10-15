<?php

namespace App\Console\Commands;

use App\Services\Decisions\DecisionsPack;
use App\Services\Decisions\DecisionsPackExporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Writes the decisions pack of this database (Staging Module Specification
 * v1, section 8.10): what people decided here, by business key, for
 * eir:bootstrap --with-decisions to carry into a fresh database. It reads
 * only; nothing in this database changes (acceptance 24). Run it on MAIIC's
 * server by MAIIC ICT, before any fresh bootstrap that is to replace a
 * database people have worked in (decision S14).
 *
 *   php artisan decisions:export
 *   php artisan decisions:export --pack=2026-10-16 --out=/path/to/folder
 */
class ExportDecisions extends Command
{
    protected $signature = 'decisions:export
        {--pack= : The name of the pack (default: today, YYYY-MM-DD)}
        {--out= : The folder to write it to (default: docs/bootstrap/decisions/<pack>)}';

    protected $description = 'Export the decisions people made in this database (read-only) as a decisions pack for a fresh bootstrap';

    public function handle(DecisionsPackExporter $exporter): int
    {
        $pack = (string) ($this->option('pack') ?: now()->toDateString());
        $out = (string) ($this->option('out') ?: base_path(DecisionsPack::FOLDER . '/' . $pack));
        try {
            $r = $exporter->export($out, $pack);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Decisions pack {$pack} written to {$r['folder']} from database {$r['manifest']['source_database']}; nothing in the database was changed.");
        foreach ($r['counts'] as $kind => $n) {
            $this->line(sprintf('  %-20s %6d', $kind, $n));
        }
        $this->line('Tie-out the replay will check: ' . json_encode($r['manifest']['tie_out']));

        return self::SUCCESS;
    }
}
