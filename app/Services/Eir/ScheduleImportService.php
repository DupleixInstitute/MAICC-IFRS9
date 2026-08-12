<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Support\ContractId;
use Illuminate\Support\Facades\DB;

/**
 * Schedule intake (docs/EIR_Build.md §4 Phase 2): takes mapped rows
 * (contract_id, due_date, principal_due, interest_due[, fee_due]) from
 * MappedFileReader and loads them into contract_cashflow_schedule as
 * version 1, IMPORTED.
 *
 * Failures are per-contract, never per-file: one bad contract's rows are
 * rejected with a named reason while the rest load. Contracts not yet on
 * the loan tape are reported as "held" — re-import after the tape lands.
 * Version-1 schedules are never overwritten here; restructures (version 2)
 * and audit-logged corrections are a separate flow.
 */
class ScheduleImportService
{
    /** |Σ principal − drawn| / drawn beyond this rejects the contract. */
    public const PRINCIPAL_TOLERANCE = 0.01;

    /**
     * @param list<array{contract_id: mixed, due_date: ?string, principal_due: float, interest_due: float, fee_due?: float}> $rows
     * @return array{
     *   loaded_contracts: int, loaded_rows: int,
     *   skipped: array<string,string>, held: array<string,string>,
     *   coverage: array{covered: int, total: int}
     * }
     */
    public function import(array $rows, float $principalTolerance = self::PRINCIPAL_TOLERANCE): array
    {
        $byContract = [];
        foreach ($rows as $row) {
            $contractId = ContractId::normalise($row['contract_id'] ?? null);
            if ($contractId === null) {
                continue;
            }
            $byContract[$contractId][] = $row;
        }

        $skipped = [];
        $held    = [];
        $loadedContracts = 0;
        $loadedRows      = 0;

        foreach ($byContract as $contractId => $contractRows) {
            $exists = DB::table('contract_cashflow_schedule')
                ->where('contract_id', $contractId)
                ->exists();
            if ($exists) {
                $skipped[$contractId] = 'schedule already exists — use the restructure (v2) or audit-logged correction flow';
                continue;
            }

            $loan = DB::table('loan_books')
                ->where('contract_id', $contractId)
                ->orderByDesc('reporting_period')
                ->first(['contract_id', 'disbursed', 'principal_balance', 'create_date']);
            if (! $loan) {
                $held[$contractId] = 'contract not on the loan tape yet — re-import after the loan book lands';
                continue;
            }

            $badDates = array_filter($contractRows, fn ($r) => empty($r['due_date']));
            if ($badDates !== []) {
                $skipped[$contractId] = count($badDates) . ' row(s) with missing/unparseable due dates';
                continue;
            }

            usort($contractRows, fn ($a, $b) => strcmp($a['due_date'], $b['due_date']));

            $dates = array_column($contractRows, 'due_date');
            if (count($dates) !== count(array_unique($dates))) {
                $skipped[$contractId] = 'duplicate due dates in file';
                continue;
            }

            // Σ principal must reconcile to what actually left the door —
            // the check that catches truncated exports.
            $drawn = (float) ($loan->disbursed ?: 0) > 0
                ? (float) $loan->disbursed
                : (float) ($loan->principal_balance ?: 0);
            $principalSum = array_sum(array_map(fn ($r) => (float) ($r['principal_due'] ?? 0), $contractRows));
            if ($drawn > 0 && abs($principalSum - $drawn) / $drawn > $principalTolerance) {
                $skipped[$contractId] = sprintf(
                    'principal sum %s does not reconcile to drawn %s (tolerance %s%%)',
                    number_format($principalSum, 2),
                    number_format($drawn, 2),
                    $principalTolerance * 100
                );
                continue;
            }

            DB::transaction(function () use ($contractId, $contractRows, $loan, &$loadedRows) {
                $now = now();
                DB::table('contract_cashflow_schedule')->insert(
                    array_map(fn ($r) => [
                        'contract_id'      => $contractId,
                        'schedule_version' => 1,
                        'effective_from'   => $loan->create_date,
                        'due_date'         => $r['due_date'],
                        'principal_due'    => (float) ($r['principal_due'] ?? 0),
                        'interest_due'     => (float) ($r['interest_due'] ?? 0),
                        'fee_due'          => (float) ($r['fee_due'] ?? 0),
                        'schedule_source'  => 'IMPORTED',
                        'source_system'    => ($r['source_system'] ?? null) ?: null,
                        'source_reference' => ($r['source_reference'] ?? null) ?: null,
                        'external_transaction_id' => ($r['external_transaction_id'] ?? null) ?: null,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ], $contractRows)
                );

                ContractEir::updateOrCreate(
                    ['contract_id' => $contractId],
                    ['schedule_source' => 'IMPORTED']
                );

                $loadedRows += count($contractRows);
            });

            $loadedContracts++;
        }

        return [
            'loaded_contracts' => $loadedContracts,
            'loaded_rows'      => $loadedRows,
            'skipped'          => $skipped,
            'held'             => $held,
            'coverage'         => $this->coverage(),
        ];
    }

    /**
     * The audit-pack number: contracts with any schedule vs contracts on
     * the latest loan tape.
     */
    public function coverage(): array
    {
        // SUBSTR works on both MySQL and the sqlite test database.
        $period = DB::table('loan_books')->max(DB::raw('SUBSTR(reporting_period, 1, 7)'));
        if (! $period) {
            return ['covered' => 0, 'total' => 0];
        }

        $total = DB::table('loan_books')
            ->whereRaw('SUBSTR(reporting_period, 1, 7) = ?', [$period])
            ->count();
        $covered = DB::table('loan_books')
            ->whereRaw('SUBSTR(reporting_period, 1, 7) = ?', [$period])
            ->whereIn('contract_id', fn ($q) => $q->select('contract_id')->from('contract_cashflow_schedule'))
            ->count();

        return ['covered' => $covered, 'total' => $total];
    }
}
