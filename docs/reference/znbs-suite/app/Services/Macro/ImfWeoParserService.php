<?php

namespace App\Services\Macro;

use App\Models\MacroVariable;
use App\Support\MacroPeriod;
use RuntimeException;

/**
 * Parses the IMF World Economic Outlook (WEO) dataset for a given macro
 * variable's indicator code and returns a normalised preview.
 *
 * Quirks of the WEO file:
 *   - Despite the `.xls` extension, the IMF publishes WEO as a tab-delimited
 *     UTF-16 LE text file (not real Excel). This parser handles that natively
 *     without needing PhpSpreadsheet.
 *   - "Estimates Start After" column (sometimes blank, often "n.a.") tells you
 *     which year is the first FORECAST year. Years <= that value are 'actual'
 *     (historical/observed), years > are 'forecast' (IMF projection).
 *   - The file covers every country × every indicator (~200k rows). We filter
 *     to ZMB + the chosen indicator before doing anything else.
 *
 * Source URL (no API key, download by hand and upload to the system):
 *   https://www.imf.org/en/Publications/WEO/weo-database/
 */
class ImfWeoParserService
{
    /** ISO3 selected when the caller does not override the country. */
    private const DEFAULT_COUNTRY = 'ZMB';

    /**
     * @param  string  $filePath   absolute path to the uploaded WEO file
     * @param  MacroVariable  $variable  the variable being imported (its
     *         `external_codes['imf_weo']` identifies the indicator row)
     *
     * @return array{
     *   variable_id: int,
     *   variable_code: string,
     *   indicator_code: string,
     *   country: string,
     *   source: string,
     *   units: ?string,
     *   estimates_start_after: ?int,
     *   fetched_at: string,
     *   rows: list<array{period_date: string, period_label: string, period_type: string, value: float, value_type: string}>,
     * }
     */
    public function parse(string $filePath, MacroVariable $variable, ?string $country = null, ?int $yearFrom = null, ?int $yearTo = null): array
    {
        // Omitted country/range reproduce the previous behaviour exactly (ZMB,
        // all years) so the change is golden-safe.
        $iso  = strtoupper(trim((string) ($country ?? ''))) ?: self::DEFAULT_COUNTRY;
        $from = $yearFrom !== null && $yearFrom > 0 ? $yearFrom : null;
        $to   = $yearTo   !== null && $yearTo   > 0 ? $yearTo   : null;
        if ($from !== null && $to !== null && $to < $from) {
            [$from, $to] = [$to, $from];
        }

        $indicator = $variable->external_codes['imf_weo'] ?? null;
        if (! $indicator) {
            return $this->emptyPayload($variable, null,
                "No IMF WEO indicator code configured for this variable. Add one under Edit → IMF WEO code.");
        }

        if (! is_file($filePath)) {
            return $this->emptyPayload($variable, $indicator, "WEO file not found at: {$filePath}");
        }

        // WEO ships as UTF-16 LE TSV. iconv on the fly.
        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new RuntimeException("Cannot read WEO file: {$filePath}");
        }

        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = @iconv('UTF-16', 'UTF-8', $raw) ?: $raw;
        }
        // Strip BOM after conversion.
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        if (! $lines || count($lines) < 2) {
            throw new RuntimeException('WEO file appears empty.');
        }

        $headers = explode("\t", array_shift($lines));
        $headers = array_map(fn ($h) => trim($h), $headers);

        $idxIso       = $this->headerIndex($headers, 'ISO');
        $idxSubject   = $this->headerIndex($headers, 'WEO Subject Code');
        $idxUnits     = $this->headerIndex($headers, 'Units', false);
        $idxEstStart  = $this->headerIndex($headers, 'Estimates Start After', false);
        $idxNotes     = $this->headerIndex($headers, 'Subject Notes', false);

        // Year columns: any header that's a 4-digit year.
        $yearCols = [];
        foreach ($headers as $i => $h) {
            if (preg_match('/^(19|20|21)\d{2}$/', $h)) {
                $yearCols[(int) $h] = $i;
            }
        }
        if ($yearCols === []) {
            throw new RuntimeException('WEO file has no year columns in the header row.');
        }
        ksort($yearCols);

        // Locate the single row for (ISO=ZMB, SubjectCode=$indicator).
        $rowFields = null;
        foreach ($lines as $line) {
            if ($line === '' || $line[0] === "\t") continue;
            $cells = explode("\t", $line);
            if (strtoupper(trim($cells[$idxIso] ?? '')) === $iso
                && strtoupper(trim($cells[$idxSubject] ?? '')) === strtoupper($indicator)) {
                $rowFields = $cells;
                break;
            }
        }

        if ($rowFields === null) {
            return $this->emptyPayload($variable, $indicator,
                "No data available: the WEO file has no row for {$iso} x {$indicator}.", $iso);
        }

        $units = $idxUnits !== null ? trim($rowFields[$idxUnits] ?? '') : null;
        $estStartRaw = $idxEstStart !== null ? trim($rowFields[$idxEstStart] ?? '') : '';
        $estStart = is_numeric($estStartRaw) ? (int) $estStartRaw : null;

        $rows = [];
        foreach ($yearCols as $year => $colIdx) {
            // Honour an optional caller-supplied year range.
            if (($from !== null && $year < $from) || ($to !== null && $year > $to)) {
                continue;
            }
            $raw = trim($rowFields[$colIdx] ?? '');
            if ($raw === '' || $raw === 'n/a' || $raw === 'n.a.' || $raw === '--') continue;

            // IMF WEO uses commas as thousand separators in some unit types.
            $clean = str_replace([',', ' '], '', $raw);
            if (! is_numeric($clean)) continue;

            $rows[] = [
                'period_date'  => sprintf('%d-12-31', $year),
                'period_label' => MacroPeriod::deriveLabel(sprintf('%d-12-31', $year), 'annual'),
                'period_type'  => 'annual',
                'value'        => round((float) $clean, 6),
                'value_type'   => ($estStart !== null && $year > $estStart) ? 'forecast' : 'actual',
            ];
        }

        if ($rows === []) {
            return $this->emptyPayload($variable, $indicator,
                "No data available: WEO row for {$iso} x {$indicator} has no numeric year values in the selected range.", $iso);
        }

        return [
            'variable_id'           => $variable->id,
            'variable_code'         => $variable->code,
            'indicator_code'        => $indicator,
            'country'               => $iso,
            'source'                => 'IMF World Economic Outlook',
            'units'                 => $units,
            'estimates_start_after' => $estStart,
            'fetched_at'            => now()->toDateTimeString(),
            'rows'                  => $rows,
        ];
    }

    private function headerIndex(array $headers, string $needle, bool $required = true): ?int
    {
        foreach ($headers as $i => $h) {
            if (strcasecmp(trim($h), $needle) === 0) {
                return $i;
            }
        }
        if ($required) {
            throw new RuntimeException("WEO header missing expected column: {$needle}");
        }
        return null;
    }

    /**
     * Well-formed "no data" payload the UI can render without erroring.
     * @return array<string,mixed>
     */
    private function emptyPayload(MacroVariable $variable, ?string $indicator, string $message, ?string $country = null): array
    {
        return [
            'variable_id'           => $variable->id,
            'variable_code'         => $variable->code,
            'indicator_code'        => $indicator,
            'country'               => $country ?: self::DEFAULT_COUNTRY,
            'source'                => 'IMF World Economic Outlook',
            'units'                 => null,
            'estimates_start_after' => null,
            'fetched_at'            => now()->toDateTimeString(),
            'rows'                  => [],
            'message'               => $message,
        ];
    }
}
