<?php

declare(strict_types=1);

namespace App\Services\Fli;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Structural-events register (the "devaluations library") consulted by the
 * diagnostics (Chow test at known dates), the scenario engine and the SICR
 * engine - FLI_AND_PD_METHODOLOGY.md section 14.
 *
 * Source of truth:
 *   - If a governed `structural_events` table exists (Schema::hasTable), the
 *     approved rows are read from it.
 *   - Otherwise this class serves the Malawi seed set (section 14.5) as a
 *     GOVERNED STUB. These are documented reference events (RBM/IMF sourced),
 *     not fabricated data; they let the diagnostics be break-aware on day one.
 *
 * Periods are CHAR(6) YYYYMM to match the rest of the schema.
 */
final class StructuralEventsRegister
{
    private string $connection;

    public function __construct(?string $connection = null)
    {
        $this->connection = $connection ?? config('database.default');
    }

    /**
     * Malawi seed set (section 14.5). event_date/end_date are YYYYMM.
     *
     * @return array<int,array{code:string,name:string,event_type:string,event_date:?string,end_date:?string,country:string,affected_variables:array<int,string>,direction:?string,detection:string,status:string}>
     */
    public static function seedSet(): array
    {
        return [
            ['code' => 'MWI_DEVAL_2023_11', 'name' => 'Kwacha devaluation (RBM)', 'event_type' => 'devaluation', 'event_date' => '202311', 'end_date' => null, 'country' => 'MWI', 'affected_variables' => ['usd_kwacha_rate', 'cpi_inflation', 'lending_rate'], 'direction' => 'up', 'detection' => 'manual', 'status' => 'approved'],
            ['code' => 'MWI_DEVAL_2022_05', 'name' => 'Kwacha devaluation (~25%)', 'event_type' => 'devaluation', 'event_date' => '202205', 'end_date' => null, 'country' => 'MWI', 'affected_variables' => ['usd_kwacha_rate', 'cpi_inflation'], 'direction' => 'up', 'detection' => 'manual', 'status' => 'approved'],
            ['code' => 'MWI_COVID_2020', 'name' => 'COVID-19 shock', 'event_type' => 'pandemic', 'event_date' => '202003', 'end_date' => '202106', 'country' => 'MWI', 'affected_variables' => [], 'direction' => 'regime_shift', 'detection' => 'manual', 'status' => 'approved'],
            ['code' => 'MWI_MPR_TIGHTENING', 'name' => 'RBM policy-rate tightening cycle', 'event_type' => 'policy_regime', 'event_date' => '202201', 'end_date' => '202412', 'country' => 'MWI', 'affected_variables' => ['rbm_policy_rate', 'lending_rate'], 'direction' => 'up', 'detection' => 'manual', 'status' => 'approved'],
            ['code' => 'FDH_T24_MIGRATION', 'name' => 'Core-system migration (data-integrity break)', 'event_type' => 'core_system_migration', 'event_date' => null, 'end_date' => null, 'country' => 'MWI', 'affected_variables' => [], 'direction' => 'regime_shift', 'detection' => 'manual', 'status' => 'approved'],
        ];
    }

    /**
     * All approved events (from the table if present, else the governed stub).
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(string $country = 'MWI'): array
    {
        try {
            if (Schema::connection($this->connection)->hasTable('structural_events')) {
                $rows = DB::connection($this->connection)->table('structural_events')
                    ->where('status', 'approved')->where('country', $country)->get();
                $out = [];
                foreach ($rows as $r) {
                    $arr = (array) $r;
                    if (isset($arr['affected_variables']) && is_string($arr['affected_variables'])) {
                        $decoded = json_decode($arr['affected_variables'], true);
                        $arr['affected_variables'] = is_array($decoded) ? $decoded : [];
                    }
                    $out[] = $arr;
                }

                return $out;
            }
        } catch (\Throwable $e) {
            // fall through to the governed stub
        }

        return array_values(array_filter(self::seedSet(), fn ($e) => $e['country'] === $country));
    }

    public function usingTable(): bool
    {
        try {
            return Schema::connection($this->connection)->hasTable('structural_events');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Registered events whose date falls inside [startPeriod, endPeriod]. Used
     * so a fit's saved reason can name the exact regime change it spans, e.g.
     * "spans MWI_DEVAL_2023_11".
     *
     * @return array<int,array<string,mixed>>
     */
    public function eventsInWindow(?string $startPeriod, ?string $endPeriod, string $country = 'MWI'): array
    {
        if ($startPeriod === null || $endPeriod === null) {
            return [];
        }
        $out = [];
        foreach ($this->all($country) as $e) {
            $d = $e['event_date'] ?? null;
            if ($d === null) {
                continue;
            }
            if ((string) $d >= $startPeriod && (string) $d <= $endPeriod) {
                $out[] = $e;
            }
        }

        return $out;
    }
}
