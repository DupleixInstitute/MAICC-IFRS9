<?php

namespace App\Services\Eir;

use Illuminate\Support\Facades\DB;

/**
 * Finance's own position on a loan's gross amount, recorded beside the system's own rebuilt
 * carrying amount, never replacing it (MAIIC Finance Overlay Importers Specification v1,
 * section 4.2). Exactly the same shape as LoanAgeingOverlayService, for the balance instead of
 * the ageing bracket: exportTemplate() writes a CSV backfilled from the loan book, with the
 * system's own amount always present and stated in the comment text itself, not just its own
 * column; import() reads a completed CSV back in, keyed on contract id and reporting period,
 * never recomputing anything. Nothing here feeds any calculation.
 */
class LoanGrossAmountOverlayService
{
    public const HEADER = ['contract_id', 'customer_name', 'reporting_period', 'system_amount', 'reference_amount', 'finance_amount', 'comment'];

    /**
     * @param  list<array{contract_id:string,customer_name:?string,reporting_period:string,system_amount:?float,reference_amount:?float,comment:?string}>  $rows
     */
    public function exportTemplate(string $path, array $rows): int
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $f = fopen($path, 'w');
        fputcsv($f, self::HEADER);
        foreach ($rows as $r) {
            $systemAmount = $r['system_amount'] ?? null;
            $comment = $r['comment'] ?? null;
            // the comment always names the original system amount first, so it can never be read without it
            $comment = sprintf('System amount: %s.%s', $systemAmount !== null ? number_format($systemAmount, 2) : 'unknown', $comment !== null ? ' ' . $comment : '');
            fputcsv($f, [
                $r['contract_id'], $r['customer_name'] ?? '', $r['reporting_period'],
                $systemAmount ?? '', $r['reference_amount'] ?? '', '', $comment,
            ]);
        }
        fclose($f);

        return count($rows);
    }

    /**
     * Reads a completed overlay CSV and records it, one row per contract id and reporting
     * period, replacing any earlier overlay for the same pair. system_amount/reference_amount
     * on the file stand exactly as exported, never recomputed here.
     *
     * @return array{imported:int,rows:list<array>}
     */
    public function import(string $path, ?int $userId = null): array
    {
        $f = fopen($path, 'r');
        $header = fgetcsv($f);
        $rows = [];
        while (($r = fgetcsv($f)) !== false) {
            if (count($r) < count($header)) {
                continue;
            }
            $d = array_combine(array_slice($header, 0, count($r)), $r);
            $contractId = trim((string) ($d['contract_id'] ?? ''));
            $period = trim((string) ($d['reporting_period'] ?? ''));
            if ($contractId === '' || $period === '') {
                continue;
            }
            $row = [
                'contract_id' => $contractId, 'reporting_period' => $period,
                'system_amount' => ($d['system_amount'] ?? '') !== '' ? (float) str_replace(',', '', (string) $d['system_amount']) : null,
                'reference_amount' => ($d['reference_amount'] ?? '') !== '' ? (float) str_replace(',', '', (string) $d['reference_amount']) : null,
                'finance_amount' => ($d['finance_amount'] ?? '') !== '' ? (float) str_replace(',', '', (string) $d['finance_amount']) : null,
                'comment' => ($d['comment'] ?? '') !== '' ? $d['comment'] : null,
                'source_file' => basename($path), 'imported_by' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ];
            DB::table('loan_gross_amount_overlays')->updateOrInsert(
                ['contract_id' => $contractId, 'reporting_period' => $period],
                $row
            );
            $rows[] = $row;
        }
        fclose($f);

        return ['imported' => count($rows), 'rows' => $rows];
    }
}
