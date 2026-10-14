<?php

namespace App\Services\Ebanker;

use Carbon\CarbonImmutable;

/**
 * E-Banker's instalment chart as it stood on a date (verification of the PD,
 * LGD and staging inputs, 14 October 2026).
 *
 * E-Banker regenerates a loan's instalment chart whenever its rate or terms
 * change: the live rows (P2_10, FIXLOAN_INST_CHART taken on 6 October 2026)
 * are only the latest version, and every earlier version is in the superseded
 * rows (RS_02a), each with the date it was created (TRANSACTION_DATE) and the
 * date it was replaced (OPERATION_DATE). Reading the live chart for an earlier
 * month-end reads a schedule that did not yet exist (Happie Foods' chart of
 * 5 August 2026 read for August 2025). The chart in force at a date is the
 * set of rows created on or before it and not replaced by it; a row carried
 * by both extracts (live on 6 October, replaced before RS_02a was taken) is
 * the RS_02a row, which knows when it was replaced. A month-end for which no
 * row was in force has no chart: nothing is assumed in its place.
 *
 * Two versions in force together. Where the extracts leave two versions of
 * one instalment (the same definition and serial) in force on a date, the
 * later-created is the chart (Sycamore Credit, January and November 2025).
 *
 * A collapsed chart. The migration of March 2024 dated several instalments
 * of one monthly definition on one day: Udon Business 000104450000056 has
 * serials 35 to 73 all on 1 December 2025, so from that day E-Banker reports
 * the whole balance overdue from the value date although the borrower is
 * paid ahead; Msusa Investments and Beaulaland Food Processors carry serials
 * in pairs on one date. (BenCal Technologies 000104460000059 has the same
 * defect, serials 25 to 61 on 1 February 2025, but only in the dated copies
 * RS_02b to RS_02e, which are not read here: it is not corrected.) Where two or more instalments of
 * one monthly definition (P3_11 INSTALLMENT_PAY_MODE 'M') share a date, the
 * definition's rows are dated by their serial numbers, one month apart from
 * the definition's first row, which is the definition E-Banker itself holds;
 * a collapsed definition of any other or of no stated mode is not re-dated
 * and the chart is reported unreadable.
 *
 * Receipts are the cash the ledger credits to the loan as a repayment, net of
 * the reversals of the same types (a 306 debit "Reallocated repayments", a
 * refund of sale proceeds) and of the write-offs posted as repayments
 * (WriteOffClassifier), with collateral taken in settlement: the same classes
 * the loan book build counts as repayments and settlement in kind.
 */
class InstalmentChartHistory
{
    /** @var array<string, list<array{created:?string,replaced:?string,due:string,amount:float,det:string,serial:int}>>|null */
    private ?array $rows = null;

    /** @var array<string,string>|null instalment definition id => pay mode */
    private ?array $modes = null;

    /** @var array<string, list<array{date:string,receipt:float,debit:float}>> */
    private array $ledger = [];

    private ?WriteOffClassifier $writeOffs = null;

    public function __construct(private LandingZoneReader $zone)
    {
    }

    /**
     * The chart in force on a date, or null when no row was in force.
     *
     * @return array{instalments:list<array{0:string,1:float}>,rows:int,collapsed:bool,unreadable:bool,first_due:?string}|null
     */
    public function at(string $account, string $date): ?array
    {
        $rows = array_values(array_filter($this->rows()[$account] ?? [], fn ($r) => $r['created'] !== null && $r['created'] <= $date && ($r['replaced'] === null || $r['replaced'] > $date)));
        if ($rows === []) {
            return null;
        }
        // two versions left in force together (Sycamore Credit: the rows of 1 January and of
        // 1 November 2025 both live) carry the same instalment twice: the later version is the chart
        $latest = [];
        foreach ($rows as $i => $r) {
            if ($r['det'] === '' || $r['serial'] <= 0) {
                $latest['row:' . $i] = $r;
                continue;
            }
            $key = $r['det'] . '#' . $r['serial'];
            if (! isset($latest[$key]) || $r['created'] >= $latest[$key]['created']) {
                $latest[$key] = $r;
            }
        }
        [$rows, $collapsed, $unreadable] = $this->redate(array_values($latest));
        $instalments = [];
        foreach ($rows as $r) {
            if ($r['amount'] > 0) {
                $instalments[] = [$r['due'], $r['amount']];
            }
        }
        usort($instalments, fn ($a, $b) => $a[0] <=> $b[0]);

        return ['instalments' => $instalments, 'rows' => count($rows), 'collapsed' => $collapsed, 'unreadable' => $unreadable, 'first_due' => $instalments[0][0] ?? null];
    }

