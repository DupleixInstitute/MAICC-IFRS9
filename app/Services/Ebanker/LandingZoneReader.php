<?php

namespace App\Services\Ebanker;

use Illuminate\Support\Facades\DB;

/**
 * Reads the landing zone for the build (spec v4 section 6.3: "the zone is
 * read by the build only"). The spec's logical tables are families of
 * queries over one physical table; this class knows which query ids make
 * up each family and returns the current version of every row, de-duplicated
 * on the source key with the latest load winning.
 *
 * Only rows of loads whose status is LANDED are read. A quarantined load
 * keeps its rows against itself so the refused file can be opened (spec 6.4;
 * system audit of 9 October 2026, finding M2), and they must never reach a
 * build.
 *
 * Two ways to read the zone (release review of 16 October 2026):
 *
 *  - for the book (new LandingZoneReader(), the container's instance): the
 *    rows of an account an approved exclusion takes out of the book (the
 *    account exclusion register, AccountExclusionService) are left out of
 *    every family, so the loan book, the contract master, the fees, the cash,
 *    the drawdowns, the charts and the take-on population never see it;
 *  - as received (LandingZoneReader::asReceived(), or includingExcluded() on
 *    a reader in hand): every row of the extracts, excluded accounts and all.
 *    Every control reads this way: the pack gates, the take-on gates, the
 *    trial balance landing, the GL fee gate, the baselines and the golden ties
 *    of BaselineService, and the GL names of the reconciliation. A control
 *    checks what E-Banker sent against the GL; an excluded account is still
 *    in E-Banker and in the GL (Global Seeds 104420000044 and Angwenda
 *    104460000029 are 58,413,511.84 of the old ledger at 31 Dec 2023), so a
 *    control that read the book's view would move by it.
 *
 * The rows stay in the zone as received whichever way it is read.
 */
class LandingZoneReader
{
    /** Whether the rows of excluded accounts are returned too (the pack gates; the register's own look-up). */
    private bool $withExcluded = false;

    /** A reader of the zone as received, for a control: no account is left out. */
    public static function asReceived(): static
    {
        return (new static())->includingExcluded();
    }

    /** Whether this reader leaves the excluded accounts out (the book's view) or reads the zone as received. */
    public function readsAsReceived(): bool
    {
        return $this->withExcluded;
    }

    /** The same reader with the rows of excluded accounts left in: the zone exactly as received. */
    public function includingExcluded(): static
    {
        $reader = clone $this;
        $reader->withExcluded = true;

        return $reader;
    }

    /** The approved exclusions this reader leaves out (none when it reads the zone as received). */
    private function excluded(): array
    {
        return $this->withExcluded ? [] : \App\Support\AccountExclusions::approved();
    }

    /** Whether a row's account is one this reader leaves out. */
    private function leavesOut(?string $account, array $excluded): bool
    {
        return $excluded !== [] && $account !== null && $account !== '' && isset($excluded[ltrim($account, '0')]);
    }

    /** Query ids that mirror each E-Banker table. */
    public const LEDGER = ['P1_01', 'M09_01', 'MF_03', 'GL_03', 'ZF_01'];
    public const LOAN_BOOK_RUNS = ['P2_08', 'M09_03', 'MF_05'];
    public const BALANCE_HISTORY = ['P2_09', 'M09_02', 'MF_04', 'ZF_03'];
    public const ACCOUNT_MASTER = ['P1_02', 'MF_01'];
    public const LOAN_MASTER = ['P1_03', 'MF_02'];
    public const RATE_SETUP = ['P1_04', 'MF_07'];
    public const STATUS_HISTORY = ['P3_13', 'MF_06'];
    public const CHARGES = ['P2_07', 'MF_08'];
    /** The keyed GL master rows (GL_02) and the trial balance lines from Finance (TB_01): the GL side of the zone. */
    public const GL_MASTER = ['GL_02'];
    public const TRIAL_BALANCE = ['TB_01'];

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
    public function family(array $queryIds, ?string $toDate = null, ?string $onDate = null, ?string $account = null): array
    {
        $q = $this->current()->whereIn('query_id', $queryIds)
            ->orderBy('load_id')->orderBy('id');
        if ($account !== null) {
            $q->where('account', $account);
        }
        if ($toDate !== null) {
            $q->where('row_date', '<=', $toDate);
        }
        if ($onDate !== null) {
            $q->where('row_date', '=', $onDate);
        }
        $out = [];
        $excluded = $this->excluded();
        foreach ($q->get(['load_id', 'query_id', 'source_key', 'account', 'row_date', 'payload']) as $r) {
            if ($this->leavesOut($r->account, $excluded)) {
                continue;
            }
            $out[$r->source_key] = [
                'load_id' => (int) $r->load_id, 'query_id' => $r->query_id, 'source_key' => $r->source_key,
                'account' => $r->account, 'row_date' => $r->row_date, 'payload' => json_decode($r->payload, true) ?: [],
            ];
        }

        return $out;
    }

