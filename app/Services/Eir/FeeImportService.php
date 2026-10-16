<?php

namespace App\Services\Eir;

use App\Support\ContractId;
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
 *
 * A file may state the treatment in an optional integral column (yes or no; blank states nothing). A line
 * it states arrives CLASSIFIED with that treatment, the file named as the reason and the person who loaded
 * it as the classifier, so a second person reviews it before it enters the EIR (maker-checker). A re-load
 * fills the treatment of a line still PENDING; it never changes a classified or reviewed line. A line with
 * no cash-flow direction stays PENDING whatever the file states, since it cannot enter the EIR.
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
    public function import(array $rows, ?FeeRuleMatcher $matcher = null, ?int $userId = null, ?string $origin = null): array
    {
        $matcher ??= app(FeeRuleMatcher::class);
        $inserts       = [];
        $skipped       = 0;
        $excluded      = 0;
        $unknownTypes  = [];
        $negativeLines = 0;
        $totals        = [];
        $years         = [];
        $now           = now();
        $stated        = [];
        $filled        = 0;

        foreach ($rows as $row) {
            $contractId = ContractId::normalise($row['contract_id'] ?? null);
            $amount     = (float) ($row['amount'] ?? 0);

            if ($contractId === null || $amount === 0.0) {
                $skipped++;
                continue;
            }
            // an account the exclusion register takes out of the book loads no fee line (AccountExclusionService)
            if (\App\Support\AccountExclusions::isExcluded($contractId)) {
                $excluded++;
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
            $direction = strtoupper(trim((string) ($row['cashflow_direction'] ?? '')));
            $sourceSystem = trim((string) ($row['source_system'] ?? '')) ?: null;
            $externalId = trim((string) ($row['external_transaction_id'] ?? '')) ?: null;

            $integral = self::statedIntegral($row['integral'] ?? null);
            if ($sourceSystem && $externalId && ($existing = DB::table('contract_fees')
                ->where('source_system', $sourceSystem)
                ->where('external_transaction_id', $externalId)->first())) {
                if ($integral !== null && $existing->classification_status === 'PENDING' && $existing->cashflow_direction !== null) {
                    $this->classifyFromFile((int) $existing->id, $integral, $userId, $origin, $now);
                    $filled++;
                }
                $skipped++;
                continue;
            }

            $candidate = [
                'fee_type' => $type,
                'description' => trim((string) ($row['description'] ?? '')) ?: null,
                'cashflow_direction' => in_array($direction, ['RECEIVED', 'PAID'], true) ? $direction : null,
                'gl_account_ref' => ($row['gl_account_ref'] ?? null) ?: null,
            ];
            $rule = $matcher->match($candidate);

            $stated[] = $integral !== null && $candidate['cashflow_direction'] !== null ? $integral : null;
            $inserts[] = [
                'contract_id'    => $contractId,
                'fee_type'       => $type,
                'description'    => $candidate['description'],
                'amount'         => $amount,
                'transaction_date' => ($row['transaction_date'] ?? null) ?: null,
                'cashflow_direction' => $candidate['cashflow_direction'],
                'currency'       => strtoupper(trim((string) ($row['currency'] ?? ''))) ?: null,
                'source_system'  => $sourceSystem,
                'source_reference' => trim((string) ($row['source_reference'] ?? '')) ?: null,
                'external_transaction_id' => $externalId,
                'basis'          => in_array($basis, ['ON_APPROVED', 'ON_DRAWN'], true) ? $basis : 'ON_APPROVED',
                'integral'       => null,
                'classification_status' => 'PENDING',
                'suggested_rule_id' => $rule?->id,
                'suggested_integral' => $rule?->proposed_integral,
                'gl_account_ref' => ($row['gl_account_ref'] ?? null) ?: null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];

            $totals[$type] = ($totals[$type] ?? 0) + $amount;
            // the years the file covers, so the fee GL check (FeeGlReconciliationService) knows what to prove
            if (preg_match('/^(\d{4})-\d{2}/', (string) ($row['transaction_date'] ?? ''), $y) === 1) {
                $years[(int) $y[1]] = true;
            }
        }

        $classified = 0;
        if ($inserts !== []) {
            DB::transaction(function () use ($inserts, $stated, $userId, $origin, $now, &$classified) {
                foreach ($inserts as $i => $insert) {
                    $id = DB::table('contract_fees')->insertGetId($insert);
                    if ($stated[$i] !== null) {
                        $this->classifyFromFile($id, $stated[$i], $userId, $origin, $now);
                        $classified++;
                    }
                }
            });
            // a fee recorded for a loan whose EIR is already locked flags that EIR for review (EirLockReviewService)
            app(EirLockReviewService::class)->detect();
        }

        return [
            'loaded_rows'    => count($inserts),
            'skipped_rows'   => $skipped,
            'excluded_rows'  => $excluded,
            'unknown_types'  => $unknownTypes,
            'negative_lines' => $negativeLines,
            'totals_by_type' => array_map(fn ($v) => round($v, 2), $totals),
            'years'          => array_map('intval', array_keys($years)),
            'classified_from_file' => $classified,
            'filled_on_reload' => $filled,
        ];
    }

    /** yes or no, as the file states it; null when blank or not a yes or no. */
    public static function statedIntegral(mixed $value): ?bool
    {
        return match (mb_strtolower(trim((string) $value))) {
            'yes', 'y', 'true', '1', 'integral' => true,
            'no', 'n', 'false', '0', 'non-integral' => false,
            default => null,
        };
    }

    /** The treatment the file states, recorded as a classification by the person who loaded it. */
    private function classifyFromFile(int $feeId, bool $integral, ?int $userId, ?string $origin, $now): void
    {
        $reason = 'Stated in the source file' . ($origin ? ' ' . $origin : '') . ': integral = ' . ($integral ? 'yes' : 'no')
            . '. Classified on load by the person who loaded it; a second person reviews it before it enters the EIR.';
        DB::table('contract_fees')->where('id', $feeId)->update(['integral' => $integral, 'classification_status' => 'CLASSIFIED',
            'classification_reason' => mb_substr($reason, 0, 2000), 'classified_by' => $userId, 'classified_at' => $now, 'updated_at' => $now]);
        DB::table('eir_fee_classification_events')->insert(['contract_fee_id' => $feeId, 'action' => 'CLASSIFIED', 'integral' => $integral,
            'reason' => mb_substr($reason, 0, 2000), 'performed_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
    }
}
