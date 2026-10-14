<?php

namespace App\Support\Imports;

use App\Services\Eir\TrialBalanceImportService;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\RedirectResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

/**
 * The check every file import runs before anything is written (13 October
 * 2026). It reads the file the way the spec's importer reads it, then tests
 * the headings (every required column present, no column twice) and every
 * row (a value in each required cell, the type, the allowed values, the
 * range, and no key repeated), and returns the problems row by row.
 *
 * It fails closed: an error on any row refuses the whole file, and the
 * importer is not called. Warnings (a value the importer folds, a repeat the
 * importer is built to skip) are listed but do not refuse the file.
 *
 * The result is a plain array, so it can be flashed to the session and read
 * by the import screens (props.flash.import_check):
 *   key, title, file, ok, rows, error_count, warning_count,
 *   missing_columns, ignored_columns, issues[{row, column, value, message, fix, severity}], truncated
 */
class ImportSpecValidator
{
    public const MAX_ISSUES = 200;

    private array $issues = [];
    private int $errors = 0;
    private int $warnings = 0;

    /**
     * Check a file against a spec.
     *
     * @param array $options mapping => [file heading => field] for a mapped import,
     *                       field_aliases => [field => spec column name],
     *                       period => a period supplied on the screen (trial balance),
     *                       sheet => a sheet name (trial balance)
     */
    public function checkFile(string $key, string $path, string $originalName = '', array $options = []): array
    {
        $this->reset();
        $spec = ImportSpecs::get($key);
        $name = $originalName !== '' ? $originalName : basename($path);

        try {
            $rows = match ($key) {
                'loan-book-ebanker' => $this->checkEbankerReport($spec, $path),
                'trial-balance' => $this->checkTrialBalance($path, $name, $options),
                'rbm-policy-rate' => $this->checkPositional($spec, $path),
                'imf-weo' => $this->checkImfWeo($spec, $path),
                default => $this->checkTabular($spec, $path, $options),
            };
        } catch (Throwable $e) {
            $this->add(null, null, null, 'The file could not be read: ' . $e->getMessage(), 'Save the file in the format of the sample file and choose it again.');
            $rows = 0;
        }

        return $this->result($spec, $name, $rows);
    }

    /**
     * Check rows already read and keyed by field (a mapped import such as
     * the EIR intake, read by MappedFileReader with no transforms).
     *
     * @param list<array<string,mixed>> $rows
     * @param list<string> $presentFields the fields the file supplies
     */
    public function checkRows(string $key, array $rows, array $presentFields, string $originalName = '', int $firstRow = 2, ?array $resolved = null): array
    {
        $this->resolved = $resolved;
        $this->reset();
        $spec = ImportSpecs::get($key);
        $byKey = [];
        foreach ($spec['columns'] as $c) {
            $byKey[$c['key']] = $c;
        }
        $present = array_values(array_intersect(array_keys($byKey), $presentFields));
        $this->checkRequiredColumns($spec, $present);
        $this->checkRowValues($spec, array_values($rows), $present, $firstRow);
        $this->resolved = null;

        return $this->result($spec, $originalName, count($rows));
    }

    /**
     * A check result built from a service's own gates (the take-on workbook and
     * the E-Banker pack check themselves), in the same shape, so the screens
     * list them the same way.
     *
     * @param list<array{row:?int,column:?string,message:string,fix:?string,severity:string}> $issues
     */
    public static function fromGates(string $key, string $file, int $rows, array $issues): array
    {
        $spec = ImportSpecs::get($key);
        $errors = count(array_filter($issues, fn ($i) => $i['severity'] !== 'warning'));

        return [
            'key' => $key, 'title' => $spec['title'], 'file' => $file, 'ok' => $errors === 0, 'rows' => $rows,
            'error_count' => $errors, 'warning_count' => count($issues) - $errors, 'missing_columns' => [], 'ignored_columns' => [],
            'issues' => array_slice(array_map(fn ($i) => $i + ['value' => null, 'fix' => null], $issues), 0, self::MAX_ISSUES),
            'truncated' => count($issues) > self::MAX_ISSUES, 'checked_at' => now()->toDateTimeString(),
        ];
    }