    /** The current rows of landed loads: the one query every reader of the zone starts from. */
    public function current(): \Illuminate\Database\Query\Builder
    {
        return DB::table('ebanker_raw_rows')->whereNull('superseded_at')->whereIn('load_id', self::landedLoadIds());
    }

    /** A sub-query of the loads whose rows may be read, for callers that query the raw rows themselves. */
    public static function landedLoadIds(): \Illuminate\Database\Query\Builder
    {
        return DB::table('ebanker_loads')->select('id')->where('status', 'LANDED');
    }

    /**
     * Ledger postings dated on or before the date, grouped by account, each in date then key order;
     * one account's only when it is named (the EIR asks for one loan at a time). Only postings on
     * the in-scope loan GLs are kept, so the Mega Farm ledger (MF_03, on the programme's GLs 1050301
     * to 1050307) is left out unless asked for: the loan book's method B asks, because the Mega Farm
     * accounts are in E-Banker's Loan Book Report (MF_05) and their carrying amount is derived from
     * their own postings; the EIR and the other readers do not (the programme is outside the EIR,
     * decision D30).
     */
    public function ledgerByAccount(?string $toDate = null, ?string $account = null, bool $withMegaFarm = false): array
    {
        return $this->loanPostings($this->family(self::ledgerFamilies($withMegaFarm), $toDate, null, $account), $withMegaFarm);
    }

    /**
     * The ledger read once, for a caller that asks for one account at a time at many dates (the
     * restructure detector asks at every regeneration of every chart: some 1,500 reads, about
     * 30 minutes of bootstrap step 5i). The returned function gives, for an account and a date,
     * exactly what ledgerByAccount(date, account)[account] gives: the account's rows of the
     * current landed loads in load order, those dated after the date (and the undated, when a
     * date is given) left out as the query leaves them out, de-duplicated on the source key with
     * the latest load winning as family() does, then the same posting filter and order.
     *
     * @return \Closure(string, ?string): list<array>
     */
    public function ledgerReader(bool $withMegaFarm = false): \Closure
    {
        $byAccount = [];
        $excluded = $this->excluded();
        foreach ($this->current()->whereIn('query_id', self::ledgerFamilies($withMegaFarm))->whereNotNull('account')->orderBy('load_id')->orderBy('id')
            ->get(['load_id', 'query_id', 'source_key', 'account', 'row_date', 'payload']) as $r) {
            if ($this->leavesOut($r->account, $excluded)) {
                continue;
            }
            $byAccount[$r->account][] = [
                'load_id' => (int) $r->load_id, 'query_id' => $r->query_id, 'source_key' => $r->source_key,
                'account' => $r->account, 'row_date' => $r->row_date, 'payload' => json_decode($r->payload, true) ?: [],
            ];
        }

        return function (string $account, ?string $toDate) use ($byAccount, $withMegaFarm): array {
            $keyed = [];
            foreach ($byAccount[$account] ?? [] as $row) {
                if ($toDate !== null && ($row['row_date'] === null || $row['row_date'] > $toDate)) {
                    continue;
                }
                $keyed[$row['source_key']] = $row;
            }

            return $this->loanPostings($keyed, $withMegaFarm)[$account] ?? [];
        };
    }

