<?php

namespace App\Jobs;

use App\Models\ContractEir;
use App\Services\AuditLoggerService;
use App\Services\Eir\EirRevenueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunEirRevenueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    /** How many blocked contracts are named individually before the list is capped. */
    private const NAMED_BLOCKED_LIMIT = 200;

    /** @param list<string>|null $contractIds */
    public function __construct(
        public readonly string $period,
        public readonly ?array $contractIds = null,
        public readonly bool $recalculate = false,
        public readonly ?int $requestedBy = null,
        public readonly ?string $reason = null,
    ) {}

    /**
     * A contract that produces no amortisation row is the outcome that matters
     * most, so the run records why rather than discarding it: the service
     * already returns a named reason, and without persisting it a partial run
     * is indistinguishable from a complete one.
     */
    public function handle(EirRevenueService $revenue): array
    {
        $ids = $this->contractIds ?? ContractEir::whereNotNull('locked_at')->orderBy('id')->pluck('contract_id')->all();

        $counts = ['CREATED' => 0, 'RECALCULATED' => 0, 'UNCHANGED' => 0, 'BLOCKED' => 0];
        $blocked = [];
        $unclassifiedCash = 0.0;
        $derivedRows = 0;
        $supersededRows = 0;

        foreach (array_values(array_unique($ids)) as $contractId) {
            $result = $revenue->run((string) $contractId, $this->period, $this->recalculate, $this->requestedBy, $this->reason);
            $status = $result['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;

            if ($status === 'BLOCKED') {
                $blocked[(string) $contractId] = $result['error'] ?? 'Unknown reason.';
                continue;
            }
            $unclassifiedCash += (float) ($result['unclassified_cash'] ?? 0);
            $supersededRows += (int) ($result['superseded'] ?? 0);
            if (($result['cash_source'] ?? null) === 'DERIVED') $derivedRows++;
        }

        $summary = [
            'reporting_period' => $this->period,
            'recalculation' => $this->recalculate,
            'reason' => $this->reason,
            'requested' => count($ids),
            'created' => $counts['CREATED'],
            'recalculated' => $counts['RECALCULATED'],
            'unchanged' => $counts['UNCHANGED'],
            'blocked' => $counts['BLOCKED'],
            // Includes later periods voided because their opening balance came
            // from a closing figure this run replaced.
            'superseded_rows' => $supersededRows,
            'cash_derived_from_schedule' => $derivedRows,
            'unclassified_cash' => round($unclassifiedCash, 2),
        ];

        AuditLoggerService::log($this->recalculate ? 'EIR Revenue Recalculated' : 'EIR Revenue Run Completed', ContractEir::class, null, [
            'reporting_period' => $this->period,
            'meta' => $summary + [
                'blocked_contracts' => array_slice($blocked, 0, self::NAMED_BLOCKED_LIMIT, true),
                'blocked_contracts_truncated' => max(0, count($blocked) - self::NAMED_BLOCKED_LIMIT),
            ],
        ]);

        return $summary + ['blocked_contracts' => $blocked];
    }
}
