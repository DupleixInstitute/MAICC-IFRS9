<?php

namespace App\Console\Commands;

use App\Services\Eir\ScheduleGeneratorService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Generates a complete, internally consistent synthetic dataset for end-to-end
 * testing of the EIR and revenue engines.
 *
 * The delivered production data cannot exercise the paths that matter most:
 * no fee has ever been classified integral, so the fee spread — the reason
 * this engine exists — has never once been solved; and no loan-book snapshot
 * carries an ECL allowance, so the Stage 3 net-basis and unwind branches have
 * never executed with a number that changes the answer. This fixture is built
 * so that they do.
 *
 * Everything reconciles by construction: schedules come from the real
 * ScheduleGeneratorService, actual transactions are derived from those
 * schedules, loan-book balances follow the actual payments, and GL interest is
 * posted on the contractual basis so the reconciliation has a *known* answer
 * to find. Contracts 16-20 are deliberately broken, one blocker each, so the
 * readiness gate and coverage report are exercised too.
 *
 * The identifiers use a 9-prefix that cannot collide with the 104/105 range
 * used by real MAIIC accounts.
 */
class MakeEirTestData extends Command
{
    protected $signature = 'eir:make-test-data
        {--out= : Output directory (default: "sample data/complete data set")}
        {--start=2024-01 : First loan-book period (YYYY-MM)}
        {--periods=22 : Monthly loan-book files to emit}
        {--load : Also insert the generated loan book into the current database connection}';

    protected $description = 'Generate a self-consistent synthetic loan book and EIR extracts for end-to-end testing';

    private const RUN_ID = 'TEST-RUN-2025';
    private const GENERATED_ON = '2025-11-30';

    public function handle(ScheduleGeneratorService $generator): int
    {
        $out = rtrim((string) ($this->option('out') ?: base_path('sample data/complete data set')), '/\\');
        $periodCount = max(1, min(36, (int) $this->option('periods')));
        foreach ([$out, $out . '/01_Loan_Books'] as $dir) {
            if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                $this->error("Could not create {$dir}");

                return self::FAILURE;
            }
        }

        $start = CarbonImmutable::parse(((string) $this->option('start')) . '-01');
        $periods = [];
        for ($i = 0; $i < $periodCount; $i++) {
            $periods[] = $start->addMonths($i)->format('Y-m');
        }

        $this->info('Building ' . count($this->specs()) . ' contracts over ' . count($periods) . ' periods...');

        $built = [];
        foreach ($this->specs() as $spec) {
            $built[] = $this->build($spec, $generator, $periods);
        }

        $this->writeLoanBooks($out, $built, $periods);
        $this->writeContractMaster($out, $built);
        $this->writeTransactions($out, $built);
        $this->writeGlInterest($out, $built, $periods);
        $this->writeFees($out, $built);
        $this->writeReadme($out, $built, $periods);
        $this->writeExpectedResults($out, $built, $periods);
        $this->writeChecksums($out);

        if ($this->option('load')) {
            $this->loadLoanBooks($built, $periods);
        }

        $this->newLine();
        $this->info("Written to {$out}");
        $this->table(
            ['Contract', 'Purpose', 'Drawn', 'Rate', 'Freq', 'Integral fees', 'Expect'],
            array_map(fn ($c) => [
                $c['id'],
                $c['spec']['purpose'],
                number_format($c['spec']['principal'], 0),
                round($c['spec']['rate'] * 100, 2) . '%',
                $c['spec']['frequency'] ?: '(blank)',
                number_format($c['integral_fee_total'], 0),
                $c['spec']['expect'],
            ], $built)
        );

