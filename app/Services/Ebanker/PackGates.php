<?php

namespace App\Services\Ebanker;

/**
 * The gates of spec v4 section 6.4 that look inside the files and across
 * them, added by the system audit of 9 October 2026, finding M2. The
 * landing service runs the gates that concern one file against the manifest
 * (it exists, its hash and row count match, its key is unique, its date
 * column parses); this class runs the rest, on the parsed rows of the pack
 * together with what earlier landed loads already hold:
 *
 *   numeric            every amount, balance and rate column parses once
 *                      commas are stripped; a value that does not refuses the
 *                      file, row named                               (ERROR)
 *   query_version      the version the manifest states for a file is the
 *                      version the register holds for its query      (ERROR)
 *   dates_iso          whether every date column of the file is ISO. The
 *                      format the manifest declares is still accepted (the
 *                      D23 relaxation), so this is recorded, not enforced
 *                                                                    (WARNING)
 *   accounts_in_master every account on a ledger posting to a loan GL exists
 *                      in the account master of this pack or an earlier
 *                      landed one                                    (ERROR)
 *   balance_history_ties
 *                      the balance history's month-end balance equals the
 *                      ledger's running balance for the account within 1.00;
 *                      the count that tie and the first that do not are
 *                      recorded. Spec section 9 says 2,533 of 2,549 tie
 *                      today (the rest are an accepted exception), so this
 *                      is recorded against the load rather than refusing it
 *                                                                    (WARNING)
 *   month_end_rows     every in-scope account (in the master, on a loan GL,
 *                      with a non-zero ledger balance) has a row in the
 *                      stored loan book at each month-end the pack's runs
 *                      cover                                        (WARNING)
 *
 * Every result is a row of the same shape: the gate, its level, PASS, FAIL,
 * WARN or SKIPPED, what was checked, and the failures with the file and row
 * named. ERROR and FAIL together quarantine the pack; WARNING never does.
 */
class PackGates
{
    public const LEVEL_ERROR = 'ERROR';
    public const LEVEL_WARNING = 'WARNING';

    /** How far the ledger's running balance may sit from the balance history before the row is reported. */
    public const BALANCE_TOLERANCE = 1.00;

    /** How many failing rows a gate lists; the count is always complete. */
    private const LISTED = 10;

    /**
     * Columns that must hold a number: the amounts, balances and rates of the
     * E-Banker extracts, by the name E-Banker gives them. A flag that happens
     * to end in AMT without the underscore (WITHDRAW_DEP_ON_DEPAMT in the
     * scheme settings) is not an amount and is not matched.
     */
    private const NUMERIC_COLUMN = '/(^|_)(AMOUNT|AMT|BALANCE|BAL)$|TRANSAMT$|^(PRINCIPAL|DEBIT|CREDIT|REPAYMENT|APPROVED|DISBURSD|NOT_YET_DISBURS|INTEREST_TO_DATE|INTEREST_RATE|OPENING_BAL|DAY_1_30|DAY_31_91|DAY_91_180|DAY_181_270|DAY_271_360)$/';

    public function __construct(private readonly LandingZoneReader $zone, private readonly PackLandingService $landing)
    {
    }

    // ----- gates on one file ------------------------------------------------

    /** @return array{level:string,result:string,columns:list<string>,checked:int,failures:list<string>} */
    public function numeric(string $file, array $headers, array $rows): array
    {
        $columns = array_values(array_filter($headers, fn ($h) => preg_match(self::NUMERIC_COLUMN, $h) === 1));
        $failures = [];
        $checked = 0;
        foreach ($rows as $i => $row) {
            foreach ($columns as $c) {
                $v = trim((string) ($row[$c] ?? ''));
                if ($v === '') {
                    continue;
                }
                $checked++;
                if (! self::isNumeric($v)) {
                    $failures[] = "{$file} row " . ($i + 2) . ": '{$v}' in {$c} is not a number";
                    if (count($failures) >= self::LISTED) {
                        break 2;
                    }
                }
            }
        }

        return ['level' => self::LEVEL_ERROR, 'result' => $failures === [] ? 'PASS' : 'FAIL', 'columns' => $columns, 'checked' => $checked, 'failures' => $failures];
    }

