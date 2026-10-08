<?php

namespace App\Services\Ebanker;

use App\Services\Eir\ContractMasterImportService;
use App\Services\Eir\ContractTransactionImportService;
use App\Services\Eir\FeeImportService;
use App\Services\Eir\GlInterestImportService;
use App\Services\Eir\ReferenceRateImportService;
use Carbon\CarbonImmutable;

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
        foreach ($this->zone->ledgerByAccount($to) as $account => $posts) {
            foreach ($posts as $p) {
                $x = $p['payload'];
                $type = (string) ($x['TRANTYPE'] ?? '');
                $amt = $this->num($x['TRANSAMT'] ?? 0);
                if ($p['row_date'] === null || $amt === 0.0) {
                    continue;
                }
                if (in_array($type, LoanBookBuildService::TYPE_DISBURSEMENT, true)) {
                    $kind = 'Disbursement'; $total = -$amt;           // a debit to the loan: cash advanced
                } elseif (in_array($type, LoanBookBuildService::TYPE_RECEIPT, true)) {
                    $kind = 'Principal+Interest'; $total = $amt;      // a credit: cash collected; a reversal arrives negative
                } elseif (in_array($type, LoanBookBuildService::TYPE_WRITEOFF, true)) {
                    $kind = 'Write-off'; $total = $amt;               // derecognition against the allowance, not cash (audit H1)
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