    /** One line naming the outcome, for a flash message. */
    public static function summary(array $check): string
    {
        if ($check['ok']) {
            return "{$check['file']}: {$check['rows']} rows checked, no errors" . ($check['warning_count'] ? " ({$check['warning_count']} warnings)" : '') . '.';
        }
        $first = collect($check['issues'])->firstWhere('severity', 'error');
        $where = $first ? ($first['row'] ? "row {$first['row']}" : 'the headings') . ($first['column'] ? ", {$first['column']}" : '') . ': ' . $first['message'] : '';

        return "{$check['file']} was not imported: {$check['error_count']} problem" . ($check['error_count'] === 1 ? '' : 's') . ' found' . ($where !== '' ? " (first: {$where})" : '') . '. Nothing was saved. Fix the rows listed and choose the file again.';
    }

    /** Back to the screen with the problems listed; nothing was written. */
    public static function refuse(array $check): RedirectResponse
    {
        return back()->with('error', self::summary($check))->with('import_check', $check);
    }

    /* ------------------------------------------------------------------ */
    /* Tabular files                                                      */
    /* ------------------------------------------------------------------ */

    private function checkTabular(array $spec, string $path, array $options): int
    {
        [$headers, $lines] = $this->readGrid($path, $spec['format'] === 'tsv' ? "\t" : null);
        if ($headers === []) {
            $this->add(null, null, null, 'The file is empty: it has no heading row.', 'Start from the sample file: its first row holds the column headings.');

            return 0;
        }

        // the file's headings as the importer sees them, then through the mapping chosen on the screen
        $mapping = [];
        foreach ((array) ($options['mapping'] ?? []) as $from => $to) {
            if (is_string($to) && $to !== '') {
                $mapping[ImportSpecs::normaliseHeader((string) $from, $spec['headers'])] = $to;
            }
        }
        $aliases = (array) ($options['field_aliases'] ?? []);
        $columnByKey = [];
        foreach ($spec['columns'] as $c) {
            $columnByKey[$c['key']] = $c['key'];
            foreach ($c['aliases_keys'] as $a) {
                $columnByKey[$a] ??= $c['key'];
            }
        }

        $index = []; // spec key => file column index
        $ignored = [];
        foreach ($headers as $i => $raw) {
            $h = ImportSpecs::normaliseHeader((string) $raw, $spec['headers']);
            if ($h === '') {
                continue;
            }
            if ($mapping !== []) {
                $field = $mapping[$h] ?? null;
                if ($field === null) {
                    $ignored[] = (string) $raw;
                    continue;
                }
                $h = ImportSpecs::normaliseHeader($aliases[$field] ?? $field, $spec['headers']);
            }
            $key = $columnByKey[$h] ?? null;
            if ($key === null) {
                $ignored[] = (string) $raw;
                continue;
            }
            if (isset($index[$key])) {
                $this->add(1, (string) $raw, null, 'This column appears twice (as "' . $headers[$index[$key]] . '" and "' . $raw . '"); only one is read.', 'Delete one of the two columns.');
                continue;
            }
            $index[$key] = $i;
        }
        $this->ignored = $ignored;

        $this->checkRequiredColumns($spec, array_keys($index));

        $rows = [];
        foreach ($lines as $n => $line) {
            $row = [];
            foreach ($index as $key => $i) {
                $row[$key] = $line[$i] ?? null;
            }
            $rows[$n] = $row;
        }
        $this->checkRowValues($spec, $rows, array_keys($index), null);

        return count($rows);
    }

    private array $ignored = [];

    /** The same rows as the reader resolves them (dates as YYYY-MM-DD), by position. */
    private ?array $resolved = null;

    private function checkRequiredColumns(array $spec, array $presentKeys): void
    {
        $this->missing = [];
        foreach ($spec['columns'] as $c) {
            if ($c['required'] && ! in_array($c['key'], $presentKeys, true)) {
                $this->missing[] = $c['name'];
                $this->add(1, $c['name'], null, "The required column {$c['name']} is missing.", 'Add the column with this heading, as in the sample file' . ($c['aliases'] ?? [] ? ' (or one of: ' . implode(', ', $c['aliases']) . ')' : '') . '.');
            }
        }
        if (! empty($spec['require_one_of'])) {
            $names = $spec['require_one_of'];
            $keys = array_map(fn ($c) => $c['key'], array_filter($spec['columns'], fn ($c) => in_array($c['name'], $names, true)));
            if (array_intersect($keys, $presentKeys) === []) {
                $this->add(1, null, null, 'None of the columns is a measure this import reads.', 'Add at least one of: ' . implode(', ', $names) . '.');
            }
        }
    }

