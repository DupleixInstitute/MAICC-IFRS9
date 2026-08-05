<?php

namespace App\Services\Eir;

use Illuminate\Support\Facades\DB;

/** Imports Extract B without confusing actual cash movements with contractual promises. */
class ExtractBImportService
{
    public const SOURCE = 'MAIIC_EXTRACT_B';

    public function __construct(
        private readonly ScheduleImportService $schedules,
        private readonly FeeImportService $fees,
    ) {}

    public function import(array $rows): array
    {
        $scheduleRows = [];
        $feeRows = [];
        $actualRows = [];
        $held = [];
        $conflicts = [];
        $seen = [];
        $duplicates = 0;

        foreach ($rows as $index => $row) {
            $account = trim((string) ($row['contract_id'] ?? ''));
            $customer = trim((string) ($row['customer_id'] ?? ''));
            $subAccount = trim((string) ($row['sub_account_no'] ?? ''));
            $reference = trim((string) ($row['gl_posting_ref'] ?? ''));
            $externalId = $reference !== '' ? $reference : implode('|', [
                trim((string) ($row['run_id'] ?? '')), $account, $subAccount,
                (string) ($row['transaction_date'] ?? ''), (string) ($index + 1),
            ]);

            if (isset($seen[$externalId])) { $duplicates++; continue; }
            $seen[$externalId] = true;

            $loan = DB::table('loan_books')->where('contract_id', $account)
                ->orderByDesc('reporting_period')->first(['contract_id', 'customer_id']);
            if (! $loan) {
                $held[$account ?: 'row ' . ($index + 2)] = 'loan account is not present in the imported loan book';
                continue;
            }
            if ($customer !== '' && trim((string) $loan->customer_id) !== '' && $customer !== trim((string) $loan->customer_id)) {
                $conflicts[$account] = "customer {$customer} does not match loan-book customer {$loan->customer_id}";
                continue;
            }

            $base = ['contract_id' => $account, 'source_system' => self::SOURCE,
                'source_reference' => $reference ?: null, 'external_transaction_id' => $externalId];
            $flag = strtoupper(trim((string) ($row['scheduled_actual_flag'] ?? '')));

            if ($flag === 'SCHEDULED') {
                $scheduleRows[] = $base + ['due_date' => $row['transaction_date'] ?? null,
                    'principal_due' => (float) ($row['principal_component'] ?? 0),
                    'interest_due' => (float) ($row['interest_component'] ?? 0),
                    'fee_due' => (float) ($row['fee_component'] ?? 0)];
                continue;
            }
            if ($flag !== 'ACTUAL') {
                $conflicts[$account] = "unknown SCHEDULED_ACTUAL_FLAG '{$flag}'";
                continue;
            }

            $actualRows[] = $base + ['customer_id' => $customer ?: null, 'sub_account_no' => $subAccount ?: null,
                'transaction_date' => $row['transaction_date'] ?? null,
                'transaction_type' => trim((string) ($row['transaction_type'] ?? 'Other')) ?: 'Other',
                'principal_component' => (float) ($row['principal_component'] ?? 0),
                'interest_component' => (float) ($row['interest_component'] ?? 0),
                'fee_component' => (float) ($row['fee_component'] ?? 0), 'total_amount' => (float) ($row['total_amount'] ?? 0),
                'balance_after_transaction' => ($row['balance_after_transaction'] ?? '') === '' ? null : (float) $row['balance_after_transaction'],
                'row_note' => trim((string) ($row['row_note'] ?? '')) ?: null];

            if ((float) ($row['fee_component'] ?? 0) !== 0.0) {
                $feeRows[] = $base + ['fee_type' => 'other',
                    'description' => trim((string) ($row['transaction_type'] ?? 'Fee from Extract B')),
                    'amount' => (float) $row['fee_component'], 'transaction_date' => $row['transaction_date'] ?? null,
                    'gl_account_ref' => $reference ?: null];
            }
        }

        $actualLoaded = $this->insertActuals($actualRows);
        $scheduleResult = $this->schedules->import($scheduleRows);
        $feeResult = $this->fees->import($feeRows);

        return ['source_rows' => count($rows), 'scheduled_rows_routed' => count($scheduleRows),
            'actual_rows_loaded' => $actualLoaded, 'fee_rows_routed' => count($feeRows),
            'duplicate_source_rows' => $duplicates, 'held' => $held + $scheduleResult['held'],
            'skipped' => $conflicts + $scheduleResult['skipped'], 'loaded_contracts' => $scheduleResult['loaded_contracts'],
            'loaded_rows' => $scheduleResult['loaded_rows'], 'fee_result' => $feeResult, 'coverage' => $scheduleResult['coverage']];
    }

    private function insertActuals(array $rows): int
    {
        $loaded = 0;
        foreach ($rows as $row) {
            if (DB::table('eir_actual_transactions')->where('source_system', self::SOURCE)
                ->where('external_transaction_id', $row['external_transaction_id'])->exists()) continue;
            DB::table('eir_actual_transactions')->insert($row + ['created_at' => now(), 'updated_at' => now()]);
            $loaded++;
        }
        return $loaded;
    }
}
