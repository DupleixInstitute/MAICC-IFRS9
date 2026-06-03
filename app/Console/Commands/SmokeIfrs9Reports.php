<?php

namespace App\Console\Commands;

use App\Http\Controllers\Reports\Ifrs9ReportsController;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Internal smoke test: renders every IFRS 9 report (Inertia + a PDF) so we
 * can confirm the queries run against the real schema without errors.
 */
class SmokeIfrs9Reports extends Command
{
    protected $signature = 'ifrs9:smoke-reports {period=2025-11}';
    protected $description = 'Smoke-test all IFRS 9 reports and PDF rendering.';

    public function handle(Ifrs9ReportsController $c): int
    {
        $period = $this->argument('period');
        $methods = ['ecl', 'accountEcl', 'stageAllocation', 'sicrTrigger', 'stageMigration',
                    'eclReconciliation', 'grossMovement', 'eclCharge', 'pdReport',
                    'lgdCollateral', 'eadReport', 'macroScenario', 'scenarioEcl',
                    'rbmClassification', 'ifrs9VsRbm', 'nplArrears', 'provisionComparison',
                    'fsDisclosure', 'dataQuality', 'sensitivity', 'ews', 'aiNarrative'];

        $failures = 0;

        foreach ($methods as $m) {
            $req = Request::create('/ifrs9-reports', 'GET', ['period' => $period]);
            app()->instance('request', $req);
            try {
                $c->$m($req);
                $this->info("  $m = OK");
            } catch (\Throwable $e) {
                $failures++;
                $this->error("  $m = ERR: {$e->getMessage()} @" . basename($e->getFile()) . ":{$e->getLine()}");
            }
        }

        try {
            Pdf::loadView('manual.ifrs9', ['company' => 'Test', 'generated_at' => now()->format('d M Y')])->output();
            $this->info('  manual PDF = OK');
        } catch (\Throwable $e) {
            $failures++;
            $this->error('  manual PDF = ERR: ' . $e->getMessage());
        }

        try {
            $req = Request::create('/ifrs9-reports/ecl', 'GET', ['period' => $period, 'download' => 'pdf']);
            app()->instance('request', $req);
            $resp = $c->ecl($req);
            $this->info('  report PDF = OK (' . class_basename($resp) . ')');
        } catch (\Throwable $e) {
            $failures++;
            $this->error('  report PDF = ERR: ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine());
        }

        $this->line('');
        $this->line($failures === 0 ? 'ALL REPORTS OK' : "FAILURES: {$failures}");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
