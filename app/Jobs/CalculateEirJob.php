<?php

namespace App\Jobs;

use App\Services\Eir\EirCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Calculates a batch independently; blocked contracts do not stop the batch. */
class CalculateEirJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    /** @param list<string> $contractIds */
    public function __construct(
        public readonly array $contractIds,
        public readonly ?int $requestedBy = null,
    ) {}

    public function handle(EirCalculationService $calculations): void
    {
        foreach (array_values(array_unique($this->contractIds)) as $contractId) {
            $calculations->calculate((string) $contractId, $this->requestedBy);
        }
    }
}
