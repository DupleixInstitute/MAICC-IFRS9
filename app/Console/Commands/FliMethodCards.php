<?php

namespace App\Console\Commands;

use App\Services\Fli\TransmissionMethodCatalogue;
use Illuminate\Console\Command;

/** The transmission-method cards on the console (spec v4 section 14.7). */
class FliMethodCards extends Command
{
    protected $signature = 'fli:method-cards {--period=} {--json}';

    protected $description = 'Show each transmission method: what it does, its formula, what it implies, its preconditions checked against the data, a worked example';

    public function handle(TransmissionMethodCatalogue $catalogue): int
    {
        $cards = $catalogue->cards($this->option('period'));
        if ($this->option('json')) {
            $this->line(json_encode($cards, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }
        foreach ($cards as $c) {
            $this->info($c['title'] . ($c['in_force'] ? '  [in force]' : '') . ($c['seeded'] ? '  [seeded default]' : '') . ($c['available'] ? '' : '  [preconditions not met]'));
            $this->line('  ' . wordwrap($c['what'], 110, "\n  "));
            $this->line('  Formula: ' . $c['formula']);
            foreach ($c['implies'] as $i) { $this->line('  - ' . $i); }
            foreach ($c['preconditions'] as $p) { $this->line('  ' . ($p['met'] ? '[met]    ' : '[not met]') . ' ' . $p['name'] . ': ' . $p['figure']); }
            if ($c['example']) {
                $this->line('  Example on ' . $c['example']['contract_id'] . ' (' . $c['example']['customer_name'] . ', stage ' . $c['example']['stage'] . '): ' . implode('; ', $c['example']['steps']));
            }
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
