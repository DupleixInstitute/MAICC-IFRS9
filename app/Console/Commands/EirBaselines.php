<?php

namespace App\Console\Commands;

use App\Services\Eir\BaselineService;
use Illuminate\Console\Command;

/** The baselines of spec v4 section 9 as a table or JSON (for the compliance workbooks). */
class EirBaselines extends Command
{
    protected $signature = 'eir:baselines {--json}';

    protected $description = 'Run the acceptance ties and golden numbers against the database';

    public function handle(BaselineService $service): int
    {
        $checks = $service->checks();
        if ($this->option('json')) {
            $this->line(json_encode(['database' => config('database.connections.' . config('database.default') . '.database'), 'generated_at' => now()->toDateTimeString(), 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }
        $this->table(['Baseline', 'Expected', 'Actual', 'Result'], array_map(fn ($c) => [$c['what'], $c['expected'], $c['actual'], $c['ok'] ? 'PASS' : 'FAIL'], $checks));

        return count(array_filter($checks, fn ($c) => ! $c['ok'])) ? self::FAILURE : self::SUCCESS;
    }
}
