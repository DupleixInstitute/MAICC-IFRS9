<?php

namespace App\Services\Ebanker;

use App\Services\Eir\ContractMasterImportService;
use App\Services\Eir\ContractTransactionImportService;
use App\Services\Eir\FeeImportService;
use App\Services\Eir\GlInterestImportService;
use App\Services\Eir\ReferenceRateImportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The EIR engine's inputs built from the landing zone (spec v4 section 7):
 * the contract master from the account and loan masters with the latest
 * stored run (7.3), the PLR series from the PLR master (7.2), the
 * origination fees from the disbursement charges (7.4), and the interest
 * posted per account and month from the ledger (7.1, which retires Extract
 * C). Each method hands rows to the importer that already exists, in the
 * field names it reads, so every rule those importers enforce still applies.
 */
class ContractInputsBuildService
{
    private ?WriteOffClassifier $writeOffClassifier = null;

    private function writeOffs(): WriteOffClassifier
    {
        return $this->writeOffClassifier ??= new WriteOffClassifier($this->zone);
    }

    public function __construct(
        private LandingZoneReader $zone,
        private ContractMasterImportService $contracts,
        private ReferenceRateImportService $rates,
        private FeeImportService $fees,
        private GlInterestImportService $glInterest,
        private ContractTransactionImportService $transactions,
    ) {
    }

    /** @return array{rows:int,result:array} */
    public function contractMaster(): array
    {
        $master = $this->zone->accountMaster();
        $loans = $this->zone->loanMaster();
        $schemes = [];
        foreach ($this->zone->family(['DD_09']) as $r) {
            $schemes[(string) ($r['payload']['SCHEME_MST_ID'] ?? '')] = trim((string) ($r['payload']['SCHEME_NAME'] ?? ''));
        }
        $latest = [];
        foreach ($this->zone->runMonthEnds() as $monthEnd) {
            foreach ($this->zone->latestRunsOn($monthEnd) as $account => $run) {
                $latest[$account] = $run['payload'];
            }
        }
        $rows = [];
        foreach ($master as $account => $a) {
            $l = $loans[$account] ?? [];
            $b = $latest[$account] ?? [];
            $gl = trim((string) ($a['GLCODE'] ?? ($b['GLCODE'] ?? '')));
            if (! isset(LandingZoneReader::LOAN_GLS[$gl])) {
                continue;
            }
            $policy = trim((string) ($a['INTEREST_POLICY'] ?? ''));
            $months = (int) round($this->num($l['PERIOD_YEARS'] ?? 0) * 12 + $this->num($l['PERIOD_MONTHS'] ?? 0));
            if ($months === 0 && isset($b['TENOR_YRS'])) {
                $months = (int) round($this->num($b['TENOR_YRS']) * 12);
            }
            $rate = $this->num($b['INTEREST_RATE'] ?? ($a['INTEREST_RATE'] ?? 0));
            $rows[] = [
                'contract_id' => $account, 'customer_id' => trim((string) ($a['CUSTOMER_ID'] ?? '')), 'customer_name' => $a['ACCOUNT_NAME'] ?? null,
                'gl_account_code' => $gl, 'product_type' => $schemes[(string) ($a['SCHEME_MST_ID'] ?? '')] ?? ('Scheme ' . ($a['SCHEME_MST_ID'] ?? '?')),
                'portfolio' => LandingZoneReader::LOAN_GLS[$gl][1], 'currency' => 'MWK',
                'origination_date' => $this->date($b['VALUE_DATE'] ?? '') ?? $this->date($a['ACCOUNT_OPEN_DATE'] ?? ''),
                'maturity_date' => $this->date($b['MATURITY_DATE'] ?? '') ?? $this->date($l['EXPIRY_DATE'] ?? ''),
                'approved_amount' => $this->num($l['SANCTION_AMOUNT'] ?? ($b['APPROVED'] ?? 0)), 'drawn_amount' => $this->num($b['DISBURSD'] ?? 0),
                'contractual_rate' => $rate > 1 ? $rate / 100 : $rate, 'rate_type' => $policy === 'P' ? 'FLOATING' : 'FIXED',
                'repayment_frequency' => match (strtoupper(trim((string) ($l['INST_FREQ'] ?? 'M')))) { 'M' => 'MONTHLY', 'Q' => 'QUARTERLY', 'H' => 'HALF-YEARLY', 'Y', 'A' => 'ANNUAL', default => 'MONTHLY' },
                'tenor_months' => $months, 'moratorium_months' => (int) $this->num($l['PMOROTORIUM_PERIOD'] ?? 0),
                // The loan master states the moratorium in two numbers, not a word:
                // PMOROTORIUM_PERIOD (months without principal) and IMOROTORIUM_PERIOD
                // (months without interest). Interest deferred as well as principal is
                // E-Banker's "Both (Interest + Principle)", capitalising; principal
                // deferred and interest paid is "Principle Only". 51 contracts had
                // been refused a schedule for "a moratorium with no type stated"
                // (system audit of 9 October 2026, section 5); the three signed offer
                // letters that state a moratorium all read "principal and interest",
                // which is what their P and I months say here. Barry asked to confirm.
                'moratorium_type' => (int) $this->num($l['PMOROTORIUM_PERIOD'] ?? 0) <= 0 ? null
                    : ((int) $this->num($l['IMOROTORIUM_PERIOD'] ?? 0) > 0 ? 'Both (Interest + Principle)' : 'Principle Only'),
                'scheme_code' => $a['SCHEME_MST_ID'] ?? null, 'interest_policy' => $policy ?: null, 'floating_flag' => $a['FLOATING_FLAG'] ?? null,
                'interest_start_date' => $this->date($b['VALUE_DATE'] ?? ''), 'account_status_code' => $a['STATUS_CODE'] ?? null,
                'sub_account_no' => $a['SUB_AC_NUMBER'] ?? null,
            ];
        }

        return ['rows' => count($rows), 'result' => $this->contracts->import($rows)];
    }

