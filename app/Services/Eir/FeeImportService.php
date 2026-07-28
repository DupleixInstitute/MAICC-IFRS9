<?php

namespace App\Services\Eir;

use Illuminate\Support\Facades\DB;

/**
 * Fee intake (docs/EIR_Build.md §4 Phase 2): loads mapped fee rows
 * (contract_id, fee_type, amount[, basis, gl_account_ref]) into
 * contract_fees. First real load is the Dec-2025 EIR assessment workbook.
 *
 * Amounts are signed — netting lines are legitimate (a negative legal-cost
 * line nets against the legal fee) — but they are counted and reported so
 * a sign-convention problem in a file is visible at import time, alongside
 * the per-type totals the operator eyeballs against the GL.
 */
class FeeImportService
{
    public const KNOWN_TYPES = ['arrangement', 'legal', 'appraisal', 'default', 'levy', 'other'];

    /**
     * @param list<array{contract_id: mixed, fee_type: ?string, amount: float, basis?: ?string, gl_account_ref?: ?string}> $rows
     * @return array{
     *   loaded_rows: int, skipped_rows: int,
     *   unknown_types: array<string,int>, negative_lines: int,
     *   totals_by_type: array<string,float>
     * }
     */
    public function import(array $rows): array
    {
        $inserts       = [];
        $skipped       = 0;
        $unknownTypes  = [];
        $negativeLines = 0;
        $totals        = [];
        $now           = now();

        foreach ($rows as $row) {
            $contractId = trim((string) ($row['contract_id'] ?? ''));
            $amount     = (float) ($row['amount'] ?? 0);

            if ($contractId === '' || $amount === 0.0) {
                $skipped++;
                continue;
            }

            $type = strtolower(trim((string) ($row['fee_type'] ?? '')));
            if (! in_array($type, self::KNOWN_TYPES, true)) {
                $unknownTypes[$type ?: '(blank)'] = ($unknownTypes[$type ?: '(blank)'] ?? 0) + 1;
                $type = 'other';
            }

            if ($amount < 0) {
                $negativeLines++;
            }

            $basis = strtoupper(trim((string) ($row['basis'] ?? '')));

            $inserts[] = [
                'contract_id'    => $contractId,
                'fee_type'       => $type,
                'amount'         => $amount,
                'basis'          => in_array($basis, ['ON_APPROVED', 'ON_DRAWN'], true) ? $basis : 'ON_APPROVED',
                'integral'       => true,
                'gl_account_ref' => ($row['gl_account_ref'] ?? null) ?: null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];

            $totals[$type] = ($totals[$type] ?? 0) + $amount;
        }

        if ($inserts !== []) {
            DB::transaction(function () use ($inserts) {
                foreach (array_chunk($inserts, 500) as $chunk) {
                    DB::table('contract_fees')->insert($chunk);
                }
            });
        }

        return [
            'loaded_rows'    => count($inserts),
            'skipped_rows'   => $skipped,
            'unknown_types'  => $unknownTypes,
            'negative_lines' => $negativeLines,
            'totals_by_type' => array_map(fn ($v) => round($v, 2), $totals),
        ];
    }
}