    private array $missing = [];

    /**
     * @param array<int, array<string,mixed>> $rows keyed by file row number when $firstRow is null
     */
    private function checkRowValues(array $spec, array $rows, array $present, ?int $firstRow): void
    {
        $byKey = [];
        foreach ($spec['columns'] as $c) {
            $byKey[$c['key']] = $c;
        }
        $seen = [];
        $i = 0;
        foreach ($rows as $n => $row) {
            $rowNo = $firstRow === null ? $n : $firstRow + $i;
            $i++;
            foreach ($present as $key) {
                $c = $byKey[$key];
                $resolvedValue = $this->resolved !== null ? ($this->resolved[$i - 1][$key] ?? null) : false;
                $this->checkCell($c, $row[$key] ?? null, $rowNo, $resolvedValue);
            }
            foreach ($spec['unique'] as $set) {
                $setKeys = array_map(fn ($name) => $this->keyOf($spec, $name), $set);
                if (array_diff($setKeys, $present) !== []) {
                    continue;
                }
                $parts = array_map(fn ($k) => mb_strtolower(trim((string) ($row[$k] ?? ''))), $setKeys);
                if (implode('', $parts) === '') {
                    continue;
                }
                $sig = implode("\x1F", $setKeys) . "\x1E" . implode("\x1F", $parts);
                if (isset($seen[$sig])) {
                    $label = implode(', ', array_map(fn ($name, $k) => $name . ' ' . trim((string) ($row[$k] ?? '')), $set, $setKeys));
                    $warning = $spec['unique_severity'] === 'warning';
                    $this->add($rowNo, implode(' + ', $set), null, "Repeats row {$seen[$sig]} ({$label}).",
                        $warning ? 'The importer keeps the first and skips the repeat; remove it if it is not meant.' : 'Each ' . implode(' and ', $set) . ' once in the file: delete or correct the repeated row.',
                        $warning ? 'warning' : 'error');
                } else {
                    $seen[$sig] = $rowNo;
                }
            }
        }
    }

    private function keyOf(array $spec, string $name): string
    {
        foreach ($spec['columns'] as $c) {
            if ($c['name'] === $name) {
                return $c['key'];
            }
        }

        return $name;
    }

