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
        $stated = [];
        foreach ($this->zone->runMonthEnds() as $monthEnd) {
            foreach ($this->zone->latestRunsOn($monthEnd) as $account => $run) {
                // the latest run that states the facility (statesTheFacility): a closed loan's later runs
                // print it blank, and must not replace the terms it was lent on
                $states = self::statesTheFacility($run['payload']);
                if ($states || ! isset($stated[$account])) {
                    $latest[$account] = $run['payload'];
                }
                if ($states) {
                    $stated[$account] = true;
                }
            }
        }
        $setups = $this->zone->rateSetupByAccount();
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
            $rate = $this->rateCharged($b, $setups[$account] ?? [], $a);
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

        $result = $this->contracts->import($rows);
        $result['over_sanction'] = $this->flagOverSanction();

        return ['rows' => count($rows), 'result' => $result];
    }

    /**
     * Whether a stored Loan Book Report row states the facility: a value date,
     * or an amount disbursed. E-Banker keeps printing a loan in the report after
     * it is closed, with the value date, the maturity date and every amount
     * blank or nil. Until 16 October 2026 the contract master read the latest
     * run, so a loan closed before the last month-end got a drawn amount of nil
     * and its account-open date for origination: its schedule was never
     * generated (DRAWN_AMOUNT_MISSING) and its EIR, and the revenue of the
     * months it was open, never solved. Pack 11 (16 October 2026) asked about
     * the 17 loans with no version 1 schedule: three of them, JVD Agro
     * 104460000109 (250,000,000.00 drawn on 12 May 2025, refinanced into
     * 104460000120 on 31 March 2026), Nature's Gift 104450000023 (24,600,000.00,
     * 22 April 2022) and Kadava 104450000041 (12,000,000.00, 2 August 2022,
     * both paid up on 16 December 2025) are such loans: every run before their
     * closure states the amount their pack 11 drawdowns (DS_01) confirm, and
     * the run of record for their terms is now the last of those. Their
     * version 1 is generated from these terms like every other E-Banker loan's
     * (ScheduleWorkflowService::generate), with the tranche register from the
     * ledger; E-Banker's instalment chart is the evidence it is compared with,
     * not the schedule itself. Nature's Gift and Kadava were taken on at the
     * migration of 31 July 2024 with no block in Finance's take-on workbook,
     * so their take-on basis stays TAKEON_BALANCE (TakeonLandingService) and
     * they take the generated route, as the three other TAKEON_BALANCE loans
     * did. A chart-based version 1 is not a route the system has, and their
     * chart (P2_10, and the same rows in pack 11's IC_01) was created on 28
     * March 2024, when the loans were set up in E-Banker for the migration, not
     * when they were made in 2022: it is not the original schedule.
     */
    public static function statesTheFacility(array $run): bool
    {
        $disbursed = str_replace([',', ' '], '', trim((string) ($run['DISBURSD'] ?? '')));

        return trim((string) ($run['VALUE_DATE'] ?? '')) !== '' || (is_numeric($disbursed) && (float) $disbursed > 0);
    }

    /**
     * Spec 7.3: an account whose drawn amount exceeds its sanction is flagged
     * for Credit. The excess is written to contract_eir.over_sanction_amount
     * (null when the drawn amount is within the sanction or either figure is
     * missing); nothing else changes, the EIR still runs on the money lent.
     *
     * @return array{flagged:int, contracts:list<string>}
     */
    public function flagOverSanction(): array
    {
        $schema = DB::getSchemaBuilder();
        if (! $schema->hasTable('contract_eir') || ! $schema->hasColumn('contract_eir', 'over_sanction_amount')) {
            return ['flagged' => 0, 'contracts' => []];
        }
        $flagged = [];
        DB::transaction(function () use (&$flagged) {
            DB::table('contract_eir')->whereNotNull('over_sanction_amount')->update(['over_sanction_amount' => null]);
            foreach (DB::table('contract_eir')->where('approved_amount', '>', 0)->where('drawn_amount', '>', 0)->whereColumn('drawn_amount', '>', 'approved_amount')->get(['id', 'contract_id', 'approved_amount', 'drawn_amount']) as $c) {
                $excess = round((float) $c->drawn_amount - (float) $c->approved_amount, 2);
                if ($excess >= 0.01) {
                    DB::table('contract_eir')->where('id', $c->id)->update(['over_sanction_amount' => $excess]);
                    $flagged[] = (string) $c->contract_id;
                }
            }
        });

        return ['flagged' => count($flagged), 'contracts' => $flagged];
    }

    /**
     * The scheme settings (spec v3 section 6.3) from E-Banker's scheme master:
     * SM_01 (pack 3, every MAIIC and Mega Farm scheme) first, and the data
     * dictionary's DD_10 where SM_01 lacks the scheme or the field. The
     * schedule generator reads the scheme where the contract states nothing;
     * with the table empty, a partly drawn facility had no interest basis
     * (INTEREST_BASIS_MISSING) even though the scheme states it.
     *
     *   interest_calc_base     LOANINT_SANCWISE_BALWISE (S sanction, B balance:
     *                          E-Banker's "Loan Interest Cal. Base On")
     *   installment_based_on   INST_BASED_ON (S sanction, D disbursement:
     *                          "Installment Based On")
     *   interest_policy        INTEREST_POLICY, verbatim
     *   floating_flag          FLOATING_FLAG, verbatim
     *   effective_from         APPLICABLE_FROM_DATE
     *
     * The EMI calculation type and the default moratorium type are not
     * loaded: the scheme master holds them as codes (EMI_BASED_ON 0 or 2,
     * INSTALLMENT_TYPE) whose reading the vendor has not confirmed, so the
     * scheme says nothing about them rather than a guess. A scheme row with
     * no readable effective date is not loaded and is named.
     *
     * @return array{rows:int, upserted:int, from_dd10:int, refused:array<string,string>}
     */
    public function schemes(): array
    {
        $sm = [];
        foreach ($this->zone->family(['SM_01']) as $r) {
            $id = trim((string) ($r['payload']['SCHEME_MST_ID'] ?? ''));
            if ($id !== '') {
                $sm[$id] = $r['payload'];
            }
        }
        $dd = [];
        foreach ($this->zone->family(['DD_10']) as $r) {
            $id = trim((string) ($r['payload']['SCHEME_MST_ID'] ?? ''));
            if ($id !== '') {
                $dd[$id] = $r['payload'];
            }
        }
        $fields = ['interest_calc_base' => 'LOANINT_SANCWISE_BALWISE', 'installment_based_on' => 'INST_BASED_ON', 'interest_policy' => 'INTEREST_POLICY',
            'floating_flag' => 'FLOATING_FLAG', 'effective_from' => 'APPLICABLE_FROM_DATE', 'scheme_name' => 'SCHEME_NAME'];
        $upserted = 0; $fromDd = 0; $refused = [];
        $codes = array_unique(array_merge(array_keys($sm), array_keys($dd)));
        sort($codes);
        foreach ($codes as $code) {
            $row = []; $sources = [];
            foreach ($fields as $column => $source) {
                $value = trim((string) ($sm[$code][$source] ?? ''));
                $from = 'SM_01';
                if ($value === '') {
                    $value = trim((string) ($dd[$code][$source] ?? ''));
                    $from = 'DD_10';
                }
                if ($value !== '') {
                    $row[$column] = $value;
                    $sources[$from] = true;
                }
            }
            $effective = isset($row['effective_from']) ? $this->date($row['effective_from']) : null;
            if ($effective === null) {
                $refused[(string) $code] = 'no readable APPLICABLE_FROM_DATE in SM_01 or DD_10';
                continue;
            }
            $values = [
                'interest_calc_base' => isset($row['interest_calc_base']) ? strtoupper(substr($row['interest_calc_base'], 0, 1)) : null,
                'installment_based_on' => isset($row['installment_based_on']) ? mb_substr($row['installment_based_on'], 0, 20) : null,
                'interest_policy' => isset($row['interest_policy']) ? strtoupper(substr($row['interest_policy'], 0, 1)) : null,
                'floating_flag' => isset($row['floating_flag']) ? strtoupper(substr($row['floating_flag'], 0, 1)) : null,
                'scheme_name' => isset($row['scheme_name']) ? mb_substr($row['scheme_name'], 0, 120) : null,
                'source' => implode('+', array_keys($sources)),
                'updated_at' => now(),
            ];
            if (! isset($sm[$code]) || isset($sources['DD_10'])) {
                $fromDd++;
            }
            $exists = DB::table('schemes')->where('scheme_code', (string) $code)->whereDate('effective_from', $effective)->exists();
            if ($exists) {
                DB::table('schemes')->where('scheme_code', (string) $code)->whereDate('effective_from', $effective)->update($values);
            } else {
                DB::table('schemes')->insert($values + ['scheme_code' => (string) $code, 'effective_from' => $effective, 'created_at' => now()]);
            }
            $upserted++;
        }

        return ['rows' => count($codes), 'upserted' => $upserted, 'from_dd10' => $fromDd, 'refused' => $refused];
    }

    /**
     * The rate charged, in the loan book's order (LoanBookBuildService::rateOn):
     * the latest run's rate; else the latest rate set-up (P1_04); else the
     * account master. The loan book report leaves INTEREST_RATE blank on some
     * accounts, and a blank is not null, so a bare ?? stopped there and the
     * contract was stored with no rate and never given a schedule. A zero is
     * passed over as well: the run and the master show 0 where interest is
     * stopped (MALAWI POLICE SACCO, status D), while the set-up still holds the
     * contractual rate, which is the one the EIR needs.
     */
    private function rateCharged(array $run, array $setups, array $master): float
    {
        $run = $this->num($run['INTEREST_RATE'] ?? 0);
        if ($run > 0) {
            return $run;
        }
        $setup = $setups !== [] ? $this->num(end($setups)['payload']['INTEREST_RATE'] ?? 0) : 0.0;

        return $setup > 0 ? $setup : $this->num($master['INTEREST_RATE'] ?? 0);
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
                'source_system' => self::LOS_SOURCE, 'source_reference' => $r['query_id'] . ':' . $r['source_key'], 'gl_account_ref' => $p['INCOME_GL'] ?? null,
                // a disbursement charge is taken from the borrower out of the advance, credited to
                // an income GL: received (spec v4 7.8; the GL fee route says the same of the posting)
                'cashflow_direction' => 'RECEIVED',
            ];
        }

        $result = $this->fees->import($rows);
        $result['direction_filled'] = $this->fillLosDirection();

        return ['rows' => count($rows), 'result' => $result];
    }

    public const LOS_SOURCE = 'E-Banker LOS_DISB_CHARGES_POST';

    /**
     * LOS charge lines loaded before the loader stated their direction carry
     * none, so they could never enter an EIR. Each is given RECEIVED; a line a
     * person had already classified or reviewed without it goes back to
     * classification (status PENDING, with the event saying why), because a
     * decision taken on a line with a missing field is not the decision on the
     * completed line. A superseded line keeps its decision (it never enters
     * the EIR); a line of a contract whose EIR is locked is left alone.
     *
     * @return array{filled:int, returned:int}
     */
    private function fillLosDirection(): array
    {
        $schema = DB::getSchemaBuilder();
        $locked = $schema->hasTable('contract_eir') ? DB::table('contract_eir')->whereNotNull('locked_at')->pluck('contract_id')->all() : [];
        $lines = DB::table('contract_fees')->where('source_system', self::LOS_SOURCE)
            ->where(fn ($q) => $q->whereNull('cashflow_direction')->orWhere('cashflow_direction', ''))
            ->when($locked !== [], fn ($q) => $q->whereNotIn('contract_id', $locked))
            ->get(array_merge(['id', 'classification_status', 'integral'], $schema->hasColumn('contract_fees', 'superseded_by_source') ? ['superseded_by_source'] : []));
        $filled = 0;
        $returned = 0;
        DB::transaction(function () use ($lines, $schema, &$filled, &$returned) {
            foreach ($lines as $line) {
                // a superseded line never enters the EIR (fee_source_precedence), so its decision stands
                $reopen = in_array($line->classification_status, ['CLASSIFIED', 'REVIEWED'], true) && empty($line->superseded_by_source ?? null);
                DB::table('contract_fees')->where('id', $line->id)->update(['cashflow_direction' => 'RECEIVED', 'updated_at' => now()]
                    + ($reopen ? ['classification_status' => 'PENDING', 'classified_by' => null, 'classified_at' => null, 'reviewed_by' => null, 'reviewed_at' => null] : []));
                $filled++;
                if ($reopen) {
                    $returned++;
                    if ($schema->hasTable('eir_fee_classification_events')) {
                        DB::table('eir_fee_classification_events')->insert(['contract_fee_id' => $line->id, 'action' => 'REOPENED', 'integral' => $line->integral,
                            'reason' => 'Returned to classification by the E-Banker fee load: the line was ' . strtolower($line->classification_status) . ' without a cash-flow direction, now set to RECEIVED (a disbursement charge taken from the borrower). Classify and review it again.',
                            'performed_by' => null, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
        });

        return ['filled' => $filled, 'returned' => $returned];
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