    /** The ledger families read: MF_03 only when Mega Farm is asked for. */
    private static function ledgerFamilies(bool $withMegaFarm): array
    {
        // every MF_03 row is on a programme GL (1050301 to 1050307), so without Mega Farm the family is
        // left unread rather than decoded and dropped: some 300,000 rows that stopped whole-ledger screens
        return $withMegaFarm ? self::LEDGER : array_values(array_diff(self::LEDGER, ['MF_03']));
    }

    /** The postings on the loan GLs, not deleted, grouped by account, each in date then key order. */
    private function loanPostings(array $read, bool $withMegaFarm): array
    {
        $gls = self::LOAN_GLS + ($withMegaFarm ? array_flip(\App\Services\MegaFarm\MegaFarmEclService::GLS) : []);
        $by = [];
        foreach ($read as $row) {
            $p = $row['payload'];
            if (($p['DELETE_FLAG'] ?? 'N') === 'Y' || $row['account'] === null || $row['account'] === '') {
                continue;
            }
            // only loan accounts: the GL_03 extract also carries the contra legs on
            // the income GLs (4215, 4216) and the FInES suspense (3065), which are
            // not loans and have no account master row
            $gl = trim((string) ($p['AC_GLCODE'] ?? ($p['CB_GLCODE'] ?? '')));
            if ($gl !== '' && ! isset($gls[$gl])) {
                continue;
            }
            $by[$row['account']][] = $row;
        }
        foreach ($by as &$rows) {
            usort($rows, fn ($a, $b) => [$a['row_date'], (int) $a['source_key']] <=> [$b['row_date'], (int) $b['source_key']]);
        }

        return $by;
    }

    /**
     * The stored Loan Book Report for the month-end, one row per account.
     *
     * E-Banker's own ASONDATE on this report is honest: it is frozen to the
     * date asked for. Its arrears-ageing columns are not — they are
     * recomputed live every time the report is re-run, so a later pull of
     * the same closed month reads as more overdue than an earlier one did
     * (Loan Book Report Point-in-Time Specification v1, sections 2 to 4).
     * Where more than one pull is on record for an account at this
     * month-end, the earliest pull (by its own TRANSACTION_DATE, the date
     * the report was actually run, not the ASONDATE it was asked about) is
     * the one closest to the truth and is preferred (section 6) — but only
     * once the fields that should never move for a closed month (the
     * carrying amount, the total overdue amount, the overdue-principal
     * date) actually agree across every pull on record, and only among the
     * pulls whose own arrears buckets are not internally nonsensical (every
     * bucket nil although that same pull's own total overdue amount is not
     * — a second, distinct E-Banker defect the 16 October 2026 December
     * 2025 workbook validation surfaced, section 6.1). When the pulls
     * genuinely disagree, or none of them has buckets worth trusting, the
     * build does not guess: it keeps the pre-fix choice (the latest pull)
     * and flags it on the row for a person to look at, via the
     * 'pick_conflict' key callers fold into their own build flag.
     */
    public function latestRunsOn(string $monthEnd): array
    {
        $byAccount = [];
        foreach ($this->family(self::LOAN_BOOK_RUNS, null, $monthEnd) as $row) {
            $byAccount[$row['account']][] = $row;
        }

        return array_map($this->pickRun(...), $byAccount);
    }

