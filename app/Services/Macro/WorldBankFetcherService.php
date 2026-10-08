<?php

namespace App\Services\Macro;

use Illuminate\Support\Facades\Http;

/**
 * Pulls an annual macro series for a country from the open World Bank Data
 * API and returns a normalised preview; writes nothing (spec v4 section
 * 13.3, ported from the Dupleix suite).
 *
 *   Endpoint: https://api.worldbank.org/v2/country/{ISO3}/indicator/{CODE}
 *   Auth:     none; JSON via ?format=json; 60-second timeout, two retries
 *
 * A World Bank value is an actual (the current vintage, revised but never a
 * projection); years the bank has not published come back null and are
 * dropped. A series with no code, or one the source cannot supply, returns
 * an empty, well-formed preview with its note, never an exception, so an
 * import of many series is non-fatal per series.
 */
class WorldBankFetcherService
{
    public const BASE = 'https://api.worldbank.org/v2/country';
    private const TIMEOUT = 60;
    private const RETRIES = 2;

    public function country(?string $override = null): string
    {
        $c = trim((string) ($override ?? ''));

        return $c !== '' ? strtoupper($c) : (string) config('services.worldbank.country', 'MWI');
    }

    /**
     * @param  object  $series  a macro_statistics row (statistic_code, external_codes)
     * @return array{series_code:string,indicator_code:?string,country:string,source:string,address:?string,fetched_at:string,rows:list<array{period:string,year:int,value:float,value_type:string}>,note:?string}
     */
    public function fetch(object $series, ?string $country = null, ?int $yearFrom = null, ?int $yearTo = null): array
    {
        $iso = $this->country($country);
        $from = $yearFrom !== null && $yearFrom > 0 ? $yearFrom : 1960;
        $to = $yearTo !== null && $yearTo > 0 ? $yearTo : (int) now()->year;
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        $codes = is_string($series->external_codes ?? null) ? (json_decode($series->external_codes, true) ?: []) : (array) ($series->external_codes ?? []);
        $indicator = $codes['world_bank'] ?? null;
        if (! $indicator) {
            return $this->empty($series, null, $iso, null, 'No World Bank indicator code on this series; add one under the series definition.');
        }
        $url = sprintf('%s/%s/indicator/%s?format=json&per_page=2000&date=%d:%d', self::BASE, $iso, $indicator, $from, $to);
        $response = null;
        $lastError = null;
        for ($attempt = 1; $attempt <= self::RETRIES + 1; $attempt++) {
            try {
                $response = Http::timeout(self::TIMEOUT)->acceptJson()->get($url);
                if ($response->ok()) {
                    break;
                }
                $lastError = "HTTP {$response->status()}";
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $response = null;
            }
        }
        if (! $response || ! $response->ok()) {
            return $this->empty($series, $indicator, $iso, $url, 'Could not reach the World Bank API after ' . (self::RETRIES + 1) . " attempts ({$lastError}); the table is left as it was.");
        }
        $payload = $response->json();
        if (! is_array($payload) || count($payload) < 2 || ! is_array($payload[1] ?? null)) {
            return $this->empty($series, $indicator, $iso, $url, "The World Bank has no data for {$indicator} ({$iso}).");
        }
        $rows = [];
        foreach ($payload[1] as $row) {
            if (! isset($row['date'], $row['value']) || $row['value'] === null) {
                continue;
            }
            $year = (int) $row['date'];
            $rows[] = ['period' => sprintf('%d-12-31', $year), 'year' => $year, 'value' => round((float) $row['value'], 6), 'value_type' => 'actual'];
        }
        usort($rows, fn ($a, $b) => $a['year'] <=> $b['year']);

        return ['series_code' => $series->statistic_code, 'indicator_code' => $indicator, 'country' => $iso, 'source' => 'World Bank Open Data', 'address' => $url, 'fetched_at' => now()->toDateTimeString(), 'rows' => $rows, 'note' => $rows === [] ? 'No published values in the range.' : null];
    }

    private function empty(object $series, ?string $indicator, string $iso, ?string $url, string $note): array
    {
        return ['series_code' => $series->statistic_code, 'indicator_code' => $indicator, 'country' => $iso, 'source' => 'World Bank Open Data', 'address' => $url, 'fetched_at' => now()->toDateTimeString(), 'rows' => [], 'note' => $note];
    }
}
