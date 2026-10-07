<?php

declare(strict_types=1);

namespace App\Services\Fli;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Derives the credit-loss proxy Y-series (into credit_loss_series) from REAL
 * data - never fabricated. Two sources, in order:
 *
 *   1. The app's own multi-period loan book (ecl_results / facility_snapshots):
 *      an NPL ratio = Stage-3 exposure / gross exposure, one point per period.
 *      Requires >= 2 loaded periods; with a single period it is not derivable
 *      and that fact is RECORDED (no interpolation, no invention).
 *
 *   2. The read-only golden transition matrices (fdh_real.pd_transition_matrices):
 *      the Stage-1 -> default 12-month PD (stage_transition '1toPD3',
 *      value_annual_average) per business unit per period - a genuine segment
 *      average-PD series (proxy_code AvgPD-<BU>), clearly sourced.
 *
 * When neither yields multiple periods, the deriver returns absent=true so the
 * caller reports Y-data is missing (the expected state while credit_loss_data
 * is empty) rather than manufacturing a series.
 *
 * Grounded in FLI_AND_PD_METHODOLOGY.md sections 12 + 15.2/15.3.
 */
final class CreditLossProxyDeriver
{
    private string $connection;

    private string $goldenConnection;

    public function __construct(?string $connection = null, string $goldenConnection = 'golden')
    {
        $this->connection = $connection ?? config('database.default');
        $this->goldenConnection = $goldenConnection;
    }

    private function db()
    {
        return DB::connection($this->connection);
    }

    /**
     * @return array{loaded:int,absent:bool,sources:array<int,array{source:string,proxies:int,observations:int,periods:array<int,string>,note:string}>}
     */
    public function derive(bool $fromGolden = true): array
    {
        $sources = [];
        $totalLoaded = 0;

        $npl = $this->deriveLoanBookNpl();
        $sources[] = $npl['report'];
        $totalLoaded += $npl['loaded'];

        if ($fromGolden) {
            $seg = $this->deriveGoldenSegmentPd();
            $sources[] = $seg['report'];
            $totalLoaded += $seg['loaded'];
        }

        return [
            'loaded' => $totalLoaded,
            'absent' => $totalLoaded === 0,
            'sources' => $sources,
        ];
    }

    /**
     * @return array{loaded:int,report:array{source:string,proxies:int,observations:int,periods:array<int,string>,note:string}}
     */
    private function deriveLoanBookNpl(): array
    {
        $periods = [];
        try {
            $periods = $this->db()->table('ecl_results')
                ->distinct()->orderBy('reporting_period')->pluck('reporting_period')->all();
        } catch (\Throwable $e) {
            return $this->emptyReport('app.ecl_results (NPL ratio)', 'ecl_results unavailable: ' . $e->getMessage());
        }

        if (count($periods) < 2) {
            $have = count($periods) === 1 ? ('single period ' . $periods[0]) : 'no periods';
            return $this->emptyReport(
                'app.ecl_results (NPL ratio)',
                "multi-period NPL series not derivable ({$have}); a proxy needs >= 2 loaded periods - recorded as absent, not invented."
            );
        }

        $now = now();
        $rows = [];
        $obsPeriods = [];
        foreach ($periods as $p) {
            $gross = (float) $this->db()->table('ecl_results')->where('reporting_period', $p)->sum('ead');
            $npl = (float) $this->db()->table('ecl_results')->where('reporting_period', $p)->where('stage_final', 3)->sum('ead');
            if ($gross <= 0.0) {
                continue;
            }
            $rows[] = [
                'proxy_code' => 'NPL-ratio',
                'proxy_name' => 'NPL ratio (Stage-3 EAD / gross EAD), derived from ecl_results',
                'observation_period' => str_pad((string) $p, 6, '0', STR_PAD_LEFT),
                'value' => $npl / $gross,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $obsPeriods[] = str_pad((string) $p, 6, '0', STR_PAD_LEFT);
        }

        $loaded = $this->upsertProxies($rows);

        return [
            'loaded' => $loaded,
            'report' => [
                'source' => 'app.ecl_results (NPL ratio)',
                'proxies' => $loaded > 0 ? 1 : 0,
                'observations' => $loaded,
                'periods' => $obsPeriods,
                'note' => "NPL ratio derived for {$loaded} period(s) from the app loan book.",
            ],
        ];
    }

    /**
     * @return array{loaded:int,report:array{source:string,proxies:int,observations:int,periods:array<int,string>,note:string}}
     */
    private function deriveGoldenSegmentPd(): array
    {
        try {
            $golden = DB::connection($this->goldenConnection);
            if (! Schema::connection($this->goldenConnection)->hasTable('pd_transition_matrices')) {
                return $this->emptyReport('golden.pd_transition_matrices (AvgPD-<BU>)', 'table not present on golden connection');
            }
        } catch (\Throwable $e) {
            return $this->emptyReport('golden.pd_transition_matrices (AvgPD-<BU>)', 'golden connection unavailable: ' . $e->getMessage());
        }

        // Stage-1 -> default 12m PD per BU per period (the segment average PD proxy).
        $src = $golden->table('pd_transition_matrices')
            ->where('stage_transition', '1toPD3')
            ->orderBy('reporting_period')
            ->get(['reporting_period', 'business_unit', 'value_annual_average']);

        if ($src->isEmpty()) {
            return $this->emptyReport('golden.pd_transition_matrices (AvgPD-<BU>)', 'no 1toPD3 transition rows found');
        }

        $now = now();
        $rows = [];
        $proxySet = [];
        $periodSet = [];
        foreach ($src as $r) {
            $bu = strtoupper(trim((string) $r->business_unit));
            if ($bu === '') {
                continue;
            }
            $proxyCode = 'AvgPD-' . $bu;
            $period = str_pad((string) $r->reporting_period, 6, '0', STR_PAD_LEFT);
            $rows[$proxyCode . '|' . $period] = [
                'proxy_code' => $proxyCode,
                'proxy_name' => 'Stage1->default 12m PD (annual avg), ' . $bu . ' [src: fdh_real.pd_transition_matrices 1toPD3]',
                'observation_period' => $period,
                'value' => (float) $r->value_annual_average,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $proxySet[$proxyCode] = true;
            $periodSet[$period] = true;
        }

        $loaded = $this->upsertProxies(array_values($rows));
        $periods = array_keys($periodSet);
        sort($periods);

        return [
            'loaded' => $loaded,
            'report' => [
                'source' => 'golden.pd_transition_matrices (AvgPD-<BU>)',
                'proxies' => count($proxySet),
                'observations' => $loaded,
                'periods' => $periods,
                'note' => 'Segment average PD (Stage1->default 12m) derived from real transition matrices for '
                    . count($proxySet) . ' business unit(s) across ' . count($periods) . ' period(s).',
            ],
        ];
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function upsertProxies(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            $this->db()->table('credit_loss_series')->upsert(
                $chunk,
                ['proxy_code', 'observation_period'],
                ['proxy_name', 'value', 'updated_at']
            );
        }

        return count($rows);
    }

    /** @return array{loaded:int,report:array{source:string,proxies:int,observations:int,periods:array<int,string>,note:string}} */
    private function emptyReport(string $source, string $note): array
    {
        return [
            'loaded' => 0,
            'report' => [
                'source' => $source, 'proxies' => 0, 'observations' => 0, 'periods' => [], 'note' => $note,
            ],
        ];
    }
}