    /** Among one account's candidate pulls for a month-end, the earliest whose buckets are worth trusting, once the stable fields agree (section 6.1). */
    private function pickRun(array $rows): array
    {
        if (count($rows) === 1) {
            return $rows[0];
        }
        $dated = array_filter($rows, fn ($row) => $this->pullDate($row) !== null);
        if ($dated === []) {
            // no TRANSACTION_DATE to tell the pulls apart by: the pre-fix rule stands
            return $this->highestSourceKey($rows);
        }
        usort($dated, fn ($a, $b) => [$this->pullDate($a), (int) $a['source_key']] <=> [$this->pullDate($b), (int) $b['source_key']]);
        $earliestAny = reset($dated);
        foreach ($rows as $row) {
            if (! self::stableFieldsAgree($earliestAny['payload'], $row['payload'])) {
                return $this->flaggedConflict($rows, sprintf(
                    '%d pulls on record for this month-end disagree on the carrying amount, the total overdue amount or the overdue-principal date; the latest pull is kept pending review (Loan Book Report Point-in-Time Specification v1, section 6.1)',
                    count($rows)
                ));
            }
        }
        $reliable = array_values(array_filter($dated, fn ($row) => ! self::bucketsLookBroken($row['payload'])));
        if ($reliable === []) {
            return $this->flaggedConflict($rows, sprintf(
                'every one of the %d pulls on record for this month-end reports its arrears buckets as nil although its own total overdue amount is not; the latest pull is kept pending review (Loan Book Report Point-in-Time Specification v1, section 6.1)',
                count($rows)
            ));
        }

        return reset($reliable);
    }

    /** The pre-fix choice (the latest pull), flagged rather than silently trusted (section 6.1). */
    private function flaggedConflict(array $rows, string $note): array
    {
        $conflicted = $this->highestSourceKey($rows);
        $conflicted['pick_conflict'] = $note;

        return $conflicted;
    }

    /**
     * Whether a pull's own arrears buckets are nil although that same pull's own total overdue
     * amount is not: an internal contradiction within one pull, not a disagreement between pulls,
     * so stableFieldsAgree (which never looks at the buckets) cannot catch it. Found on the Loan
     * Book Report's re-runs for several Mega Farm accounts, scattered through 2026 with no
     * time-based pattern, on the December 2025 workbook validation of 16 October 2026.
     */
    private static function bucketsLookBroken(array $payload): bool
    {
        $buckets = self::payloadNum($payload, 'DAY_1_30') + self::payloadNum($payload, 'DAY_31_91') + self::payloadNum($payload, 'DAY_91_180')
            + self::payloadNum($payload, 'DAY_181_270') + self::payloadNum($payload, 'DAY_271_360');

        return $buckets < 0.01 && self::payloadNum($payload, 'ARREAS_TOTAL') > 0.01;
    }

    /** The date a Loan Book Report pull was actually run (TRANSACTION_DATE), not the ASONDATE it was asked about. */
    private function pullDate(array $row): ?string
    {
        return \App\Services\Ebanker\InstalmentChartHistory::date((string) ($row['payload']['TRANSACTION_DATE'] ?? ''));
    }

    /**
     * The fields a closed month's pulls should never disagree on, whatever the run date (section
     * 6.1). Public and static so the landing side (PackLandingService's drift notice, section 6.3)
     * reads the same rule as the build side, rather than a second copy of it drifting apart.
     */
    public static function stableFieldsAgree(array $a, array $b): bool
    {
        return abs(self::payloadNum($a, 'CARRYING_AMOUNT') - self::payloadNum($b, 'CARRYING_AMOUNT')) < 0.01
            && abs(self::payloadNum($a, 'ARREAS_TOTAL') - self::payloadNum($b, 'ARREAS_TOTAL')) < 0.01
            && \App\Services\Ebanker\InstalmentChartHistory::date((string) ($a['OVERDUE_PRINCI_DATE'] ?? ''))
                === \App\Services\Ebanker\InstalmentChartHistory::date((string) ($b['OVERDUE_PRINCI_DATE'] ?? ''));
    }

    private static function payloadNum(array $payload, string $key): float
    {
        $s = str_replace([',', ' ', "\xC2\xA0"], '', trim((string) ($payload[$key] ?? '')));

        return is_numeric($s) ? (float) $s : 0.0;
    }