    /** One cell against its column's rules. */
    private function checkCell(array $c, $value, ?int $rowNo, $resolved = false): void
    {
        $text = is_string($value) ? trim(preg_replace('/^\xEF\xBB\xBF/', '', $value)) : (is_scalar($value) ? (string) $value : '');
        $text = str_replace("\xC2\xA0", ' ', $text);
        $numericish = in_array($c['type'], ['integer', 'decimal', 'percent', 'fraction', 'date', 'month'], true);
        $blank = $text === '' || ($numericish && trim($text, " -") === '');
        $severity = $c['severity'] ?? 'error';

        if ($blank) {
            if ($c['required'] && empty($c['blank_ok'])) {
                $this->add($rowNo, $c['name'], null, "{$c['name']} is blank; every row needs it.", 'Fill in ' . $c['name'] . ($c['example'] !== null && $c['example'] !== '' ? ', e.g. ' . $c['example'] : '') . '.', $severity);
            }

            return;
        }

        $problem = null;
        $fix = null;
        switch ($c['type']) {
            case 'code':
                if (preg_match('/^-?\d+(\.\d+)?E[+\-]?\d+$/i', $text)) {
                    [$problem, $fix] = ['looks like a number Excel shortened to scientific notation', 'Format the column as text in Excel and type the full number again.'];
                }
                break;
            case 'integer':
                $n = str_replace([',', ' '], '', $text);
                if (! preg_match('/^-?\d+(\.0+)?$/', $n)) {
                    [$problem, $fix] = ['is not a whole number', 'Write digits only, e.g. ' . ($c['example'] ?? 12) . '.'];
                } else {
                    [$problem, $fix] = $this->range((float) $n, $c);
                }
                break;
            case 'decimal':
            case 'percent':
            case 'fraction':
                $n = str_replace([',', ' ', '"'], '', $text);
                if ($c['type'] === 'percent' || ! empty($c['percent_sign'])) {
                    $n = rtrim($n, '%');
                }
                if (preg_match('/^\((.*)\)$/', $n, $m)) {
                    if (empty($c['parens'])) {
                        [$problem, $fix] = ['is a negative written in brackets, which this import does not read', 'Write a negative with a minus sign, e.g. -1234.50.'];
                        break;
                    }
                    $n = '-' . $m[1];
                }
                if (! is_numeric($n)) {
                    [$problem, $fix] = ['is not a number', match ($c['type']) {
                        'percent' => 'Write the percentage as a number, e.g. 28.5 for 28.5%.',
                        'fraction' => 'Write a decimal between 0 and 1, e.g. 0.045.',
                        default => 'Write digits with a decimal point, e.g. 1250000.50; thousands commas are allowed.',
                    }];
                } else {
                    [$problem, $fix] = $this->range((float) $n, $c);
                }
                break;
            case 'date':
                // a mapped import: the date is good when the reader resolves it (with the
                // transform chosen on the mapping screen), since that is what is stored
                $ok = $resolved !== false
                    ? is_string($resolved) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $resolved) === 1
                    : $this->isDate($text, $value, $c);
                if (! $ok) {
                    $formats = implode(', ', array_map([ImportSpecs::class, 'humanFormat'], array_slice($c['formats'] ?? ['Y-m-d'], 0, 3)));
                    [$problem, $fix] = is_numeric($text) && empty($c['serial'])
                        ? ['is an Excel date number, which this import does not read', 'Format the column as text and write the date as YYYY-MM-DD.']
                        : ['is not a date in a format this import reads', 'Write the date as ' . $formats . ', e.g. ' . ($c['example'] ?: '2025-12-31') . '.'];
                }
                break;
            case 'month':
                if (! preg_match('/^(\d{4})-(\d{2})$/', $text, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
                    [$problem, $fix] = ['is not a month written YYYY-MM', 'Write the month as YYYY-MM, e.g. 2025-12.'];
                }
                break;
            case 'boolean':
                $t = strtolower($text);
                $true = $c['tokens_true'] ?? ['1', 'true', 'yes', 'on'];
                $false = $c['tokens_false'] ?? ['0', 'false', 'no', 'off'];
                if (! in_array($t, $true, true) && ! in_array($t, $false, true)) {
                    [$problem, $fix] = ['is not yes or no', 'Write one of: ' . implode(', ', array_merge($true, $false)) . '.'];
                }
                break;
            case 'enum':
                $allowed = array_map('strval', $c['allowed'] ?? []);
                if ($allowed === [] && ! empty($c['allowed_source'])) {
                    [$problem, $fix] = ['cannot be checked: no values are set up yet', 'Set up the ' . str_replace('_', ' ', $c['allowed_source']) . ' first, then import.'];
                } elseif (! in_array(mb_strtolower($text), array_map('mb_strtolower', $allowed), true)) {
                    $list = count($allowed) > 12 ? implode(', ', array_slice($allowed, 0, 12)) . ', ...' : implode(', ', $allowed);
                    [$problem, $fix] = ['is not one of the allowed values', 'Use one of: ' . $list . '.'];
                    if (($c['strict'] ?? true) === false) {
                        $severity = 'warning';
                        $fix .= ' It is loaded with a warning otherwise.';
                    }
                }
                break;
        }
        if ($problem === null && ! empty($c['pattern']) && ! preg_match($c['pattern'], $text)) {
            [$problem, $fix] = ['is not in the expected form', 'Write ' . ($c['pattern_hint'] ?? 'the value as in the sample file') . '.'];
        }
        if ($problem !== null) {
            $this->add($rowNo, $c['name'], $text, "'" . mb_strimwidth($text, 0, 60, '...') . "' {$problem}.", $fix, $severity);
        }
    }