        return self::SUCCESS;
    }

    /**
     * Twenty contracts, each earning its place by exercising something the
     * production data cannot.
     */
    private function specs(): array
    {
        return [
            // Originations sit in 2023 so that every facility is already on the
            // book by the first 2024 reporting period. Transition matrices join
            // a contract to itself across consecutive periods, so a facility
            // that appears halfway through contributes no migration history.

            // --- Fee-bearing: the whole point of the engine. EIR must exceed
            // the contractual rate by a visible, reconcilable margin. ---
            ['n' => 1, 'purpose' => 'Fees: arrangement + legal', 'principal' => 500_000_000, 'rate' => 0.285, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 40, 'start' => '2023-07-31',
                'fees' => [['ARRANGEMENT_FEE', 12_500_000, 'RECEIVED', 1], ['LEGAL_COST', 2_400_000, 'PAID', 1]], 'expect' => 'EIR > contractual'],
            ['n' => 2, 'purpose' => 'Fees: heavy arrangement', 'principal' => 250_000_000, 'rate' => 0.32, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 30, 'start' => '2023-10-31',
                'fees' => [['ARRANGEMENT_FEE', 11_000_000, 'RECEIVED', 1]], 'expect' => 'EIR > contractual'],
            ['n' => 3, 'purpose' => 'Fees + netting credit line', 'principal' => 180_000_000, 'rate' => 0.30, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 36, 'start' => '2023-09-30',
                'fees' => [['ARRANGEMENT_FEE', 6_000_000, 'RECEIVED', 1], ['LEGAL_COST_REBATE', -1_990_000, 'RECEIVED', 1]], 'expect' => 'Signed fee survives'],

            // --- Frequencies other than monthly: annualisation labels and the
            // quarterly-solved-monthly trap. ---
            ['n' => 4, 'purpose' => 'Quarterly + fees, to stage 3', 'principal' => 96_000_000, 'rate' => 0.3449, 'ppy' => 4, 'frequency' => 'Quarterly', 'n_payments' => 12, 'start' => '2023-06-30',
                'fees' => [['ARRANGEMENT_FEE', 4_000_000, 'RECEIVED', 1]], 'stage_path' => 'stage3', 'provision_rate' => 0.40,
                'expect' => 'NET accrual where ppy != 12'],
            ['n' => 5, 'purpose' => 'Half-yearly', 'principal' => 150_000_000, 'rate' => 0.26, 'ppy' => 2, 'frequency' => 'Half-Yearly', 'n_payments' => 8, 'start' => '2023-03-31',
                'fees' => [['ARRANGEMENT_FEE', 3_750_000, 'RECEIVED', 1]], 'expect' => 'ppy = 2'],
            ['n' => 6, 'purpose' => 'Annual, migrates to stage 3', 'principal' => 120_000_000, 'rate' => 0.24, 'ppy' => 1, 'frequency' => 'Yearly', 'n_payments' => 5, 'start' => '2023-01-31',
                'fees' => [['ARRANGEMENT_FEE', 2_400_000, 'RECEIVED', 1]], 'stage_path' => 'migrate', 'provision_rate' => 0.30,
                'expect' => 'NET accrual where ppy = 1'],

            // --- Floating: ECL discounting must label it a proxy. ---
            ['n' => 7, 'purpose' => 'Floating rate, to stage 3', 'principal' => 200_000_000, 'rate' => 0.10, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 40, 'start' => '2023-08-31',
                'rate_basis' => 'Variable', 'fees' => [['ARRANGEMENT_FEE', 4_000_000, 'RECEIVED', 1]], 'stage_path' => 'stage3', 'provision_rate' => 0.50,
                'behaviour' => 'partial', 'stops_at' => '2025-01', 'expect' => 'FLOATING proxy on a NET basis'],

            // --- Stage 3 with a real allowance: NET basis and unwind. ---
            ['n' => 8, 'purpose' => 'Stage 3 + allowance', 'principal' => 300_000_000, 'rate' => 0.333, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 30, 'start' => '2023-04-30',
                'fees' => [['ARRANGEMENT_FEE', 9_000_000, 'RECEIVED', 1]], 'stage_path' => 'stage3', 'provision_rate' => 0.45, 'behaviour' => 'stopped', 'stops_at' => '2025-04', 'expect' => 'NET basis + unwind'],
            ['n' => 9, 'purpose' => 'Stage 3, deep allowance', 'principal' => 80_000_000, 'rate' => 0.31, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 30, 'start' => '2023-05-31',
                'fees' => [['ARRANGEMENT_FEE', 2_400_000, 'RECEIVED', 1]], 'stage_path' => 'stage3', 'provision_rate' => 0.70, 'behaviour' => 'stopped', 'stops_at' => '2025-02', 'expect' => 'NET basis + unwind'],

            // --- Movement between stages across the reporting window. ---
            ['n' => 10, 'purpose' => 'Migration 1 -> 2 -> 3', 'principal' => 220_000_000, 'rate' => 0.295, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 36, 'start' => '2023-06-30',
                'fees' => [['ARRANGEMENT_FEE', 6_600_000, 'RECEIVED', 1]], 'stage_path' => 'migrate', 'provision_rate' => 0.35, 'behaviour' => 'partial', 'stops_at' => '2025-05', 'expect' => 'GROSS -> NET mid-run'],
            ['n' => 11, 'purpose' => 'Cure 3 -> 2 -> 1', 'principal' => 140_000_000, 'rate' => 0.28, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 36, 'start' => '2023-05-31',
                'fees' => [['ARRANGEMENT_FEE', 4_200_000, 'RECEIVED', 1]], 'stage_path' => 'cure', 'provision_rate' => 0.30, 'expect' => 'NET -> GROSS mid-run'],

            // --- Lifecycle edges. ---
            ['n' => 12, 'purpose' => 'Matures mid-2025', 'principal' => 60_000_000, 'rate' => 0.27, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 25, 'start' => '2023-05-31',
                'fees' => [['ARRANGEMENT_FEE', 1_800_000, 'RECEIVED', 1]], 'expect' => 'past-maturity path'],
            ['n' => 13, 'purpose' => 'Six-month moratorium', 'principal' => 175_000_000, 'rate' => 0.10, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 40, 'start' => '2023-06-30',
                'moratorium' => 6, 'fees' => [['ARRANGEMENT_FEE', 3_500_000, 'RECEIVED', 1]], 'expect' => 'capitalised moratorium'],
            ['n' => 14, 'purpose' => 'Partial payer', 'principal' => 90_000_000, 'rate' => 0.305, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 30, 'start' => '2023-08-31',
                'fees' => [['ARRANGEMENT_FEE', 2_700_000, 'RECEIVED', 1]], 'behaviour' => 'partial', 'stops_at' => '2025-03', 'stage_path' => 'stage2', 'expect' => 'cash < schedule'],
            ['n' => 15, 'purpose' => 'Non-integral fee only', 'principal' => 110_000_000, 'rate' => 0.29, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 30, 'start' => '2023-07-31',
                'fees' => [['PENALTY_FEE', 1_500_000, 'RECEIVED', 0]], 'expect' => 'EIR == contractual'],

            // --- Deliberately blocked, one named reason each. ---
            ['n' => 16, 'purpose' => 'BLOCKER: equity', 'principal' => 50_000_000, 'rate' => 0.0, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 12, 'start' => '2023-02-28',
                'fees' => [], 'product' => 'EQUITY INVESTMENT', 'expect' => 'out of scope'],
            ['n' => 17, 'purpose' => 'BLOCKER: blank frequency', 'principal' => 75_000_000, 'rate' => 0.30, 'ppy' => 12, 'frequency' => '', 'n_payments' => 30, 'start' => '2023-09-30',
                'fees' => [['ARRANGEMENT_FEE', 2_250_000, 'RECEIVED', 1]], 'expect' => 'FREQUENCY_ASSUMED'],
            ['n' => 18, 'purpose' => 'BLOCKER: principal mismatch', 'principal' => 130_000_000, 'rate' => 0.30, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 30, 'start' => '2023-08-31',
                'fees' => [['ARRANGEMENT_FEE', 3_900_000, 'RECEIVED', 1]], 'truncate_schedule' => true, 'expect' => 'PRINCIPAL_NOT_RECONCILED'],
            ['n' => 19, 'purpose' => 'BLOCKER: no schedule', 'principal' => 95_000_000, 'rate' => 0.31, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 30, 'start' => '2023-10-31',
                'fees' => [['ARRANGEMENT_FEE', 2_850_000, 'RECEIVED', 1]], 'no_schedule' => true, 'expect' => 'ORIGINAL_SCHEDULE_MISSING'],
            ['n' => 20, 'purpose' => 'BLOCKER: unreviewed fee', 'principal' => 85_000_000, 'rate' => 0.30, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 30, 'start' => '2023-11-30',
                'fees' => [['ADVISORY_FEE', 2_550_000, 'RECEIVED', null]], 'expect' => 'FEE_CLASSIFICATION_PENDING'],
        ];
    }

    /** Expands one spec into schedule, actuals, balances and fee lines. */
    private function build(array $spec, ScheduleGeneratorService $generator, array $periods): array
    {
        $spec += ['moratorium' => 0, 'fees' => [], 'behaviour' => 'full', 'stops_at' => null,
            'stage_path' => 'performing', 'provision_rate' => 0.0, 'rate_basis' => 'Fixed',
            'product' => 'TERM LOAN', 'truncate_schedule' => false, 'no_schedule' => false];

        $id = '9000000000' . str_pad((string) $spec['n'], 2, '0', STR_PAD_LEFT);
        $start = CarbonImmutable::parse($spec['start']);

        $schedule = [];
        if (! $spec['no_schedule'] && $spec['rate'] > 0) {
            $generated = $generator->generate([
                'principal' => $spec['principal'], 'annual_rate' => $spec['rate'],
                'payments_per_year' => $spec['ppy'], 'n_payments' => $spec['n_payments'],
                'start_date' => $start, 'moratorium_months' => $spec['moratorium'],
            ]);
            $schedule = $generated['rows'];
            // A truncated export is the commonest real-world schedule defect:
            // the rows are individually valid, the total simply is not.
            if ($spec['truncate_schedule']) {
                $schedule = array_slice($schedule, 0, max(1, (int) floor(count($schedule) * 0.6)));
            }
        }

        $actuals = $this->actuals($id, $schedule, $spec, $periods);
        $balances = $this->balances($spec, $schedule, $actuals, $periods);

        $integralTotal = 0.0;
        foreach ($spec['fees'] as $fee) {
            if ($fee[3] === 1) $integralTotal += $fee[3] === 1 && $fee[2] === 'RECEIVED' ? $fee[1] : -$fee[1];
        }

        return ['id' => $id, 'spec' => $spec, 'start' => $start, 'schedule' => $schedule,
            'actuals' => $actuals, 'balances' => $balances, 'integral_fee_total' => $integralTotal];
    }

    /** Actual receipts derived from the schedule, modulated by behaviour. */
    private function actuals(string $id, array $schedule, array $spec, array $periods): array
    {
        $lastPeriod = end($periods);
        $rows = [];
        $balance = (float) $spec['principal'];

        foreach ($schedule as $row) {
            $period = substr($row['due_date'], 0, 7);
            if ($period > $lastPeriod) break;

            $principal = (float) $row['principal_due'];
            $interest = (float) $row['interest_due'];
            $factor = 1.0;

            if ($spec['stops_at'] !== null && $period >= $spec['stops_at']) {
                $factor = $spec['behaviour'] === 'stopped' ? 0.0 : 0.6;
            }
            if ($factor <= 0.0) continue;

            $principal = round($principal * $factor, 2);
            $interest = round($interest * $factor, 2);
            $balance = max(0.0, $balance - $principal);

            $rows[] = [
                'date' => $row['due_date'],
                'type' => $principal > 0 ? 'Principal+Interest' : 'Interest',
                'principal' => $principal,
                'interest' => $interest,
                'fee' => 0.0,
                'total' => round($principal + $interest, 2),
                'balance_after' => round($balance, 2),
            ];
        }

        return $rows;
    }

    /** Month-end loan-book position for each reporting period. */
    private function balances(array $spec, array $schedule, array $actuals, array $periods): array
    {
        $paidByPeriod = [];
        foreach ($actuals as $a) {
            $paidByPeriod[substr($a['date'], 0, 7)] = ($paidByPeriod[substr($a['date'], 0, 7)] ?? 0) + $a['principal'];
        }

        // Facilities originate before the first reporting period, so the
        // opening balance is the principal less everything already repaid by
        // then. Starting at the full drawn amount would overstate every
        // balance on the tape and stop amortising loans ever closing.
        $firstPeriod = $periods[0];
        $out = [];
        $outstanding = (float) $spec['principal'];
        foreach ($actuals as $a) {
            if (substr($a['date'], 0, 7) < $firstPeriod) $outstanding -= $a['principal'];
        }
        $outstanding = max(0.0, $outstanding);
        $stageIndex = 0;

        foreach ($periods as $period) {
            $outstanding = max(0.0, $outstanding - (float) ($paidByPeriod[$period] ?? 0));
            $stage = $this->stageFor($spec['stage_path'], $stageIndex, count($periods));
            $provision = $stage === 3 ? round($outstanding * $spec['provision_rate'], 2)
                : ($stage === 2 ? round($outstanding * $spec['provision_rate'] * 0.25, 2) : 0.0);

            $out[$period] = [
                'carrying_amount' => round($outstanding, 2),
                'stage' => $stage,
                'provision' => $provision,
                'overdue_days' => $stage === 3 ? 210 : ($stage === 2 ? 45 : 0),
                'repayments' => round((float) ($paidByPeriod[$period] ?? 0), 2),
            ];
            $stageIndex++;
        }

        return $out;
    }

    /**
     * Stage transitions are placed as fractions of the reporting window rather
     * than at fixed offsets, so migrations stay spread across whatever period
     * range is requested instead of all landing in the first few months.
     */
    private function stageFor(string $path, int $i, int $total): int
    {
        $at = $total > 1 ? $i / ($total - 1) : 0.0;

        return match ($path) {
            'stage3' => $at < 0.25 ? 1 : ($at < 0.45 ? 2 : 3),
            'stage2' => $at < 0.40 ? 1 : 2,
            'migrate' => $at < 0.30 ? 1 : ($at < 0.60 ? 2 : 3),
            // Cure runs the other way: impaired, then recovering.
            'cure' => $at < 0.30 ? 3 : ($at < 0.60 ? 2 : 1),
            default => 1,
        };
    }

    // ---------------------------------------------------------------- writers

    private function writeContractMaster(string $out, array $built): void
    {
        $headers = ['AS_OF_DATE', 'CUSTOMER_ID', 'CUSTOMER_NAME', 'LOAN_ACCOUNT_NUMBER', 'SUB_ACCOUNT_NO',
            'GL_ACCOUNT_CODE', 'GL_ACCOUNT_TITLE', 'PORTFOLIO', 'PRODUCT_TYPE', 'CURRENCY', 'LOAN_START_DATE',
            'SANCTIONED_AMOUNT', 'PRINCIPAL_DISBURSED', 'DISBURSEMENT_TRANCHES', 'INTEREST_RATE', 'RATE_BASIS',
            'COMPOUNDING', 'DAY_COUNT_BASIS', 'REPAYMENT_FREQUENCY', 'TENOR', 'NUM_INSTALMENTS',
            'FIRST_REPAYMENT_DATE', 'CONTRACTUAL_MATURITY_DATE', 'ACTUAL_CLOSURE_DATE', 'WRITEOFF_DATE',
            'WRITEOFF_FLAG', 'WRITEOFF_AMOUNT', 'ACCOUNT_STATUS', 'RESTRUCTURE_DATE', 'PRINCIPAL_GRACE_PERIOD',
            'INTEREST_GRACE_PERIOD', 'BALANCE_SNAPSHOT_DATE', 'OPENING_BALANCE', 'BALANCE_DATA_NOTE', 'GENERATED_ON'];

        $rows = [];
        foreach ($built as $c) {
            $s = $c['spec'];
            $months = (int) round($s['n_payments'] * (12 / $s['ppy'])) + $s['moratorium'];
            $maturity = $c['start']->addMonthsNoOverflow($months);
            $first = $c['schedule'][0]['due_date'] ?? '';

            $rows[] = [
                '2025-11-30', 'CUS' . substr($c['id'], -4), 'Test Counterparty ' . $s['n'], $c['id'], '01',
                '1050102', 'Loans and advances', $s['n'] % 3 === 0 ? 'FInES' : 'MAIIC', $s['product'], 'MWK',
                $c['start']->format('Y-m-d'), $s['principal'], $s['principal'], '',
                round($s['rate'] * 100, 4), $s['rate_basis'], 'Compound', '365', $s['frequency'],
                intdiv($months, 12) . 'y ' . ($months % 12) . 'm 0d', $s['n_payments'],
                $first, $maturity->format('Y-m-d'), '', '', 'N', '', 'Active', '',
                $s['moratorium'] > 0 ? $s['moratorium'] . ' M' : '0 M', '0 M',
                '2025-01-01', '', 'Synthetic fixture', self::GENERATED_ON,
            ];
        }

        $this->put($out . '/02_Extract_A_Contract_Master.csv', $headers, $rows);
    }

    private function writeTransactions(string $out, array $built): void
    {
        $headers = ['RUN_ID', 'CUSTOMER_ID', 'LOAN_ACCOUNT_NUMBER', 'SUB_ACCOUNT_NO', 'GL_POSTING_REF',
            'TRANSACTION_DATE', 'TRANSACTION_TYPE', 'PRINCIPAL_COMPONENT', 'INTEREST_COMPONENT',
            'FEE_COMPONENT', 'TOTAL_AMOUNT', 'SCHEDULED_ACTUAL_FLAG', 'BALANCE_AFTER_TRANSACTION',
            'ROW_NOTE', 'GENERATED_ON'];

        $rows = [];
        $ref = 1;
        foreach ($built as $c) {
            $cust = 'CUS' . substr($c['id'], -4);

            // The contractual promise, in full — this is what the solver reads.
            foreach ($c['schedule'] as $row) {
                $rows[] = [self::RUN_ID, $cust, $c['id'], '01', 'SCH-' . str_pad((string) $ref++, 6, '0', STR_PAD_LEFT),
                    $row['due_date'], 'Principal+Interest', $row['principal_due'], $row['interest_due'], 0,
                    round($row['principal_due'] + $row['interest_due'], 2), 'Scheduled', '', '', self::GENERATED_ON];
            }
            // What was actually collected.
            foreach ($c['actuals'] as $a) {
                $rows[] = [self::RUN_ID, $cust, $c['id'], '01', 'ACT-' . str_pad((string) $ref++, 6, '0', STR_PAD_LEFT),
                    $a['date'], $a['type'], $a['principal'], $a['interest'], $a['fee'], $a['total'],
                    'Actual', $a['balance_after'], '', self::GENERATED_ON];
            }
        }

        $this->put($out . '/03_Extract_B_Transactions.csv', $headers, $rows);
    }

    /**
     * The ledger posts simple contractual interest on the outstanding balance.
     * That is deliberately a different basis from the EIR the engine solves, so
     * the reconciliation has a known, decomposable answer: with integral fees
     * present the rate effect must now be non-zero, which is exactly what the
     * production data has never been able to demonstrate.
     */
    private function writeGlInterest(string $out, array $built, array $periods): void
    {
        $headers = ['RUN_ID', 'CUSTOMER_ID', 'LOAN_ACCOUNT_NUMBER', 'SUB_ACCOUNT_NO', 'GL_ACCOUNT_CODE',
            'PERIOD_TYPE', 'PERIOD_YEAR', 'PERIOD_MONTH', 'INTEREST_INCOME_POSTED', 'TRANSACTION_COUNT',
            'POSTING_REFERENCES', 'ROW_NOTE', 'GENERATED_ON'];

        $rows = [];
        foreach ($built as $c) {
            if ($c['spec']['rate'] <= 0) continue;
            foreach ($periods as $period) {
                $balance = $c['balances'][$period]['carrying_amount'] ?? 0.0;
                if ($balance <= 0) continue;
                [$year, $month] = explode('-', $period);
                $rows[] = [self::RUN_ID, 'CUS' . substr($c['id'], -4), $c['id'], '01', '4010100', 'MONTH',
                    (int) $year, (int) $month, round($balance * $c['spec']['rate'] / 12, 2), 1,
                    'GL-' . $period . '-' . substr($c['id'], -4), '', self::GENERATED_ON];
            }
        }

        $this->put($out . '/04_Extract_C_GL_Postings.csv', $headers, $rows);
    }

    private function writeFees(string $out, array $built): void
    {
        // source_system and external_transaction_id are what make a re-import
        // idempotent: FeeImportService only skips a duplicate when BOTH are
        // present, so a file without them loads a second copy of every line.
        $headers = ['contract_id', 'fee_type', 'description', 'amount', 'cashflow_direction',
            'transaction_date', 'gl_account_ref', 'currency', 'source_reference',
            'source_system', 'external_transaction_id'];

        // FeeImportService::KNOWN_TYPES is the vocabulary the intake accepts;
        // anything else is folded into `other`. Emitting a canonical type plus
        // a description carrying the words the rulebook matches on is what
        // lets the accounting rules actually fire on this data. ADVISORY_FEE
        // is deliberately left as a bare `other` line so it matches nothing
        // and holds its contract at FEE_CLASSIFICATION_PENDING.
        $canonical = [
            'ARRANGEMENT_FEE' => ['arrangement', 'Arrangement fee on facility'],
            'LEGAL_COST' => ['legal', 'Legal cost - origination and documentation'],
            'LEGAL_COST_REBATE' => ['legal', 'Legal cost rebate on advance'],
            'PENALTY_FEE' => ['default', 'Penalty fee on arrears'],
            'ADVISORY_FEE' => ['other', 'Advisory fee'],
        ];

        $rows = [];
        foreach ($built as $c) {
            foreach ($c['spec']['fees'] as $i => $fee) {
                [$type, $amount, $direction, $integral] = $fee;
                [$feeType, $description] = $canonical[$type] ?? ['other', ucwords(strtolower(str_replace('_', ' ', $type)))];
                $reference = 'FEE-' . substr($c['id'], -4) . '-' . ($i + 1);
                $rows[] = [$c['id'], $feeType, $description, $amount,
                    $direction, $c['start']->format('Y-m-d'), '4020100', 'MWK',
                    $reference, 'EIR_TEST_FIXTURE', $reference];
            }
        }

        $this->put($out . '/05_Fees.csv', $headers, $rows);
    }

    private function writeLoanBooks(string $out, array $built, array $periods): void
    {
        $headers = ['contract_id', 'customer_id', 'customer_name', 'product_group', 'product_code',
            'reporting_period', 'create_date', 'due_date', 'industry_code', 'interest_rate',
            'principal_balance', 'approved_amount', 'disbursed', 'repayments', 'carrying_amount',
            'commitments', 'facility_utilisation_rate', 'overdue_days', 'tenor', 'remaining_tenor',
            'expected_loss_provision', 'ifrs9_stage', 'calculated_ifrs9_stage',
            'ifrs9stage_pre_qualitative', 'ifrs9stage_post_qualitative',
            'pd_prefli', 'pd_post_fli', 'customer_lgd', 'collection_lgd',
            'funding_source', 'contract_status'];

        foreach ($periods as $period) {
            $rows = [];
            foreach ($built as $c) {
                $s = $c['spec'];
                $b = $c['balances'][$period] ?? null;
                if (! $b || $b['carrying_amount'] <= 0) continue;

                $months = (int) round($s['n_payments'] * (12 / $s['ppy'])) + $s['moratorium'];
                $maturity = $c['start']->addMonthsNoOverflow($months);
                $remaining = max(0, CarbonImmutable::parse($period . '-01')->diffInMonths($maturity, false));
                $risk = $this->riskInputs((int) $s['n'], (int) $b['stage']);

                $rows[] = [
                    $c['id'], 'CUS' . substr($c['id'], -4), 'Test Counterparty ' . $s['n'],
                    $s['product'], 'TL01', $period, $c['start']->format('Y-m-d'), $maturity->format('Y-m-d'),
                    'A01', round($s['rate'] * 100, 4),
                    $b['carrying_amount'], $s['principal'], $s['principal'], $b['repayments'],
                    $b['carrying_amount'], 0, 1, $b['overdue_days'], $months, $remaining,
                    $b['provision'], $b['stage'], $b['stage'], $b['stage'], $b['stage'],
                    $risk['pd_prefli'], $risk['pd_post_fli'], $risk['customer_lgd'], $risk['collection_lgd'],
                    $s['n'] % 3 === 0 ? 'FInES' : 'MAIIC', 'Active',
                ];
            }
            $this->put($out . '/01_Loan_Books/loan_book_' . $period . '.csv', $headers, $rows);
        }
    }

    private function writeReadme(string $out, array $built, array $periods): void
    {
        $blocked = array_filter($built, fn ($c) => str_starts_with($c['spec']['purpose'], 'BLOCKER'));

        $lines = [
            'MAIIC EIR COMPLETE TEST DATA SET — SYNTHETIC DATA ONLY',
            'Generated: ' . self::GENERATED_ON,
            'Loan-book periods: ' . $periods[0] . ' to ' . end($periods),
            '',
            'IMPORTANT',
            '- Every figure in this pack is synthetic. No real MAIIC account, customer or',
            '  balance appears anywhere in it.',
            '- Account numbers use a 9000000000xx range that cannot collide with the real',
            '  104/105 series, so this pack can be loaded into a database alongside real',
            '  data without contaminating it — though a clean database is recommended.',
            '- Unlike "EIR Test Pack", this set ships its own loan book. It does not depend',
            '  on any pre-existing tape, which is what makes it usable on an empty database.',
            '',
            'WHY THIS PACK EXISTS',
            '  The delivered production data cannot exercise the two paths that matter most.',
            '  No fee has ever been classified integral, so the fee spread — the reason the',
            '  EIR engine exists — has never once been solved: the rate effect across the',
            '  whole production book is MK4.87. And no loan-book snapshot carries an ECL',
            '  allowance, so the Stage 3 net-basis and unwind branches have never executed',
            '  with a number that changes the answer. This pack is built so that they do.',
            '',
            'CONSISTENCY',
            '  Schedules are produced by the real ScheduleGeneratorService. Actual receipts',
            '  are derived from those schedules. Loan-book balances follow the actual',
            '  receipts. GL interest is posted on the CONTRACTUAL basis, deliberately',
            '  different from the EIR the engine solves, so the reconciliation bridge has a',
            '  known and decomposable answer to find.',
            '',
            'RECOMMENDED TEST ORDER',
            '  1. Import 01_Loan_Books/loan_book_' . $periods[0] . '.csv through',
            '     loan_book_' . end($periods) . '.csv as the monthly loan book (' . count($periods) . ' files).',
            '  2. Import 02_Extract_A_Contract_Master.csv as "Contract master (Extract A)".',
            '  3. Import 03_Extract_B_Transactions.csv as "Contract transactions (Extract B)".',
            '  4. Import 05_Fees.csv as "Fees".',
            '  5. Import 04_Extract_C_GL_Postings.csv as "GL interest postings (Extract C)".',
            '  6. Classify fees (EIR Fee & Cost Classification). Mark every fee integral',
            '     EXCEPT PENALTY_FEE on ' . $built[14]['id'] . ', and leave ADVISORY_FEE on',
            '     ' . $built[19]['id'] . ' unreviewed — those two are deliberate controls.',
            '  7. Calculate EIR, then approve and lock (maker must differ from checker).',
            '  8. Run: php artisan eir:run-revenue ' . $periods[0] . '   ... through ' . end($periods),
            '  9. Open EIR Coverage & Blockers, then GL Reconciliation.',
            '',
            'THE TWO ASSERTIONS THAT MATTER',
            '  A. RATE EFFECT MUST BE NON-ZERO.',
            '     Contracts with integral fees must each solve to an EIR above their',
            '     contractual rate, and the GL reconciliation bridge must show a material',
            '     rate effect. On production data that figure is MK4.87 — effectively nil.',
            '  B. STAGE 3 MUST ACCRUE ON THE NET BALANCE.',
            '     Contracts ' . $built[7]['id'] . ', ' . $built[8]['id'] . ', ' . $built[9]['id']
                . ' and ' . $built[10]['id'] . ' carry real ECL allowances,',
            '     so interest_basis must switch to NET and unwind_amount must be non-zero.',
            '     On production data every ecl_allowance is 0.00, so NET and GROSS agree.',
            '',
            'DELIBERATE BLOCKERS (' . count($blocked) . ')',
            '  These must be REFUSED by the readiness gate, each for one named reason.',
            '  A pack where everything succeeds does not test the gate.',
        ];
        foreach ($blocked as $c) {
            $lines[] = '  - ' . $c['id'] . '  ' . $c['spec']['expect'];
        }

        $lines[] = '';
        $lines[] = 'KNOWN FINDING — MORATORIUM CONTRACTS ARE CURRENTLY BLOCKED';
        $lines[] = '  ' . $built[12]['id'] . ' carries a 6-month moratorium. Its scheduled principal exceeds';
        $lines[] = '  the drawn amount by the capitalised moratorium interest, which is correct, but';
        $lines[] = '  EirReadinessService compares scheduled principal to the DRAWN amount within 1%.';
        $lines[] = '  The tolerance breaks when (1 + rate/12)^months > 1.01 — at MAIIC\'s typical 28-33%';
        $lines[] = '  that is a single month. So this contract will report PRINCIPAL_NOT_RECONCILED';
        $lines[] = '  until the gate compares against the capitalised principal instead. That is an';
        $lines[] = '  accounting judgement, so the fixture documents it rather than working around it.';
        $lines[] = '';
        $lines[] = 'CONTRACT INVENTORY';
        $lines[] = '';
        foreach ($built as $c) {
            $lines[] = '  ' . $c['id'] . '  ' . str_pad($c['spec']['purpose'], 30) . $c['spec']['expect'];
        }
        $lines[] = '';
        $lines[] = 'EXPECTED COUNTS AND SOLVED RATES';
        $lines[] = '  See expected_results.json. File integrity: SHA256SUMS.txt.';
        $lines[] = '';
        $lines[] = 'REGENERATE';
        $lines[] = '  php artisan eir:make-test-data';
        $lines[] = '';

        file_put_contents($out . '/00_READ_ME_FIRST.txt', implode(PHP_EOL, $lines) . PHP_EOL);
        $this->line('  ' . str_pad('00_READ_ME_FIRST.txt', 38) . 'written');
    }

    /**
     * The solved EIR each contract must reproduce, computed here with the real
     * solver so the pack can be validated rather than eyeballed.
     */
    private function writeExpectedResults(string $out, array $built, array $periods): void
    {
        $solver = app(\App\Services\Eir\CalculateEirService::class);
        $nonIntegral = ['PENALTY_FEE', 'ADVISORY_FEE'];

        $contracts = [];
        foreach ($built as $c) {
            $s = $c['spec'];
            $entry = [
                'contract_id' => $c['id'],
                'purpose' => $s['purpose'],
                'expectation' => $s['expect'],
                'drawn_amount' => $s['principal'],
                'contractual_nominal_annual' => round($s['rate'], 6),
                'payments_per_year' => $s['ppy'],
                'schedule_rows' => count($c['schedule']),
                'actual_transactions' => count($c['actuals']),
            ];

            $received = 0.0;
            $paid = 0.0;
            foreach ($s['fees'] as [$type, $amount, $direction, $integral]) {
                if ($integral !== 1 || in_array($type, $nonIntegral, true)) continue;
                $direction === 'RECEIVED' ? $received += $amount : $paid += $amount;
            }
            $entry['integral_fees_received'] = round($received, 2);
            $entry['integral_costs_paid'] = round($paid, 2);

            $flows = [];
            foreach ($c['schedule'] as $i => $row) {
                $flows[] = ['period' => $i + 1, 'amount' => round($row['principal_due'] + $row['interest_due'], 2)];
            }

            if ($flows !== [] && ! $s['truncate_schedule']) {
                try {
                    $r = $solver->calculate($s['principal'] - $received + $paid, $flows, $s['ppy']);
                    $contractualEffective = pow(1 + $s['rate'] / $s['ppy'], $s['ppy']) - 1;
                    $entry['expected_eir_period'] = round($r['eir_period'], 8);
                    $entry['expected_eir_nominal_annual'] = round($r['eir_nominal_annual'], 8);
                    $entry['expected_eir_effective_annual'] = round($r['eir_effective_annual'], 8);
                    $entry['contractual_effective_annual'] = round($contractualEffective, 8);
                    $entry['expected_uplift_pp'] = round(($r['eir_effective_annual'] - $contractualEffective) * 100, 4);
                } catch (\Throwable $e) {
                    $entry['expected_eir_effective_annual'] = null;
                    $entry['solver_note'] = $e->getMessage();
                }
            } else {
                $entry['expected_eir_effective_annual'] = null;
                $entry['solver_note'] = $s['no_schedule']
                    ? 'No schedule: blocked before the solver.'
                    : ($s['truncate_schedule'] ? 'Truncated schedule: blocked by PRINCIPAL_NOT_RECONCILED before the solver.' : 'Not solvable.');
            }

            $contracts[] = $entry;
        }

        $solvable = array_filter($contracts, fn ($c) => ($c['expected_uplift_pp'] ?? 0) > 0.01);

        $payload = [
            'synthetic_data_only' => true,
            'generated_at' => self::GENERATED_ON,
            'loan_book_periods' => $periods,
            'warning' => 'Every figure is synthetic. Safe to load into a clean test database.',
            'import_order' => [
                '01_Loan_Books/loan_book_*.csv as the monthly loan book',
                '02_Extract_A_Contract_Master.csv as Contract master (Extract A)',
                '03_Extract_B_Transactions.csv as Contract transactions (Extract B)',
                '05_Fees.csv as Fees',
                '04_Extract_C_GL_Postings.csv as GL interest postings (Extract C)',
            ],
            'headline_assertions' => [
                'contracts_with_expected_fee_spread' => count($solvable),
                'rate_effect_must_be_material' => true,
                'stage3_contracts_expecting_net_basis' => array_values(array_map(
                    fn ($c) => $c['id'],
                    array_filter($built, fn ($c) => ($c['spec']['provision_rate'] ?? 0) > 0)
                )),
            ],
            'expected_blockers' => array_values(array_map(
                fn ($c) => ['contract_id' => $c['id'], 'reason' => $c['spec']['expect']],
                array_filter($built, fn ($c) => str_starts_with($c['spec']['purpose'], 'BLOCKER'))
            )),
            'contracts' => $contracts,
        ];

        file_put_contents($out . '/expected_results.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $this->line('  ' . str_pad('expected_results.json', 38) . count($contracts) . ' contracts');
    }

    /**
     * Inserts the generated tape straight into the current connection.
     *
     * This bypasses LoanBooksImport deliberately: the point of the fixture is
     * to exercise the EIR, revenue and ECL engines, and going through the
     * intake screens for twenty-two monthly files is a separate test. Existing
     * rows for these synthetic contracts are replaced so the command can be
     * re-run without stacking duplicates.
     */
    private function loadLoanBooks(array $built, array $periods): void
    {
        $connection = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
        $this->newLine();
        $this->warn("Loading the generated loan book into database: {$connection}");

        $portfolioId = \Illuminate\Support\Facades\DB::table('loan_portfolios')->where('name', 'EIR Test Portfolio')->value('id');
        if (! $portfolioId) {
            $portfolioId = \Illuminate\Support\Facades\DB::table('loan_portfolios')->insertGetId([
                'name' => 'EIR Test Portfolio',
                'description' => 'Synthetic portfolio for the complete EIR/ECL test data set.',
                'active' => 1,
                'created_by_id' => \Illuminate\Support\Facades\DB::table('users')->min('id') ?? 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $ids = array_map(fn ($c) => $c['id'], $built);
        \Illuminate\Support\Facades\DB::table('loan_books')->whereIn('contract_id', $ids)->delete();

        $inserted = 0;
        foreach ($periods as $period) {
            [$year, $month] = array_map('intval', explode('-', $period));
            $rows = [];
            foreach ($built as $c) {
                $s = $c['spec'];
                $b = $c['balances'][$period] ?? null;
                if (! $b || $b['carrying_amount'] <= 0) continue;

                $months = (int) round($s['n_payments'] * (12 / $s['ppy'])) + $s['moratorium'];
                $maturity = $c['start']->addMonthsNoOverflow($months);
                $risk = $this->riskInputs((int) $s['n'], (int) $b['stage']);

                $rows[] = [
                    'loan_portfolio_id' => $portfolioId,
                    'contract_id' => $c['id'],
                    'customer_id' => 'CUS' . substr($c['id'], -4),
                    'customer_name' => 'Test Counterparty ' . $s['n'],
                    'product_group' => $s['product'],
                    'product_code' => 'TL01',
                    'reporting_year' => $year,
                    'reporting_month' => $month,
                    'reporting_period' => $period,
                    'create_date' => $c['start']->format('Y-m-d'),
                    'due_date' => $maturity->format('Y-m-d'),
                    'industry_code' => 'A01',
                    'interest_rate' => round($s['rate'] * 100, 4),
                    'principal_balance' => $b['carrying_amount'],
                    'approved_amount' => $s['principal'],
                    'disbursed' => $s['principal'],
                    'repayments' => $b['repayments'],
                    'carrying_amount' => $b['carrying_amount'],
                    'commitments' => 0,
                    'facility_utilisation_rate' => 1,
                    'overdue_days' => $b['overdue_days'],
                    'tenor' => $months,
                    'remaining_tenor' => max(0, \Carbon\CarbonImmutable::parse($period . '-01')->diffInMonths($maturity, false)),
                    'expected_loss_provision' => $b['provision'],
                    'ifrs9_stage' => $b['stage'],
                    'calculated_ifrs9_stage' => $b['stage'],
                    'ifrs9stage_pre_qualitative' => $b['stage'],
                    'ifrs9stage_post_qualitative' => $b['stage'],
                    'pd_prefli' => $risk['pd_prefli'],
                    'pd_post_fli' => $risk['pd_post_fli'],
                    'customer_lgd' => $risk['customer_lgd'],
                    'collection_lgd' => $risk['collection_lgd'],
                    'funding_source' => $s['n'] % 3 === 0 ? 'FInES' : 'MAIIC',
                    'contract_status' => 'Active',
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            if ($rows !== []) {
                \Illuminate\Support\Facades\DB::table('loan_books')->insert($rows);
                $inserted += count($rows);
            }
        }

        $this->line('  loan_portfolio_id ' . $portfolioId . '  ("EIR Test Portfolio")');
        $this->line('  inserted ' . number_format($inserted) . ' loan-book rows across ' . count($periods) . ' periods');
    }

    /** Deterministic decimal probabilities for varied end-to-end ECL testing. */
    private function riskInputs(int $sequence, int $stage): array
    {
        $pdPrefli = match ($stage) {
            1 => 0.010 + (($sequence % 5) * 0.005),
            2 => 0.120 + (($sequence % 5) * 0.030),
            default => 1.000,
        };

        return [
            'pd_prefli' => round($pdPrefli, 6),
            'pd_post_fli' => round(min(1, $pdPrefli * (1.05 + (($sequence % 3) * 0.05))), 6),
            'customer_lgd' => round(0.25 + (($sequence % 5) * 0.08), 6),
            'collection_lgd' => round(0.20 + (($sequence % 6) * 0.07), 6),
        ];
    }

    private function writeChecksums(string $out): void
    {
        $lines = [];
        $files = array_merge(
            glob($out . '/*.{csv,txt,json}', GLOB_BRACE) ?: [],
            glob($out . '/01_Loan_Books/*.csv') ?: []
        );
        sort($files);

        foreach ($files as $file) {
            if (basename($file) === 'SHA256SUMS.txt') continue;
            $relative = ltrim(str_replace([$out, '\\'], ['', '/'], $file), '/');
            $lines[] = hash_file('sha256', $file) . '  ' . $relative;
        }

        file_put_contents($out . '/SHA256SUMS.txt', implode(PHP_EOL, $lines) . PHP_EOL);
        $this->line('  ' . str_pad('SHA256SUMS.txt', 38) . count($lines) . ' files');
    }

    private function put(string $path, array $headers, array $rows): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);
        foreach ($rows as $row) fputcsv($handle, $row);
        fclose($handle);

        $this->line('  ' . str_pad(basename($path), 38) . number_format(count($rows)) . ' rows');
    }
}
