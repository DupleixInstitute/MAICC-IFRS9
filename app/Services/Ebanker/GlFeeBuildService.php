<?php

namespace App\Services\Ebanker;

use App\Services\AuditLoggerService;
use App\Services\Eir\FeeGlReconciliationService;
use App\Services\Eir\FeeImportService;
use App\Services\Eir\FeeSourcePrecedence;
use App\Services\Eir\TrialBalanceMovementService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The GL fee route (spec v4 sections 7.8.3 to 7.8.5; results review of 13
 * October 2026, E3).
 *
 * The fees MAIIC charges at drawdown are credited to 4871 and 4873 (4874 and
 * 4875 for insurance and guarantee income) in the drawdown batch. About half
 * of them have a matching fee debit on the loan account; the rest were
 * credited in a transfer batch with no leg on any loan (spec 3.7), so they
 * never reach the loan's ledger and, until this route, never reached the EIR:
 * 180.8 million of 2025 fees on eight loans were missing, and six of those
 * loans were locked at an EIR with no fee.
 *
 * GL_04a carries every posting to the fee GLs and GL_04b every leg of the
 * same batches. The build joins them on the batch (transaction date, batch
 * type and batch number) and writes one fee line per fee posting into
 * contract_fees with the source "E-Banker GL fee posting":
 *
 *  - one loan account in the batch: the fee is that loan's;
 *  - several: the loan whose own debit in the batch is the fee's amount, then
 *    the loan whose account name the fee's narration carries; anything else
 *    is held, never guessed;
 *  - none, but the batch clears money out of GL 2010 (Working Capital Loan)
 *    and GL_07 (pack 5) shows exactly one loan drawn into 2010 by that same
 *    amount on or before the fee's date: the fee is that loan's. In October
 *    2024 a drawdown was posted loan debit / 2010 credit, then a second batch
 *    paid 2010 out to the bank net of the fee and credited the fee GL (pack 5
 *    results review, RUN 3: Mafeni 4,250,000; Agwenda 1,205,000 and
 *    1,500,000). The link is E-Banker's own postings, never a name or amount
 *    guess elsewhere;
 *  - none, or a Mega Farm programme loan (outside the EIR, decision D30): held
 *    in fee_gl_unassigned with the reason, never dropped.
 *
 * A reversal on a fee GL (a debit) is kept as its own negative line. Every
 * line lands PENDING: a person classifies and reviews it before it can affect
 * an EIR, exactly as for every other source. The governed precedence then
 * marks the lower sources' lines of the same contract superseded, so a fee in
 * both GL_04 and the charges table is counted once.
 *
 * Two checks come back with it: assignment completeness (every fee posting is
 * on a loan or held, with the totals) and the fee GL gate (the month's
 * movement of each fee GL in GL_04a against the trial balance).
 */
class GlFeeBuildService
{
    public const SOURCE = FeeSourcePrecedence::SOURCE_GL;

    /** The fee income GLs (FeeGlReconciliationService::FEE_GLS). */
    public const FEE_GLS = FeeGlReconciliationService::FEE_GLS;

    public const GL_FEE_LEGS = ['GL_04a'];
    public const GL_FEE_BATCHES = ['GL_04b'];
    /** Every leg of the batches touching GL 2010 since 2024 (pack 5 RUN 3b). */
    public const GL_2010_BATCHES = ['GL_07'];
    /** The clearing GL a drawdown passed through before the bank paid it out net of the fee. */
    public const CLEARING_GL = '2010';
    public const RULE_CLEARING = 'the loan drawn through GL 2010 that the batch clears (GL_07)';

    /** The fee type each fee GL stands for: 4874 is insurance income, 4875 guarantee fees. */
    private const TYPE_BY_GL = ['4873' => 'arrangement', '4871' => 'legal', '4874' => 'other', '4875' => 'other'];

    public function __construct(
        private LandingZoneReader $zone,
        private FeeImportService $fees,
        private FeeSourcePrecedence $precedence,
        private TrialBalanceMovementService $movements,
    ) {
    }

