<?php

namespace App\Services\Eir;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\LoanBookBuildService;
use Illuminate\Support\Facades\DB;

/**
 * The acceptance ties of spec v4 section 9 and the golden numbers of 6.10.2,
 * read from the database: run by eir:bootstrap --verify, by eir:baselines,
 * and by the compliance workbooks' Baselines sheet (section 12.3), so that
 * all three show the same figures.
 */
class BaselineService
{
    private LandingZoneReader $zone;

    /**
     * Every baseline and golden tie here is a control on the data as received (the ledger
     * against the GL, the old ledger against the GL opening, the year-end batch, the overdue
     * run, the take-on worksheet): it reads the zone unfiltered, excluded accounts and all
     * (LandingZoneReader::asReceived). The book side of a check reads the book's tables.
     */
    public function __construct(LandingZoneReader $zone)
    {
        $this->zone = $zone->includingExcluded();
    }

    /** @return list<array{what:string,expected:string,actual:string,ok:bool,expected_value:?float,actual_value:?float}> */
    public function checks(): array
    {
        $zone = $this->zone;
        $checks = [];

        // contractual interest from the ledger, Jan 2025 to Jul 2026 (section 9)
        $total = 0.0;
        foreach ($zone->ledgerByAccount('2026-07-31') as $posts) {
            foreach ($posts as $p) {
                if (in_array((string) ($p['payload']['TRANTYPE'] ?? ''), LoanBookBuildService::TYPE_INTEREST, true) && $p['row_date'] >= '2025-01-01') {
                    $total -= (float) str_replace(',', '', (string) ($p['payload']['TRANSAMT'] ?? 0));
                }
            }
        }
        $checks[] = ['Interest posted Jan 2025 to Jul 2026 (ledger, types 303 and 120)', '5,293,988,207.06', number_format($total, 2), abs($total - 5293988207.06) < 0.01];

        // method B ties to the stored report on every account-month of the in-scope loan GLs but the one-cent row
        $inScope = fn () => DB::table('loan_books')->where('build_method', 'B')->whereNotNull('stored_carrying_amount')->whereIn('product_code', array_keys(\App\Services\Ebanker\LandingZoneReader::LOAN_GLS));
        $flagged = $inScope()->whereRaw('abs(carrying_amount - stored_carrying_amount) > 0.02')->count();
        $tied = $inScope()->count();
        $checks[] = ['Derived carrying amount against the stored report, in-scope loan GLs (over 2 cents)', '0 differences', "{$flagged} of {$tied} account-months", $flagged === 0];

        // the Mega Farm accounts are derived from their MF_03 ledger with the government's 67 percent interest legs
        // (LoanBookBuildService, 14 October 2026); where it still differs the report's amount stands: reported, not asserted
        $mfRun = fn () => DB::table('loan_books')->where('build_method', 'B')->whereIn('product_code', \App\Services\MegaFarm\MegaFarmEclService::GLS)->whereNotNull('stored_carrying_amount');
        $mfResidual = $mfRun()->where('build_flag', 'like', '%' . LoanBookBuildService::MEGA_FARM_REPORT_STANDS . '%')->count();
        $mfSign = $mfRun()->where('build_flag', 'like', '%' . LoanBookBuildService::MEGA_FARM_SIGN_FLAG . '%')->count();
        $checks[] = ['Mega Farm: account-months whose MF_03 ledger, with its government-portion legs, still differs from the Loan Book Report (the report stands; reported, not a test)', 'reported', "{$mfResidual} of " . $mfRun()->count() . ' account-months', true];
        $checks[] = ['Mega Farm: account-months whose Loan Book Report prints the ledger credit balance as a debit (the ledger stands; reported, not a test)', 'reported', "{$mfSign} of " . $mfRun()->count() . ' account-months', true];

        // E-Banker's own overdue run (OD_01, pack 9) against the days past due staging reads: reported, not asserted
        $od = $this->overdueRunComparison();
        if ($od['compared'] > 0 || $od['od_rows'] > 0) {
            $checks[] = ["Days past due: E-Banker's overdue run (OD_01) at month-end against the days staging reads, account-months in both (reported, not a test)", 'reported',
                "{$od['agree']} agree, {$od['differ']} differ ({$od['differ_over_30']} by more than 30 days) of {$od['compared']}; {$od['not_in_book']} OD_01 account-months not in the loan book", true];
        }

        // the Diff-Int year-end batch nets to the two income GLs (section 3.4)
        $net = ['4215' => 0.0, '4216' => 0.0];
        foreach ($zone->family(['GL_03']) as $r) {
            $gl = (string) ($r['payload']['AC_GLCODE'] ?? '');
            if (isset($net[$gl])) {
                // the export may write thousands commas ('1,234.50'): a bare cast read it as 1 (audit L5)
                $net[$gl] += (float) str_replace([',', ' '], '', (string) ($r['payload']['TRANSAMT'] ?? 0));
            }
        }
        $checks[] = ['Year-end interest batch contra on 4215', '32,956,675.55', number_format($net['4215'], 2), abs($net['4215'] - 32956675.55) < 0.01];
        $checks[] = ['Year-end interest batch contra on 4216', '-21,733,262.70', number_format($net['4216'], 2), abs($net['4216'] + 21733262.70) < 0.01];

        // loan balances by GL at 31 Dec 2025 against the audited mapping (when the TB bridge is loaded)
        $dec = DB::table('loan_books')->where('reporting_period', '2025-12')->whereIn('product_code', array_keys(LandingZoneReader::LOAN_GLS))->selectRaw('product_code, round(sum(carrying_amount), 2) ca')->groupBy('product_code')->pluck('ca', 'product_code');
        $tbTable = DB::getSchemaBuilder()->hasTable('gl_trial_balance_lines');
        // the keyed GL openings of section 3.5, accepted on the load until Finance corrects them
        $accepted = ['1050201' => -400000.00, '1050202' => 1000000.00];
        foreach ($dec as $gl => $ca) {
            if (! $tbTable) {
                continue;
            }
            $line = DB::table('gl_trial_balance_lines')->where('period', 'like', '2025-12%')->where('gl_code', $gl)->orderByDesc('id')->first();
            $tb = $line ? (float) $line->debit - (float) $line->credit : null;
            if ($tb === null && abs((float) $ca) < 0.005) {
                continue; // no balance and no line: nothing to tie
            }
            $diff = $tb === null ? null : round((float) $ca - $tb, 2);
            $ok = $diff !== null && abs($diff) < 1;
            $label = "Loan book {$gl} at 31 Dec 2025 against the trial balance";
            if (! $ok && $diff !== null && isset($accepted[$gl]) && abs($diff - $accepted[$gl]) < 1) {
                $label .= ' (accepted exception, spec 3.5: ' . number_format($accepted[$gl], 2) . ')';
                $ok = true;
            }
            $checks[] = [$label, $tb === null ? 'no TB line' : number_format($tb, 2), number_format((float) $ca, 2), $ok];
        }

        // every committed client file landed whole; then the golden numbers of docs/bootstrap/golden.json
        array_push($checks, ...$this->packInventory(base_path(self::INPUTS)));
        $actuals = $this->goldenActuals();
        foreach ($actuals as $key => $g) {
            $checks[] = $this->goldenCheck($key, $g['what'], $g['value'], $g['amount']);
        }
        // a golden committed that this run no longer produces (a reason or a stage that disappeared) fails too
        foreach (array_diff_key($this->golden(), $actuals) as $key => $value) {
            $checks[] = ["Golden {$key}: committed but not produced by this run", is_float($value) ? number_format($value, 2) : (string) $value, 'none', false];
        }

        $out = [];
        foreach ($checks as [$what, $expected, $actual, $ok]) {
            $out[] = ['what' => $what, 'expected' => $expected, 'actual' => $actual, 'ok' => (bool) $ok,
                'expected_value' => is_numeric(str_replace(',', '', $expected)) ? (float) str_replace(',', '', $expected) : null,
                'actual_value' => is_numeric(str_replace(',', '', $actual)) ? (float) str_replace(',', '', $actual) : null];
        }

        return $out;
    }