    /** Whether E-Banker holds any instalment chart for the account, in force or not. */
    public function hasChart(string $account): bool
    {
        return isset($this->rows()[$account]);
    }

    /** Cash received on the loan to the date (inclusive), net of reversals, write-offs left out. */
    public function receiptsTo(string $account, string $date): float
    {
        $sum = 0.0;
        foreach ($this->ledgerOf($account) as $p) {
            if ($p['date'] > $date) {
                break;
            }
            $sum += $p['receipt'];
        }

        return $sum;
    }

    /** Everything the ledger has debited to the loan to the date: drawdowns, interest and charges (reversed receipts left out). */
    public function debitsTo(string $account, string $date): float
    {
        $sum = 0.0;
        foreach ($this->ledgerOf($account) as $p) {
            if ($p['date'] > $date) {
                break;
            }
            $sum += $p['debit'];
        }

        return $sum;
    }

    /** The instalments due on or before the date, in total. */
    public static function dueTo(array $instalments, string $date): float
    {
        $sum = 0.0;
        foreach ($instalments as [$due, $amount]) {
            if ($due > $date) {
                break;
            }
            $sum += $amount;
        }

        return $sum;
    }

    /**
     * Receipts allocated to the instalments in due-date order: the number of
     * instalments due by the date that the receipts leave unpaid at the end
     * (the consecutive run of missed instalments).
     */
    public static function missed(array $instalments, float $receipts, string $date): int
    {
        $missed = 0;
        foreach ($instalments as [$due, $amount]) {
            if ($due > $date) {
                break;
            }
            if ($receipts + 0.005 >= $amount) {
                $receipts -= $amount;
                $missed = 0;
            } else {
                $receipts = 0.0;
                $missed++;
            }
        }

        return $missed;
    }

    /**
     * Days past due on the chart: from the oldest instalment the receipts,
     * allocated in due-date order, do not cover. [0, null] when every
     * instalment due is covered.
     *
     * @return array{0:int,1:?string}
     */
    public static function daysPastDue(array $instalments, float $receipts, string $date): array
    {
        $cum = 0.0;
        foreach ($instalments as [$due, $amount]) {
            if ($due > $date) {
                break;
            }
            $cum += $amount;
            if ($cum > $receipts + 0.005) {
                return [max(0, (int) CarbonImmutable::parse($due)->diffInDays(CarbonImmutable::parse($date), false)), $due];
            }
        }

        return [0, null];
    }

    /**
     * Date the rows of a collapsed monthly definition by their serial numbers.
     *
     * @return array{0:list<array>,1:bool,2:bool} the rows, whether any definition was collapsed, whether one could not be read
     */
    private function redate(array $rows): array
    {
        $byDef = [];
        foreach ($rows as $i => $r) {
            $byDef[$r['det']][] = $i;
        }
        $collapsed = false;
        $unreadable = false;
        foreach ($byDef as $det => $idx) {
            $dates = [];
            foreach ($idx as $i) {
                if ($rows[$i]['amount'] > 0) {
                    $dates[$rows[$i]['due']] = ($dates[$rows[$i]['due']] ?? 0) + 1;
                }
            }
            if ($dates === [] || max($dates) < 2) {
                continue;
            }
            $collapsed = true;
            if ($det === '' || ($this->modes()[$det] ?? null) !== 'M') {
                $unreadable = true;
                continue;
            }
            usort($idx, fn ($a, $b) => $rows[$a]['serial'] <=> $rows[$b]['serial']);
            $first = $rows[$idx[0]];
            $anchor = CarbonImmutable::parse($first['due']);
            foreach ($idx as $i) {
                $rows[$i]['due'] = $anchor->addMonthsNoOverflow($rows[$i]['serial'] - $first['serial'])->toDateString();
            }
        }

        return [$rows, $collapsed, $unreadable];
    }

