<?php
// App\Jobs\CalculateLGDMasterJob.php

namespace App\Jobs;

use App\Models\LoanBook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateLGDJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $startPeriod;
    public $reportingPeriod;
    public $portfolioGroupId;

    public function __construct(string $startPeriod, string $reportingPeriod, int $portfolioGroupId)
    {
        $this->startPeriod = $startPeriod;
        $this->reportingPeriod = $reportingPeriod;
        $this->portfolioGroupId = $portfolioGroupId;
    }

    public function handle()
    {
        // Dispatch a sub-job per chunk
        LoanBook::where('loan_portfolio_id', $this->portfolioGroupId)
            ->whereRaw("LEFT(reporting_period,7) = ?", [$this->startPeriod])
            ->where('calculated_ifrs9_stage', '3')
            ->orderBy('contract_id')
            ->chunk(500, function($contracts) {
                ProcessLGDChunkJob::dispatch(
                    $contracts->pluck('contract_id')->toArray(),
                    $this->startPeriod,
                    $this->reportingPeriod,
                    $this->portfolioGroupId
                );
            });

        \Log::info('LGD Master job dispatched all chunk jobs');
    }
}