    /**
     * @return array{legs:int, assigned:array, held:array, loaded:array, precedence:array, gate:array}
     */
    public function build(?int $userId = null): array
    {
        $legs = $this->feeLegs();
        $batches = $this->batches();
        $names = $this->accountNames();

        $rows = []; $held = []; $assigned = ['lines' => 0, 'amount' => 0.0, 'by_rule' => []];
        // a held line a person has since tied to its loan is in contract_fees already and stays there
        $tiedByHand = DB::table('contract_fees')->where('source_system', self::SOURCE)->where('external_transaction_id', 'like', 'GL_04a:%')
            ->pluck('external_transaction_id')->flip()->all();
        $drawnOn = $this->drawdownsByDate();
        $cleared = $this->clearedThrough2010();
        foreach ($legs as $leg) {
            [$contract, $rule, $reason, $loans] = $this->assign($leg, $batches[$leg['batch']] ?? [], $names, $cleared[$leg['batch']] ?? null);
            if ($rule === self::RULE_CLEARING && isset($tiedByHand['GL_04a:' . $leg['voucher']])) {
                continue; // a person tied it before the GL 2010 route existed: that tie stands and the line is not loaded twice
            }
            if ($contract === null) {
                if (isset($tiedByHand['GL_04a:' . $leg['voucher']])) {
                    continue;
                }
                $held[] = $leg + ['reason' => $reason, 'loans' => $loans] + $this->candidate($leg, $drawnOn[$leg['date']] ?? [], $names);
                continue;
            }
            $assigned['lines']++;
            $assigned['amount'] += $leg['amount'];
            $assigned['by_rule'][$rule] = ($assigned['by_rule'][$rule] ?? 0) + 1;
            $rows[] = [
                'contract_id' => $contract,
                'fee_type' => self::TYPE_BY_GL[$leg['gl']],
                'amount' => $leg['amount'],
                'transaction_date' => $leg['date'],
                'cashflow_direction' => 'RECEIVED',
                'currency' => 'MWK',
                'description' => mb_substr(trim(($leg['narration'] ?: 'Fee') . ' (GL ' . $leg['gl'] . ', ' . $rule . ')'), 0, 199),
                'source_system' => self::SOURCE,
                'source_reference' => mb_substr('batch ' . $leg['batch'] . ' voucher ' . $leg['voucher'], 0, 199),
                'external_transaction_id' => 'GL_04a:' . $leg['voucher'],
                'gl_account_ref' => $leg['gl'],
            ];
        }

        $loaded = DB::transaction(function () use ($rows, $held) {
            $result = $rows === [] ? ['loaded_rows' => 0, 'skipped_rows' => 0] : $this->fees->import($rows);
            // the held lines are rewritten from the zone each time; a line now assigned leaves the list
            DB::table('fee_gl_unassigned')->delete();
            $now = now();
            foreach (array_chunk($held, 200) as $chunk) {
                DB::table('fee_gl_unassigned')->insert(array_map(fn ($h) => [
                    'external_transaction_id' => 'GL_04a:' . $h['voucher'], 'transaction_date' => $h['date'], 'batch' => $h['batch'],
                    'gl_code' => $h['gl'], 'amount' => $h['amount'], 'narration' => mb_substr((string) $h['narration'], 0, 255),
                    'reason' => mb_substr($h['reason'], 0, 500), 'loans_in_batch' => json_encode($h['loans']),
                    'candidate_contract_id' => $h['candidate'], 'candidate_evidence' => $h['candidate_evidence'] === null ? null : mb_substr($h['candidate_evidence'], 0, 255),
                    'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }

            return $result;
        });

        $precedence = $this->precedence->apply($userId);
        $out = [
            'legs' => count($legs),
            'assigned' => ['lines' => $assigned['lines'], 'amount' => round($assigned['amount'], 2), 'by_rule' => $assigned['by_rule']],
            'held' => ['lines' => count($held), 'amount' => round(array_sum(array_column($held, 'amount')), 2),
                'with_a_candidate' => count(array_filter($held, fn ($h) => $h['candidate'] !== null)),
                'by_reason' => array_count_values(array_map(fn ($h) => strtok($h['reason'], ':'), $held))],
            'loaded' => $loaded,
            'precedence' => $precedence,
            'gate' => $this->gate($legs),
        ];
        AuditLoggerService::log('GL Fee Postings Built', 'contract_fees', null, ['new_values' => array_diff_key($out, ['gate' => 1, 'precedence' => 1]) + [
            'gate_months_with_difference' => count(array_filter($out['gate']['months'], fn ($m) => $m['status'] === 'DIFFERENCE')),
            'precedence_superseded' => $precedence['superseded_lines'], 'cross_source_exceptions' => count($precedence['exceptions']),
        ], 'meta' => ['user' => $userId]]);

        return $out;
    }

    /**
     * The loan-account vouchers that are a fee debit: a type-301 leg on a loan
     * in a batch whose only other legs are fee GL credits of the same total
     * (the DISB pairs of 2025: loan debit 30,000,000, 4873 credit 30,000,000).
     * The disbursement builder adds such a debit to its drawdown instead of
     * making it a tranche of its own (spec 7.8.4).
     *
     * @return array<string, array{gl:string, batch:string}>
     */
    public function feeDebitVouchers(): array
    {
        $out = [];
        foreach ($this->batches() as $batch => $legs) {
            $loanLegs = array_filter($legs, fn ($l) => isset(LandingZoneReader::LOAN_GLS[$l['gl']]));
            $feeLegs = array_filter($legs, fn ($l) => in_array($l['gl'], self::FEE_GLS, true));
            if ($loanLegs === [] || $feeLegs === [] || count($loanLegs) + count($feeLegs) !== count($legs)) {
                continue;
            }
            $loanTotal = -array_sum(array_column($loanLegs, 'amount'));
            $feeTotal = array_sum(array_column($feeLegs, 'amount'));
            if (abs($loanTotal - $feeTotal) >= 0.01) {
                continue;
            }
            foreach ($loanLegs as $l) {
                if ($l['type'] === '301') {
                    $out[$l['voucher']] = ['gl' => implode('+', array_unique(array_column($feeLegs, 'gl'))), 'batch' => $batch];
                }
            }
        }

        return $out;
    }

    /**
     * Tie a held fee line to its loan: a person's act, with the reason, after
     * reading the batch and the narration (Fee Classification). The line moves
     * into contract_fees PENDING, so it still needs its classification and
     * review before it can affect an EIR; the precedence is applied again.
     */
    public function assignHeld(int $heldId, string $contractId, int $userId, string $reason): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new \InvalidArgumentException('Say why the fee belongs to this loan (at least 10 characters).');
        }
        $held = DB::table('fee_gl_unassigned')->where('id', $heldId)->first();
        if ($held === null) {
            throw new \InvalidArgumentException("No held fee line {$heldId}.");
        }
        $contractId = ltrim(trim($contractId), '0');
        if (! DB::table('contract_eir')->where('contract_id', $contractId)->exists() && ! DB::table('loan_books')->where('contract_id', $contractId)->exists()) {
            throw new \InvalidArgumentException("Loan {$contractId} is not in the contract master or the loan book.");
        }
        $result = DB::transaction(function () use ($held, $contractId, $userId, $reason) {
            $r = $this->fees->import([[
                'contract_id' => $contractId, 'fee_type' => self::TYPE_BY_GL[$held->gl_code] ?? 'other', 'amount' => (float) $held->amount,
                'transaction_date' => $held->transaction_date, 'cashflow_direction' => 'RECEIVED', 'currency' => 'MWK',
                'description' => mb_substr(trim(($held->narration ?: 'Fee') . ' (GL ' . $held->gl_code . ', tied by hand: ' . $reason . ')'), 0, 199),
                'source_system' => self::SOURCE, 'source_reference' => mb_substr('batch ' . $held->batch, 0, 199),
                'external_transaction_id' => $held->external_transaction_id, 'gl_account_ref' => $held->gl_code,
            ]]);
            DB::table('fee_gl_unassigned')->where('id', $held->id)->delete();
            AuditLoggerService::log('GL Fee Tied To Loan', 'contract_fees', null, ['new_values' => ['contract_id' => $contractId, 'external_transaction_id' => $held->external_transaction_id,
                'amount' => (float) $held->amount, 'reason' => $reason, 'candidate_was' => $held->candidate_contract_id], 'meta' => ['user' => $userId]]);

            return $r;
        });

        return $result + ['precedence' => $this->precedence->apply($userId)];
    }

    /**
     * The loan a held line points to, for the person who ties it: a loan whose
     * account name (in full) the narration carries and which drew money on the
     * fee's date. Exactly one such loan is a candidate; it is shown, not used.
     *
     * @param  list<string>  $drawnThatDay  loan accounts with a type-301 posting on the fee's date
     * @return array{candidate:?string, candidate_evidence:?string}
     */
    private function candidate(array $leg, array $drawnThatDay, array $names): array
    {
        $narration = $this->normalise((string) $leg['narration']);
        $matches = array_values(array_filter(array_unique($drawnThatDay), fn ($a) => ($names[$a] ?? '') !== '' && str_contains($narration, $this->normalise($names[$a]))));
        if (count($matches) !== 1) {
            return ['candidate' => null, 'candidate_evidence' => null];
        }

        return ['candidate' => ltrim($matches[0], '0'), 'candidate_evidence' => sprintf('the narration carries the account name "%s" and the loan drew on %s', $names[$matches[0]], $leg['date'])];
    }

    /** @return array<string, list<string>> loan accounts with a type-301 posting, by date */
    private function drawdownsByDate(): array
    {
        $out = [];
        foreach ($this->zone->ledgerByAccount() as $account => $posts) {
            foreach ($posts as $p) {
                if ((string) ($p['payload']['TRANTYPE'] ?? '') === '301' && $p['row_date'] !== null) {
                    $out[substr((string) $p['row_date'], 0, 10)][] = (string) $account;
                }
            }
        }

        return $out;
    }

    /**
     * The fee batches that pay money out of GL 2010, each with the one loan whose drawdown put that
     * money into 2010 (GL_07). A fee batch's 2010 debit of X is matched to a batch dated on or before
     * it with a 2010 credit of X and a debit of X on exactly one in-scope loan account; a batch with
     * no such drawdown, or with more than one candidate loan, gets no loan and its fee stays held.
     *
     * @return array<string, array{account:?string, reason:?string}> keyed by fee batch
     */
    private function clearedThrough2010(): array
    {
        $byBatch = [];
        foreach ($this->zone->family(self::GL_2010_BATCHES) as $r) {
            $leg = self::leg($r);
            if ($leg !== null) {
                $byBatch[$leg['batch']][] = $leg;
            }
        }
        $out = [];
        foreach ($byBatch as $batch => $legs) {
            $paidOut = array_filter($legs, fn ($l) => $l['gl'] === self::CLEARING_GL && $l['amount'] < 0);
            if ($paidOut === [] || array_filter($legs, fn ($l) => in_array($l['gl'], self::FEE_GLS, true)) === []) {
                continue;
            }
            $date = $legs[0]['date'];
            $loans = [];
            foreach ($paidOut as $out2010) {
                $x = -$out2010['amount'];
                foreach ($byBatch as $other => $olegs) {
                    if ($other === $batch || $olegs[0]['date'] > $date) {
                        continue;
                    }
                    $into = array_filter($olegs, fn ($l) => $l['gl'] === self::CLEARING_GL && abs($l['amount'] - $x) < 0.01);
                    $drawn = array_filter($olegs, fn ($l) => isset(LandingZoneReader::LOAN_GLS[$l['gl']]) && abs(-$l['amount'] - $x) < 0.01);
                    if ($into !== [] && $drawn !== []) {
                        foreach ($drawn as $d) {
                            $loans[$d['account']] = $other;
                        }
                    }
                }
            }
            $out[$batch] = count($loans) === 1
                ? ['account' => (string) array_key_first($loans), 'reason' => null]
                : ['account' => null, 'reason' => $loans === [] ? 'the batch pays money out of GL 2010 but no loan drawdown into 2010 of that amount is in GL_07' : count($loans) . ' loans were drawn into GL 2010 by that amount; held, not guessed'];
        }

        return $out;
    }

    /** @return array{0:?string, 1:?string, 2:?string, 3:list<string>} the contract, the rule, the reason it is held, the loans in the batch */
    private function assign(array $leg, array $batchLegs, array $names, ?array $cleared = null): array
    {
        $loanLegs = []; $programme = [];
        foreach ($batchLegs as $l) {
            if (isset(LandingZoneReader::LOAN_GLS[$l['gl']])) {
                $loanLegs[] = $l;
            } elseif (in_array($l['gl'], \App\Services\MegaFarm\MegaFarmEclService::GLS, true)) {
                $programme[] = $l['account'];
            }
        }
        $loans = array_values(array_unique(array_column($loanLegs, 'account')));
        if ($loans === [] && $programme === [] && $cleared !== null) {
            if ($cleared['account'] !== null) {
                return [ltrim($cleared['account'], '0'), self::RULE_CLEARING, null, [$cleared['account']]];
            }

            return [null, null, 'NO_LOAN_IN_BATCH: no loan account is a leg of the batch, and ' . $cleared['reason'], []];
        }
        if ($loans === []) {
            return $programme !== []
                ? [null, null, 'MEGA_FARM: the only loan in the batch is a Mega Farm programme account, outside the EIR (decision D30)', array_values(array_unique($programme))]
                : [null, null, 'NO_LOAN_IN_BATCH: no loan account is a leg of the batch (a fee credited by transfer, spec 3.7); held until a person ties it to its loan', []];
        }
        if (count($loans) === 1) {
            return [ltrim($loans[0], '0'), 'one loan in the batch', null, $loans];
        }
        // several loans: the loan whose own debit in the batch is the fee's amount
        $byAmount = array_values(array_unique(array_column(array_filter($loanLegs, fn ($l) => $l['amount'] < 0 && abs(-$l['amount'] - abs($leg['amount'])) < 0.01), 'account')));
        if (count($byAmount) === 1) {
            return [ltrim($byAmount[0], '0'), 'the loan\'s own debit of the same amount', null, $loans];
        }
        // then the loan whose account name the narration carries, in full
        $narration = $this->normalise((string) $leg['narration']);
        $byName = array_values(array_filter($loans, fn ($a) => ($names[$a] ?? '') !== '' && str_contains($narration, $this->normalise($names[$a]))));
        if (count($byName) === 1) {
            return [ltrim($byName[0], '0'), 'the account name in the narration', null, $loans];
        }

        return [null, null, 'SEVERAL_LOANS: ' . count($loans) . ' loans in the batch and neither an equal debit nor the narration names one of them; held, not guessed', $loans];
    }

    /**
     * The fee GL gate (7.8.5): each month's movement on each fee GL in GL_04a
     * against the trial balance's, inside the reconciliation band. Year-end
     * closing vouchers are not movement and are left out, as the trial
     * balance reads December pre-closing.
     */
    public function gate(array $legs): array
    {
        $byMonth = [];
        foreach ($legs as $l) {
            $byMonth[$l['gl']][substr((string) $l['date'], 0, 7)] = ($byMonth[$l['gl']][substr((string) $l['date'], 0, 7)] ?? 0.0) + $l['amount'];
        }
        $months = [];
        foreach ($byMonth as $gl => $periods) {
            ksort($periods);
            foreach ($periods as $ym => $amount) {
                $mv = $this->movements->movement((string) $gl, $ym . '-01');
                $tb = $mv['movement'];
                $months[] = ['gl' => (string) $gl, 'month' => $ym, 'gl_04a' => round($amount, 2), 'trial_balance' => $tb,
                    'difference' => $tb === null ? null : round($amount - $tb, 2),
                    'status' => $tb === null ? 'NO_TB' : (abs($amount - $tb) < 1.0 ? 'AGREES' : 'DIFFERENCE')];
            }
        }

        return ['months' => $months, 'agree' => count(array_filter($months, fn ($m) => $m['status'] === 'AGREES')),
            'differ' => count(array_filter($months, fn ($m) => $m['status'] === 'DIFFERENCE')), 'no_tb' => count(array_filter($months, fn ($m) => $m['status'] === 'NO_TB'))];
    }

    /** The posted fee legs of GL_04a, closing vouchers and deleted rows left out. */
    private function feeLegs(): array
    {
        $out = [];
        foreach ($this->zone->family(self::GL_FEE_LEGS) as $r) {
            $leg = self::leg($r);
            if (! self::isGateLeg($leg)) {
                continue;
            }
            $out[] = $leg;
        }
        usort($out, fn ($a, $b) => [$a['date'], (int) $a['voucher']] <=> [$b['date'], (int) $b['voucher']]);

        return $out;
    }

    /** Every posted leg of the fee batches, grouped by batch. */
    private function batches(): array
    {
        $out = [];
        foreach ($this->zone->family(self::GL_FEE_BATCHES) as $r) {
            $leg = $this->leg($r);
            if ($leg !== null) {
                $out[$leg['batch']][] = $leg;
            }
        }

        return $out;
    }

    /** A posted fee leg the gate counts: on a fee GL, not a year-end closing voucher (R04). */
    public static function isGateLeg(?array $leg): bool
    {
        return $leg !== null && in_array($leg['gl'], self::FEE_GLS, true) && $leg['batch_type'] !== 'YRCLO' && ! in_array($leg['type'], ['800', '801'], true);
    }

    /** One landed row as a leg ({source_key, row_date, account, payload}); null for a deleted, unpassed, undated or nil row. */
    public static function leg(array $r): ?array
    {
        $p = $r['payload'];
        if (strtoupper((string) ($p['DELETE_FLAG'] ?? 'N')) === 'Y' || strtoupper((string) ($p['PASS_FLAG'] ?? 'Y')) === 'N' || $r['row_date'] === null) {
            return null;
        }
        $amount = str_replace([',', ' '], '', (string) ($p['TRANSAMT'] ?? ''));
        if (! is_numeric($amount) || (float) $amount == 0.0) {
            return null;
        }
        $date = CarbonImmutable::parse($r['row_date'])->toDateString();
        $batchType = trim((string) ($p['BATCH_TYPE'] ?? ''));

        return [
            'voucher' => (string) ($p['CUMVOUCH_DET_ID'] ?? $r['source_key']),
            'date' => $date,
            'batch' => $date . ' ' . $batchType . ' ' . trim((string) ($p['BATCH_NUMBER'] ?? '')),
            'batch_type' => $batchType,
            'gl' => trim((string) ($p['AC_GLCODE'] ?? ($p['CB_GLCODE'] ?? ''))),
            'account' => (string) ($p['NEW_AC_NUMBER'] ?? $r['account']),
            'type' => trim((string) ($p['TRANTYPE'] ?? '')),
            // a credit to a fee GL is positive in E-Banker's sign, a reversal negative: kept as received, never ABS'd
            'amount' => round((float) $amount, 2),
            'narration' => trim((string) ($p['PARTICULARS'] ?? '')),
        ];
    }

    /** @return array<string,string> account names from the account master */
    private function accountNames(): array
    {
        $out = [];
        foreach ($this->zone->accountMaster() as $account => $a) {
            $out[(string) $account] = trim((string) ($a['ACCOUNT_NAME'] ?? ''));
        }

        return $out;
    }

    private function normalise(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower(preg_replace("/[^A-Za-z0-9' ]+/", ' ', $text))));
    }
}
