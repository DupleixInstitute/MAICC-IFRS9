<?php

namespace App\Console\Commands;

use App\Http\Controllers\ExpectedCreditLossController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Reusable ECL (re)calculation for a reporting period — a CLI wrapper around
 * the existing ExpectedCreditLossController::calculateECL so back-dated
 * periods or post-fix recalculations can run from the command line.
 */
class RecalculateEcl extends Command
{
    protected $signature = 'ifrs9:recalculate-ecl
        {period : Reporting period, e.g. 2025-11}
        {--level=portfolio : portfolio|sector}
        {--portfolio= : loan_portfolios.id (required when level=portfolio)}
        {--sector= : industry_types.code (required when level=sector)}
        {--pd=pd_prefli : pd_prefli|pd_post_fli}
        {--lgd=collection_lgd : customer_lgd|collection_lgd|both}';

    protected $description = 'Recalculate IFRS 9 ECL for a reporting period (CLI wrapper around the ECL controller).';

    public function handle(ExpectedCreditLossController $controller): int
    {
        $period = $this->argument('period');
        $level  = $this->option('level');

        $payload = [
            'ecl_calculation_level' => $level,
            'reporting_period'      => $period . '-01',
            'pd_type'               => $this->option('pd'),
            'lgd_type'              => $this->option('lgd'),
        ];

        if ($level === 'portfolio') {
            $payload['ecl_calculation_id'] = $this->option('portfolio');
        } else {
            $payload['ecl_calculation_code'] = $this->option('sector');
        }

        $this->info("Calculating ECL for {$period} ({$level}) pd={$payload['pd_type']} lgd={$payload['lgd_type']} ...");

        try {
            $request = Request::create('/expected-credit-loss/calculations', 'POST', $payload);
            $controller->calculateECL($request);
        } catch (\Throwable $e) {
            $this->error('ECL calculation failed: ' . $e->getMessage());
            $this->line($e->getFile() . ':' . $e->getLine());
            return self::FAILURE;
        }

        $this->info('ECL calculation completed for ' . $period . '.');
        return self::SUCCESS;
    }
}