    /** @return array{0:?string,1:?string} */
    private function range(float $n, array $c): array
    {
        $min = $c['min'] ?? null;
        $max = $c['max'] ?? null;
        $low = $min !== null && (! empty($c['min_exclusive']) ? $n <= $min : $n < $min);
        $high = $max !== null && (! empty($c['max_exclusive']) ? $n >= $max : $n > $max);
        if (! $low && ! $high) {
            return [null, null];
        }
        $rule = match (true) {
            $min !== null && $max !== null => (! empty($c['min_exclusive']) ? 'above ' : 'from ') . $min . (! empty($c['max_exclusive']) ? ' and below ' : ' to ') . $max,
            $min !== null => (! empty($c['min_exclusive']) ? 'above ' : 'at least ') . $min,
            default => (! empty($c['max_exclusive']) ? 'below ' : 'at most ') . $max,
        };
        $hint = match ($c['type']) {
            'percent' => ' Rates are percentages, such as 25.30, not fractions.',
            'fraction' => ' Write a fraction, e.g. 0.045 for 4.5%.',
            default => '',
        };

        return ['is out of range', 'The value must be ' . $rule . '.' . $hint];
    }

    private function isDate(string $text, $raw, array $c): bool
    {
        if (! empty($c['serial']) && is_numeric($text)) {
            $n = (float) $text;

            return $n > 20000 && $n < 80000;
        }
        foreach ($c['formats'] ?? ['Y-m-d'] as $format) {
            $d = DateTime::createFromFormat('!' . $format, $text);
            $errors = DateTime::getLastErrors();
            if ($d !== false && (! $errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && (empty($c['round_trip']) || $d->format($format) === $text)) {
                return true;
            }
        }

        return false;
    }

    /* ------------------------------------------------------------------ */
    /* Positional layouts                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * The E-Banker loan book report, read the way ProcessLoanImportJob reads
     * it: context rows, header rows and data rows by their first cells.
     */
    private function checkEbankerReport(array $spec, string $path): int
    {
        [, $lines] = $this->readGrid($path, null, false);
        $context = 0;
        $data = 0;
        $seen = [];
        $col = fn (string $name) => collect($spec['columns'])->firstWhere('name', $name);
        foreach ($lines as $n => $row) {
            if (array_filter($row, fn ($v) => trim((string) $v) !== '') === []) {
                continue;
            }
            foreach ($row as $cell) {
                if (stripos(trim((string) $cell), 'Loan Type') !== false) {
                    $context++;
                    continue 2;
                }
            }
            // a row that starts with its serial number is a loan, whatever its other cells say
            // (a name such as David holds "ID"); anything else is a heading or a note
            $first = trim((string) ($row[0] ?? ''));
            $serial = $first === '' && is_numeric(trim((string) ($row[1] ?? ''))) ? trim((string) $row[1]) : $first;
            if (! is_numeric($serial)) {
                continue;
            }
            $data++;
            $s = $first === '' ? 1 : 0;
            if (count($row) < 29 + $s) {
                $this->add($n, null, null, 'The row has ' . count($row) . ' columns; the report has at least ' . (29 + $s) . ', so the arrears columns would be read as zero.', 'Export the report again without removing columns.');
                continue;
            }
            $cells = [
                'Customer ID' => $row[1 + $s] ?? null, 'Loan account' => $row[3 + $s] ?? null, 'Value date' => $row[5 + $s] ?? null, 'Maturity' => $row[6 + $s] ?? null,
                'Interest' => $row[9 + $s] ?? null, 'Principal' => $row[13 + $s] ?? null, 'Carrying' => $row[19 + $s] ?? null,
                '1 - 30 Days' => $row[24 + $s] ?? null, '31 - 90 Days' => $row[25 + $s] ?? null, '91 - 180 Days' => $row[26 + $s] ?? null, '181 - 270 Days' => $row[28 + $s] ?? null,
            ];
            foreach ($cells as $name => $v) {
                $c = $col($name);
                if ($name === 'Interest' && is_string($v) && str_contains($v, '%')) {
                    $this->add($n, $name, $v, "'{$v}' carries a % sign, which this import reads as zero.", 'Write the rate as a number, e.g. 28.5.');
                    continue;
                }
                $this->checkCell($c, $v, $n);
            }
            $account = trim((string) $cells['Loan account']);
            if ($account !== '') {
                if (isset($seen[$account])) {
                    $this->add($n, 'Loan account', $account, "Loan account {$account} repeats row {$seen[$account]}; only the last would be kept.", 'Each loan account once in the report.');
                } else {
                    $seen[$account] = $n;
                }
            }
        }
        if ($data === 0) {
            $this->add(null, null, null, 'No loan rows were found: no row starts with a serial number.', 'Choose the loan book report as E-Banker exports it, or use the Standard CSV layout.');
        }
        if ($context === 0 && $data > 0) {
            $this->add(null, null, null, 'The report has no "Loan Type :" rows, so every loan is loaded with the product group Unknown.', 'Keep the Loan Type rows of the report.', 'warning');
        }

        return $data;
    }

    /** The trial balance gates of TrialBalanceLandingService, run without writing. */
    private function checkTrialBalance(string $path, string $name, array $options): int
    {
        $importer = new TrialBalanceImportService();
        $period = ! empty($options['period']) ? (strlen($options['period']) === 7 ? $options['period'] . '-01' : $options['period']) : null;
        $read = $importer->read($path, $period, $options['sheet'] ?? null);
        if ($read['period'] === null) {
            $this->add(1, 'Period (B1)', null, 'Cell B1 carries no date and no period was entered.', 'Enter the period on the screen, or keep the date E-Banker writes in B1.');
        }
        if ($read['rows'] === []) {
            $this->add(null, 'GL Title', null, "No trial balance rows were found; each GL line needs 'code...title' in column B.", 'Choose the trial balance as E-Banker exports it.');

            return 0;
        }
        foreach ($read['rows'] as $r) {
            foreach (['debit_text' => 'Debit', 'credit_text' => 'Credit'] as $k => $label) {
                $v = (string) ($r[$k] ?? '');
                $n = str_replace([',', '(', ')', ' '], '', $v);
                if ($v !== '' && $v !== '-' && ! is_numeric($n)) {
                    $this->add($r['row'], $label, $v, "'{$v}' in {$label} is not a number.", 'Correct the amount in the file.');
                }
            }
        }
        try {
            $importer->assertTies($name, $read['rows'], $read['debit'], $read['credit'], $read['grand_total']);
        } catch (RuntimeException $e) {
            $this->add(null, 'Grand Total :', null, $e->getMessage(), 'The file is refused as it stands: check that no GL line is missing and that the Grand Total row is the file\'s own.');
        }

        return count($read['rows']);
    }

    /** A two-column file read by position, with an optional heading row. */
    private function checkPositional(array $spec, string $path): int
    {
        [, $lines] = $this->readGrid($path, null, false);
        $cols = $spec['columns'];
        $rows = [];
        $first = true;
        foreach ($lines as $n => $line) {
            if ($first && ! empty($spec['header_optional'])) {
                $first = false;
                if (! is_numeric(str_replace(['%', ' '], '', (string) ($line[1] ?? '')))) {
                    continue;
                }
            }
            $first = false;
            if (array_filter($line, fn ($v) => trim((string) $v) !== '') === []) {
                continue;
            }
            $row = [];
            foreach ($cols as $i => $c) {
                $row[$c['key']] = $line[$i] ?? null;
            }
            $rows[$n] = $row;
        }
        if ($rows === []) {
            $this->add(null, null, null, 'The file has no rows.', 'Fill one row per date, as in the sample file.');
        }
        $this->checkRowValues($spec, $rows, array_map(fn ($c) => $c['key'], $cols), null);

        return count($rows);
    }

    /** The IMF WEO download: its headings, as ImfWeoParserService needs them. */
    private function checkImfWeo(array $spec, string $path): int
    {
        $raw = (string) file_get_contents($path);
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = @iconv('UTF-16', 'UTF-8', $raw) ?: $raw;
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $headers = array_map('trim', explode("\t", (string) array_shift($lines)));
        foreach (['ISO', 'WEO Subject Code'] as $need) {
            if (! collect($headers)->contains(fn ($h) => strcasecmp($h, $need) === 0)) {
                $this->add(1, $need, null, "The required column {$need} is missing.", 'Choose the WEO file as the IMF publishes it (tab-delimited, by countries).');
            }
        }
        if (! collect($headers)->contains(fn ($h) => preg_match('/^(19|20|21)\d{2}$/', $h))) {
            $this->add(1, '1980 ... 2030', null, 'The heading row has no year columns.', 'Choose the WEO file as the IMF publishes it.');
        }

        return count(array_filter($lines, fn ($l) => trim($l) !== ''));
    }

    /* ------------------------------------------------------------------ */
    /* Reading                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{0:list<string>,1:array<int,list<mixed>>} the heading row and the data rows keyed by file row number
     */
    private function readGrid(string $path, ?string $delimiter = null, bool $withHeader = true): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException('the file cannot be opened');
        }
        $grid = $this->isSpreadsheet($path) ? $this->readSpreadsheet($path) : $this->readCsv($path, $delimiter);
        if (! $withHeader) {
            $out = [];
            foreach ($grid as $i => $line) {
                $out[$i + 1] = $line;
            }

            return [[], $out];
        }
        $headers = array_map(fn ($h) => is_scalar($h) ? (string) $h : '', $grid[0] ?? []);
        $rows = [];
        foreach (array_slice($grid, 1, null, true) as $i => $line) {
            if (array_filter($line, fn ($v) => $v !== null && trim((string) $v) !== '') === []) {
                continue;
            }
            $rows[$i + 1] = $line;
        }

