<?php

namespace App\Services\Eir;

use Illuminate\Support\Facades\DB;

/**
 * Finance's own position on a loan's ageing, recorded beside the system's own figure at the
 * time of export, never replacing it (December 2025 Loan Book Investigation Findings v1,
 * section 10, folded in after the Codex and Copilot reviews of 17 October 2026). Whether the
 * staging policy should ever prefer a schedule-corrected count over the raw report bucket is a
 * governance decision for Edward and Dr Thom, not something this import silently does — this
 * service only ever records what Finance says and what the system already said, side by side.
 *
 * Two halves: exportTemplate() writes a CSV backfilled from the loan book, with the system's
 * own days and bracket always present, and, for a loan already flagged by this investigation, a
 * comment that states that original bracket in words so it is never read without it. import()
 * reads a completed CSV back in, keyed on contract id and reporting period; it never computes a
 * bracket or a day count itself, so the original figure a comment refers to can never drift from
 * what the comment says.
 */
class LoanAgeingOverlayService
{
    public const HEADER = ['contract_id', 'customer_name', 'reporting_period', 'system_days', 'system_bracket', 'workbook_bracket', 'finance_days', 'finance_bracket', 'comment'];

    /** The directive's own bracket labels (Staging Module Specification v1; mirrors StagingDecemberCheck::bracket). */
    public static function bracket(?int $days): ?string
    {
        if ($days === null) {
            return null;
        }

        return match (true) {
            $days <= 30 => '1-30 Days',
            $days <= 90 => '31-90 Days',
            $days <= 180 => '91-180 Days',
            default => '>181 Days',
        };
    }

    /**
     * @param  list<array{contract_id:string,customer_name:?string,reporting_period:string,system_days:?int,workbook_bracket:?string,comment:?string}>  $rows
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
            $systemDays = $r['system_days'] ?? null;
            $systemBracket = self::bracket($systemDays);
            $comment = $r['comment'] ?? null;
            // the comment always names the original bracket first, so it can never be read without it
            $comment = sprintf('System bracket: %s (%s days).%s', $systemBracket ?? 'unknown', $systemDays ?? '?', $comment !== null ? ' ' . $comment : '');
            fputcsv($f, [
                $r['contract_id'], $r['customer_name'] ?? '', $r['reporting_period'],
                $systemDays ?? '', $systemBracket ?? '', $r['workbook_bracket'] ?? '', '', '', $comment,
            ]);
        }
        fclose($f);

        return count($rows);
    }

    /**
     * Reads a completed overlay CSV and records it, one row per contract id and reporting
     * period, replacing any earlier overlay for the same pair. system_days/system_bracket on
     * the file stand exactly as exported, never recomputed here.
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
                'system_days' => ($d['system_days'] ?? '') !== '' ? (int) $d['system_days'] : null,
                'system_bracket' => ($d['system_bracket'] ?? '') !== '' ? $d['system_bracket'] : null,
                'workbook_bracket' => ($d['workbook_bracket'] ?? '') !== '' ? $d['workbook_bracket'] : null,
                'finance_days' => ($d['finance_days'] ?? '') !== '' ? (int) $d['finance_days'] : null,
                'finance_bracket' => ($d['finance_bracket'] ?? '') !== '' ? $d['finance_bracket'] : null,
                'comment' => ($d['comment'] ?? '') !== '' ? $d['comment'] : null,
                'source_file' => basename($path), 'imported_by' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ];
            DB::table('loan_ageing_overlays')->updateOrInsert(
                ['contract_id' => $contractId, 'reporting_period' => $period],
                $row
            );
            $rows[] = $row;
        }
        fclose($f);

        return ['imported' => count($rows), 'rows' => $rows];
    }
}
