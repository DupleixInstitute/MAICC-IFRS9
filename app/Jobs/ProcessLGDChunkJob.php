<?php
// App\Jobs\ProcessLGDChunkJob.php

namespace App\Jobs;

use App\Models\LoanBook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessLGDChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $contractIds;
    public $startPeriod;
    public $reportingPeriod;
    public $portfolioGroupId;

    public function __construct(array $contractIds, string $startPeriod, string $reportingPeriod, int $portfolioGroupId)
    {
        $this->contractIds = $contractIds;
        $this->startPeriod = $startPeriod;
        $this->reportingPeriod = $reportingPeriod;
        $this->portfolioGroupId = $portfolioGroupId;
    }

    public function handle()
    {
        ini_set('max_execution_time', 0);

        \Log::info("Processing LGD chunk: " . implode(',', $this->contractIds));

        $startBalance = 0;
        $endBalance = 0;
        $totalDisbursments = 0;
        $totalRecoveredAmount = 0;
        $curedLoanIds = [];

        $endContracts = LoanBook::whereIn('contract_id', $this->contractIds)
            ->where('loan_portfolio_id', $this->portfolioGroupId)
            ->whereRaw("LEFT(reporting_period,7) = ?", [$this->reportingPeriod])
            ->select('contract_id', 'principal_balance', 'calculated_ifrs9_stage')
            ->get()
            ->keyBy('contract_id');

        foreach ($this->contractIds as $contractId) {
            $startContract = LoanBook::where('loan_portfolio_id', $this->portfolioGroupId)
                ->where('contract_id', $contractId)
                ->whereRaw("LEFT(reporting_period,7) = ?", [$this->startPeriod])
                ->first(['principal_balance']);

            if (!$startContract) continue;

            $startBal = $startContract->principal_balance;
            $startBalance += $startBal;

            if (isset($endContracts[$contractId])) {
                $endContract = $endContracts[$contractId];
                $endBal = $endContract->principal_balance;
                $endBalance += $endBal;

                if ($endBal > $startBal) {
                    $totalDisbursments += ($endBal - $startBal);
                }

                if ($endBal < $startBal) {
                    $totalRecoveredAmount += ($startBal - $endBal);
                }

                if (in_array($endContract->calculated_ifrs9_stage, ['1', '2'])) {
                    $curedLoanIds[] = $contractId;
                }
            } else {
                // Fully recovered (paid in full)
                $totalRecoveredAmount += $startBal;
            }
        }

        // Optionally: persist intermediate results to DB or log
        \Log::info("LGD Chunk Summary", [
            'startBalance' => $startBalance,
            'endBalance' => $endBalance,
            'recovered' => $totalRecoveredAmount,
            'disbursed' => $totalDisbursments,
            'curedCount' => count($curedLoanIds)
        ]);
    }
}