        return [$headers, $rows];
    }

    private function isSpreadsheet(string $path): bool
    {
        $h = fopen($path, 'rb');
        $magic = $h ? (string) fread($h, 8) : '';
        if ($h) {
            fclose($h);
        }

        return str_starts_with($magic, "PK\x03\x04") || str_starts_with($magic, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
    }

    private function readSpreadsheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getSheet(0);

        return array_map(fn ($line) => array_map(fn ($v) => is_string($v) ? $this->utf8($v) : $v, $line), $sheet->toArray(null, true, false, false));
    }

    private function readCsv(string $path, ?string $delimiter): array
    {
        $raw = (string) file_get_contents($path);
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = @iconv('UTF-16', 'UTF-8', $raw) ?: $raw;
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $first = strtok($raw, "\n") ?: '';
        if ($delimiter === null) {
            $delimiter = ',';
            $best = 0;
            foreach ([',', ';', "\t", '|'] as $d) {
                if (($n = substr_count($first, $d)) > $best) {
                    [$delimiter, $best] = [$d, $n];
                }
            }
        }
        $h = fopen('php://temp', 'r+');
        fwrite($h, $raw);
        rewind($h);
        $grid = [];
        while (($line = fgetcsv($h, 0, $delimiter, '"', '\\')) !== false) {
            $grid[] = array_map(fn ($v) => $v === null ? null : $this->utf8((string) $v), $line);
        }
        fclose($h);

        return $grid;
    }

    private function utf8(string $v): string
    {
        return mb_check_encoding($v, 'UTF-8') ? $v : mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
    }

    /* ------------------------------------------------------------------ */

    private function reset(): void
    {
        $this->issues = [];
        $this->errors = 0;
        $this->warnings = 0;
        $this->ignored = [];
        $this->missing = [];
    }

    private function add(?int $row, ?string $column, $value, string $message, ?string $fix = null, string $severity = 'error'): void
    {
        $severity === 'warning' ? $this->warnings++ : $this->errors++;
        if (count($this->issues) < self::MAX_ISSUES) {
            $this->issues[] = ['row' => $row, 'column' => $column, 'value' => $value === null ? null : mb_strimwidth((string) $value, 0, 80, '...'), 'message' => $message, 'fix' => $fix, 'severity' => $severity];
        }
    }

    private function result(array $spec, string $name, int $rows, ?array $missing = null, ?array $ignored = null): array
    {
        // errors first, then by row
        $issues = $this->issues;
        usort($issues, fn ($a, $b) => [$a['severity'] === 'error' ? 0 : 1, $a['row'] ?? 0] <=> [$b['severity'] === 'error' ? 0 : 1, $b['row'] ?? 0]);

        return [
            'key' => $spec['key'], 'title' => $spec['title'], 'file' => $name, 'ok' => $this->errors === 0, 'rows' => $rows,
            'error_count' => $this->errors, 'warning_count' => $this->warnings,
            'missing_columns' => $missing ?? $this->missing, 'ignored_columns' => array_values(array_slice($ignored ?? $this->ignored, 0, 40)),
            'issues' => $issues, 'truncated' => ($this->errors + $this->warnings) > count($issues), 'checked_at' => now()->toDateTimeString(),
        ];
    }
}