    /**
     * E-Banker's overdue run at month-end (OD_01, OVERDUE_DETAILS, pack 9 RUN 8b) against the days
     * past due staging reads from the loan book (StagingService::daysPastDueOf), per account-month:
     * the independent check of the days-past-due input that pack 9 RUN 8 was asked for. The run's
     * days are counted from its own oldest overdue date (OVERDUE_PRINCI_DATE where principal is
     * overdue, OVERDUE_INT_DATE where interest is) to its calculation date, nil when nothing is
     * overdue; where one account has two rows for a month-end the later key wins. Reported, not
     * asserted: a difference is evidence for the review, not a failure. RESCHEDULEMENT_DATE is
     * empty on every row received, so the run says nothing about reschedules.
     *
     * @return array{od_rows:int,compared:int,agree:int,differ:int,differ_over_30:int,run_higher:int,book_higher:int,not_in_book:int,differences:list<array>}
     */
    public function overdueRunComparison(int $sample = 50): array
    {
        $num = fn ($v) => (float) str_replace([',', ' '], '', (string) $v);
        $date = function (string $v): ?string {
            $v = trim($v);
            foreach (['n/j/Y', 'm/d/Y', 'Y-m-d'] as $f) {
                $d = \DateTimeImmutable::createFromFormat('!' . $f, $v);
                if ($d !== false && $d->format($f) === $v) {
                    return $d->format('Y-m-d');
                }
            }

            return null;
        };
        $run = [];
        $rows = 0;
        foreach ($this->zone->family(['OD_01']) as $r) {
            $p = $r['payload'];
            if (($p['DELETE_FLAG'] ?? 'N') === 'Y' || $r['row_date'] === null || (string) $r['account'] === '') {
                continue;
            }
            $rows++;
            $key = $r['account'] . '|' . substr($r['row_date'], 0, 7);
            if (isset($run[$key]) && (int) $run[$key]['source_key'] > (int) $r['source_key']) {
                continue;
            }
            $oldest = null;
            foreach (['OVERDUE_PRINCIPLE' => 'OVERDUE_PRINCI_DATE', 'OVERDUE_INTEREST' => 'OVERDUE_INT_DATE'] as $amount => $col) {
                $d = $date((string) ($p[$col] ?? ''));
                if ($num($p[$amount] ?? 0) > 0.005 && $d !== null && ($oldest === null || $d < $oldest)) {
                    $oldest = $d;
                }
            }
            $days = $num($p['OVERDUE_AMOUNT'] ?? 0) > 0.005 && $oldest !== null && $oldest < $r['row_date']
                ? (int) (new \DateTimeImmutable($oldest))->diff(new \DateTimeImmutable($r['row_date']))->days : 0;
            $run[$key] = ['source_key' => $r['source_key'], 'account' => $r['account'], 'period' => substr($r['row_date'], 0, 7), 'date' => $r['row_date'], 'days' => $days, 'oldest' => $oldest];
        }
        $out = ['od_rows' => $rows, 'compared' => 0, 'agree' => 0, 'differ' => 0, 'differ_over_30' => 0, 'run_higher' => 0, 'book_higher' => 0, 'not_in_book' => 0, 'differences' => []];
        if ($run === []) {
            return $out;
        }
        $book = [];
        $periods = array_values(array_unique(array_column($run, 'period')));
        foreach (array_chunk($periods, 50) as $chunk) {
            foreach (DB::table('loan_books')->whereIn('reporting_period', $chunk)->get(['contract_id', 'reporting_period', 'overdue_days', 'build_flag', 'arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180', 'arrears_180_to_270', 'arrears_271_to_360']) as $b) {
                $book[$b->contract_id . '|' . $b->reporting_period] = $b;
            }
        }
        foreach ($run as $o) {
            $b = $book[ltrim($o['account'], '0') . '|' . $o['period']] ?? null;
            if ($b === null) {
                $out['not_in_book']++;
                continue;
            }
            $out['compared']++;
            $ours = \App\Services\Eir\StagingService::daysPastDueOf($b);
            if ($ours === $o['days']) {
                $out['agree']++;
                continue;
            }
            $out['differ']++;
            $out[$o['days'] > $ours ? 'run_higher' : 'book_higher']++;
            if (abs($o['days'] - $ours) > 30) {
                $out['differ_over_30']++;
            }
            $out['differences'][] = ['account' => $o['account'], 'period' => $o['period'], 'overdue_run_days' => $o['days'], 'overdue_run_oldest' => $o['oldest'], 'staging_days' => $ours, 'difference' => $o['days'] - $ours];
        }
        usort($out['differences'], fn ($a, $b) => [abs($b['difference']), $a['account'], $a['period']] <=> [abs($a['difference']), $b['account'], $b['period']]);
        $out['differences'] = array_slice($out['differences'], 0, $sample);

        return $out;
    }

