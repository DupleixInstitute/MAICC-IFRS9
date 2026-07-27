<?php

namespace App\Services\Imports;

use App\Models\ImportMapping;
use Carbon\Carbon;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

/**
 * Dynamic column-mapping file reader (docs/EIR_Build.md §4 Phase 2).
 *
 * Client files arrive in whatever shape Ebanker exports. Instead of exact
 * header validation (legacy repo) or hardcoded alias lists (legacy
 * map_table_columns.php), headers are mapped to target fields once — via
 * the mapping UI or a saved template in import_mappings — and this reader
 * applies the mapping plus per-column transforms.
 *
 * Rules:
 *  - unmapped REQUIRED fields block the read with a named list;
 *  - unmapped source columns are ignored loudly (returned in the report,
 *    never silently dropped);
 *  - all values pass through the shared cleaning rules (thousands
 *    separators, non-breaking spaces, "-" placeholders) that the loan book
 *    import already applies.
 */
class MappedFileReader
{
    /** Required target fields per import type. */
    public const REQUIRED_FIELDS = [
        'schedule' => ['contract_id', 'due_date', 'principal_due', 'interest_due'],
        'fees'     => ['contract_id', 'fee_type', 'amount'],
    ];

    /** Optional target fields per import type (for the mapping UI). */
    public const OPTIONAL_FIELDS = [
        'schedule' => ['fee_due'],
        'fees'     => ['basis', 'integral', 'gl_account_ref'],
    ];

    /**
     * Inspect a file for the mapping UI: detected headers, a short data
     * preview, the saved template's matches, and which required fields are
     * still unmapped.
     */
    public function analyze(string $path, string $importType): array
    {
        $this->assertKnownType($importType);

        [$headers, $rows] = $this->readRaw($path, 5);

        $template = $this->normaliseTemplate(ImportMapping::templateFor($importType));
        $mapping  = $this->resolveMapping($headers, $template);

        return [
            'headers'          => $headers,
            'preview'          => $rows,
            'mapping'          => $mapping,
            'unmapped_headers' => array_values(array_diff($headers, array_keys($mapping))),
            'missing_required' => $this->missingRequired($importType, $mapping),
            'required_fields'  => self::REQUIRED_FIELDS[$importType],
            'optional_fields'  => self::OPTIONAL_FIELDS[$importType],
        ];
    }

