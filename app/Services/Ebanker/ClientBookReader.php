<?php

namespace App\Services\Ebanker;

use App\Services\Eir\StagingClassifier;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Reads a loan book the client gave for one month-end into plain rows, as received (Client Book
 * Reconciliation Specification v1, section 3). It computes nothing the file does not say: the day
 * count of a row is the lower bound of the oldest arrears bucket the file fills (or of the age
 * bracket the audited model gives), named on the row, never a count it invents. The E-Banker
 * report's buckets end at 270 days: an overdue amount no bucket holds is older than that, and the
 * row says so (271 days, the lower bound), so a loan long past maturity never reads as current.
 *
 * Three kinds:
 *  - ebanker_report: MAIIC's monthly E-Banker "Loan Book Detail Report" (rpt_Loan_Book_Detail_Report),
 *    .xls/.xlsx/.csv, one row per account under its "Loan Type : <GL>-<code>" heading, with the
 *    carrying amount, the overdue amounts, the arrears category and the arrears buckets. The rows of
 *    each GL must add up to the report's "GL Wise Total" for it, or the file is refused.
 *  - audited_model: the values of the December 2025 ECL model's portfolio report (the committed CSV
 *    of docs/bootstrap/models): customer names (the model masks the accounts), the carrying amount,
 *    the revised age bracket and the stage the model gave.
 *  - template: the reconciliation template this system exports for a month (TEMPLATE_HEADER), the
 *    client's columns filled in; a blank client cell means the client takes no position on it.
 */
class ClientBookReader
{
    public const KINDS = [
        'ebanker_report' => 'E-Banker loan book report',
        'audited_model' => 'Audited ECL model (portfolio report values)',
        'template' => 'Reconciliation template',
    ];

    public const TEMPLATE_HEADER = ['contract_id', 'customer_name', 'system_carrying_amount', 'system_days_past_due', 'system_stage', 'carrying_amount', 'days_past_due', 'stage', 'comment'];

    /** The audited model's revised brackets at their lower bound (the bounds StagingDecemberCheck uses). */
    public const BRACKET_DAYS = ['1-30 days' => 0, '31-90 days' => 31, '91-180 days' => 91, '>181 days' => 181];