    /** The committed client inputs (eir:bootstrap --inputs) and the golden values a clean install must reproduce. */
    public const INPUTS = 'docs/bootstrap';
    public const GOLDEN = 'docs/bootstrap/golden.json';

    private ?array $golden = null;

    /**
     * The golden values committed in docs/bootstrap/golden.json ("values": key => number), set
     * from a full bootstrap on the date and commit the file names. A check compares the figure it
     * recomputes from the database with the committed value; a key with no committed value fails
     * (fail closed), so a golden is never taken from the run it is meant to test.
     *
     * @return array<string, float|int>
     */
    public function golden(): array
    {
        if ($this->golden === null) {
            $path = base_path(self::GOLDEN);
            $this->golden = is_file($path) ? ((json_decode((string) file_get_contents($path), true)['values'] ?? []) ?: []) : [];
        }

        return $this->golden;
    }

    /**
     * Every golden figure, recomputed from the database: key => what it is, its value, and whether
     * it is an amount (compared to the cent) or a count (compared exactly). --verify compares each
     * with docs/bootstrap/golden.json; the same method gives the values when the goldens are set,
     * so what is committed is what verify recomputes.
     *
     * @return array<string, array{what:string,value:float|int|null,amount:bool}>
     */
    public function goldenActuals(): array
    {
        return $this->takeonWorksheetTie() + $this->bookGoldens() + $this->outputGoldens() + $this->controlGoldens();
    }