    /**
     * Read the full file as rows keyed by target field, transforms applied.
     *
     * @param array|null $mapping  [source_header => target_field] override
     *                             (the UI flow); null loads the saved
     *                             template (the recurring flow).
     * @param array      $transforms [target_field => transform] e.g.
     *                             ['due_date' => 'date:d/m/Y', 'amount' => 'number']
     * @return array{rows: list<array<string,mixed>>, report: array}
     */
    public function read(string $path, string $importType, ?array $mapping = null, array $transforms = []): array
    {
        $this->assertKnownType($importType);

        [$headers, $rawRows] = $this->readRaw($path, null);

        $mapping = $this->resolveMapping(
            $headers,
            $this->normaliseTemplate($mapping ?? ImportMapping::templateFor($importType))
        );

        $missing = $this->missingRequired($importType, $mapping);
        if ($missing !== []) {
            throw new RuntimeException(
                "{$importType} import needs unmapped required field(s): " . implode(', ', $missing)
            );
        }

        $rows = [];
        foreach ($rawRows as $raw) {
            $row = [];
            foreach ($mapping as $sourceHeader => $targetField) {
                $value = $raw[$sourceHeader] ?? null;
                $row[$targetField] = $this->applyTransform($value, $transforms[$targetField] ?? null);
            }
            $rows[] = $row;
        }

        return [
            'rows'   => $rows,
            'report' => [
                'total_rows'       => count($rows),
                'mapped_headers'   => $mapping,
                'unmapped_headers' => array_values(array_diff($headers, array_keys($mapping))),
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Cleaning + transforms                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Shared numeric cleaning — same rules the loan book import applies
     * (thousands separators, NBSP/zero-width garbage, "-" placeholders).
     */
    public static function cleanNumber($value): float
    {
        if ($value === null || $value === false) {
            return 0.0;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        if ($value === '' || $value === '-' || trim($value, " -\xC2\xA0") === '') {
            return 0.0;
        }

        $cleaned = str_replace(
            [',', ' ', "\xC2\xA0", "\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", '"', "\t"],
            '',
            $value
        );

        // Accounting negatives: (1,234.56) means -1234.56.
        if (preg_match('/^\((.*)\)$/', $cleaned, $m)) {
            $cleaned = '-' . $m[1];
        }

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }

    /** Header normalisation — same convention as LoanBooksImport. */
    public static function normalizeHeader($key): string
    {
        $key = trim((string) $key, " \t\n\r\0\x0B\"'");
        $key = preg_replace('/\s+/', ' ', $key);
        $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
        $key = preg_replace('/_+/', '_', $key);

        return trim(strtolower($key), '_');
    }

    private function applyTransform($value, ?string $transform)
    {
        if ($transform === null || $transform === 'text') {
            return is_string($value) ? trim($value) : $value;
        }

        if ($transform === 'number' || $transform === 'strip_commas') {
            return self::cleanNumber($value);
        }

        if ($transform === 'percent') {
            // Normalise percent-vs-decimal ambiguity: 32.10 → 0.3210.
            $n = self::cleanNumber($value);
            return $n > 1 ? $n / 100 : $n;
        }

        if ($transform === 'date' || str_starts_with($transform, 'date:')) {
            return $this->toDateString($value, substr($transform, 5) ?: null);
        }

        throw new InvalidArgumentException("Unknown transform '{$transform}'");
    }

    private function toDateString($value, ?string $format): ?string
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }

        // Excel serial dates arrive as numerics from XLSX cells.
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        try {
            $date = $format
                ? Carbon::createFromFormat($format, trim((string) $value))
                : Carbon::parse(trim((string) $value));

            return $date->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /* ------------------------------------------------------------------ */
    /* File reading                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{0: list<string>, 1: list<array<string,mixed>>}
     *         normalised headers + rows keyed by normalised header
     */
    private function readRaw(string $path, ?int $limit): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("File not readable: {$path}");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['xlsx', 'xls', 'ods'], true)
            ? $this->readSpreadsheet($path, $limit)
            : $this->readCsv($path, $limit);
    }

    private function readCsv(string $path, ?int $limit): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open {$path}");
        }

        try {
            $firstLine = fgets($handle) ?: '';
            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);

            $headerRow = fgetcsv($handle, 0, $delimiter, '"', '\\');
            if ($headerRow === false) {
                throw new RuntimeException('File has no header row');
            }
            // Strip a UTF-8 BOM from the first header cell.
            $headerRow[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headerRow[0]);

            $headers = array_map([self::class, 'normalizeHeader'], $headerRow);

            $rows = [];
            while (($line = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue; // skip blank lines
                }
                $rows[] = $this->combineRow($headers, $line);
                if ($limit !== null && count($rows) >= $limit) {
                    break;
                }
            }

            return [$headers, $rows];
        } finally {
            fclose($handle);
        }
    }

    private function readSpreadsheet(string $path, ?int $limit): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();

        $headers = [];
        $rows = [];
        foreach ($sheet->toArray(null, true, false, false) as $i => $line) {
            if ($i === 0) {
                $headers = array_map([self::class, 'normalizeHeader'], $line);
                continue;
            }
            if (count(array_filter($line, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                continue;
            }
            $rows[] = $this->combineRow($headers, $line);
            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
        }

        return [$headers, $rows];
    }

    private function combineRow(array $headers, array $line): array
    {
        $row = [];
        foreach ($headers as $i => $header) {
            if ($header === '') {
                continue;
            }
            $row[$header] = $line[$i] ?? null;
        }

        return $row;
    }

    private function detectDelimiter(string $line): string
    {
        $best = ',';
        $bestCount = 0;
        foreach ([',', ';', "\t", '|'] as $candidate) {
            $count = substr_count($line, $candidate);
            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }

    /* ------------------------------------------------------------------ */
    /* Mapping resolution                                                 */
    /* ------------------------------------------------------------------ */

    /** Normalise template keys so saved headers match detected headers. */
    private function normaliseTemplate(array $template): array
    {
        $normalised = [];
        foreach ($template as $sourceHeader => $targetField) {
            $normalised[self::normalizeHeader($sourceHeader)] = $targetField;
        }

        return $normalised;
    }

    /** Keep only template entries whose source header exists in the file. */
    private function resolveMapping(array $headers, array $template): array
    {
        return array_intersect_key($template, array_flip($headers));
    }

    private function missingRequired(string $importType, array $mapping): array
    {
        return array_values(array_diff(self::REQUIRED_FIELDS[$importType], array_values($mapping)));
    }

    private function assertKnownType(string $importType): void
    {
        if (! isset(self::REQUIRED_FIELDS[$importType])) {
            throw new InvalidArgumentException(
                "Unknown import type '{$importType}' — known: " . implode(', ', array_keys(self::REQUIRED_FIELDS))
            );
        }
    }
}