    /** @return array{rows:int,result:array} */
    public function referenceRates(?int $userId = null): array
    {
        $rows = [];
        foreach ($this->zone->family(['P2_06']) as $r) {
            $p = $r['payload'];
            if (($p['DELETE_FLAG'] ?? 'N') === 'Y' || $r['row_date'] === null) {
                continue;
            }
            $rows[] = ['effective_date' => $r['row_date'], 'rate' => $this->num($p['PLR_RATE'] ?? 0), 'index_code' => 'PLR', 'label' => 'PLR master id ' . $r['source_key'], 'source_row' => 'P2_06:' . $r['source_key']];
        }
        usort($rows, fn ($a, $b) => $a['effective_date'] <=> $b['effective_date']);

        return ['rows' => count($rows), 'result' => $rows === [] ? [] : $this->rates->import($rows, 'PLR', null, $userId)];
    }

    /** @return array{rows:int,result:array} */
    public function fees(): array
    {
        $rows = [];
        foreach ($this->zone->family(LandingZoneReader::CHARGES) as $r) {
            $p = $r['payload'];
            $amount = $this->num($p['ACTUAL_CHARGES_AMT'] ?? 0);
            if ($amount <= 0 || $r['account'] === null) {
                continue;
            }
            $name = strtolower((string) ($p['DISB_CHARGES_NAME'] ?? ''));
            $rows[] = [
                'contract_id' => $r['account'], 'fee_type' => str_contains($name, 'agree') || str_contains($name, 'arrange') ? 'arrangement' : (str_contains($name, 'legal') ? 'legal' : 'other'),
                'amount' => $amount, 'currency' => 'MWK', 'description' => $p['DISB_CHARGES_NAME'] ?? null, 'external_transaction_id' => 'P2_07:' . $r['source_key'],
                'source_system' => 'E-Banker LOS_DISB_CHARGES_POST', 'source_reference' => $r['query_id'] . ':' . $r['source_key'], 'gl_account_ref' => $p['INCOME_GL'] ?? null,
            ];
        }

        return ['rows' => count($rows), 'result' => $this->fees->import($rows)];
    }

    /**
     * Interest posted per account and month from the ledger: types 303 and
     * 120, debits negated, the voucher ids kept as the posting references.
     *
     * @return array{rows:int,result:array}
     */
    public function glInterest(?string $from = null, ?string $to = null): array
    {
        $by = [];
        foreach ($this->zone->ledgerByAccount($to) as $account => $posts) {
            foreach ($posts as $p) {
                $x = $p['payload'];
                if (! in_array((string) ($x['TRANTYPE'] ?? ''), LoanBookBuildService::TYPE_INTEREST, true) || $p['row_date'] === null) {
                    continue;
                }
                $period = substr($p['row_date'], 0, 7);
                if ($from !== null && $period < $from) {
                    continue;
                }
                $k = $account . '|' . $period;
                $by[$k] ??= ['contract_id' => $account, 'gl_account_code' => trim((string) ($x['AC_GLCODE'] ?? '')), 'reporting_period' => $period, 'period_year' => (int) substr($period, 0, 4), 'period_month' => (int) substr($period, 5, 2),
                    'period_type' => 'MONTHLY', 'interest_income_posted' => 0.0, 'transaction_count' => 0, 'posting_references' => [], 'generated_on' => CarbonImmutable::today()->toDateString(), 'row_note' => 'from the ledger (P1_01), types 303 and 120'];
                $by[$k]['interest_income_posted'] -= $this->num($x['TRANSAMT'] ?? 0);
                $by[$k]['transaction_count']++;
                $by[$k]['posting_references'][] = (string) ($x['CUMVOUCH_DET_ID'] ?? $p['source_key']);
            }
        }
        $rows = [];
        foreach ($by as $r) {
            $r['interest_income_posted'] = round($r['interest_income_posted'], 2);
            $r['posting_references'] = implode(',', $r['posting_references']);
            $rows[] = $r;
        }

        return ['rows' => count($rows), 'result' => $this->glInterest->import($rows)];
    }

