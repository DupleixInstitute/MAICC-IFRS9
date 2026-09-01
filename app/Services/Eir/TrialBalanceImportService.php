<?php

namespace App\Services\Eir;

use App\Models\GlTrialBalanceLine;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * Reads one `rpt_Trial_Balance_Malawi` file into gl_trial_balance_lines (§3.4).
 *
 * The layout is consistent across all 19 delivered months: row 1 carries the
 * period as an Excel serial in column B, row 2 is the header
 * `GL Title | Debit | Credit`, and every data row holds `code...title` in
 * column B. The AFS workbook's pre-closing December sheet uses the same shape
 * with a blank row 1, which is why the period can be supplied explicitly.
 *
 * Three rules, each of which has already produced a wrong answer once when it
 * was not applied (spec §3.5.5, §3.4.6):
 *
 *  1. A data row is one whose title matches `code...title`. Everything else —
 *     the date stamp, the header, the total — is not a balance.
 *  2. The file's own `Grand Total :` row must be EXCLUDED from the sum. It
 *     restates the total, so including it doubles the answer: this is the
 *     117.37bn figure that was circulated as 234.74bn on 2026-08-18.
 *  3. That same row is then USED as a checksum. Because it is the file's own
 *     assertion of its total, an import that ties to it has proven both that
 *     no row was missed and that the total row was not counted — the two
 *     failure modes are opposite, so one comparison catches both.
 *
 * A file that does not tie is rejected rather than imported with a warning.
 * A partially-correct ledger is worse than no ledger: it reconciles to
 * something, so nobody goes looking.
 */
class TrialBalanceImportService
{
    /** Balances are stated to the kwacha; anything above rounding is a real break. */
    private const TIE_TOLERANCE = 0.01;

    private const DATA_ROW_PATTERN = '/^\s*(\d+)\.\.\.(.*)$/';

    /**
     * @return array{period:string,basis:string,lines:int,debit:float,credit:float,
     *               grand_total:?float,imported:int,updated:int,source_period_stamp:?string}
     */
    public function import(
        string $path,
        string $basis = GlTrialBalanceLine::BASIS_POSTCLOSING,
        ?string $period = null,
        ?string $sheetName = null
    ): array {
        $parsed = $this->parse($path, $period, $sheetName);

        $imported = 0;
        $updated = 0;
        foreach ($parsed['rows'] as $row) {
            $existing = GlTrialBalanceLine::where('period', $parsed['period'])
                ->where('gl_code', $row['gl_code'])->where('basis', $basis)->first();

            $attributes = [
                'period' => $parsed['period'],
                'source_period_stamp' => $parsed['source_period_stamp'],
                'gl_code' => $row['gl_code'],
                'gl_title' => $row['gl_title'],
                'debit' => $row['debit'],
                'credit' => $row['credit'],
                'basis' => $basis,
                'source_file' => basename($path),
                'source_sheet' => $parsed['sheet'],
            ];

            if ($existing) {
                $existing->update($attributes);
                $updated++;
            } else {
                GlTrialBalanceLine::create($attributes);
                $imported++;
            }
        }

        return [
            'period' => $parsed['period'],
            'basis' => $basis,
            'lines' => count($parsed['rows']),
            'debit' => $parsed['debit'],
            'credit' => $parsed['credit'],
            'grand_total' => $parsed['grand_total'],
            'source_period_stamp' => $parsed['source_period_stamp'],
            'imported' => $imported,
            'updated' => $updated,
        ];
    }

    /**
     * Parses and validates without writing, so the rules can be exercised
     * against the delivered files without a database.
     *
     * @return array{period:string,source_period_stamp:?string,sheet:string,rows:list<array>,
     *               debit:float,credit:float,grand_total:?float}
     */
    public function parse(string $path, ?string $period = null, ?string $sheetName = null): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Trial balance file not found: {$path}");
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $sheetName !== null ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getSheet(0);
        if ($sheet === null) {
            throw new RuntimeException("Sheet '{$sheetName}' not found in " . basename($path));
        }

        $grid = $sheet->toArray(null, false, false, false);
        $stamp = $this->readPeriodStamp($grid);

        $resolvedPeriod = $period !== null
            ? Carbon::parse($period)->startOfMonth()->toDateString()
            : $stamp;

        if ($resolvedPeriod === null) {
            throw new RuntimeException(
                'No period stamp in ' . basename($path) . ' and none supplied. The AFS pre-closing '
                . 'sheet carries no stamp, so its period must be passed explicitly.'
            );
        }

        $rows = [];
        $debit = 0.0;
        $credit = 0.0;
        $grandTotal = null;

        foreach ($grid as $line) {
            $title = (string) ($line[1] ?? '');
            if (trim($title) === '') {
                continue;
            }

            if (preg_match(self::DATA_ROW_PATTERN, $title, $match)) {
                $rowDebit = $this->amount($line[2] ?? null);
                $rowCredit = $this->amount($line[3] ?? null);
                $rows[] = [
                    'gl_code' => $match[1],
                    'gl_title' => trim(preg_replace('/\s+/', ' ', $match[2])),
                    'debit' => $rowDebit,
                    'credit' => $rowCredit,
                ];
                $debit += $rowDebit;
                $credit += $rowCredit;

                continue;
            }

            if (str_starts_with(trim($title), 'Grand Total')) {
                $grandTotal = $this->amount($line[2] ?? null);
            }
        }

        $this->assertTies($path, $rows, $debit, $credit, $grandTotal);

        return [
            'period' => $resolvedPeriod,
            'source_period_stamp' => $stamp,
            'sheet' => $sheet->getTitle(),
            'rows' => $rows,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * Row 1 column B holds the period as an Excel serial. The delivered files
     * stamp the FIRST of the month on a file named for the last day of it
     * (open item #22), so the stamp is normalised to the month start and also
     * returned as read — the report shows what we read, not just what we
     * concluded, because a month of movement rides on that reading.
     */
    private function readPeriodStamp(array $grid): ?string
    {
        $raw = $grid[0][1] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $raw))->startOfMonth()->toDateString();
        }

        try {
            return Carbon::parse((string) $raw)->startOfMonth()->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Amounts arrive as formatted text ("868,069,514.47"), not numbers. */
    private function amount(mixed $value): float
    {
        $text = trim((string) $value);
        if ($text === '' || $text === '-') {
            return 0.0;
        }

        $negative = str_starts_with($text, '(') && str_ends_with($text, ')');
        $text = str_replace([',', '(', ')', ' '], '', $text);

        return $negative ? -(float) $text : (float) $text;
    }

    private function assertTies(string $path, array $rows, float $debit, float $credit, ?float $grandTotal): void
    {
        $name = basename($path);

        if ($rows === []) {
            throw new RuntimeException("No trial-balance rows found in {$name}. Expected 'code...title' in column B.");
        }

        if (abs($debit - $credit) > self::TIE_TOLERANCE) {
            throw new RuntimeException(sprintf(
                '%s does not balance: debits %s, credits %s, difference %s.',
                $name,
                number_format($debit, 2),
                number_format($credit, 2),
                number_format($debit - $credit, 2)
            ));
        }

        if ($grandTotal !== null && abs($grandTotal - $debit) > self::TIE_TOLERANCE) {
            throw new RuntimeException(sprintf(
                '%s does not tie to its own Grand Total: rows sum to %s, the file states %s. '
                . 'A difference of exactly the row total means the Grand Total row was counted as data.',
                $name,
                number_format($debit, 2),
                number_format($grandTotal, 2)
            ));
        }
    }
}
