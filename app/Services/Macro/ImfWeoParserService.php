<?php

namespace App\Services\Macro;

use RuntimeException;

/**
 * Reads the IMF World Economic Outlook download (a tab-delimited, often
 * UTF-16 text file despite its .xls name), filters to the country and the
 * indicator a series carries in external_codes['imf_weo'], and marks each
 * year actual or forecast from the "Estimates Start After" column. This is
 * where the forecast years come from (spec v4 section 13.3). Ported from
 * the Dupleix suite; returns a preview, writes nothing.
 */
class ImfWeoParserService
{
    public const DEFAULT_COUNTRY = 'MWI';

    /** @return array{series_code:string,indicator_code:?string,country:string,source:string,address:?string,units:?string,estimates_start_after:?int,fetched_at:string,rows:list<array{period:string,year:int,value:float,value_type:string}>,note:?string} */
    public function parse(string $filePath, object $series, ?string $country = null, ?int $yearFrom = null, ?int $yearTo = null): array
    {
        $iso = strtoupper(trim((string) ($country ?? ''))) ?: self::DEFAULT_COUNTRY;
        $codes = is_string($series->external_codes ?? null) ? (json_decode($series->external_codes, true) ?: []) : (array) ($series->external_codes ?? []);
        $indicator = $codes['imf_weo'] ?? null;
        $empty = fn (string $note) => ['series_code' => $series->statistic_code, 'indicator_code' => $indicator, 'country' => $iso, 'source' => 'IMF World Economic Outlook', 'address' => basename($filePath), 'units' => null, 'estimates_start_after' => null, 'fetched_at' => now()->toDateTimeString(), 'rows' => [], 'note' => $note];
        if (! $indicator) {
            return $empty('No IMF WEO indicator code on this series.');
        }
        if (! is_file($filePath)) {
            return $empty("WEO file not found: {$filePath}");
        }
        $raw = file_get_contents($filePath);
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = @iconv('UTF-16', 'UTF-8', $raw) ?: $raw;
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        if (! $lines || count($lines) < 2) {
            throw new RuntimeException('The WEO file is empty.');
        }
        $headers = array_map('trim', explode("\t", array_shift($lines)));
        $idxIso = $this->headerIndex($headers, 'ISO');
        $idxSubject = $this->headerIndex($headers, 'WEO Subject Code');
        $idxUnits = $this->headerIndex($headers, 'Units', false);
        $idxEst = $this->headerIndex($headers, 'Estimates Start After', false);
        $yearCols = [];
        foreach ($headers as $i => $h) {
            if (preg_match('/^(19|20|21)\d{2}$/', $h)) {
                $yearCols[(int) $h] = $i;
            }
        }
        if ($yearCols === []) {
            throw new RuntimeException('The WEO file has no year columns in its header row.');
        }
        ksort($yearCols);
        $row = null;
        foreach ($lines as $line) {
            if ($line === '' || $line[0] === "\t") {
                continue;
            }
            $cells = explode("\t", $line);
            if (strtoupper(trim($cells[$idxIso] ?? '')) === $iso && strtoupper(trim($cells[$idxSubject] ?? '')) === strtoupper($indicator)) {
                $row = $cells;
                break;
            }
        }
        if ($row === null) {
            return $empty("The WEO file has no row for {$iso} x {$indicator}.");
        }
        $estRaw = $idxEst !== null ? trim($row[$idxEst] ?? '') : '';
        $est = is_numeric($estRaw) ? (int) $estRaw : null;
        $rows = [];
        foreach ($yearCols as $year => $col) {
            if (($yearFrom && $year < $yearFrom) || ($yearTo && $year > $yearTo)) {
                continue;
            }
            $v = str_replace([',', ' '], '', trim($row[$col] ?? ''));
            if ($v === '' || ! is_numeric($v)) {
                continue;
            }
            $rows[] = ['period' => sprintf('%d-12-31', $year), 'year' => $year, 'value' => round((float) $v, 6), 'value_type' => ($est !== null && $year > $est) ? 'forecast' : 'actual'];
        }
        if ($rows === []) {
            return $empty("The WEO row for {$iso} x {$indicator} has no numeric year values.");
        }

        return ['series_code' => $series->statistic_code, 'indicator_code' => $indicator, 'country' => $iso, 'source' => 'IMF World Economic Outlook', 'address' => basename($filePath), 'units' => $idxUnits !== null ? trim($row[$idxUnits] ?? '') : null, 'estimates_start_after' => $est, 'fetched_at' => now()->toDateTimeString(), 'rows' => $rows, 'note' => null];
    }

    private function headerIndex(array $headers, string $needle, bool $required = true): ?int
    {
        foreach ($headers as $i => $h) {
            if (strcasecmp(trim($h), $needle) === 0) {
                return $i;
            }
        }
        if ($required) {
            throw new RuntimeException("The WEO header has no column '{$needle}'.");
        }

        return null;
    }
}
