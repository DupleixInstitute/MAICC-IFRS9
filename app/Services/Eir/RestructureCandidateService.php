<?php

namespace App\Services\Eir;

use App\Services\AuditLoggerService;
use App\Services\Ebanker\InstalmentChartHistory;
use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\LoanBookBuildService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Candidate restructures from E-Banker's reschedule history (specification v4
 * section 3.9; review item R10; built 14 October 2026).
 *
 * A candidate is evidence that a loan's terms may have changed. It is never a
 * confirmed event: a person classifies it with a reason and a different person
 * approves (RestructureRegisterService). Nothing here confirms anything, and the
 * bootstrap only ever runs this detector.
 *
 * Where the evidence is, and how it is read:
 *
 *  - The instalment chart history (P2_10, the live chart of 6 October 2026, and
 *    RS_02a, the superseded rows with the date each was created and replaced).
 *    E-Banker writes a new set of instalments whenever it regenerates a chart.
 *    Each date on which new instalments were created, after the chart's first,
 *    is a regeneration: the old terms are the chart in force the day before, the
 *    new terms are the instalments created that day and due after it. Most
 *    regenerations follow a rate change (the PLR moves, or the account's rate
 *    is keyed in P1_04 on that date) and only re-amortise the same balance over
 *    about the same term: those are B5.4.5 resets, recorded on the rate-reset
 *    register, not here. A regeneration is a candidate when it extends the
 *    maturity by more than one instalment period, when the new chart amortises
 *    more than the old chart expected at that date beyond what the ledger
 *    advanced in between (capitalised arrears or charges), or when it falls on
 *    a date with no rate change and is not explained by a drawdown.
 *  - The dated copies of the chart (RS_02b of 30 August 2024, RS_02d of
 *    28 September 2024 and RS_02c of 14 November 2024; RS_02e, FIXLOAN_INST_CHART1,
 *    carries no date). For a loan whose chart is in neither P2_10 nor RS_02a,
 *    two dated copies that differ show a change between their dates. The date
 *    of the change is not known, so the candidate fails closed.
 *  - MODIFICATION_LOG (RS_02f): an accepted edit to a loan's maturity, tenor,
 *    sanction, moratorium, rate or interest policy, resolved to its account
 *    through the loan master (P1_03) or the account master (P1_02). A rejected
 *    edit never took effect and is not a candidate; one not yet passed is a
 *    candidate that fails closed. An edit before the loan's first drawdown
 *    changes nothing lent and is suggested as a correction.
 *
 *  - The migration worksheet (CV_10, OPENING_BAL, pack 9 of 14 October 2026):
 *    the loans taken on at 31 July 2024, eight of them with a RESTRUCTURE_DATE
 *    and RESTRUCURE_AMT, the new maturity and tenor. These are MAIIC's own
 *    record of a restructure, so the suggestion is a modification, but they
 *    are still candidates until a person confirms them. Every one predates the
 *    take-on: the balance taken on (NEWDISBURS_AMT, the restructured amount)
 *    and the take-on EIR (decision D41, solved on the workbook's chain) already
 *    carry it, so a confirmed pre-take-on restructure is history and
 *    disclosure (the RBM return, paragraph 16(1)), never a new modification
 *    gain or loss after the take-on (RestructureRegisterService::changesAccounting).
 *    The worksheet holds neither the carrying amount before the restructure
 *    nor the old cash flows, so the 10 percent test cannot be re-performed
 *    from it; the candidate says so.
 *
 * The 28 September copy is dated 2024: the table is named FIX_INST_CHART28SEP,
 * its latest row was created on 7 August 2024, and the 14 November 2024 copy
 * holds rows created up to 5 November 2024.
 */
class RestructureCandidateService
{
    /** The dated copies of the instalment chart, by the date in the E-Banker table's name. */
    public const COPIES = InstalmentChartHistory::COPIES;

    /** FIXLOAN_INST_CHART1: a copy with no date (spec 3.9). */
    public const UNDATED_COPY = 'RS_02e';

    /** The MODIFICATION_LOG fields that are a loan's terms, by E-Banker table. */
    public const TERM_FIELDS = [
        'ACLOANMASTER' => [
            'EXPIRY_DATE' => 'maturity', 'PERIOD_MONTHS' => 'tenor in months', 'PERIOD_YEARS' => 'tenor in years', 'PERIOD_DAYS' => 'tenor in days',
            'PERIOD_WEEKS' => 'tenor in weeks', 'SANCTION_AMOUNT' => 'sanctioned amount', 'PMOROTORIUM_PERIOD' => 'principal moratorium',
            'IMOROTORIUM_PERIOD' => 'interest moratorium', 'INST_FREQ' => 'instalment frequency',
        ],
        'ACMASTER' => ['INTEREST_RATE' => 'interest rate', 'INTEREST_POLICY' => 'interest policy'],
    ];

    public const SUGGESTIONS = ['CORRECTION', 'CONTRACTUAL_RESET', 'MODIFICATION', 'REVISED_ESTIMATE', 'UNCERTAIN'];

    /** @var array<string, list<array>>|null */
    private ?array $chart = null;

    /** @var \Closure(string, ?string): list<array>|null the ledger, read once per detection (LandingZoneReader::ledgerReader) */
    private ?\Closure $ledger = null;

    public function __construct(private LandingZoneReader $zone)
    {
    }

    /**
     * Find the candidates and write them to the register. A candidate a person has
     * acted on (proposed or confirmed) is never changed; a plain candidate whose
     * evidence is gone is removed.
     *
     * @return array{found:int, written:int, kept:int, removed:int, incomplete:int, by_source:array<string,int>, by_suggestion:array<string,int>}
     */
    public function detect(?int $userId = null): array
    {
        $candidates = array_merge($this->fromChartHistory(), $this->fromCopies(), $this->fromOpeningBalance());
        $candidates = $this->mergeLog($candidates, $this->fromModificationLog());
        $names = $this->names();

        $written = 0; $kept = 0;
        $keys = [];
        foreach ($candidates as $c) {
            $keys[] = $c['detection_key'];
            $c['customer_name'] = $names[$c['contract_id']] ?? null;
            $existing = DB::table('restructure_events')->where('detection_key', $c['detection_key'])->first(['id', 'status']);
            $row = $this->row($c);
            if ($existing === null) {
                DB::table('restructure_events')->insert($row + ['status' => 'CANDIDATE', 'created_at' => now(), 'updated_at' => now()]);
                $written++;
            } elseif ($existing->status === 'CANDIDATE') {
                DB::table('restructure_events')->where('id', $existing->id)->update($row + ['updated_at' => now()]);
                $written++;
            } else {
                $kept++;
            }
        }
        // plain candidates whose evidence is no longer in the zone; rate-change candidates belong to the reset detector
        $stale = DB::table('restructure_events')->where('status', 'CANDIDATE')->where('source', '!=', 'RATE_CHANGE')
            ->whereNotIn('detection_key', $keys === [] ? ['-'] : $keys)->pluck('id');
        $removed = $stale->count();
        if ($removed > 0) {
            DB::table('restructure_events')->whereIn('id', $stale)->delete();
        }

        $summary = [
            'found' => count($candidates), 'written' => $written, 'kept' => $kept, 'removed' => $removed,
            'incomplete' => count(array_filter($candidates, fn ($c) => ($c['incomplete_reason'] ?? null) !== null)),
            'by_source' => array_count_values(array_column($candidates, 'source')),
            'by_suggestion' => array_count_values(array_column($candidates, 'suggested_class')),
        ];
        AuditLoggerService::log('Restructure Candidates Detected', 'restructure_events', null, ['new_values' => $summary + ['user_id' => $userId]]);

        return $summary;
    }

    /**
     * Write one candidate found by another detector (the rate changes of a
     * fixed-rate loan, or a reset a person classified as negotiated).
     */
    public function upsert(array $c): int
    {
        $existing = DB::table('restructure_events')->where('detection_key', $c['detection_key'])->first(['id', 'status']);
        $c['customer_name'] ??= $this->names()[$c['contract_id']] ?? null;
        if ($existing === null) {
            return (int) DB::table('restructure_events')->insertGetId($this->row($c) + ['status' => 'CANDIDATE', 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($existing->status === 'CANDIDATE') {
            DB::table('restructure_events')->where('id', $existing->id)->update($this->row($c) + ['updated_at' => now()]);
        }

        return (int) $existing->id;
    }

    /** @return list<array> */
    public function fromChartHistory(): array
    {
        $plr = $this->plrDates();
        $keyed = $this->keyedRateDates();
        $out = [];
        foreach ($this->chart() as $account => $rows) {
            $created = array_values(array_unique(array_filter(array_column($rows, 'created'))));
            sort($created);
            $previous = $created[0] ?? null;
            foreach (array_slice($created, 1) as $date) {
                $old = array_values(array_filter($this->inForce($rows, self::dayBefore($date)), fn ($r) => $r['due'] > $date && $r['amount'] > 0));
                $new = array_values(array_filter($rows, fn ($r) => $r['created'] === $date && ($r['replaced'] === null || $r['replaced'] > $date) && $r['due'] > $date && $r['amount'] > 0));
                usort($new, fn ($a, $b) => $a['due'] <=> $b['due']);
                if ($new === []) {
                    continue; // nothing still to come was written that day: a re-dating of paid rows, not a new schedule
                }
                $rateDate = isset($plr[$date]) || isset($keyed[$account][$date]);
                $advanced = $this->advanced($account, $previous, $date);
                $previous = $date;
                $c = $this->compare($account, $date, $old, $new, $advanced, $rateDate);
                if ($c !== null) {
                    $out[] = $c;
                }
            }
        }

        return $out;
    }

    /**
     * Compare the old and the new chart at a regeneration and decide whether it is a
     * candidate, with the engine's suggestion. Null when it is a reset or a tranche.
     */
    public function compare(string $account, string $date, array $old, array $new, float $advanced, bool $rateDate): ?array
    {
        $contract = ltrim($account, '0');
        $newTerms = $this->terms($new);
        $base = [
            'detection_key' => "CHART|{$account}|{$date}", 'contract_id' => $contract, 'account' => $account, 'event_date' => $date,
            'source' => 'CHART_REPLACED', 'source_reference' => "Instalment chart regenerated on {$date} (RS_02a / P2_10): " . count($new) . ' instalments created that day',
            'new_terms' => $newTerms, 'new_flows' => $this->flows($new), 'advanced_in_window' => round($advanced, 2),
            'ebanker_entered_by' => $new[0]['user'] ?? null, 'ebanker_passed_by' => $new[0]['passed'] ?? null, 'ebanker_flag' => $new[0]['verify'] ?? null,
        ];
        if ($old === []) {
            return $base + ['suggested_class' => 'UNCERTAIN', 'old_terms' => null,
                'suggestion_reason' => 'A new schedule was written while no earlier instalment was in force the day before, so the old terms cannot be read.',
                'incomplete_reason' => 'No old terms: no instalment of an earlier chart was in force on ' . self::dayBefore($date) . '.'];
        }
        $oldTerms = $this->terms($old);
        $oldBalance = $old[0]['balance'] + $old[0]['principal'];
        $newBalance = $new[0]['balance'] + $new[0]['principal'];
        $capitalised = $newBalance - $oldBalance - $advanced;
        // E-Banker rounds each instalment to the kwacha: a difference inside one kwacha per instalment is rounding
        $tolerance = max(count($old), count($new)) + 1.0;
        $step = $this->stepMonths($new);
        $shift = self::months($oldTerms['maturity'], $newTerms['maturity']);

        $base += ['old_terms' => $oldTerms, 'old_balance' => round($oldBalance, 2), 'new_balance' => round($newBalance, 2), 'capitalised_amount' => round($capitalised, 2)];
        // E-Banker rebuilds a chart from what is owed: a chart that amortises more than the whole ledger balance
        // on that date (drawn, charged, less repaid) counts money not lent or already repaid, and is not the
        // loan's modified cash flows (a facility still drawing down is the usual case)
        $ledger = $this->ledgerBalance($account, $date);
        $base['new_terms']['ledger_balance'] = $ledger === null ? null : round($ledger, 2);
        if ($ledger !== null && $newBalance > $ledger + $tolerance) {
            $base['incomplete_reason'] = sprintf('The new chart amortises %s while the ledger owes %s on %s: it counts money not lent or already repaid, so it cannot be booked as the modified cash flows.', self::money($newBalance), self::money($ledger), $date);
        }
        // the latest chart of the loan: an extension E-Banker later reverses is its re-amortisation flexing the term, not a new term
        $latest = $this->latestMaturity($account);
        $persists = $latest === null || $latest >= $newTerms['maturity'];
        if ($rateDate) {
            // At a rate change E-Banker re-amortises the outstanding balance, so up to one instalment of the
            // period's own difference moves with it. Arrears beyond one instalment re-spread over the term,
            // or a longer term that stays, are for MAIIC to classify (O17); anything less is the reset.
            if ($capitalised > max($tolerance, (float) $newTerms['instalment'])) {
                return $base + ['suggested_class' => 'UNCERTAIN', 'suggestion_reason' => sprintf('Regenerated on a rate change, but the new chart amortises %s more than the old chart expected at that date (more than one instalment of %s), beyond the %s the ledger advanced in between: E-Banker re-amortises from the outstanding balance, which spreads arrears over the remaining instalments. Whether that is a modification is MAIIC\'s classification (O17).', self::money($capitalised), self::money((float) $newTerms['instalment']), self::money($advanced))];
            }
            if ($shift > $step && $persists) {
                return $base + ['suggested_class' => 'MODIFICATION', 'suggestion_reason' => sprintf('Regenerated on a rate change, and the maturity moved from %s to %s (%d months, more than one instalment period) and is still at least that in the latest chart (%s).', $oldTerms['maturity'], $newTerms['maturity'], $shift, $latest ?? 'this chart')];
            }

            return null; // a re-amortisation for a rate change: a B5.4.5 reset, on the rate-reset register
        }
        if ($advanced > 0 && $capitalised <= max($tolerance, (float) $newTerms['instalment'])) {
            return null; // the new chart takes in a tranche drawn in between: a drawdown (the tranche register, D40), not a change of terms
        }
        if ($capitalised > $tolerance) {
            return $base + ['suggested_class' => 'MODIFICATION', 'suggestion_reason' => sprintf('The new chart amortises %s more than the old chart expected at that date, beyond the %s the ledger advanced in between: arrears or charges capitalised, on a date with no rate change.', self::money($capitalised), self::money($advanced))];
        }
        if ($shift > $step) {
            return $base + ['suggested_class' => 'MODIFICATION',
                'suggestion_reason' => sprintf('The maturity moved from %s to %s (%d months, more than one instalment period of %d month%s), on a date with no rate change%s.', $oldTerms['maturity'], $newTerms['maturity'], $shift, $step, $step === 1 ? '' : 's', $persists ? '' : sprintf(' (the latest chart has %s)', $latest))];
        }
        if ($capitalised < -$tolerance) {
            return $base + ['suggested_class' => 'REVISED_ESTIMATE',
                'suggestion_reason' => sprintf('Re-amortised on a date with no rate change from a balance %s below what the old chart expected: a prepayment or a part settlement re-spread over the remaining instalments (B5.4.6), unless MAIIC agreed new terms.', self::money(-$capitalised))];
        }

        return $base + ['suggested_class' => 'UNCERTAIN',
            'suggestion_reason' => sprintf('The chart was regenerated on a date with no rate change keyed and no drawdown (maturity %s to %s, %d to %d instalments).', $oldTerms['maturity'], $newTerms['maturity'], $oldTerms['instalments'], $newTerms['instalments'])];
    }

    /** @return list<array> differences between dated copies, for loans whose chart is in no other extract */
    public function fromCopies(): array
    {
        $history = $this->chart();
        $copies = [];
        foreach (array_merge(array_keys(self::COPIES), [self::UNDATED_COPY]) as $q) {
            foreach ($this->zone->family([$q]) as $row) {
                $p = $row['payload'];
                $account = (string) $row['account'];
                if ($account === '' || isset($history[$account]) || ($p['ACTIVE_FLAG'] ?? 'Y') !== 'Y') {
                    continue;
                }
                $due = InstalmentChartHistory::date((string) ($p['INSTALLMENT_DATE'] ?? ''));
                $amount = self::num($p['INSTALLMENT_AMT'] ?? 0);
                if ($due !== null && $amount > 0) {
                    $copies[$account][$q][] = ['due' => $due, 'amount' => round($amount, 2), 'serial' => (int) ($p['INST_SR_NO'] ?? 0)];
                }
            }
        }
        $out = [];
        foreach ($copies as $account => $byCopy) {
            $dated = array_values(array_filter(array_keys(self::COPIES), fn ($q) => isset($byCopy[$q])));
            usort($dated, fn ($a, $b) => self::COPIES[$a] <=> self::COPIES[$b]);
            for ($i = 1; $i < count($dated); $i++) {
                [$a, $b] = [$dated[$i - 1], $dated[$i]];
                if ($this->sameChart($byCopy[$a], $byCopy[$b])) {
                    continue;
                }
                $out[] = $this->copyCandidate($account, $byCopy[$a], $byCopy[$b], "{$a} of " . self::COPIES[$a] . " and {$b} of " . self::COPIES[$b],
                    'COPY|' . $account . '|' . $a . '|' . $b, 'the change happened between ' . self::COPIES[$a] . ' and ' . self::COPIES[$b] . ', and no record dates it');
            }
            if (isset($byCopy[self::UNDATED_COPY]) && $dated !== [] && ! $this->sameChart($byCopy[end($dated)], $byCopy[self::UNDATED_COPY])) {
                $last = end($dated);
                $out[] = $this->copyCandidate($account, $byCopy[$last], $byCopy[self::UNDATED_COPY], "{$last} of " . self::COPIES[$last] . ' and the undated copy RS_02e (FIXLOAN_INST_CHART1)',
                    'COPY|' . $account . '|' . $last . '|' . self::UNDATED_COPY, 'FIXLOAN_INST_CHART1 carries no date, so neither the order nor the date of the change can be read');
            }
        }

        return $out;
    }

    /** @return list<array> the restructures the migration worksheet records (CV_10), all before the take-on */
    public function fromOpeningBalance(): array
    {
        $inScope = array_flip(array_map(fn ($c) => (string) $c, DB::table('loan_books')->distinct()->pluck('contract_id')->all()));
        $out = [];
        foreach ($this->zone->family(['CV_10']) as $row) {
            $p = $row['payload'];
            $account = trim((string) ($p['ACCNO'] ?? $row['account'] ?? ''));
            $date = InstalmentChartHistory::date((string) ($p['RESTRUCTURE_DATE'] ?? ''));
            $amount = abs(self::num($p['RESTRUCURE_AMT'] ?? 0));
            if ($account === '' || $date === null || $amount <= 0 || ! isset($inScope[ltrim($account, '0')])) {
                continue;
            }
            $maturity = InstalmentChartHistory::date((string) ($p['MATURITY_DATE'] ?? ''));
            $rate = self::num($p['INT_RATE'] ?? 0);
            $rate = $rate > 0 && $rate < 1 ? $rate * 100 : $rate; // the worksheet writes 0.33 for 33 percent
            $tenor = trim((string) ($p['TENOR_YRS'] ?? ''));
            $sanctioned = abs(self::num($p['SANCTION_AMOUNT'] ?? 0));
            $disbursed = abs(self::num($p['DISBURS_AMT'] ?? 0));
            $out[] = [
                'detection_key' => "OPENBAL|{$account}|{$date}", 'contract_id' => ltrim($account, '0'), 'account' => $account, 'event_date' => $date,
                'source' => 'OPENING_BAL',
                'source_reference' => sprintf('Migration worksheet OPENING_BAL (CV_10, row %s): restructured on %s for %s, maturity %s, tenor %s years', (string) ($p['OP_BAL_DET_ID'] ?? $row['source_key']),
                    $date, self::money($amount), $maturity ?? '-', $tenor === '' ? '-' : rtrim(rtrim($tenor, '0'), '.')),
                'suggested_class' => 'MODIFICATION',
                'suggestion_reason' => 'MAIIC\'s migration worksheet records this restructure, so it is suggested as a modification. It predates the take-on of ' . \App\Services\Ebanker\TakeonLandingService::TAKEON_DATE
                    . ': the balance taken on and the take-on EIR already reflect it, so once confirmed it is history and disclosure (the RBM return, paragraph 16(1)), not a modification gain or loss after the take-on. The worksheet holds neither the carrying amount before the restructure nor the old cash flows, so the 10 percent test cannot be re-performed from it.',
                'old_terms' => ['sanctioned' => round($sanctioned, 2), 'disbursed' => round($disbursed, 2), 'sanction_date' => InstalmentChartHistory::date((string) ($p['SANCTION_DATE'] ?? ''))],
                'new_terms' => ['restructured_amount' => round($amount, 2), 'maturity' => $maturity, 'tenor_years' => $tenor === '' ? null : (float) $tenor,
                    'balance_at_takeon' => round(abs(self::num($p['BALANCE_AMT'] ?? 0)), 2), 'taken_on_as' => round(abs(self::num($p['NEWDISBURS_AMT'] ?? 0)), 2)],
                'new_maturity' => $maturity, 'new_balance' => round($amount, 2), 'new_rate' => $rate > 0 ? round($rate, 5) : null, 'new_flows' => null,
            ];
        }

        return $out;
    }

    /** @return list<array> accepted MODIFICATION_LOG edits to a loan's terms, one candidate per account and day */
    public function fromModificationLog(): array
    {
        $loanIds = []; $accountIds = [];
        foreach ($this->zone->family(LandingZoneReader::LOAN_MASTER) as $row) {
            $loanIds[(string) ($row['payload']['ACLOAN_MST_ID'] ?? '')] = (string) $row['account'];
        }
        foreach ($this->zone->family(LandingZoneReader::ACCOUNT_MASTER) as $row) {
            $accountIds[(string) ($row['payload']['ACCOUNT_MST_ID'] ?? '')] = (string) $row['account'];
        }
        $inScope = array_flip(array_map(fn ($c) => (string) $c, DB::table('loan_books')->distinct()->pluck('contract_id')->all()));
        $byKey = [];
        foreach ($this->zone->family(['RS_02f']) as $row) {
            $p = $row['payload'];
            $table = strtoupper(trim((string) ($p['TABLE_NAME'] ?? '')));
            $field = strtoupper(trim((string) ($p['FIELD_NAME'] ?? '')));
            if (! isset(self::TERM_FIELDS[$table][$field])) {
                continue;
            }
            $flag = strtoupper(trim((string) ($p['ACCEPTED_REJECTED_FLAG'] ?? '')));
            if ($flag === 'R') {
                continue; // rejected in E-Banker: it never took effect
            }
            $id = (string) ($p['PRIMARY_KEY_ID'] ?? '');
            $account = $table === 'ACLOANMASTER' ? ($loanIds[$id] ?? null) : ($accountIds[$id] ?? null);
            if ($account === null || ! isset($inScope[ltrim($account, '0')])) {
                continue;
            }
            $date = InstalmentChartHistory::date((string) ($p['TRANSACTION_DATE'] ?? ''));
            if ($date !== null && $date < '1900-01-01') {
                $date = null; // 1/1/0001: E-Banker's empty date
            }
            $key = $account . '|' . ($date ?? 'undated-' . ($p['MOD_LOG_DET_ID'] ?? $row['source_key']));
            $byKey[$key]['account'] = $account;
            $byKey[$key]['date'] = $date;
            $byKey[$key]['edits'][] = ['field' => self::TERM_FIELDS[$table][$field], 'code' => "{$table}.{$field}", 'old' => (string) ($p['OLD_VALUE'] ?? ''),
                'new' => (string) ($p['NEW_VALUE'] ?? ''), 'log_id' => (string) ($p['MOD_LOG_DET_ID'] ?? ''), 'flag' => $flag,
                'entered_by' => (string) ($p['ENTER_USER_ID'] ?? ''), 'passed_by' => (string) ($p['PASS_USER_ID'] ?? '')];
        }
        $out = [];
        foreach ($byKey as $key => $g) {
            $account = $g['account']; $date = $g['date']; $edits = $g['edits'];
            $pending = array_filter($edits, fn ($e) => $e['flag'] !== 'A');
            $firstDraw = $this->firstDrawdown($account);
            $describe = implode('; ', array_map(fn ($e) => "{$e['field']} {$e['old']} to {$e['new']}", $edits));
            [$class, $why] = $this->suggestForEdits($edits, $date, $firstDraw, $account);
            $incomplete = $date === null ? 'The edit carries no date (1/1/0001 in MODIFICATION_LOG).'
                : ($pending !== [] ? 'Not passed in E-Banker (MODIFICATION_LOG flag ' . implode(', ', array_unique(array_column($pending, 'flag'))) . ').' : null);
            $after = $date === null ? [] : $this->remainingAt($account, $date);
            $before = $date === null ? [] : array_values(array_filter($this->inForce($this->chart()[$account] ?? [], self::dayBefore($date)), fn ($r) => $r['due'] > $date && $r['amount'] > 0));
            $out[] = [
                'detection_key' => 'LOG|' . $key, 'contract_id' => ltrim($account, '0'), 'account' => $account, 'event_date' => $date,
                'source' => 'MODIFICATION_LOG', 'source_reference' => 'MODIFICATION_LOG ' . implode(', ', array_unique(array_column($edits, 'log_id'))) . ': ' . $describe,
                'suggested_class' => $class, 'suggestion_reason' => $why, 'incomplete_reason' => $incomplete,
                'old_terms' => ($before === [] ? [] : $this->terms($before)) + ['edits' => array_map(fn ($e) => [$e['code'] => $e['old']], $edits)],
                'new_terms' => ($after === [] ? [] : $this->terms($after)) + ['edits' => array_map(fn ($e) => [$e['code'] => $e['new']], $edits)],
                'new_flows' => $after === [] ? null : $this->flows($after),
                'old_rate' => $this->editValue($edits, 'ACMASTER.INTEREST_RATE', 'old'), 'new_rate' => $this->editValue($edits, 'ACMASTER.INTEREST_RATE', 'new'),
                'old_maturity' => InstalmentChartHistory::date($this->ebankerDate($this->editValue($edits, 'ACLOANMASTER.EXPIRY_DATE', 'old'))),
                'new_maturity' => InstalmentChartHistory::date($this->ebankerDate($this->editValue($edits, 'ACLOANMASTER.EXPIRY_DATE', 'new'))),
                'ebanker_entered_by' => $edits[0]['entered_by'] ?: null, 'ebanker_passed_by' => $edits[0]['passed_by'] ?: null, 'ebanker_flag' => implode(',', array_unique(array_column($edits, 'flag'))),
            ];
        }

        return $out;
    }

    /**
     * The chart in force on a date, one instalment per due date (where the extracts
     * leave two versions in force together, the later-created one).
     *
     * @return list<array>
     */
    public function inForce(array $rows, string $date): array
    {
        $best = [];
        foreach ($rows as $r) {
            if ($r['created'] !== null && $r['created'] <= $date && ($r['replaced'] === null || $r['replaced'] > $date)) {
                if (! isset($best[$r['due']]) || $r['created'] >= $best[$r['due']]['created']) {
                    $best[$r['due']] = $r;
                }
            }
        }
        ksort($best);

        return array_values($best);
    }

    /** The instalments due after a date on the chart in force at the end of that date. */
    public function remainingAt(string $account, string $date): array
    {
        return array_values(array_filter($this->inForce($this->chart()[$account] ?? [], $date), fn ($r) => $r['due'] > $date && $r['amount'] > 0));
    }

    /** @return array<string, list<array>> the live and superseded chart rows by account */
    public function chart(): array
    {
        if ($this->chart !== null) {
            return $this->chart;
        }
        $by = [];
        foreach ([['P2_10', false], ['RS_02a', true]] as [$query, $superseded]) {
            foreach ($this->zone->family([$query]) as $row) {
                $p = $row['payload'];
                if (($p['ACTIVE_FLAG'] ?? 'Y') !== 'Y' || $row['account'] === null || $row['account'] === '') {
                    continue;
                }
                if (! $superseded && ($p['DELETE_FLAG'] ?? 'N') === 'Y') {
                    continue;
                }
                $due = InstalmentChartHistory::date((string) ($p['INSTALLMENT_DATE'] ?? ''));
                if ($due === null) {
                    continue;
                }
                $amount = self::num($p['INSTALLMENT_AMT'] ?? 0);
                $principal = self::num($p['PRIN_INSTALLMENT_AMT'] ?? 0);
                $by[$row['account']][(string) ($p['INST_CHART_DET_ID'] ?? ($query . ':' . $row['source_key']))] = [
                    'created' => InstalmentChartHistory::date((string) ($p['TRANSACTION_DATE'] ?? '')),
                    'replaced' => $superseded ? (InstalmentChartHistory::date((string) ($p['OPERATION_DATE'] ?? '')) ?? '0000-00-00') : null,
                    'due' => $due, 'amount' => $amount, 'principal' => $principal, 'interest' => self::num($p['INTR_INSTALLMENT_AMT'] ?? 0),
                    'balance' => self::num($p['EXP_BALANCE'] ?? 0), 'user' => ($p['ENTER_USER_ID'] ?? null) ?: null, 'passed' => ($p['PASS_USER_ID'] ?? null) ?: null,
                    'verify' => ($p['VERIFY_FLAG'] ?? null) ?: null,
                ];
            }
        }

        return $this->chart = array_map('array_values', $by);
    }

    /** @return array{instalments:int,maturity:?string,first_due:?string,instalment:?float,remaining_amount:float,remaining_principal:float} */
    public function terms(array $rows): array
    {
        $amounts = array_column($rows, 'amount');

        return [
            'instalments' => count($rows), 'first_due' => $rows[0]['due'] ?? null, 'maturity' => $rows === [] ? null : end($rows)['due'],
            'instalment' => $rows === [] ? null : round(self::mode($amounts), 2),
            'remaining_amount' => round(array_sum($amounts), 2), 'remaining_principal' => round(array_sum(array_column($rows, 'principal')), 2),
        ];
    }

    /** @return list<array{due_date:string,amount:float,principal:float,interest:float}> */
    public function flows(array $rows): array
    {
        return array_map(fn ($r) => ['due_date' => $r['due'], 'amount' => round($r['amount'], 2), 'principal' => round($r['principal'], 2),
            'interest' => round($r['amount'] - $r['principal'], 2)], $rows);
    }

    private function copyCandidate(string $account, array $old, array $new, string $copies, string $key, string $why): array
    {
        usort($old, fn ($a, $b) => $a['due'] <=> $b['due']);
        usort($new, fn ($a, $b) => $a['due'] <=> $b['due']);
        $to = fn ($rows) => array_map(fn ($r) => ['due' => $r['due'], 'amount' => $r['amount'], 'principal' => 0.0, 'balance' => 0.0], $rows);
        $o = $this->terms($to($old)); $n = $this->terms($to($new));

        return ['detection_key' => $key, 'contract_id' => ltrim($account, '0'), 'account' => $account, 'event_date' => null, 'source' => 'CHART_COPY',
            'source_reference' => "The dated copies of the instalment chart differ: {$copies}", 'suggested_class' => 'UNCERTAIN',
            'suggestion_reason' => sprintf('The chart is in no other extract, and two copies differ (maturity %s to %s, %d to %d instalments, %s to %s in total).',
                $o['maturity'] ?? '-', $n['maturity'] ?? '-', $o['instalments'], $n['instalments'], self::money($o['remaining_amount']), self::money($n['remaining_amount'])),
            'incomplete_reason' => 'No effective date: ' . $why . '.', 'old_terms' => $o, 'new_terms' => $n, 'new_flows' => null];
    }

    /** Two copies hold the same chart: the same instalments on the same dates (definition numbers aside). */
    private function sameChart(array $a, array $b): bool
    {
        $key = fn ($rows) => implode('|', array_map(fn ($r) => $r['due'] . '=' . number_format($r['amount'], 2, '.', ''), (function ($r) { usort($r, fn ($x, $y) => [$x['due'], $x['amount']] <=> [$y['due'], $y['amount']]); return $r; })($rows)));

        return $key($a) === $key($b);
    }

    /** A chart candidate and a log edit on the same account and day are one event: the edit is added to it. */
    private function mergeLog(array $candidates, array $log): array
    {
        $index = [];
        foreach ($candidates as $i => $c) {
            if ($c['event_date'] !== null) {
                $index[$c['account'] . '|' . $c['event_date']] = $i;
            }
        }
        foreach ($log as $l) {
            $k = $l['account'] . '|' . $l['event_date'];
            if ($l['event_date'] !== null && isset($index[$k])) {
                $c = &$candidates[$index[$k]];
                $c['source_reference'] .= '; ' . $l['source_reference'];
                $c['old_terms'] = ($c['old_terms'] ?? []) + ['edits' => $l['old_terms']['edits']];
                $c['new_terms'] = ($c['new_terms'] ?? []) + ['edits' => $l['new_terms']['edits']];
                $c['old_rate'] ??= $l['old_rate']; $c['new_rate'] ??= $l['new_rate'];
                if ($l['suggested_class'] === 'MODIFICATION' && $c['suggested_class'] !== 'MODIFICATION') {
                    $c['suggested_class'] = 'MODIFICATION';
                    $c['suggestion_reason'] = $l['suggestion_reason'] . ' ' . $c['suggestion_reason'];
                }
                $c['incomplete_reason'] ??= $l['incomplete_reason'];
                unset($c);
                continue;
            }
            $candidates[] = $l;
        }

        return $candidates;
    }

    /** @return array{0:string,1:string} the suggestion for a set of term edits */
    private function suggestForEdits(array $edits, ?string $date, ?string $firstDraw, ?string $account = null): array
    {
        if ($date !== null && ($firstDraw === null || $date < $firstDraw)) {
            return ['CORRECTION', 'Edited before the loan\'s first drawdown' . ($firstDraw ? " ({$firstDraw})" : ' (no drawdown on the ledger)') . ': nothing had been lent, so the edit set up the terms rather than changing a loan.'];
        }
        $codes = array_column($edits, 'code');
        $notes = [];
        // the tenor written again in other units (48 months to 0 months and 4 years) is the same tenor
        $tenor = fn (string $side) => (float) ($this->editValue($edits, 'ACLOANMASTER.PERIOD_MONTHS', $side) ?? 0) + 12 * (float) ($this->editValue($edits, 'ACLOANMASTER.PERIOD_YEARS', $side) ?? 0);
        if (array_intersect($codes, ['ACLOANMASTER.PERIOD_MONTHS', 'ACLOANMASTER.PERIOD_YEARS']) !== [] && abs($tenor('old') - $tenor('new')) < 0.5) {
            $codes = array_values(array_diff($codes, ['ACLOANMASTER.PERIOD_MONTHS', 'ACLOANMASTER.PERIOD_YEARS']));
            $notes[] = 'the tenor was written again in other units (' . $tenor('new') . ' months either way)';
        }
        // did the instalments still to come change across the edit? (the chart in force the day before and at the end of the day)
        $chartUnchanged = null;
        if ($date !== null && $account !== null && isset($this->chart()[$account])) {
            $before = array_values(array_filter($this->inForce($this->chart()[$account], self::dayBefore($date)), fn ($r) => $r['due'] > $date && $r['amount'] > 0));
            $chartUnchanged = $before !== [] && $this->flows($before) === $this->flows($this->remainingAt($account, $date));
        }
        foreach ($edits as $e) {
            if ($e['code'] === 'ACLOANMASTER.EXPIRY_DATE') {
                $old = InstalmentChartHistory::date($this->ebankerDate($e['old']));
                $new = InstalmentChartHistory::date($this->ebankerDate($e['new']));
                if ($chartUnchanged === true && array_diff($codes, ['ACLOANMASTER.EXPIRY_DATE']) === []) {
                    return ['CORRECTION', "The maturity field was changed from {$old} to {$new}, but the instalments still to come did not change: a correction of the master record, not of the contract" . ($notes ? '; ' . implode('; ', $notes) : '') . '.'];
                }
                if ($old !== null && $new !== null && $new > $old) {
                    return ['MODIFICATION', "The maturity was extended from {$old} to {$new}" . ($notes ? '; ' . implode('; ', $notes) : '') . '.'];
                }
                $notes[] = "the maturity field moved earlier, from {$old} to {$new}";
            }
        }
        if (array_intersect($codes, ['ACLOANMASTER.PMOROTORIUM_PERIOD', 'ACLOANMASTER.IMOROTORIUM_PERIOD', 'ACLOANMASTER.INST_FREQ']) !== []) {
            return ['MODIFICATION', 'A moratorium or the instalment frequency was changed after the loan was drawn' . ($notes ? '; ' . implode('; ', $notes) : '') . '.'];
        }
        if (in_array('ACMASTER.INTEREST_POLICY', $codes, true)) {
            $p = $this->editValue($edits, 'ACMASTER.INTEREST_POLICY', 'old') . ' to ' . $this->editValue($edits, 'ACMASTER.INTEREST_POLICY', 'new');
            return ['UNCERTAIN', "The interest policy was changed ({$p}; M manual, P linked to the PLR): a correction of the code if the contract was always PLR-linked, a modification if the rate basis changed (review item R14, O17)" . ($notes ? '; ' . implode('; ', $notes) : '') . '.'];
        }
        if (in_array('ACLOANMASTER.SANCTION_AMOUNT', $codes, true)) {
            return ['UNCERTAIN', 'The sanctioned amount was changed: further money lent is a new advance, not a modification, unless the existing terms changed with it' . ($notes ? '; ' . implode('; ', $notes) : '') . '.'];
        }
        if (in_array('ACMASTER.INTEREST_RATE', $codes, true)) {
            return ['UNCERTAIN', 'The rate on the account master was edited; the rate E-Banker charged each month is read from the loan book and every move of it is on the rate-reset register' . ($notes ? '; ' . implode('; ', $notes) : '') . '.'];
        }
        if ($codes === [] || $chartUnchanged === true) {
            return ['CORRECTION', 'Nothing in the edit changes the loan\'s cash flows: ' . ($notes ? implode('; ', $notes) : 'the instalments still to come did not change') . '.'];
        }

        return ['UNCERTAIN', 'A term of the loan was edited after it was drawn; the edit alone does not say whether the contract changed' . ($notes ? '; ' . implode('; ', $notes) : '') . '.'];
    }

    /** The maturity of the chart in force today (the live extract), or of the last chart on record. */
    private function latestMaturity(string $account): ?string
    {
        // the instalments written at the latest regeneration: a version left in force beside it keeps
        // its own later dates for a while (Sycamore Credit), so the newest set is the chart's maturity
        $rows = array_filter($this->chart()[$account] ?? [], fn ($r) => $r['amount'] > 0 && $r['created'] !== null);
        if ($rows === []) {
            return null;
        }
        $last = max(array_column($rows, 'created'));

        return max(array_column(array_filter($rows, fn ($r) => $r['created'] === $last), 'due'));
    }

    private function editValue(array $edits, string $code, string $side): ?string
    {
        foreach ($edits as $e) {
            if ($e['code'] === $code && trim($e[$side]) !== '') {
                return trim($e[$side]);
            }
        }

        return null;
    }

    /** E-Banker's log writes dates as 24-FEB-25 or m/d/Y. */
    private function ebankerDate(?string $v): string
    {
        $v = trim((string) $v);
        $d = \DateTimeImmutable::createFromFormat('!d-M-y', ucfirst(strtolower($v)));

        return $d !== false ? $d->format('Y-m-d') : $v;
    }

    /** Everything the ledger says the borrower owes at the end of a date (drawn and charged, less repaid), or null with no posting. */
    private function ledgerBalance(string $account, string $date): ?float
    {
        $rows = $this->ledgerOf($account, $date);

        return $rows === [] ? null : -array_sum(array_map(fn ($p) => self::num($p['payload']['TRANSAMT'] ?? 0), $rows));
    }

    /** Money advanced to the loan (drawdowns) after one date and up to another. */
    private function advanced(string $account, ?string $after, string $upTo): float
    {
        $sum = 0.0;
        foreach ($this->ledgerOf($account, $upTo) as $p) {
            if ($after !== null && $p['row_date'] <= $after) {
                continue;
            }
            $amount = self::num($p['payload']['TRANSAMT'] ?? 0);
            if (in_array((string) ($p['payload']['TRANTYPE'] ?? ''), LoanBookBuildService::TYPE_DISBURSEMENT, true) && $amount < 0) {
                $sum += -$amount;
            }
        }

        return $sum;
    }

    private function firstDrawdown(string $account): ?string
    {
        foreach ($this->ledgerOf($account, null) as $p) {
            if (in_array((string) ($p['payload']['TRANTYPE'] ?? ''), LoanBookBuildService::TYPE_DISBURSEMENT, true) && self::num($p['payload']['TRANSAMT'] ?? 0) < 0) {
                return (string) $p['row_date'];
            }
        }

        return null;
    }

    /**
     * One account's ledger to a date, as ledgerByAccount(date, account) returns it, from the ledger
     * read once: the detector asks at every regeneration of every chart, and a query each time
     * (some 1,500) made bootstrap step 5i take about 30 minutes.
     *
     * @return list<array>
     */
    private function ledgerOf(string $account, ?string $toDate): array
    {
        $this->ledger ??= $this->zone->ledgerReader();

        return ($this->ledger)($account, $toDate);
    }

    /** @return array<string,true> the dates the PLR changed (P2_06) */
    private function plrDates(): array
    {
        $out = [];
        foreach ($this->zone->family(['P2_06']) as $row) {
            if (($row['payload']['DELETE_FLAG'] ?? 'N') === 'Y') {
                continue;
            }
            foreach (['APPLI_FROM_DATE', 'TRAN_DATE'] as $f) {
                if ($d = InstalmentChartHistory::date((string) ($row['payload'][$f] ?? ''))) {
                    $out[$d] = true;
                }
            }
        }

        return $out;
    }

    /** @return array<string, array<string,true>> the dates each account's rate was keyed (P1_04) */
    private function keyedRateDates(): array
    {
        $out = [];
        foreach ($this->zone->rateSetupByAccount() as $account => $rows) {
            foreach ($rows as $row) {
                foreach (['APPLICABLE_FROM_DATE', 'TRAN_DATE'] as $f) {
                    if ($d = InstalmentChartHistory::date((string) ($row['payload'][$f] ?? ''))) {
                        $out[$account][$d] = true;
                    }
                }
            }
        }

        return $out;
    }

    /** The instalment period of a chart in months: the most common gap between its due dates. */
    private function stepMonths(array $rows): int
    {
        $gaps = [];
        for ($i = 1; $i < count($rows); $i++) {
            $gaps[] = max(1, self::months($rows[$i - 1]['due'], $rows[$i]['due']));
        }

        return $gaps === [] ? 1 : (int) self::mode($gaps);
    }

    /** @var array<string,string>|null */
    private ?array $names = null;

    private function names(): array
    {
        return $this->names ??= DB::table('loan_books')->whereNotNull('customer_name')->orderBy('reporting_period')->pluck('customer_name', 'contract_id')
            ->mapWithKeys(fn ($n, $c) => [(string) $c => $n])->all();
    }

    private function row(array $c): array
    {
        $json = fn ($v) => $v === null ? null : json_encode($v);

        return [
            'detection_key' => $c['detection_key'], 'contract_id' => $c['contract_id'], 'account' => $c['account'] ?? null, 'customer_name' => $c['customer_name'] ?? null,
            'event_date' => $c['event_date'], 'source' => $c['source'], 'source_reference' => mb_substr((string) ($c['source_reference'] ?? ''), 0, 500),
            'suggested_class' => $c['suggested_class'], 'suggestion_reason' => mb_substr((string) ($c['suggestion_reason'] ?? ''), 0, 1000),
            'old_maturity' => $c['old_maturity'] ?? ($c['old_terms']['maturity'] ?? null), 'new_maturity' => $c['new_maturity'] ?? ($c['new_terms']['maturity'] ?? null),
            'old_instalments' => $c['old_terms']['instalments'] ?? null, 'new_instalments' => $c['new_terms']['instalments'] ?? null,
            'old_instalment' => $c['old_terms']['instalment'] ?? null, 'new_instalment' => $c['new_terms']['instalment'] ?? null,
            'old_balance' => $c['old_balance'] ?? null, 'new_balance' => $c['new_balance'] ?? null, 'capitalised_amount' => $c['capitalised_amount'] ?? null,
            'advanced_in_window' => $c['advanced_in_window'] ?? null,
            'old_rate' => is_numeric($c['old_rate'] ?? null) ? (float) $c['old_rate'] : null, 'new_rate' => is_numeric($c['new_rate'] ?? null) ? (float) $c['new_rate'] : null,
            'old_terms' => $json($c['old_terms'] ?? null), 'new_terms' => $json($c['new_terms'] ?? null), 'new_flows' => $json($c['new_flows'] ?? null),
            'ebanker_entered_by' => $c['ebanker_entered_by'] ?? null, 'ebanker_passed_by' => $c['ebanker_passed_by'] ?? null, 'ebanker_flag' => $c['ebanker_flag'] ?? null,
            'incomplete_reason' => $c['incomplete_reason'] ?? null,
        ];
    }

    public static function months(?string $a, ?string $b): int
    {
        if ($a === null || $b === null) {
            return 0;
        }
        $x = CarbonImmutable::parse($a); $y = CarbonImmutable::parse($b);

        return ($y->year - $x->year) * 12 + ($y->month - $x->month);
    }

    public static function dayBefore(string $date): string
    {
        return CarbonImmutable::parse($date)->subDay()->toDateString();
    }

    private static function mode(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }
        $counts = array_count_values(array_map(fn ($v) => number_format((float) $v, 2, '.', ''), $values));
        arsort($counts);

        return (float) array_key_first($counts);
    }

    public static function num(mixed $v): float
    {
        return (float) str_replace(',', '', (string) ($v ?? 0));
    }

    private static function money(float $v): string
    {
        return 'MWK ' . number_format($v, 2);
    }
}