    /**
     * The controls a run must reproduce besides the figures: the discounted allowance by stage in
     * loans, the loans the time-phased engine could not discount by reason, the month records and
     * whether each reconciles, the compliance register as loaded, the Mega Farm ledger tie by
     * outcome and the days-past-due comparison with E-Banker's overdue run.
     *
     * @return array<string, array{what:string,value:float|int|null,amount:bool}>
     */
    private function controlGoldens(): array
    {
        $g = [];
        $mf = \App\Services\MegaFarm\MegaFarmEclService::GLS;
        foreach (self::GOLDEN_PERIODS as $p) {
            foreach (DB::table('loan_books')->where('reporting_period', $p)->whereNotIn('product_code', $mf)->whereNotNull('ecl_value_discounted')
                ->selectRaw('ifrs9_stage s, count(*) n')->groupBy('ifrs9_stage')->orderBy('ifrs9_stage')->get() as $s) {
                $g["ecl_{$p}_maiic_stage{$s->s}_discounted_loans"] = ['what' => "ECL {$p}, MAIIC's book, stage {$s->s}: loans discounted", 'value' => (int) $s->n, 'amount' => false];
            }
            // the loans left undiscounted, by the reason their projection run recorded (digits masked, so a
            // reason that names an amount or a date counts with its kind)
            $runs = [];
            $byReason = [];
            foreach (DB::table('loan_books')->where('reporting_period', $p)->whereNotIn('product_code', $mf)->where('ecl_discount_status', 'TIME_PHASED_UNRESOLVED')
                ->orderBy('contract_id')->get(['contract_id', 'ecl_calculation_run_id']) as $l) {
                $run = (string) $l->ecl_calculation_run_id;
                $runs[$run] ??= json_decode((string) DB::table('ecl_projection_runs')->where('run_id', $run)->value('exceptions'), true) ?: [];
                $reason = trim(preg_replace('/\d[\d,.\/-]*/', '#', (string) ($runs[$run][(string) $l->contract_id] ?? 'no reason recorded')));
                $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(mb_substr($reason, 0, 60))), '_');
                $byReason[$slug] ??= ['what' => "ECL {$p}, MAIIC's book: loans not discounted, \"" . mb_substr($reason, 0, 90) . '"', 'value' => 0, 'amount' => false];
                $byReason[$slug]['value']++;
            }
            ksort($byReason);
            foreach ($byReason as $slug => $row) {
                $g["ecl_{$p}_unresolved_{$slug}"] = $row;
            }
            $g["ecl_{$p}_unresolved_loans"] = ['what' => "ECL {$p}, MAIIC's book: loans not discounted (all reasons)", 'value' => array_sum(array_column($byReason, 'value')), 'amount' => false];
            // the month record of the period (the latest not superseded)
            if (DB::getSchemaBuilder()->hasTable('fli_run_snapshots')) {
                $rec = DB::table('fli_run_snapshots')->where('reporting_period', $p)->where('status', '<>', 'SUPERSEDED')->orderByDesc('id')->first();
                $g["month_record_{$p}_booked_discounted"] = ['what' => "Month record {$p}: discounted allowance booked", 'value' => $rec?->booked_ecl_discounted === null ? null : (float) $rec->booked_ecl_discounted, 'amount' => true];
                $g["month_record_{$p}_weighted_discounted"] = ['what' => "Month record {$p}: scenario-weighted discounted allowance", 'value' => $rec?->weighted_ecl_discounted === null ? null : (float) $rec->weighted_ecl_discounted, 'amount' => true];
                $g["month_record_{$p}_reconciles"] = ['what' => "Month record {$p}: discounted weighted = booked (1) or not (0)", 'value' => $rec === null ? null : (int) (bool) $rec->discounted_reconciles, 'amount' => false];
            }
        }
        if (DB::getSchemaBuilder()->hasTable('fli_run_snapshots')) {
            $live = DB::table('fli_run_snapshots')->where('status', '<>', 'SUPERSEDED');
            $g['month_records'] = ['what' => 'Month records (not superseded)', 'value' => (clone $live)->count(), 'amount' => false];
            $g['month_records_reconciling'] = ['what' => 'Month records whose discounted weighted allowance equals the booked', 'value' => (clone $live)->where('discounted_reconciles', true)->count(), 'amount' => false];
            // which months reconcile, as one number: the sum of the YYYYMM of each reconciling month
            $g['month_records_reconciling_periods'] = ['what' => 'Month records reconciling: the sum of their YYYYMM (pins which months)', 'value' => (int) (clone $live)->where('discounted_reconciles', true)->get(['reporting_period'])
                ->sum(fn ($r) => (int) str_replace('-', '', $r->reporting_period)), 'amount' => false];
        }
        if (DB::getSchemaBuilder()->hasTable('compliance_audit_rows')) {
            $g['compliance_register_rows'] = ['what' => 'Compliance register rows loaded from the committed workbooks', 'value' => DB::table('compliance_audit_rows')->count(), 'amount' => false];
        }
        // the Mega Farm ledger tie (method B on the programme's GLs, where the report holds a row)
        $mfRows = fn () => DB::table('loan_books')->where('build_method', 'B')->whereIn('product_code', $mf)->whereNotNull('stored_carrying_amount');
        $sign = $mfRows()->where('build_flag', LoanBookBuildService::MEGA_FARM_SIGN_FLAG)->count();
        $report = $mfRows()->where('build_flag', 'like', LoanBookBuildService::MEGA_FARM_REPORT_STANDS . '%')->count();
        $other = $mfRows()->where('build_flag', 'like', LoanBookBuildService::MEGA_FARM_FLAG . '%')->count() - $sign - $report;
        $g['megafarm_ledger_tie'] = ['what' => 'Mega Farm account-months whose ledger ties to the Loan Book Report', 'value' => $mfRows()->count() - $sign - $report - $other, 'amount' => false];
        $g['megafarm_ledger_sign_error_ledger_stands'] = ['what' => 'Mega Farm account-months where the report prints a credit as a debit (the ledger stands)', 'value' => $sign, 'amount' => false];
        $g['megafarm_ledger_report_stands'] = ['what' => 'Mega Farm account-months where the report\'s amount stands', 'value' => $report, 'amount' => false];
        $g['megafarm_ledger_other_flags'] = ['what' => 'Mega Farm account-months with another Mega Farm flag', 'value' => $other, 'amount' => false];
        // E-Banker's overdue run against the days past due staging reads
        $od = $this->overdueRunComparison(0);
        foreach (['od_rows' => 'OD_01 month-end rows read', 'compared' => 'account-months in both', 'agree' => 'agree', 'differ' => 'differ', 'differ_over_30' => 'differ by more than 30 days', 'not_in_book' => 'OD_01 account-months not in the loan book'] as $k => $what) {
            $g["overdue_run_{$k}"] = ['what' => "Days past due, E-Banker's overdue run against staging: {$what}", 'value' => (int) $od[$k], 'amount' => false];
        }

        return $g;
    }

    /** The periods whose outputs are golden: the audited year-end and the last month of the committed packs. */
    public const GOLDEN_PERIODS = ['2025-12', '2026-09'];

    /**
     * The data ties: the loan book the build derives at each golden period, MAIIC's book and the
     * Mega Farm programme apart, and the ledger write-offs as WriteOffClassifier reads them.
     *
     * @return array<string, array{what:string,value:float|int|null,amount:bool}>
     */
    private function bookGoldens(): array
    {
        $g = [];
        $mf = \App\Services\MegaFarm\MegaFarmEclService::GLS;
        foreach (self::GOLDEN_PERIODS as $p) {
            $maiic = DB::table('loan_books')->where('reporting_period', $p)->whereIn('product_code', array_keys(LandingZoneReader::LOAN_GLS))
                ->selectRaw('count(*) n, round(sum(carrying_amount), 2) ca')->first();
            $prog = DB::table('loan_books')->where('reporting_period', $p)->whereIn('product_code', $mf)
                ->selectRaw('sum(carrying_amount <> 0) n, round(sum(carrying_amount), 2) ca')->first();
            $g["book_{$p}_maiic_amount"] = ['what' => "Loan book {$p}, MAIIC's loan GLs: carrying amount", 'value' => $maiic->n > 0 ? (float) $maiic->ca : null, 'amount' => true];
            $g["book_{$p}_maiic_loans"] = ['what' => "Loan book {$p}, MAIIC's loan GLs: rows", 'value' => $maiic->n > 0 ? (int) $maiic->n : null, 'amount' => false];
            $g["book_{$p}_megafarm_amount"] = ['what' => "Loan book {$p}, Mega Farm programme GLs: carrying amount", 'value' => $prog->n !== null ? (float) $prog->ca : null, 'amount' => true];
            $g["book_{$p}_megafarm_accounts"] = ['what' => "Loan book {$p}, Mega Farm programme GLs: accounts with a balance", 'value' => $prog->n !== null ? (int) $prog->n : null, 'amount' => false];
        }
        // the ledger write-offs (spec 3.8): every credit on a MAIIC loan the classifier names a write-off
        $classifier = new \App\Services\Ebanker\WriteOffClassifier($this->zone);
        $n = 0; $sum = 0;
        foreach ($this->zone->ledgerByAccount() as $account => $posts) {
            foreach ($posts as $p) {
                if ($classifier->isWriteOff($p['payload'], $p['row_date'], (string) $account)) {
                    $n++;
                    $sum += (int) round((float) str_replace([',', ' '], '', (string) ($p['payload']['TRANSAMT'] ?? 0)) * 100);
                }
            }
        }
        $g['ledger_writeoffs_postings'] = ['what' => 'Ledger write-offs on MAIIC loans (WriteOffClassifier): postings', 'value' => $n, 'amount' => false];
        $g['ledger_writeoffs_amount'] = ['what' => 'Ledger write-offs on MAIIC loans (WriteOffClassifier): amount', 'value' => $sum / 100, 'amount' => true];

        return $g;
    }

    /**
     * The engine outputs: the ECL at each golden period (MAIIC's book undiscounted and discounted at
     * the EIR, by stage; the Mega Farm programme and MAIIC's share of it), the EIR solved, locked and
     * blocked, the revenue by year, and the RBM loss-age list.
     *
     * @return array<string, array{what:string,value:float|int|null,amount:bool}>
     */
    private function outputGoldens(): array
    {
        $g = [];
        $mf = \App\Services\MegaFarm\MegaFarmEclService::GLS;
        foreach (self::GOLDEN_PERIODS as $p) {
            $e = DB::table('loan_books')->where('reporting_period', $p)->whereNotIn('product_code', $mf)
                ->selectRaw('sum(ecl_value is not null) n, round(sum(ecl_value), 2) ecl, round(sum(ecl_value_discounted), 2) disc, sum(ecl_value_discounted is not null) nd')->first();
            $has = (int) $e->n > 0;
            $g["ecl_{$p}_maiic_undiscounted"] = ['what' => "ECL {$p}, MAIIC's book: undiscounted", 'value' => $has ? (float) $e->ecl : null, 'amount' => true];
            $g["ecl_{$p}_maiic_discounted"] = ['what' => "ECL {$p}, MAIIC's book: discounted at the EIR (booked)", 'value' => $has ? (float) $e->disc : null, 'amount' => true];
            $g["ecl_{$p}_maiic_discounted_loans"] = ['what' => "ECL {$p}, MAIIC's book: loans discounted at the EIR", 'value' => $has ? (int) $e->nd : null, 'amount' => false];
            foreach (DB::table('loan_books')->where('reporting_period', $p)->whereNotIn('product_code', $mf)->whereNotNull('ecl_value')
                ->selectRaw('ifrs9_stage s, round(sum(ecl_value_discounted), 2) disc')->groupBy('ifrs9_stage')->orderBy('ifrs9_stage')->get() as $s) {
                $g["ecl_{$p}_maiic_stage{$s->s}_discounted"] = ['what' => "ECL {$p}, MAIIC's book, stage {$s->s}: discounted", 'value' => (float) $s->disc, 'amount' => true];
            }
            $run = DB::table('megafarm_ecl_runs')->where('reporting_period', $p)->where('role', \App\Services\MegaFarm\MegaFarmEclService::ROLE_BOOKED)->orderByDesc('id')->first();
            // a period the programme engine declined (no PD on MAIIC's own agricultural book that month) is
            // golden as declined: verify then checks it is still declined rather than failing for want of a figure
            $declined = $run === null || (bool) $run->declined;
            $g["ecl_{$p}_megafarm_declined"] = ['what' => "ECL {$p}, Mega Farm programme: declined by the engine (1) or provided (0)", 'value' => $declined ? 1 : 0, 'amount' => false];
            if (! $declined) {
                $g["ecl_{$p}_megafarm_programme"] = ['what' => "ECL {$p}, Mega Farm programme (latest run)", 'value' => (float) $run->programme_ecl, 'amount' => true];
                $g["ecl_{$p}_megafarm_maiic_share"] = ['what' => "ECL {$p}, Mega Farm: MAIIC's share", 'value' => (float) $run->maiic_ecl, 'amount' => true];
            }
        }
        // the S&P basis (spec v4 section 17), the comparison the bootstrap runs for each golden month: its ECL,
        // its loans and its ECL by the stage it gives, from the latest run of the month (null when none ran)
        foreach (self::GOLDEN_PERIODS as $p) {
            $sp = \Illuminate\Support\Facades\Schema::hasTable('sp_basis_runs') ? DB::table('sp_basis_runs')->where('reporting_period', $p)->orderByDesc('id')->first() : null;
            $g["sp_{$p}_ecl"] = ['what' => "S&P basis {$p}: ECL on the December 2025 model's method (comparison only)", 'value' => $sp ? (float) $sp->ecl : null, 'amount' => true];
            $g["sp_{$p}_loans"] = ['what' => "S&P basis {$p}: loans compared", 'value' => $sp ? (int) $sp->loans : null, 'amount' => false];
            $stages = $sp ? (json_decode((string) $sp->by_stage, true)['sp_basis'] ?? []) : [];
            foreach ([1, 2, 3] as $st) {
                $g["sp_{$p}_stage{$st}_ecl"] = ['what' => "S&P basis {$p}, stage {$st} by the arrears bracket: ECL", 'value' => $sp ? (float) ($stages[$st]['ecl'] ?? $stages[(string) $st]['ecl'] ?? 0) : null, 'amount' => true];
            }
        }
        foreach (['LOCKED' => 'locked', 'BLOCKED' => 'blocked'] as $status => $word) {
            $g["eir_contracts_{$word}"] = ['what' => "EIR contracts {$word}", 'value' => DB::table('contract_eir')->where('calculation_status', $status)->count(), 'amount' => false];
        }
        $g['eir_contracts_solved'] = ['what' => 'EIR contracts solved (an EIR computed)', 'value' => DB::table('contract_eir')->whereNotNull('eir_effective_annual')->count(), 'amount' => false];
        $g['eir_contracts'] = ['what' => 'EIR contracts in the contract master', 'value' => DB::table('contract_eir')->count(), 'amount' => false];
        foreach (DB::table('eir_amortisation')->selectRaw('substr(reporting_period, 1, 4) y, round(sum(interest_accrued), 2) i, round(sum(cash_received), 2) c, count(*) n')
            ->groupByRaw('substr(reporting_period, 1, 4)')->orderBy('y')->get() as $y) {
            $g["revenue_{$y->y}_eir_interest"] = ['what' => "Revenue {$y->y}: EIR interest recognised", 'value' => (float) $y->i, 'amount' => true];
            $g["revenue_{$y->y}_cash_received"] = ['what' => "Revenue {$y->y}: cash received", 'value' => (float) $y->c, 'amount' => true];
            $g["revenue_{$y->y}_rows"] = ['what' => "Revenue {$y->y}: roll-forward rows", 'value' => (int) $y->n, 'amount' => false];
        }
        $last = self::GOLDEN_PERIODS[count(self::GOLDEN_PERIODS) - 1];
        try {
            $loss = app(\App\Services\Rbm\RbmReturnService::class)->build($last)['sections']['B_loss_write_offs'] ?? null;
        } catch (\Throwable) {
            $loss = null;
        }
        $g["rbm_{$last}_loss_facilities"] = ['what' => "RBM return {$last}: facilities classified loss (paragraph 12 list)", 'value' => $loss === null ? null : (int) $loss['facilities'], 'amount' => false];
        $g["rbm_{$last}_loss_balance"] = ['what' => "RBM return {$last}: balance of the loss list", 'value' => $loss === null ? null : (float) $loss['balance'], 'amount' => true];

        return $g;
    }

    /** One golden row: the recomputed figure against the committed one, to the cent (counts exactly). */
    private function goldenCheck(string $key, string $what, float|int|null $actual, bool $amount = false): array
    {
        $fmt = fn ($v) => $v === null ? 'none' : ($amount ? number_format((float) $v, 2) : number_format((float) $v, 0));
        $expected = $this->golden()[$key] ?? null;
        if ($expected === null) {
            return [$what, "no golden value committed ({$key})", $fmt($actual), false];
        }

        return [$what, $fmt($expected), $fmt($actual), $actual !== null && abs((float) $actual - (float) $expected) < 0.005];
    }

    /**
     * The pack inventory (14 October 2026): every client file committed under docs/bootstrap is
     * still the file the manifest names (its SHA-256 recomputed from the committed copy, inside the
     * zip for a zipped pack), every extract (CSV) is landed in a LANDED load with exactly its
     * manifest row count (each row landed new, as a new version, or recognised as already held) and
     * that SHA-256, every CSV in a pack folder is named in its manifest (else it would be skipped
     * silently), and the trial balances and take-on workbooks the bootstrap lands are landed. The
     * files received and deliberately not landed are listed with their reasons in each manifest's
     * not_landed and counted here.
     *
     * @return list<array{0:string,1:string,2:string,3:bool}>
     */
    public function packInventory(string $inputs): array
    {
        $checks = [];
        $packs = [];
        if (is_file($inputs . '/manifest.json')) {
            $packs['manifest.json'] = ['dir' => $inputs, 'zip' => null, 'prefix' => '', 'json' => (string) file_get_contents($inputs . '/manifest.json')];
        }
        foreach (glob($inputs . '/ebanker-pack-*/manifest.json') ?: [] as $m) {
            $packs[basename(dirname($m))] = ['dir' => dirname($m), 'zip' => null, 'prefix' => '', 'json' => (string) file_get_contents($m)];
        }
        foreach (glob($inputs . '/packs/ebanker-pack-*.zip') ?: [] as $zipPath) {
            $name = basename($zipPath, '.zip');
            if (isset($packs[$name])) {
                continue;
            }
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true || ($json = $zip->getFromName($name . '/manifest.json')) === false) {
                $checks[] = ["Pack inventory: packs/{$name}.zip", 'a readable zip with its manifest', 'not readable', false];
                continue;
            }
            $packs[$name] = ['dir' => null, 'zip' => $zip, 'prefix' => $name . '/', 'json' => $json];
        }
        if ($packs === []) {
            return [["Pack inventory under {$inputs}", 'the committed packs', 'no manifest found', false]];
        }
        ksort($packs);
        $landedLoads = DB::table('ebanker_loads')->where('status', 'LANDED');
        foreach ($packs as $name => $p) {
            $m = json_decode($p['json'], true);
            if (! is_array($m) || ! is_array($m['files'] ?? null)) {
                $checks[] = ["Pack inventory: {$name}", 'a manifest with a files list', 'unreadable manifest', false];
                continue;
            }
            $problems = [];
            $csv = 0; $rows = 0; $landedFiles = 0; $landedRows = 0; $other = 0; $otherLanded = 0; $otherToLand = 0;
            $named = [];
            foreach ($m['files'] as $e) {
                $file = (string) ($e['file'] ?? '');
                $named[strtolower(str_replace('\\', '/', $file))] = true;
                $sha = $this->committedSha($p, $file);
                if ($sha === null) {
                    $problems[] = "{$file} missing";
                    continue;
                }
                if (! empty($e['sha256']) && strtolower((string) $e['sha256']) !== $sha) {
                    $problems[] = "{$file} SHA-256 differs from the manifest";
                }
                if (str_ends_with(strtolower($file), '.csv')) {
                    $csv++;
                    $want = (int) ($e['rows'] ?? -1);
                    $rows += max($want, 0);
                    $ok = DB::table('ebanker_pack_files')->whereIn('load_id', (clone $landedLoads)->select('id'))
                        ->where('file', $file)->where('sha256', $sha)->where('status', 'LANDED')->where('rows_declared', $want)
                        ->whereRaw('rows_new + rows_versioned + rows_unchanged = ?', [$want])->exists();
                    if ($ok) {
                        $landedFiles++;
                        $landedRows += $want;
                    } else {
                        $problems[] = "{$file} not landed with {$want} rows";
                    }
                    continue;
                }
                // a workbook: the bootstrap lands the monthly trial balances, the AFS bridge of
                // 10 September 2026 and the two take-on workbooks (Bootstrap::stepLand); the rest
                // (the earlier AFS bridge, the AFS mapping, the SQL, the macro snapshot) are reference
                $other++;
                $toLand = preg_match('#^(trial-balances/monthly/|trial-balances/afs-bridge-2025-12/2026-09-10/|takeon/[^/]+\.xlsx$)#', str_replace('\\', '/', $file)) === 1;
                if ($toLand) {
                    $otherToLand++;
                    if ((clone $landedLoads)->where(fn ($q) => $q->where('pack_hash', $sha)->orWhere('manifest', 'like', '%' . $sha . '%'))->exists()) {
                        $otherLanded++;
                    } else {
                        $problems[] = "{$file} not landed";
                    }
                }
            }
            // every CSV in the pack's folders is named in the manifest or recorded as not landed
            $notLanded = array_map(fn ($n) => strtolower((string) ($n['received_as'] ?? '')), $m['not_landed'] ?? []);
            foreach ($this->packCsvFiles($p, $m) as $f) {
                if (! isset($named[strtolower($f)]) && ! $this->recordedNotLanded(basename($f), $notLanded)) {
                    $problems[] = "{$f} is in the pack but not in its manifest";
                }
            }
            $label = $name === 'manifest.json' ? 'manifest.json (packs 1 and 2, data dictionary, trial balances, take-on)' : $name;
            $checks[] = ["Pack inventory: {$label}: every file as committed and landed with its manifest rows and SHA-256; "
                . count($m['not_landed'] ?? []) . ' received files not landed, each with its reason in the manifest' . ($other > 0 ? "; {$other} workbooks and scripts, {$otherToLand} landed by the bootstrap" : ''),
                "{$csv} extracts, " . number_format($rows) . ' rows' . ($otherToLand > 0 ? ", {$otherToLand} workbooks" : ''),
                "{$landedFiles} extracts, " . number_format($landedRows) . ' rows' . ($otherToLand > 0 ? ", {$otherLanded} workbooks" : '') . ($problems === [] ? '' : '; ' . implode('; ', array_slice($problems, 0, 3)) . (count($problems) > 3 ? '; and ' . (count($problems) - 3) . ' more' : '')),
                $problems === []];
        }
        foreach ($packs as $p) {
            $p['zip']?->close();
        }

        return $checks;
    }

    /** SHA-256 of a manifest file as committed: on disk, or inside the zip for a zipped pack; null when absent. */
    private function committedSha(array $pack, string $file): ?string
    {
        if ($pack['zip'] !== null) {
            $stream = $pack['zip']->getStream($pack['prefix'] . $file);
            if ($stream === false) {
                return null;
            }
            $ctx = hash_init('sha256');
            hash_update_stream($ctx, $stream);
            fclose($stream);

            return hash_final($ctx);
        }
        $path = $pack['dir'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);

        return is_file($path) ? hash_file('sha256', $path) : null;
    }

    /**
     * The CSVs a pack holds, as manifest-relative paths: the files of a zipped pack; the CSVs of a
     * pack folder; for the top-level manifest, the CSVs of every folder its entries name.
     *
     * @return list<string>
     */
    private function packCsvFiles(array $pack, array $manifest): array
    {
        if ($pack['zip'] !== null) {
            $out = [];
            for ($i = 0; $i < $pack['zip']->numFiles; $i++) {
                $n = (string) $pack['zip']->getNameIndex($i);
                if (str_starts_with($n, $pack['prefix']) && str_ends_with(strtolower($n), '.csv')) {
                    $out[] = substr($n, strlen($pack['prefix']));
                }
            }

            return $out;
        }
        $dirs = [];
        foreach ($manifest['files'] as $e) {
            if (str_ends_with(strtolower((string) ($e['file'] ?? '')), '.csv')) {
                $dirs[str_replace('\\', '/', dirname((string) $e['file']))] = true;
            }
        }
        $out = [];
        foreach (array_keys($dirs) as $d) {
            foreach (glob($pack['dir'] . '/' . ($d === '.' ? '' : $d . '/') . '*.csv') ?: [] as $f) {
                $out[] = ($d === '.' ? '' : $d . '/') . basename($f);
            }
        }

        return $out;
    }

    /** Whether a received file name appears among the manifest's not_landed records. */
    private function recordedNotLanded(string $base, array $notLanded): bool
    {
        foreach ($notLanded as $r) {
            if (str_contains($r, strtolower($base))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The migration worksheet (CV_10, OPENING_BAL, pack 9) against the take-on postings of
     * 31 July 2024 in the ledger: per loan, type 301 (principal taken on) against NEWDISBURS_AMT,
     * 303 (opening interest) against TOTAL_INT_CHARG and 305 (opening recoveries) against
     * TOTA_REPAY_UPTO, to the cent, signed as exported. Only the worksheet rows on the in-scope loan
     * GLs are loans (the GL 2000 rows are supplier balances). Reads the landing zone; with no CV_10
     * landed, every value is null and fails its golden.
     *
     * @return array<string, array{what:string,value:float|int|null,amount:bool}>
     */
    public function takeonWorksheetTie(): array
    {
        $cents = fn ($v) => (int) round((float) str_replace([',', ' '], '', (string) $v) * 100);
        $worksheet = [];
        foreach ($this->zone->family(['CV_10']) as $r) {
            $p = $r['payload'];
            if (isset(LandingZoneReader::LOAN_GLS[trim((string) ($p['GLCODE'] ?? ''))])) {
                $worksheet[trim((string) ($p['ACCNO'] ?? ''))] = $p;
            }
        }
        $posted = [];
        $ledger = array_values(array_diff(LandingZoneReader::LEDGER, ['MF_03']));
        foreach ($this->zone->family($ledger, null, '2024-07-31') as $r) {
            $p = $r['payload'];
            $type = (string) ($p['TRANTYPE'] ?? '');
            if (! in_array($type, ['301', '303', '305'], true) || ($p['DELETE_FLAG'] ?? 'N') === 'Y' || ! isset($worksheet[(string) $r['account']])) {
                continue;
            }
            $posted[$r['account']][$type] = ($posted[$r['account']][$type] ?? 0) + $cents($p['TRANSAMT'] ?? 0);
        }
        $both = count($posted);
        $agree = ['301' => 0, '303' => 0, '305' => 0];
        $columns = ['301' => 'NEWDISBURS_AMT', '303' => 'TOTAL_INT_CHARG', '305' => 'TOTA_REPAY_UPTO'];
        foreach ($posted as $account => $byType) {
            foreach ($columns as $type => $col) {
                if (($byType[$type] ?? 0) === $cents($worksheet[$account][$col] ?? 0)) {
                    $agree[$type]++;
                }
            }
        }
        $label = 'Take-on worksheet (CV_10) against the take-on postings of 31 Jul 2024: ';
        $g = ['takeon_worksheet_loans' => ['what' => $label . 'term loans on the worksheet with take-on postings', 'value' => $worksheet === [] ? null : $both, 'amount' => false]];
        foreach (['301' => 'principal taken on (301) = NEWDISBURS_AMT', '303' => 'opening interest (303) = TOTAL_INT_CHARG', '305' => 'opening recoveries (305) = TOTA_REPAY_UPTO'] as $type => $what) {
            $g["takeon_worksheet_{$type}_agree"] = ['what' => $label . "loans whose {$what}, to the cent", 'value' => $worksheet === [] ? null : $agree[$type], 'amount' => false];
        }

        // the pre-migration ledger copy (CV_04a, pack 7) against the GL opening balances of 1 January 2024 (CV_14, pack 9):
        // on each in-scope loan GL the copy's net postings to 31 December 2023 equal the opening balance (pack 9 review: within 0.50)
        $opening = [];
        foreach ($this->zone->family(['CV_14']) as $r) {
            $gl = trim((string) ($r['payload']['GLCODE'] ?? ''));
            if (isset(LandingZoneReader::LOAN_GLS[$gl])) {
                $opening[$gl] = ($opening[$gl] ?? 0) + $cents($r['payload']['OPENING_BAL'] ?? 0);
            }
        }
        $net = [];
        foreach ($this->zone->family(['CV_04a'], '2023-12-31') as $r) {
            $p = $r['payload'];
            $gl = trim((string) ($p['AC_GLCODE'] ?? ''));
            if (isset($opening[$gl]) && ($p['DELETE_FLAG'] ?? 'N') !== 'Y') {
                $net[$gl] = ($net[$gl] ?? 0) + $cents($p['TRANSAMT'] ?? 0);
            }
        }
        $diff = 0;
        foreach ($opening as $gl => $o) {
            $diff += abs(($net[$gl] ?? 0) - $o);
        }
        $g['premigration_ledger_vs_gl_opening'] = ['what' => 'Ledger copy CUMVOUCH_BK240824 (CV_04a) to 31 Dec 2023 against the GL opening balances of 1 Jan 2024 (CV_14), ' . count($opening) . ' loan GLs: sum of the differences',
            'value' => $opening === [] || $net === [] ? null : $diff / 100, 'amount' => true];

        return $g;
    }
}