    /**
     * The cash movements of the ledger as the actual transactions the revenue
     * roll-forward prefers over the schedule (spec v4 section 7.1, which
     * retires Extract B): receipts and their reversals as collections,
     * disbursements as advances; the interest charges (303, 120) are accruals,
     * not cash, and are not transactions here. The voucher id is the
     * external id, so a re-run loads nothing twice.
     *
     * @return array{rows:int,result:array}
     */
    public function actualTransactions(?string $to = null): array
    {
        $rows = [];
        return $this->actualTransactionsFrom($rows, $to);
    }

    /**
     * The tranche register from the ledger (spec 7.5; system audit of 9 October
     * 2026, finding H2): every type-301 drawdown posting becomes a disbursement
     * on its date, so a facility drawn in tranches enters the EIR with each
     * tranche at its own date. P3_12 is E-Banker's disbursement schedule (the
     * plan), landed for reference; the postings are what was paid. The voucher
     * id is the external id, so a re-run loads nothing twice.
     *
     * @return array{rows:int,result:array}
     */
    public function disbursements(?string $to = null): array
    {
        // A fee debit is not a drawdown (spec 7.8.4; results review of 13 October
        // 2026, E1 and E3): a type-301 line in a batch that only credits a fee GL
        // (GL_04), or whose narration names an arrangement or legal fee, is the
        // fee capitalised into the loan. It is added to the drawdown of its date
        // (with none that day, to the first drawdown) and never becomes a tranche
        // of its own; the drawdown records how much of it is fee. Several
        // postings on one day are one drawdown. Until 14 October 2026 every
        // type-301 line was a tranche, so 18 facilities appeared to be drawn in
        // tranches that were drawn once with their fees.
        $feeDebits = app(GlFeeBuildService::class)->feeDebitVouchers();
        $rows = [];
        $accounts = [];
        foreach ($this->zone->ledgerByAccount($to) as $account => $posts) {
            $byDate = [];
            $fees = [];
            foreach ($posts as $p) {
                $x = $p['payload'];
                if (! in_array((string) ($x['TRANTYPE'] ?? ''), LoanBookBuildService::TYPE_DISBURSEMENT, true) || $p['row_date'] === null) {
                    continue;
                }
                $amt = -$this->num($x['TRANSAMT'] ?? 0); // a debit to the loan: cash advanced
                if ($amt <= 0) {
                    continue;
                }
                // the take-on opening balance of 31 July 2024 is the migration, not money lent that day
                // (the same opening legs TakeonLandingService reads: operated on the migration day, narrated "opening")
                if (($x['OPERATION_DATE'] ?? '') === '7/31/2024' && stripos((string) ($x['PARTICULARS'] ?? ''), 'opening') !== false) {
                    continue;
                }
                $voucher = (string) ($x['CUMVOUCH_DET_ID'] ?? $p['source_key']);
                if (isset($feeDebits[$voucher]) || $this->namesAFee((string) ($x['PARTICULARS'] ?? ''))) {
                    $fees[] = ['date' => $p['row_date'], 'amount' => round($amt, 2), 'voucher' => $voucher];
                } else {
                    $byDate[$p['row_date']]['amount'] = ($byDate[$p['row_date']]['amount'] ?? 0.0) + $amt;
                    $byDate[$p['row_date']]['vouchers'][] = $voucher;
                }
            }
            // every loan account the ledger holds is rebuilt, so one left with no drawdown loses the rows an earlier build gave it
            $accounts[] = $account;
            if ($byDate === [] && $fees === []) {
                continue;
            }
            ksort($byDate);
            foreach ($fees as $f) {
                // into the drawdown of its date; with none that day, into the first
                $date = isset($byDate[$f['date']]) ? $f['date'] : (array_key_first($byDate) ?? $f['date']);
                $byDate[$date]['amount'] = ($byDate[$date]['amount'] ?? 0.0) + $f['amount'];
                $byDate[$date]['fees'] = ($byDate[$date]['fees'] ?? 0.0) + $f['amount'];
                $byDate[$date]['fee_vouchers'][] = $f['voucher'];
            }
            ksort($byDate);
            $n = 0;
            foreach ($byDate as $date => $d) {
                $reference = 'CUMVOUCH:' . implode('+', $d['vouchers'] ?? []) . (isset($d['fee_vouchers']) ? ' fees ' . implode('+', $d['fee_vouchers']) : '');
                $rows[] = ['contract_id' => $account, 'disbursement_date' => $date, 'amount' => round($d['amount'], 2), 'tranche_no' => ++$n,
                    'reference' => mb_substr($reference, 0, 190), 'sub_account_no' => null, 'capitalised_fees' => isset($d['fees']) ? round($d['fees'], 2) : null];
            }
        }

        // The register is rebuilt from the ledger each time: the rows an earlier
        // build wrote for these accounts (references CUMVOUCH:) give way to the
        // new ones, so a fee debit that was a tranche stops being one. Rows
        // delivered by file carry their own references and are left alone.
        $removed = 0;
        if ($accounts !== []) {
            $removed = DB::table('contract_disbursements')->whereIn('contract_id', array_map(fn ($a) => ltrim((string) $a, '0'), $accounts))
                ->where('reference', 'like', 'CUMVOUCH:%')->delete();
        }

        $result = $rows === [] ? [] : app(\App\Services\Eir\DisbursementImportService::class)->import($rows);

        return ['rows' => count($rows), 'result' => $result + ['ledger_rows_replaced' => $removed, 'fee_debits_capitalised' => array_sum(array_map(fn ($r) => $r['capitalised_fees'] === null ? 0 : 1, $rows))]];
    }