    /** @return array<string, list<array>> */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }
        $by = [];
        foreach ([['P2_10', false], ['RS_02a', true]] as [$query, $superseded]) {
            foreach ($this->zone->family([$query]) as $row) {
                $p = $row['payload'];
                if (($p['ACTIVE_FLAG'] ?? 'Y') !== 'Y' || $row['account'] === null || $row['account'] === '') {
                    continue;
                }
                if (! $superseded && ($p['DELETE_FLAG'] ?? 'N') === 'Y') {
                    continue; // a deleted row in the live extract: its replacement date is in RS_02a
                }
                $due = self::date((string) ($p['INSTALLMENT_DATE'] ?? '')) ?? $row['row_date'];
                if ($due === null) {
                    continue;
                }
                $key = (string) ($p['INST_CHART_DET_ID'] ?? ($query . ':' . $row['source_key']));
                $by[$row['account']][$key] = [
                    'created' => self::date((string) ($p['TRANSACTION_DATE'] ?? '')),
                    // a superseded row with no replacement date cannot be placed: it is never in force
                    'replaced' => $superseded ? (self::date((string) ($p['OPERATION_DATE'] ?? '')) ?? '0000-00-00') : null,
                    'due' => $due, 'amount' => (float) str_replace(',', '', (string) ($p['INSTALLMENT_AMT'] ?? '0')),
                    'det' => (string) ($p['INST_DET_ID'] ?? ''), 'serial' => (int) ($p['INST_SR_NO'] ?? 0),
                ];
            }
        }

        return $this->rows = array_map('array_values', $by);
    }

    /** @return array<string,string> */
    private function modes(): array
    {
        if ($this->modes !== null) {
            return $this->modes;
        }
        $out = [];
        foreach ($this->zone->family(['P3_11']) as $row) {
            $id = (string) ($row['payload']['INST_DET_ID'] ?? '');
            if ($id !== '') {
                $out[$id] = strtoupper(trim((string) ($row['payload']['INSTALLMENT_PAY_MODE'] ?? '')));
            }
        }

        return $this->modes = $out;
    }

    /** @return list<array{date:string,receipt:float,debit:float}> */
    private function ledgerOf(string $account): array
    {
        if (isset($this->ledger[$account])) {
            return $this->ledger[$account];
        }
        $this->writeOffs ??= new WriteOffClassifier($this->zone);
        $out = [];
        foreach ($this->zone->ledgerByAccount(null, $account)[$account] ?? [] as $post) {
            $amount = (float) str_replace(',', '', (string) ($post['payload']['TRANSAMT'] ?? 0));
            $type = (string) ($post['payload']['TRANTYPE'] ?? '');
            $receipt = 0.0; $debit = 0.0;
            if ($this->writeOffs->isWriteOff($post['payload'], $post['row_date'], $account)) {
                // a write-off is not cash
            } elseif (in_array($type, LoanBookBuildService::TYPE_RECEIPT, true) || in_array($type, LoanBookBuildService::TYPE_SETTLEMENT_IN_KIND, true)) {
                $receipt = $amount; // a credit, or the debit that reverses one
            } elseif ($amount < 0) {
                $debit = -$amount;
            }
            $out[] = ['date' => (string) $post['row_date'], 'receipt' => $receipt, 'debit' => $debit];
        }

        return $this->ledger[$account] = $out;
    }

    /** E-Banker writes dates as m/d/Y; the landing zone's own dates are Y-m-d. */
    public static function date(string $v): ?string
    {
        $v = trim(explode(' ', trim($v))[0] ?? '');
        if ($v === '') {
            return null;
        }
        foreach (['n/j/Y', 'm/d/Y', 'Y-m-d'] as $f) {
            $d = \DateTimeImmutable::createFromFormat('!' . $f, $v);
            if ($d !== false && $d->format($f) === $v) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }
}
