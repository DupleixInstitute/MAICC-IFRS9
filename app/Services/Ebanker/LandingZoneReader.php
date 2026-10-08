<?php

namespace App\Services\Ebanker;

use Illuminate\Support\Facades\DB;

/**
 * Reads the landing zone for the build (spec v4 section 6.3: "the zone is
 * read by the build only"). The spec's logical tables are families of
 * queries over one physical table; this class knows which query ids make
 * up each family and returns the current version of every row, de-duplicated
 * on the source key with the latest load winning.
 */
class LandingZoneReader
{
    /** Query ids that mirror each E-Banker table. */
    public const LEDGER = ['P1_01', 'M09_01', 'MF_03', 'GL_03', 'ZF_01'];
    public const LOAN_BOOK_RUNS = ['P2_08', 'M09_03', 'MF_05'];
    public const BALANCE_HISTORY = ['P2_09', 'M09_02', 'MF_04', 'ZF_03'];
    public const ACCOUNT_MASTER = ['P1_02', 'MF_01'];
    public const LOAN_MASTER = ['P1_03', 'MF_02'];
    public const RATE_SETUP = ['P1_04', 'MF_07'];
    public const STATUS_HISTORY = ['P3_13', 'MF_06'];
    public const CHARGES = ['P2_07', 'MF_08'];

    /** Loan GL codes in scope, with the product group and funding source each carries. */
    public const LOAN_GLS = [
        '1050101' => ['MAIIC Agricultural Loans', 'MAIIC'],
        '1050102' => ['MAIIC Industrial Loans', 'MAIIC'],
        '1050103' => ['MAIIC Loans (1050103)', 'MAIIC'],
        '1050201' => ['FInES Agricultural Loans', 'FInES'],
        '1050202' => ['FInES Industrial Loans', 'FInES'],
        '1050401' => ['MAIIC Term Loans', 'MAIIC'],
    ];

    /**
     * Current rows of a family, keyed by source key; where two queries carry
     * the same source row (the ledger since August re-pulls July), the row
     * from the later load wins.
     *
     * @param  list<string>  $queryIds
     * @return array<string, array{load_id:int,query_id:string,source_key:string,account:?string,row_date:?string,payload:array}>
     */
    public function family(array $queryIds, ?string $toDate = null, ?string $onDate = null): array
    {
        $q = DB::table('ebanker_raw_rows')->whereIn('query_id', $queryIds)->whereNull('superseded_at')
            ->orderBy('load_id')->orderBy('id');
        if ($toDate !== null) {
            $q->where('row_date', '<=', $toDate);
        }
        if ($onDate !== null) {
            $q->where('row_date', '=', $onDate);
        }
        $out = [];
        foreach ($q->get(['load_id', 'query_id', 'source_key', 'account', 'row_date', 'payload']) as $r) {
            $out[$r->source_key] = [
                'load_id' => (int) $r->load_id, 'query_id' => $r->query_id, 'source_key' => $r->source_key,
                'account' => $r->account, 'row_date' => $r->row_date, 'payload' => json_decode($r->payload, true) ?: [],
            ];
        }

        return $out;
    }

    /** Ledger postings dated on or before the date, grouped by account, each in date then key order. */
    public function ledgerByAccount(?string $toDate = null): array
    {
        $by = [];
        foreach ($this->family(self::LEDGER, $toDate) as $row) {
            $p = $row['payload'];
            if (($p['DELETE_FLAG'] ?? 'N') === 'Y' || $row['account'] === null || $row['account'] === '') {
                continue;
            }
            // only loan accounts: the GL_03 extract also carries the contra legs on
            // the income GLs (4215, 4216) and the FInES suspense (3065), which are
            // not loans and have no account master row
            $gl = trim((string) ($p['AC_GLCODE'] ?? ($p['CB_GLCODE'] ?? '')));
            if ($gl !== '' && ! isset(self::LOAN_GLS[$gl])) {
                continue;
            }
            $by[$row['account']][] = $row;
        }
        foreach ($by as &$rows) {
            usort($rows, fn ($a, $b) => [$a['row_date'], (int) $a['source_key']] <=> [$b['row_date'], (int) $b['source_key']]);
        }

        return $by;
    }

    /** The stored Loan Book Report: the latest run per account on the month-end, as method A defines it. */
    public function latestRunsOn(string $monthEnd): array
    {
        $latest = [];
        foreach ($this->family(self::LOAN_BOOK_RUNS, null, $monthEnd) as $row) {
            $a = $row['account'];
            if (! isset($latest[$a]) || (int) $row['source_key'] > (int) $latest[$a]['source_key']) {
                $latest[$a] = $row;
            }
        }

        return $latest;
    }

    /** @return array<string, array> account master payloads keyed by account number */
    public function accountMaster(): array
    {
        $out = [];
        foreach ($this->family(self::ACCOUNT_MASTER) as $row) {
            $out[$row['account']] = $row['payload'] + ['_load_id' => $row['load_id']];
        }

        return $out;
    }

    /** @return array<string, array> loan master payloads keyed by account number */
    public function loanMaster(): array
    {
        $out = [];
        foreach ($this->family(self::LOAN_MASTER) as $row) {
            $out[$row['account']] = $row['payload'];
        }

        return $out;
    }

    /** The rate set-up rows per account, in applicable-from order. */
    public function rateSetupByAccount(): array
    {
        $by = [];
        foreach ($this->family(self::RATE_SETUP) as $row) {
            if (($row['payload']['DELETE_FLAG'] ?? 'N') === 'Y') {
                continue;
            }
            $by[$row['account']][] = $row;
        }
        foreach ($by as &$rows) {
            usort($rows, fn ($a, $b) => [$a['row_date'], (int) $a['source_key']] <=> [$b['row_date'], (int) $b['source_key']]);
        }

        return $by;
    }

    /** Status history per account in effective-date order, deleted rows left out. */
    public function statusHistoryByAccount(): array
    {
        $by = [];
        foreach ($this->family(self::STATUS_HISTORY) as $row) {
            if (($row['payload']['DELETE_FLAG'] ?? 'N') === 'Y') {
                continue;
            }
            $by[$row['account']][] = $row;
        }
        foreach ($by as &$rows) {
            usort($rows, fn ($a, $b) => [$a['row_date'], (int) $a['source_key']] <=> [$b['row_date'], (int) $b['source_key']]);
        }

        return $by;
    }

    /** The month-ends the stored report was run for. */
    public function runMonthEnds(): array
    {
        return DB::table('ebanker_raw_rows')->whereIn('query_id', self::LOAN_BOOK_RUNS)->whereNull('superseded_at')
            ->whereNotNull('row_date')->distinct()->orderBy('row_date')->pluck('row_date')->all();
    }

    /** The last date any ledger posting carries: the date after which a build is refused, never estimated. */
    public function lastLedgerDate(): ?string
    {
        return DB::table('ebanker_raw_rows')->whereIn('query_id', self::LEDGER)->whereNull('superseded_at')->max('row_date');
    }

    /** Loads read by a family, for the build's audit trail. */
    public function loadsOf(array $queryIds): array
    {
        return DB::table('ebanker_raw_rows')->join('ebanker_loads', 'ebanker_loads.id', '=', 'ebanker_raw_rows.load_id')
            ->whereIn('query_id', $queryIds)->whereNull('superseded_at')->distinct()
            ->get(['ebanker_loads.id', 'ebanker_loads.pack_hash', 'ebanker_loads.pack_name'])
            ->map(fn ($l) => ['load_id' => (int) $l->id, 'pack_hash' => $l->pack_hash, 'pack' => $l->pack_name])->values()->all();
    }
}