    /** A narration that names an arrangement, legal or similar fee, as a fee debit's does ("Arrangement fees- Mphunzitsi SACCO"). */
    private function namesAFee(string $narration): bool
    {
        return preg_match('/\b(arrangement|legal|lega|appraisal|commitment|processing|guarantee)\b[^\n]*\bfees?\b/i', $narration) === 1;
    }

    private function actualTransactionsFrom(array $rows, ?string $to): array
    {
        foreach ($this->zone->ledgerByAccount($to) as $account => $posts) {
            foreach ($posts as $p) {
                $x = $p['payload'];
                $type = (string) ($x['TRANTYPE'] ?? '');
                $amt = $this->num($x['TRANSAMT'] ?? 0);
                if ($p['row_date'] === null || $amt === 0.0) {
                    continue;
                }
                if ($this->writeOffs()->isWriteOff($x, $p['row_date'], $account)) {
                    $kind = 'Write-off'; $total = $amt;               // derecognition, not cash (audit H1; spec 3.8: type 300, a 1064 batch, an H closure)
                } elseif (in_array($type, LoanBookBuildService::TYPE_DISBURSEMENT, true)) {
                    $kind = 'Disbursement'; $total = -$amt;           // a debit to the loan: cash advanced
                } elseif (in_array($type, LoanBookBuildService::TYPE_RECEIPT, true)) {
                    $kind = 'Principal+Interest'; $total = $amt;      // a credit: cash collected; a reversal arrives negative
                } elseif (in_array($type, LoanBookBuildService::TYPE_SETTLEMENT_IN_KIND, true)) {
                    $kind = 'Settlement in kind'; $total = $amt;      // the Nascomex share redemption: settles the loan, not cash (audit H1)
                } else {
                    continue;
                }
                $rows[] = [
                    'contract_id' => $account, 'transaction_date' => $p['row_date'], 'transaction_type' => $kind, 'total_amount' => round($total, 2),
                    'principal_component' => 0, 'interest_component' => 0, 'fee_component' => 0, 'scheduled_actual_flag' => 'ACTUAL',
                    'gl_posting_ref' => 'CUMVOUCH:' . (string) ($x['CUMVOUCH_DET_ID'] ?? $p['source_key']), 'balance_after_transaction' => '',
                    'row_note' => 'type ' . $type . ' ' . trim((string) ($x['PARTICULARS'] ?? '')),
                ];
            }
        }

        return ['rows' => count($rows), 'result' => $rows === [] ? [] : $this->transactions->import($rows)];
    }

    private function num(mixed $v): float
    {
        $s = str_replace([',', ' '], '', trim((string) $v));

        return is_numeric($s) ? (float) $s : 0.0;
    }

    private function date(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        foreach (['m/d/Y', 'n/j/Y', 'Y-m-d'] as $f) {
            try {
                $d = CarbonImmutable::createFromFormat('!' . $f, $v);
                if ($d !== false && $d->format($f) === $v) {
                    return $d->toDateString();
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }
}