    /** Today's rule, kept as the fallback when the earliest pull cannot be told from the others (no readable TRANSACTION_DATE) or they disagree on the stable fields. */
    private function highestSourceKey(array $rows): array
    {
        $latest = $rows[0];
        foreach ($rows as $row) {
            if ((int) $row['source_key'] > (int) $latest['source_key']) {
                $latest = $row;
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
        return $this->current()->whereIn('query_id', self::LOAN_BOOK_RUNS)
            ->whereNotNull('row_date')->distinct()->orderBy('row_date')->pluck('row_date')->all();
    }

    /** The last date any ledger posting carries: the date after which a build is refused, never estimated. */
    public function lastLedgerDate(): ?string
    {
        return self::onCurrentIndex()->whereNull('superseded_at')->whereIn('load_id', self::landedLoadIds())
            ->whereIn('query_id', self::LEDGER)->max('row_date');
    }

    /**
     * The raw rows read through the covering index (query_id, superseded_at, load_id, row_date)
     * for a count, a last date or a list of keys, which never need the payload. MySQL's planner
     * otherwise walks the load index and reads every row, payload and all: after packs 3 and 4
     * (620,000 rows, 742 MB) that took 90 seconds. SQLite (the tests) has no index hint.
     */
    public static function onCurrentIndex(): \Illuminate\Database\Query\Builder
    {
        return DB::connection()->getDriverName() === 'mysql'
            ? DB::table(DB::raw('ebanker_raw_rows FORCE INDEX (ebanker_raw_rows_current_idx)'))
            : DB::table('ebanker_raw_rows');
    }

    /** Loads read by a family, for the build's audit trail. */
    public function loadsOf(array $queryIds): array
    {
        return DB::table('ebanker_raw_rows')->join('ebanker_loads', 'ebanker_loads.id', '=', 'ebanker_raw_rows.load_id')
            ->whereIn('query_id', $queryIds)->whereNull('superseded_at')->where('ebanker_loads.status', 'LANDED')->distinct()
            ->get(['ebanker_loads.id', 'ebanker_loads.pack_hash', 'ebanker_loads.pack_name'])
            ->map(fn ($l) => ['load_id' => (int) $l->id, 'pack_hash' => $l->pack_hash, 'pack' => $l->pack_name])->values()->all();
    }

    /**
     * The trial balance lines landed from Finance's files (TB_01), the
     * current version of each, keyed by period, basis and GL code.
     *
     * @return array<string, array{load_id:int,source_key:string,payload:array}>
     */
    public function trialBalanceLines(): array
    {
        return $this->family(self::TRIAL_BALANCE);
    }

    /**
     * GL account names by GL code, from the landing zone: the keyed GL
     * master rows (GL_02) where they carry a name, and otherwise the title
     * printed on the trial balance line for that code (TB_01), the latest
     * period winning. Used where a screen would otherwise show a bare code
     * (system audit of 9 October 2026, finding M1). Empty when the zone has
     * not been created, so a caller outside the feed never fails on it.
     *
     * @return array<string, string>
     */
    public function glNames(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('ebanker_raw_rows')) {
            return [];
        }
        $names = [];
        $byPeriod = [];
        foreach ($this->family(self::TRIAL_BALANCE) as $row) {
            $p = $row['payload'];
            $code = trim((string) ($p['GL_CODE'] ?? ''));
            $title = trim((string) ($p['GL_TITLE'] ?? ''));
            $period = (string) ($p['PERIOD'] ?? '');
            if ($code !== '' && $title !== '' && ($byPeriod[$code] ?? '') <= $period) {
                $byPeriod[$code] = $period;
                $names[$code] = $title;
            }
        }
        foreach ($this->family(self::GL_MASTER) as $row) {
            $p = $row['payload'];
            $code = trim((string) ($p['GLCODE'] ?? ''));
            $title = trim((string) ($p['GLNAME'] ?? ($p['GL_NAME'] ?? ($p['GLDESC'] ?? ($p['GL_DESCRIPTION'] ?? '')))));
            if ($code !== '' && $title !== '') {
                $names[$code] = $title;
            }
        }

        return $names;
    }
}