    /**
     * @return array{rows: list<array>, printed_total: ?float, rows_total: float}
     */
    public function read(string $path, string $kind, string $period): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("The client book file is not at {$path}.");
        }
        if (! isset(self::KINDS[$kind])) {
            throw new RuntimeException("'{$kind}' is not a kind of client book this system reads: " . implode(', ', array_keys(self::KINDS)) . '.');
        }
        $out = match ($kind) {
            'ebanker_report' => $this->ebankerReport($path, $period),
            'audited_model' => $this->auditedModel($path),
            'template' => $this->template($path),
        };
        if ($out['rows'] === []) {
            throw new RuntimeException('No loan row was read from ' . basename($path) . ' as a ' . self::KINDS[$kind] . '; check the file is the right kind.');
        }

        return $out;
    }

    /** @return list<list<mixed>> every row of the first sheet (or of the CSV), raw values */
    private function grid(string $path): array
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'csv') {
            $rows = [];
            $f = fopen($path, 'r');
            while (($r = fgetcsv($f)) !== false) {
                $rows[] = $r;
            }
            fclose($f);

            return $rows;
        }
        $sheet = IOFactory::load($path)->getSheet(0);

        return $sheet->toArray(null, true, false, false);
    }

    private function ebankerReport(string $path, string $period): array
    {
        $grid = $this->grid($path);
        $col = null;
        $code = null;
        $rows = [];
        $printed = null;
        $classifier = new StagingClassifier(CarbonImmutable::parse($period . '-01')->endOfMonth()->toDateString());
        foreach ($grid as $n => $r) {
            $cells = array_map(fn ($v) => trim(str_replace(["\r", "\n"], ' ', (string) $v)), $r);
            if ($col === null) {
                // the header row: its cells name the columns; they are read by name, so a shifted layout reads the same
                $norm = array_map(fn ($c) => strtolower(preg_replace('/\s+/', ' ', $c)), $cells);
                if (in_array('a/c number', $norm, true) && in_array('carrying amount', $norm, true)) {
                    $col = array_flip(array_filter($norm, fn ($c) => $c !== ''));
                }
                continue;
            }
            $joined = implode(' ', $cells);
            if (preg_match('/Loan Type\s*:\s*(\d+)/i', $joined, $m)) {
                $code = $m[1];
                continue;
            }
            // each GL's rows add up to its printed "GL Wise Total"; the "Printed Grand Total" line repeats the last
            // GL's carrying amount in that column (an E-Banker quirk), so the control is GL by GL
            if (stripos($joined, 'GL Wise Total') !== false) {
                $glTotal = $this->num($cells[$col['carrying amount']] ?? null) ?? 0.0;
                $glRows = round(array_sum(array_map(fn ($r) => (float) $r['carrying_amount'], array_filter($rows, fn ($r) => $r['product_code'] === $code))), 2);
                if (abs($glRows - $glTotal) > 0.05) {
                    throw new RuntimeException(sprintf('%s: the rows of GL %s add up to %s but the report prints a GL total of %s; the file is refused, not read in part.', basename($path), $code ?? '?', number_format($glRows, 2), number_format($glTotal, 2)));
                }
                $printed = round(($printed ?? 0.0) + $glTotal, 2);
                continue;
            }
            if (stripos($joined, 'Printed Grand') !== false) {
                continue;
            }
            $account = preg_replace('/\D/', '', $cells[$col['a/c number']] ?? '');
            if ($account === '' || ! is_numeric($cells[$col['sr. no.']] ?? ($cells[0] ?? ''))) {
                continue;
            }
            $buckets = [
                '1_30_days' => $this->num($cells[$col['1 - 30 days']] ?? null),
                '31_90_days' => $this->num($cells[$col['31 - 90 days']] ?? null),
                '91_180_days' => $this->num($cells[$col['91 - 180 days']] ?? null),
                '181_270_days' => $this->num($cells[$col['181 - 270 days']] ?? null),
            ];
            $days = $classifier->daysPastDue($buckets);
            $basis = $days === 0 ? 'no arrears bucket filled' : "the oldest arrears bucket filled, at its lower bound ({$days} days)";
            // the report's buckets end at 270 days: overdue older than that (a loan long past maturity) is in the
            // total overdue and in no bucket, so the part no bucket holds is over 270 days, never "current"
            $overdue = $this->num($cells[$col['total overdue']] ?? null) ?? 0.0;
            $unbucketed = round($overdue - array_sum(array_map(fn ($v) => (float) $v, $buckets)), 2);
            if ($overdue > 0.005 && $unbucketed > 1.0) {
                $days = 271;
                $basis = 'overdue of ' . number_format($unbucketed, 2) . ' in no bucket: older than the 270-day buckets of the report (271 days, the lower bound)';
            }
            $rows[] = [
                'row_no' => $n + 1, 'contract_id' => ltrim($account, '0'), 'customer_name' => $cells[$col['name']] ?? null, 'product_code' => $code,
                'carrying_amount' => $this->num($cells[$col['carrying amount']] ?? null),
                'principal_overdue' => $this->num($cells[$col['principal overdue']] ?? null),
                'interest_overdue' => $this->num($cells[$col['interest overdue']] ?? null),
                'arrears_category' => ($cells[$col['arrears category']] ?? '') !== '' ? mb_substr($cells[$col['arrears category']], 0, 60) : null,
                'buckets' => $buckets + ['total_overdue' => $overdue], 'days_past_due' => $days, 'days_basis' => $basis,
                'stage' => null,
            ];
        }
        if ($col === null) {
            throw new RuntimeException(basename($path) . ' has no header row naming "A/C Number" and "Carrying Amount": it is not an E-Banker loan book report.');
        }
        $total = round(array_sum(array_column($rows, 'carrying_amount')), 2);
        if ($printed !== null && abs($total - $printed) > 0.05) {
            throw new RuntimeException(sprintf('%s: the rows add up to %s but the report prints a grand total of %s; the file is refused, not read in part.', basename($path), number_format($total, 2), number_format($printed, 2)));
        }

        return ['rows' => $rows, 'printed_total' => $printed, 'rows_total' => $total];
    }

    private function auditedModel(string $path): array
    {
        $grid = $this->grid($path);
        // the provenance line, then the header
        $start = isset($grid[0][0]) && str_starts_with(trim((string) $grid[0][0]), '#') ? 1 : 0;
        $head = array_map(fn ($h) => trim((string) $h), $grid[$start] ?? []);
        foreach (['customer_name', 'carrying_amount', 'age_bracket_revised', 'workbook_stage'] as $need) {
            if (! in_array($need, $head, true)) {
                throw new RuntimeException(basename($path) . " has no '{$need}' column: it is not the audited model's portfolio report values.");
            }
        }
        $rows = [];
        foreach (array_slice($grid, $start + 1, null, true) as $n => $r) {
            if (count($r) < count($head)) {
                continue;
            }
            $d = array_combine($head, array_slice($r, 0, count($head)));
            $bracket = strtolower(trim((string) $d['age_bracket_revised']));
            if (! array_key_exists($bracket, self::BRACKET_DAYS)) {
                throw new RuntimeException("Row " . ($n + 1) . " of " . basename($path) . " has the age bracket '{$d['age_bracket_revised']}', which is not one of the model's four.");
            }
            $days = self::BRACKET_DAYS[$bracket];
            $rows[] = [
                'row_no' => $n + 1, 'contract_id' => null, 'customer_name' => (string) $d['customer_name'], 'product_code' => null,
                'carrying_amount' => $this->num($d['carrying_amount']), 'principal_overdue' => $this->num($d['overdue_principal'] ?? null),
                'interest_overdue' => $this->num($d['overdue_interest'] ?? null), 'arrears_category' => (string) $d['age_bracket_revised'],
                'buckets' => null, 'days_past_due' => $days, 'days_basis' => "the model's revised age bracket {$d['age_bracket_revised']}, at its lower bound ({$days} days)",
                'stage' => (int) $d['workbook_stage'],
            ];
        }

        return ['rows' => $rows, 'printed_total' => null, 'rows_total' => round(array_sum(array_column($rows, 'carrying_amount')), 2)];
    }

    private function template(string $path): array
    {
        $grid = $this->grid($path);
        $head = array_map(fn ($h) => strtolower(trim((string) $h)), $grid[0] ?? []);
        if (! in_array('contract_id', $head, true) || ! in_array('carrying_amount', $head, true) || ! in_array('days_past_due', $head, true)) {
            throw new RuntimeException(basename($path) . ' has no contract_id, carrying_amount and days_past_due columns: it is not the reconciliation template.');
        }
        $rows = [];
        foreach (array_slice($grid, 1, null, true) as $n => $r) {
            $d = array_combine($head, array_pad(array_slice($r, 0, count($head)), count($head), null));
            $contract = preg_replace('/\D/', '', (string) $d['contract_id']);
            $amount = trim((string) ($d['carrying_amount'] ?? ''));
            $days = trim((string) ($d['days_past_due'] ?? ''));
            $stage = trim((string) ($d['stage'] ?? ''));
            if ($contract === '' || ($amount === '' && $days === '' && $stage === '')) {
                continue; // a row the client left blank takes no position
            }
            if ($days !== '' && (! ctype_digit($days))) {
                throw new RuntimeException('Row ' . ($n + 1) . " of " . basename($path) . ": days_past_due '{$days}' is not a whole number of days.");
            }
            if ($stage !== '' && ! in_array($stage, ['1', '2', '3'], true)) {
                throw new RuntimeException('Row ' . ($n + 1) . " of " . basename($path) . ": stage '{$stage}' is not 1, 2 or 3.");
            }
            $rows[] = [
                'row_no' => $n + 1, 'contract_id' => ltrim($contract, '0'), 'customer_name' => $d['customer_name'] ?? null, 'product_code' => null,
                'carrying_amount' => $amount === '' ? null : $this->num($amount), 'principal_overdue' => null, 'interest_overdue' => null,
                'arrears_category' => null, 'buckets' => null, 'days_past_due' => $days === '' ? null : (int) $days,
                'days_basis' => $days === '' ? null : "the client's day count on the template ({$days} days)" . (trim((string) ($d['comment'] ?? '')) !== '' ? '; ' . mb_substr(trim((string) $d['comment']), 0, 80) : ''),
                'stage' => $stage === '' ? null : (int) $stage,
            ];
        }

        return ['rows' => $rows, 'printed_total' => null, 'rows_total' => round(array_sum(array_map(fn ($r) => (float) ($r['carrying_amount'] ?? 0), $rows)), 2)];
    }

    private function num(mixed $v): ?float
    {
        if ($v === null) {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return round((float) $v, 2);
        }
        $s = trim(str_replace([',', ' '], '', (string) $v));
        if ($s === '' || $s === '-') {
            return null;
        }
        $neg = str_starts_with($s, '(') && str_ends_with($s, ')');
        $s = trim($s, '()');

        return is_numeric($s) ? round(($neg ? -1 : 1) * (float) $s, 2) : null;
    }
}
