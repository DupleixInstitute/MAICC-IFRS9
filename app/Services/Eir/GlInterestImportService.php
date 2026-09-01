<?php

namespace App\Services\Eir;

use App\Support\ContractId;
use Illuminate\Support\Facades\DB;

/**
 * Imports the GL interest postings file (Extract C) — what MAIIC's ledger
 * actually posted as interest income, per loan, per period.
 *
 * This is the evidence side of the Phase 6 reconciliation, so the service
 * records rather than interprets:
 *
 *  - signs are stored exactly as delivered and the negative/positive profile
 *    is reported at import. The Extract C sign convention is an open item;
 *    flipping a sign to "look right" would move a real misstatement into the
 *    noise and out of the reconciliation;
 *  - a period that already carries a posting for the same loan and GL account
 *    is not silently overwritten. An identical figure is a duplicate delivery;
 *    a different figure is a GL restatement, applied and named individually,
 *    because a restatement changes a reconciliation that may already have been
 *    reviewed.
 */
class GlInterestImportService
{
    /** Persisted lineage: names the vendor file an auditor will ask for. */
    public const SOURCE = 'MAIIC_EXTRACT_C';

    /**
     * @param  list<array<string,mixed>>  $rows  mapped Extract C rows
     * @return array{
     *   source_rows:int, loaded_rows:int, restated_rows:int, unchanged:int,
     *   duplicate_source_rows:int, conflicting_duplicate_source_rows:int, held:array<string,string>,
     *   skipped:array<string,string>, restatements:array<string,string>,
     *   annual_summary_rows:int, negative_rows:int, total_posted:float,
     *   periods:array<string,float>
     * }
     */
    public function import(array $rows): array
    {
        $held = [];
        $skipped = [];
        $restatements = [];
        $seen = [];
        $duplicates = 0;
        $conflictingDuplicates = 0;
        $loaded = 0;
        $restated = 0;
        $unchanged = 0;
        $annualSummaries = 0;
        $negatives = 0;
        $total = 0.0;
        $periods = [];
        $now = now();

        foreach ($rows as $index => $row) {
            $label = 'row ' . ($index + 2);

            $contractId = ContractId::normalise($row['contract_id'] ?? null);
            if ($contractId === null) {
                $skipped[$label] = 'no loan account number on the row';
                continue;
            }

            // The full-population Extract C includes annual control totals
            // identified by PERIOD_MONTH = 0. They repeat the monthly detail
            // below them and must never enter a period-grain reconciliation.
            // Keep them visible in the import evidence rather than silently
            // discarding them or reporting them as malformed transactions.
            $rawMonth = $row['period_month'] ?? null;
            if ($rawMonth !== null && trim((string) $rawMonth) !== ''
                && (int) $this->amount($rawMonth) === 0) {
                $annualSummaries++;
                continue;
            }

            [$year, $month] = $this->period($row);
            if ($year === null || $month === null) {
                $skipped[$contractId . ' @ ' . $label] = 'posting period is missing or not a valid year/month';
                continue;
            }

            $glAccount = trim((string) ($row['gl_account_code'] ?? '')) ?: null;
            $naturalKey = implode('|', [$contractId, $year, $month, $glAccount ?? '']);
            $posted = $this->amount($row['interest_income_posted'] ?? null);
            $postingReferences = trim((string) ($row['posting_references'] ?? '')) ?: null;

            if (isset($seen[$naturalKey])) {
                $duplicates++;
                $sameAmount = abs($seen[$naturalKey]['amount'] - $posted) < 0.005;
                $sameReferences = $seen[$naturalKey]['references'] === $postingReferences;
                if (! $sameAmount || ! $sameReferences) {
                    $conflictingDuplicates++;
                    $skipped[$naturalKey] = 'conflicting duplicate: the file carries different amounts or references for the same loan, period and GL account';
                }
                continue;
            }
            $seen[$naturalKey] = ['amount' => $posted, 'references' => $postingReferences];

            $loan = DB::table('loan_books')->where('contract_id', $contractId)
                ->orderByDesc('reporting_period')->first(['contract_id']);
            if (! $loan) {
                $held[$contractId] = 'loan account is not present in the imported loan book';
                continue;
            }

            if ($posted < 0) {
                $negatives++;
            }

            $runId = trim((string) ($row['run_id'] ?? '')) ?: null;
            $record = [
                'contract_id' => $contractId,
                'gl_account_code' => $glAccount,
                'period_type' => strtoupper(trim((string) ($row['period_type'] ?? 'MONTHLY'))) ?: 'MONTHLY',
                'period_year' => $year,
                'period_month' => $month,
                'reporting_period' => sprintf('%04d-%02d-01', $year, $month),
                'interest_income_posted' => $posted,
                'transaction_count' => max(0, (int) $this->amount($row['transaction_count'] ?? null)),
                'posting_references' => $postingReferences,
                'row_note' => trim((string) ($row['row_note'] ?? '')) ?: null,
                'generated_on' => $this->date($row['generated_on'] ?? null),
                'source_system' => self::SOURCE,
                'source_reference' => $runId,
                'external_transaction_id' => self::SOURCE . '|' . ($runId ?? 'no-run') . '|' . $naturalKey,
                'updated_at' => $now,
            ];

            $existing = DB::table('gl_interest_postings')
                ->where('contract_id', $contractId)
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->where(fn ($q) => $glAccount === null
                    ? $q->whereNull('gl_account_code')
                    : $q->where('gl_account_code', $glAccount))
                ->first();

            if ($existing === null) {
                DB::table('gl_interest_postings')->insert($record + ['created_at' => $now]);
                $loaded++;
            } elseif (abs((float) $existing->interest_income_posted - $posted) < 0.005) {
                $unchanged++;
            } else {
                $restatements[$naturalKey] = sprintf(
                    'GL restated from %s to %s',
                    number_format((float) $existing->interest_income_posted, 2),
                    number_format($posted, 2)
                );
                DB::table('gl_interest_postings')->where('id', $existing->id)->update($record);
                $restated++;
            }

            $periodKey = sprintf('%04d-%02d', $year, $month);
            $periods[$periodKey] = round(($periods[$periodKey] ?? 0) + $posted, 2);
            $total += $posted;
        }

        ksort($periods);

        return [
            'source_rows' => count($rows),
            'loaded_rows' => $loaded,
            'restated_rows' => $restated,
            'unchanged' => $unchanged,
            'duplicate_source_rows' => $duplicates,
            'conflicting_duplicate_source_rows' => $conflictingDuplicates,
            'annual_summary_rows' => $annualSummaries,
            'held' => $held,
            'skipped' => $skipped,
            'restatements' => $restatements,
            'negative_rows' => $negatives,
            'total_posted' => round($total, 2),
            'periods' => $periods,
        ];
    }

    /**
     * Period from explicit year/month columns, falling back to a date column
     * the operator mapped instead.
     *
     * @return array{0:?int, 1:?int}
     */
    private function period(array $row): array
    {
        $year = (int) $this->amount($row['period_year'] ?? null);
        $month = (int) $this->amount($row['period_month'] ?? null);

        if ($year === 0 && isset($row['reporting_period'])) {
            $date = $this->date($row['reporting_period']);
            if ($date !== null) {
                [$year, $month] = array_map('intval', explode('-', substr($date, 0, 7)));
            }
        }

        if ($year < 1900 || $year > 2999 || $month < 1 || $month > 12) {
            return [null, null];
        }

        return [$year, $month];
    }

    private function amount($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) ($value ?? ''));
        if ($text === '' || $text === '-') {
            return 0.0;
        }

        $cleaned = str_replace([',', ' ', "\xC2\xA0", '"'], '', $text);
        if (preg_match('/^\((.*)\)$/', $cleaned, $m)) {
            $cleaned = '-' . $m[1];
        }

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }

    private function date($value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $text) ? substr($text, 0, 10) : null;
    }
}