    /** A number as the export tool writes one: an optional sign, digits, a point, thousands commas allowed. */
    public static function isNumeric(string $value): bool
    {
        $v = str_replace([',', ' '], '', trim($value));

        return $v !== '' && is_numeric($v);
    }

    /** @return array{level:string,result:string,manifest:?string,register:string,failures:list<string>} */
    public function queryVersion(string $file, ?string $stated, object $query): array
    {
        $register = (string) ($query->version ?? '1');
        $stated = $stated === null || trim($stated) === '' ? null : trim($stated);
        $failures = [];
        if ($stated !== null && $stated !== $register) {
            $failures[] = "{$file}: query {$query->query_id} version {$stated} in the manifest, {$register} in the register";
        }

        return ['level' => self::LEVEL_ERROR, 'result' => $failures === [] ? 'PASS' : 'FAIL', 'manifest' => $stated, 'register' => $register,
            'note' => $stated === null ? 'the manifest states no version; the register version is recorded on the file' : null, 'failures' => $failures];
    }

    /**
     * Whether every date column of the file is written as ISO (Y-m-d, with
     * or without a time). The register's date column and every column whose
     * name ends in DATE are read.
     *
     * @return array{level:string,result:string,iso:?bool,columns:list<string>,first_non_iso:?string,failures:list<string>}
     */
    public function datesIso(string $file, array $headers, array $rows, ?string $dateColumn): array
    {
        $columns = array_values(array_unique(array_filter($headers, fn ($h) => $h === $dateColumn || preg_match('/DATE$/', $h) === 1)));
        if ($columns === []) {
            return ['level' => self::LEVEL_WARNING, 'result' => 'SKIPPED', 'iso' => null, 'columns' => [], 'first_non_iso' => null, 'failures' => []];
        }
        $first = null;
        $any = false;
        foreach ($rows as $i => $row) {
            foreach ($columns as $c) {
                $v = trim((string) ($row[$c] ?? ''));
                if ($v === '') {
                    continue;
                }
                $any = true;
                if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $v) !== 1) {
                    $first = "{$file} row " . ($i + 2) . ": '{$v}' in {$c} is not ISO";
                    break 2;
                }
            }
        }
        $iso = $first === null;

        return ['level' => self::LEVEL_WARNING, 'result' => $iso ? 'PASS' : 'WARN', 'iso' => $any ? $iso : null, 'columns' => $columns, 'first_non_iso' => $first,
            'failures' => $first === null ? [] : [$first]];
    }

    // ----- gates across the pack --------------------------------------------

    /**
     * The three cross-file gates on the parsed files of a pack. Each entry of
     * $parsed is ['file' => name, 'query' => register row, 'headers' => [...],
     * 'rows' => [...]] for a file the landing could read and whose query the
     * register knows.
     *
     * @return array<string, array>  gate name => result row
     */
    public function acrossPack(array $parsed, string $dateFormat): array
    {
        $master = $this->mergedMaster($parsed);
        $ledger = $this->mergedLedger($parsed, $dateFormat);

        return [
            'accounts_in_master' => $this->accountsInMaster($parsed, $master),
            'balance_history_ties' => $this->balanceHistoryTies($parsed, $ledger, $dateFormat),
            'month_end_rows' => $this->monthEndRows($parsed, $master, $ledger, $dateFormat),
        ];
    }

    private function accountsInMaster(array $parsed, array $master): array
    {
        $checked = 0; $failed = 0; $failures = [];
        foreach ($parsed as $p) {
            if (! in_array($p['query']->query_id, LandingZoneReader::LEDGER, true)) {
                continue;
            }
            foreach ($p['rows'] as $i => $r) {
                if (! $this->isLoanPosting($r)) {
                    continue;
                }
                $checked++;
                $account = trim((string) ($r['NEW_AC_NUMBER'] ?? ''));
                if (! isset($master[$account])) {
                    $failed++;
                    if (count($failures) < self::LISTED) {
                        $failures[] = "{$p['file']} row " . ($i + 2) . ": account {$account} is not in the account master of this pack or an earlier landed one";
                    }
                }
            }
        }
        if ($checked === 0) {
            return ['level' => self::LEVEL_ERROR, 'result' => 'SKIPPED', 'checked' => 0, 'failed' => 0, 'detail' => 'no ledger file in the pack', 'failures' => []];
        }

        return ['level' => self::LEVEL_ERROR, 'result' => $failed === 0 ? 'PASS' : 'FAIL', 'checked' => $checked, 'failed' => $failed,
            'detail' => ($checked - $failed) . " of {$checked} loan postings carry an account in the master", 'failures' => $failures];
    }

    private function balanceHistoryTies(array $parsed, array $ledger, string $dateFormat): array
    {
        $checked = 0; $tie = 0; $failures = [];
        foreach ($parsed as $p) {
            if (! in_array($p['query']->query_id, LandingZoneReader::BALANCE_HISTORY, true)) {
                continue;
            }
            foreach ($p['rows'] as $i => $r) {
                $account = trim((string) ($r['NEW_AC_NUMBER'] ?? ''));
                $date = $this->landing->parseDate(trim((string) ($r['TRANSACTION_DATE'] ?? '')), $dateFormat);
                $balance = trim((string) ($r['PRINCIPAL_BALANCE'] ?? ''));
                if ($account === '' || $date === null || ! self::isNumeric($balance)) {
                    continue;
                }
                $checked++;
                $running = $this->runningBalance($ledger, $account, $date);
                $diff = round($running - self::number($balance), 2);
                if (abs($diff) <= self::BALANCE_TOLERANCE) {
                    $tie++;
                } elseif (count($failures) < self::LISTED) {
                    $failures[] = sprintf('%s row %d: account %s at %s: ledger running balance %s, balance history %s, difference %s',
                        $p['file'], $i + 2, $account, $date, number_format($running, 2), number_format(self::number($balance), 2), number_format($diff, 2));
                }
            }
        }
        if ($checked === 0) {
            return ['level' => self::LEVEL_WARNING, 'result' => 'SKIPPED', 'checked' => 0, 'tie' => 0, 'detail' => 'no balance history file in the pack', 'failures' => []];
        }

        return ['level' => self::LEVEL_WARNING, 'result' => $tie === $checked ? 'PASS' : 'WARN', 'checked' => $checked, 'tie' => $tie,
            'detail' => "{$tie} of {$checked} account month-ends tie to the ledger within " . number_format(self::BALANCE_TOLERANCE, 2), 'failures' => $failures];
    }

    private function monthEndRows(array $parsed, array $master, array $ledger, string $dateFormat): array
    {
        $runs = [];   // month-end => account => true, from the pack
        foreach ($parsed as $p) {
            if (! in_array($p['query']->query_id, LandingZoneReader::LOAN_BOOK_RUNS, true)) {
                continue;
            }
            foreach ($p['rows'] as $r) {
                $d = $this->landing->parseDate(trim((string) ($r['ASONDATE'] ?? '')), $dateFormat);
                $a = trim((string) ($r['NEW_AC_NUMBER'] ?? ''));
                if ($d !== null && $a !== '') {
                    $runs[$d][$a] = true;
                }
            }
        }
        if ($runs === []) {
            return ['level' => self::LEVEL_WARNING, 'result' => 'SKIPPED', 'checked' => 0, 'missing' => 0, 'month_ends' => [], 'detail' => 'no stored loan book file in the pack', 'failures' => []];
        }
        ksort($runs);
        $inScope = array_keys(array_filter($master, fn ($m) => isset(LandingZoneReader::LOAN_GLS[trim((string) ($m['GLCODE'] ?? ''))])));
        sort($inScope);
        $checked = 0; $missing = 0; $failures = [];
        foreach ($runs as $monthEnd => $accounts) {
            // a re-pulled month may already hold the row from an earlier load
            foreach ($this->zone->latestRunsOn($monthEnd) as $a => $row) {
                $accounts[$a] = true;
            }
            foreach ($inScope as $account) {
                if (abs($this->runningBalance($ledger, $account, $monthEnd)) < 0.005) {
                    continue;
                }
                $checked++;
                if (! isset($accounts[$account])) {
                    $missing++;
                    if (count($failures) < self::LISTED) {
                        $failures[] = "account {$account} has a ledger balance at {$monthEnd} and no stored loan book row for that month-end";
                    }
                }
            }
        }

        return ['level' => self::LEVEL_WARNING, 'result' => $missing === 0 ? 'PASS' : 'WARN', 'checked' => $checked, 'missing' => $missing, 'month_ends' => array_keys($runs),
            'detail' => ($checked - $missing) . " of {$checked} in-scope account month-ends have a stored loan book row", 'failures' => $failures];
    }

    // ----- the merged view of the pack and the earlier loads ----------------

    /** @return array<string, array> account => master payload, the pack's rows over the earlier landed ones */
    private function mergedMaster(array $parsed): array
    {
        $master = $this->zone->accountMaster();
        foreach ($parsed as $p) {
            if (! in_array($p['query']->query_id, LandingZoneReader::ACCOUNT_MASTER, true)) {
                continue;
            }
            foreach ($p['rows'] as $r) {
                $a = trim((string) ($r['NEW_AC_NUMBER'] ?? ''));
                if ($a !== '') {
                    $master[$a] = $r;
                }
            }
        }

        return $master;
    }

    /**
     * Loan postings per account in date order, the pack's row winning over an
     * earlier landed version of the same source key.
     *
     * @return array<string, list<array{0:string,1:float}>> account => [[date, amount], ...]
     */
    private function mergedLedger(array $parsed, string $dateFormat): array
    {
        // keyed on the source key alone, as the reader's family is: the monthly
        // ledger query re-pulls keys the history query already landed
        $byKey = [];
        foreach ($this->zone->ledgerByAccount() as $account => $rows) {
            foreach ($rows as $row) {
                $byKey[$row['source_key']] = [$account, $row['row_date'], self::number((string) ($row['payload']['TRANSAMT'] ?? '0'))];
            }
        }
        foreach ($parsed as $p) {
            if (! in_array($p['query']->query_id, LandingZoneReader::LEDGER, true)) {
                continue;
            }
            foreach ($p['rows'] as $i => $r) {
                $key = trim((string) ($r['CUMVOUCH_DET_ID'] ?? ''));
                if ($key === '') {
                    $key = $p['file'] . '#' . ($i + 1);
                }
                if (! $this->isLoanPosting($r)) {
                    unset($byKey[$key]);   // a re-pull that now carries the deleted flag
                    continue;
                }
                $date = $this->landing->parseDate(trim((string) ($r['TRANSACTION_DATE'] ?? '')), $dateFormat);
                $amt = trim((string) ($r['TRANSAMT'] ?? ''));
                if ($date === null || ! self::isNumeric($amt)) {
                    continue;   // the file's own gates name the row
                }
                $byKey[$key] = [trim((string) $r['NEW_AC_NUMBER']), $date, self::number($amt)];
            }
        }
        $ledger = [];
        foreach ($byKey as [$account, $date, $amt]) {
            $ledger[$account][] = [$date, $amt];
        }
        foreach ($ledger as &$rows) {
            usort($rows, fn ($a, $b) => $a[0] <=> $b[0]);
        }

        return $ledger;
    }

    /** A live posting to one of the loan GLs with an account on it. */
    private function isLoanPosting(array $r): bool
    {
        if (($r['DELETE_FLAG'] ?? 'N') === 'Y' || trim((string) ($r['NEW_AC_NUMBER'] ?? '')) === '') {
            return false;
        }
        $gl = trim((string) ($r['AC_GLCODE'] ?? ($r['CB_GLCODE'] ?? '')));

        return $gl === '' || isset(LandingZoneReader::LOAN_GLS[$gl]);
    }

    /** The sum of the account's postings dated on or before the date, signed as E-Banker signs them (debits negative). */
    private function runningBalance(array $ledger, string $account, string $date): float
    {
        $sum = 0.0;
        foreach ($ledger[$account] ?? [] as [$d, $amt]) {
            if ($d > $date) {
                break;
            }
            $sum += $amt;
        }

        return round($sum, 2);
    }

    private static function number(string $value): float
    {
        return (float) str_replace([',', ' '], '', trim($value));
    }
}
