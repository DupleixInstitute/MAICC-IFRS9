<?php

namespace App\Services\Macro;

use App\Models\MacroVariable;
use App\Support\MacroPeriod;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pulls annual macro-indicator data for a given country from the open
 * World Bank Data API. Returns a normalised preview ready to display before
 * the user commits the import.
 *
 *   Endpoint: https://api.worldbank.org/v2/country/{ISO3}/indicator/{CODE}
 *   Auth:     none (public, JSON via ?format=json)
 *   Quota:    very generous; no API key required for typical use
 *
 * The fetcher does NOT write to macro_observations on its own — `MacroController`
 * exposes a preview endpoint that calls `fetch()`, and a separate commit
 * endpoint that writes after the user clicks "Import".
 */
class WorldBankFetcherService
{
    private const BASE = 'https://api.worldbank.org/v2/country';
    private const TIMEOUT = 60;              // worldbank.org is occasionally slow; allow up to 60s
    private const RETRIES = 2;               // retry transient timeouts twice before failing

    /**
     * ISO3 country whose indicators are fetched. A caller-supplied override
     * (the UI country selector) wins; otherwise the configured default
     * (services.worldbank.country, default ZMB).
     */
    private function country(?string $override = null): string
    {
        $c = trim((string) ($override ?? ''));

        return $c !== '' ? strtoupper($c) : (string) config('services.worldbank.country', 'ZMB');
    }

    /**
     * @return array{
     *   variable_id: int,
     *   variable_code: string,
     *   indicator_code: string,
     *   country: string,
     *   source: string,
     *   fetched_at: string,
     *   rows: list<array{period_date: string, period_label: string, period_type: string, value: float, value_type: string}>,
     * }
     */
    public function fetch(MacroVariable $variable, ?string $country = null, ?int $yearFrom = null, ?int $yearTo = null): array
    {
        $iso = $this->country($country);
        // Default the span to the historical 1960..current-year window so an
        // omitted range reproduces the previous behaviour exactly (golden-safe).
        $from = $yearFrom !== null && $yearFrom > 0 ? $yearFrom : 1960;
        $to   = $yearTo   !== null && $yearTo   > 0 ? $yearTo   : (int) now()->year;
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $indicator = $variable->external_codes['world_bank'] ?? null;
        if (! $indicator) {
            // No mapping configured — return an empty, well-formed preview so
            // the UI can show "No data available for this indicator" rather
            // than an error toast. The user can add the code via Edit Variable.
            return $this->emptyPayload($variable, null, 'No World Bank indicator code configured for this variable. Add one under Edit → World Bank code.', $iso);
        }

        $url = sprintf('%s/%s/indicator/%s?format=json&per_page=2000&date=%d:%d',
            self::BASE, $iso, $indicator, $from, $to);

        $response = null;
        $lastError = null;
        for ($attempt = 1; $attempt <= self::RETRIES + 1; $attempt++) {
            try {
                $response = Http::timeout(self::TIMEOUT)->acceptJson()->get($url);
                if ($response->ok()) break;
                $lastError = "HTTP {$response->status()}";
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $response  = null;
            }
        }

        if (! $response || ! $response->ok()) {
            return $this->emptyPayload($variable, $indicator,
                "Could not reach World Bank API after " . (self::RETRIES + 1) . " attempts ({$lastError}). The series may not exist for {$iso}; try again later or check the indicator code at api.worldbank.org.", $iso);
        }

        $payload = $response->json();
        if (! is_array($payload) || count($payload) < 2 || ! is_array($payload[1] ?? null)) {
            return $this->emptyPayload($variable, $indicator, "World Bank API has no data for indicator {$indicator} ({$iso}).", $iso);
        }

        // World Bank publishes "current vintage" historicals only — no forecast
        // data. Anything we get back is `actual` (subject to later revision but
        // not a projection). Years where the bank hasn't published yet come
        // back with value=null and we drop them.
        $rows = collect($payload[1])
            ->filter(fn ($row) => isset($row['date'], $row['value']) && $row['value'] !== null)
            ->map(function ($row): array {
                $year = (int) $row['date'];
                return [
                    'period_date'  => sprintf('%d-12-31', $year),
                    'period_label' => MacroPeriod::deriveLabel(sprintf('%d-12-31', $year), 'annual'),
                    'period_type'  => 'annual',
                    'value'        => round((float) $row['value'], 6),
                    'value_type'   => 'actual',
                ];
            })
            ->sortBy('period_date')
            ->values()
            ->all();

        return [
            'variable_id'    => $variable->id,
            'variable_code'  => $variable->code,
            'indicator_code' => $indicator,
            'country'        => $iso,
            'source'         => 'World Bank Open Data',
            'fetched_at'     => now()->toDateTimeString(),
            'rows'           => $rows,
        ];
    }

    private function indicatorCode(MacroVariable $variable): string
    {
        $code = $variable->external_codes['world_bank'] ?? null;
        if (! $code) {
            throw new RuntimeException(
                "Variable {$variable->code} has no World Bank indicator code. "
                . "Add one to external_codes (e.g. {\"world_bank\": \"NY.GDP.MKTP.KD.ZG\"})."
            );
        }
        return $code;
    }

    /**
     * A well-formed "no data" preview the UI can render without erroring.
     * @return array<string,mixed>
     */
    private function emptyPayload(MacroVariable $variable, ?string $indicator, string $message, ?string $country = null): array
    {
        return [
            'variable_id'    => $variable->id,
            'variable_code'  => $variable->code,
            'indicator_code' => $indicator,
            'country'        => $this->country($country),
            'source'         => 'World Bank Open Data',
            'fetched_at'     => now()->toDateTimeString(),
            'rows'           => [],
            'message'        => $message,
        ];
    }
}
