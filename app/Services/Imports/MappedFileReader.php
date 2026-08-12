<?php

namespace App\Services\Imports;

use App\Imports\ContractMasterImport;
use App\Imports\ContractTransactionImport;
use App\Imports\GlInterestImport;
use App\Models\ImportMapping;
use App\Support\ContractId;
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
    /**
     * Rows scanned to profile a column's distinct values. MAIIC's files run to
     * hundreds of rows, not millions; the cap exists so a mis-selected file
     * cannot stall the mapping screen, not because the real ones approach it.
     */
    private const PROFILE_ROW_LIMIT = 5000;

    /** Beyond this a column is a key, not a category — stop collecting. */
    private const PROFILE_DISTINCT_LIMIT = 200;

    /**
     * Distinct values returned per column, most frequent first. Three is
     * enough to recognise a column; the distinct and blank counts carry the
     * rest, so a wide file does not turn the mapping screen into a wall.
     */
    private const PROFILE_SHOW_VALUES = 3;

    /** Required target fields per import type. */
    public const REQUIRED_FIELDS = [
        'schedule' => ['contract_id', 'due_date', 'principal_due', 'interest_due'],
        'fees'     => ['contract_id', 'fee_type', 'amount'],
        // Extract A. Only the identifier is required: the delivered column set
        // is still being confirmed (spec open item 10), and refusing a file for
        // a missing optional term would block the master load over a term the
        // solver's readiness gate already reports contract by contract.
        'contract_master' => ['contract_id'],
        'contract_transactions' => [
            'customer_id', 'contract_id', 'sub_account_no', 'transaction_date',
            'transaction_type', 'principal_component', 'interest_component',
            'fee_component', 'total_amount', 'scheduled_actual_flag', 'gl_posting_ref',
        ],
        // Extract C. The period is required because a posting without one
        // cannot be reconciled against any month.
        'gl_interest' => ['contract_id', 'period_year', 'period_month', 'interest_income_posted'],
    ];

    /** Optional target fields per import type (for the mapping UI). */
    public const OPTIONAL_FIELDS = [
        'schedule' => ['fee_due'],
        'fees'     => [
            'description', 'transaction_date', 'cashflow_direction', 'currency',
            'source_system', 'source_reference', 'external_transaction_id',
            'basis', 'gl_account_ref',
        ],
        'contract_master' => [
            'run_id', 'customer_id', 'portfolio', 'sub_account_no', 'gl_account_code', 'currency',
            'product_type', 'origination_date', 'first_repayment_date', 'maturity_date',
            'closure_date', 'last_restructure_date', 'approved_amount', 'drawn_amount',
            'contractual_rate', 'rate_basis', 'rate_type', 'reference_rate_at_origination',
            'source_day_count_basis', 'source_compounding', 'disbursement_tranches',
            'markup', 'repayment_frequency', 'payments_per_year', 'tenor_months',
            'moratorium_months', 'arrangement_fee', 'legal_fees',
            'opening_amortised_cost', 'opening_amortised_cost_date',
        ],
        'contract_transactions' => ['run_id', 'balance_after_transaction', 'row_note'],
        'gl_interest' => [
            'run_id', 'gl_account_code', 'period_type', 'reporting_period',
            'transaction_count', 'posting_references', 'row_note', 'generated_on',
        ],
    ];

    /**
     * Inspect a file for the mapping UI: detected headers, a short data
     * preview, the saved template's matches, and which required fields are
     * still unmapped.
     */
    public function analyze(string $path, string $importType): array
    {
        $this->assertKnownType($importType);

        [$headers, $rows] = $this->readRaw($path, self::PROFILE_ROW_LIMIT);

        $template = $this->templateFor($importType);
        $mapping  = $this->resolveMapping($headers, $template);

        return [
            'headers'          => $headers,
            'preview'          => array_slice($rows, 0, 5),
            'profile'          => $this->profile($headers, $rows),
            'profiled_rows'    => count($rows),
            'mapping'          => $mapping,
            'unmapped_headers' => array_values(array_diff($headers, array_keys($mapping))),
            'missing_required' => $this->missingRequired($importType, $mapping),
            'required_fields'  => self::REQUIRED_FIELDS[$importType],
            'optional_fields'  => self::OPTIONAL_FIELDS[$importType],
        ];
    }

    /**
     * Per-column distinct values with counts — the mapping screen's equivalent
     * of switching on Excel's filter dropdown.
     *
     * Showing the first three rows told an operator almost nothing: a column
     * reading "FInES · FInES · FInES" hides that a second portfolio exists,
     * and "45658 · 45658 · 45658" hides that the column is an Excel serial at
     * all. The facts that actually decide a mapping are categorical — that
     * day_count_basis holds both 365 and 360, that repayment_frequency has
     * blanks, that a column is constant and therefore useless as a key — and
     * none of them are visible in a three-row sample.
     *
     * @param  list<string>  $headers
     * @param  list<array<string,mixed>>  $rows
     * @return array<string,array{values:list<array{value:string,count:int}>,distinct:int,blank:int,truncated:bool}>
     */
    private function profile(array $headers, array $rows): array
    {
        $profile = [];

        foreach ($headers as $header) {
            if ($header === '') {
                continue;
            }

            $counts = [];
            $blank = 0;
            $truncated = false;

            foreach ($rows as $row) {
                $value = $row[$header] ?? null;
                $text = is_string($value) ? trim($value) : (is_scalar($value) ? (string) $value : '');

                if ($text === '' || $text === '-') {
                    $blank++;
                    continue;
                }
                if (isset($counts[$text])) {
                    $counts[$text]++;
                    continue;
                }
                // Stop collecting new values once a column is clearly a key
                // rather than a category; the count still tells the operator
                // it is high-cardinality.
                if (count($counts) >= self::PROFILE_DISTINCT_LIMIT) {
                    $truncated = true;
                    continue;
                }
                $counts[$text] = 1;
            }

            arsort($counts);

            $profile[$header] = [
                'values' => array_map(
                    fn ($value, $count) => ['value' => (string) $value, 'count' => $count],
                    array_keys(array_slice($counts, 0, self::PROFILE_SHOW_VALUES, true)),
                    array_values(array_slice($counts, 0, self::PROFILE_SHOW_VALUES, true))
                ),
                'distinct'  => count($counts),
                'blank'     => $blank,
                'truncated' => $truncated,
            ];
        }

        return $profile;
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
            $mapping === null ? $this->templateFor($importType) : $this->normaliseTemplate($mapping)
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
                // contract_id is always canonicalised, whatever transform the
                // operator picked: a mis-mapped identifier holds every row of
                // the file, and the padding differs between E-Banker and the
                // loan tape (see App\Support\ContractId).
                $row[$targetField] = $targetField === 'contract_id'
                    ? ContractId::normalise($value)
                    : $this->applyTransform($value, $transforms[$targetField] ?? null);
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
        $key = self::toUtf8((string) $key);
        $key = trim($key, " \t\n\r\0\x0B\"'");
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

        if ($transform === 'contract_id') {
            return ContractId::normalise($value);
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

    /**
     * Date columns in the MAIIC extracts are mixed-type: Extract B carries
     * every "Actual" row as a dd-mm-yyyy string and every "Scheduled" row as
     * an Excel serial, and Extract A mixes serials, dd-mm-yyyy and yyyy-mm-dd
     * inside a single column. A declared format is therefore treated as a
     * hint, not a contract — when it does not fit the cell we fall back to
     * flexible parsing rather than discarding the value, because a silent
     * null here drops the row at validation with a misleading reason.
     */
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

        $raw = trim((string) $value);

        if ($format !== null) {
            try {
                // createFromFormat is lenient about separators, so require the
                // round-trip to match before trusting the declared format.
                $date = Carbon::createFromFormat($format, $raw);
                if ($date !== false && $date->format($format) === $raw) {
                    return $date->toDateString();
                }
            } catch (Throwable) {
                // fall through to flexible parsing
            }
        }

        try {
            return Carbon::parse($raw)->toDateString();
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

        return $this->isSpreadsheet($path)
            ? $this->readSpreadsheet($path, $limit)
            : $this->readCsv($path, $limit);
    }

    /**
     * Choose the reader by content, never by file name.
     *
     * The intake controller analyses the raw upload via
     * UploadedFile::getPathname(), which is a temp file called phpXXXX.tmp —
     * so an extension check sends every uploaded .xlsx down the CSV path,
     * where the ZIP container is parsed as text. The symptom is not a helpful
     * error but "Malformed UTF-8 characters" from json_encode, because the
     * binary becomes header strings. Sniffing also covers the opposite case,
     * a CSV a user has renamed .xlsx.
     */
    private function isSpreadsheet(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $magic = (string) fread($handle, 8);
        fclose($handle);

        // xlsx and ods are ZIP containers; legacy xls is an OLE2 compound file.
        return str_starts_with($magic, "PK\x03\x04")
            || str_starts_with($magic, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
    }

    /**
     * Excel writes "CSV" in the machine's ANSI codepage, not UTF-8, so a file
     * with a client name carrying an accent arrives as invalid UTF-8 and takes
     * down the whole JSON response rather than one cell.
     */
    private static function toUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8')
            ? $value
            : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
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
            $value = $line[$i] ?? null;
            $row[$header] = is_string($value) ? self::toUtf8($value) : $value;
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

    private function templateFor(string $importType): array
    {
        $aliases = match ($importType) {
            'contract_master' => ContractMasterImport::aliases(),
            'contract_transactions' => ContractTransactionImport::aliases(),
            'gl_interest' => GlInterestImport::aliases(),
            default => [],
        };

        return array_replace(
            $this->normaliseTemplate($aliases),
            $this->normaliseTemplate(ImportMapping::templateFor($importType))
        );
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
