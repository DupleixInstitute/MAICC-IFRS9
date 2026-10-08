<?php

namespace App\Console\Commands;

use App\Services\MegaFarm\MegaFarmEclService;
use Illuminate\Console\Command;
use Throwable;

/** The Mega Farm programme's ECL for a period under the governed scope and method (spec v4 section 16). */
class MegaFarmEcl extends Command
{
    protected $signature = 'megafarm:ecl {period : YYYY-MM} {--user=} {--bootstrap}';

    protected $description = 'Run the Mega Farm programme through the ECL module: the governed scope, PD method, scalar and MAIIC share';

    public function handle(MegaFarmEclService $service): int
    {
        try {
            $r = $service->run((string) $this->argument('period'), $this->option('user') !== null ? (int) $this->option('user') : null, $this->option('bootstrap') ? \App\Services\Scenario\ScenarioSetService::BOOTSTRAP_LABEL : null);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->info("Mega Farm {$r['period']}: {$r['loans']} loans, gross " . number_format($r['gross'], 0) . '; scope: ' . $r['scope'] . '; method: ' . $r['method']);
        $this->line('By stage: ' . json_encode($r['by_stage']));
        if ($r['declined']) {
            $this->warn('Declined: ' . $r['declined']);
            return self::SUCCESS;
        }
        $this->line('PD by stage: ' . json_encode($r['pd_by_stage']) . ($r['scalar'] !== null ? '; scalar ' . $r['scalar'] . ' (measured ' . ($r['basis']['measured_scalar'] ?? '-') . ', ceiling ' . ($r['basis']['ceiling'] ?? '-') . ')' : '') . '; LGD ' . round($r['lgd'] * 100, 2) . '%');
        $this->info('Programme ECL ' . number_format($r['programme_ecl'], 0) . '; MAIIC share ' . ($r['share'] * 100) . '% = ' . number_format($r['maiic_ecl'], 0));

        return self::SUCCESS;
    }
}
