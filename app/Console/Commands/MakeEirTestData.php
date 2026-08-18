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
        {--out= : Output directory (default: storage/app/eir-test-data)}
        {--periods=10 : Monthly loan-book files to emit, starting 2025-01}';

    protected $description = 'Generate a self-consistent synthetic loan book and EIR extracts for end-to-end testing';

    private const RUN_ID = 'TEST-RUN-2025';
    private const GENERATED_ON = '2025-11-30';

    public function handle(ScheduleGeneratorService $generator): int
    {
        $out = rtrim((string) ($this->option('out') ?: storage_path('app/eir-test-data')), '/\\');
        $periodCount = max(1, min(12, (int) $this->option('periods')));
        if (! is_dir($out) && ! mkdir($out, 0755, true) && ! is_dir($out)) {
            $this->error("Could not create {$out}");

            return self::FAILURE;
        }

        $periods = [];
        for ($i = 0; $i < $periodCount; $i++) {
            $periods[] = CarbonImmutable::parse('2025-01-01')->addMonths($i)->format('Y-m');
        }

        $this->info('Building ' . count($this->specs()) . ' contracts over ' . count($periods) . ' periods...');

        $built = [];
        foreach ($this->specs() as $spec) {
            $built[] = $this->build($spec, $generator, $periods);
        }

        $this->writeContractMaster($out, $built);
        $this->writeTransactions($out, $built);
        $this->writeGlInterest($out, $built, $periods);
        $this->writeFees($out, $built);
        $this->writeLoanBooks($out, $built, $periods);
        $this->writeReadme($out, $built, $periods);

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
            // --- Fee-bearing: the whole point of the engine. EIR must exceed
            // the contractual rate by a visible, reconcilable margin. ---
            ['n' => 1, 'purpose' => 'Fees: arrangement + legal', 'principal' => 500_000_000, 'rate' => 0.285, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 36, 'start' => '2024-07-31',
                'fees' => [['ARRANGEMENT_FEE', 12_500_000, 'RECEIVED', 1], ['LEGAL_COST', 2_400_000, 'PAID', 1]], 'expect' => 'EIR > contractual'],
            ['n' => 2, 'purpose' => 'Fees: heavy arrangement', 'principal' => 250_000_000, 'rate' => 0.32, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 24, 'start' => '2024-10-31',
                'fees' => [['ARRANGEMENT_FEE', 11_000_000, 'RECEIVED', 1]], 'expect' => 'EIR > contractual'],
            ['n' => 3, 'purpose' => 'Fees + netting credit line', 'principal' => 180_000_000, 'rate' => 0.30, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 30, 'start' => '2024-09-30',
                'fees' => [['ARRANGEMENT_FEE', 6_000_000, 'RECEIVED', 1], ['LEGAL_COST_REBATE', -1_990_000, 'RECEIVED', 1]], 'expect' => 'Signed fee survives'],

            // --- Frequencies other than monthly: annualisation labels and the
            // quarterly-solved-monthly trap. ---
            ['n' => 4, 'purpose' => 'Quarterly + fees', 'principal' => 96_000_000, 'rate' => 0.3449, 'ppy' => 4, 'frequency' => 'Quarterly', 'n_payments' => 8, 'start' => '2024-06-30',
                'fees' => [['ARRANGEMENT_FEE', 4_000_000, 'RECEIVED', 1]], 'expect' => 'nominal != effective'],
            ['n' => 5, 'purpose' => 'Half-yearly', 'principal' => 150_000_000, 'rate' => 0.26, 'ppy' => 2, 'frequency' => 'Half-Yearly', 'n_payments' => 6, 'start' => '2024-03-31',
                'fees' => [['ARRANGEMENT_FEE', 3_750_000, 'RECEIVED', 1]], 'expect' => 'ppy = 2'],
            ['n' => 6, 'purpose' => 'Annual', 'principal' => 120_000_000, 'rate' => 0.24, 'ppy' => 1, 'frequency' => 'Yearly', 'n_payments' => 4, 'start' => '2024-01-31',
                'fees' => [['ARRANGEMENT_FEE', 2_400_000, 'RECEIVED', 1]], 'expect' => 'ppy = 1'],

            // --- Floating: ECL discounting must label it a proxy. ---
            ['n' => 7, 'purpose' => 'Floating rate', 'principal' => 200_000_000, 'rate' => 0.10, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 36, 'start' => '2024-08-31',
                'rate_basis' => 'Variable', 'fees' => [['ARRANGEMENT_FEE', 4_000_000, 'RECEIVED', 1]], 'expect' => 'FLOATING proxy label'],

            // --- Stage 3 with a real allowance: NET basis and unwind. ---
            ['n' => 8, 'purpose' => 'Stage 3 + allowance', 'principal' => 300_000_000, 'rate' => 0.333, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 24, 'start' => '2024-04-30',
                'fees' => [['ARRANGEMENT_FEE', 9_000_000, 'RECEIVED', 1]], 'stage_path' => 'stage3', 'provision_rate' => 0.45, 'behaviour' => 'stopped', 'stops_at' => '2025-04', 'expect' => 'NET basis + unwind'],
            ['n' => 9, 'purpose' => 'Stage 3, deep allowance', 'principal' => 80_000_000, 'rate' => 0.31, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 18, 'start' => '2024-05-31',
                'fees' => [['ARRANGEMENT_FEE', 2_400_000, 'RECEIVED', 1]], 'stage_path' => 'stage3', 'provision_rate' => 0.70, 'behaviour' => 'stopped', 'stops_at' => '2025-02', 'expect' => 'NET basis + unwind'],

            // --- Movement between stages across the ten periods. ---
            ['n' => 10, 'purpose' => 'Migration 1 -> 2 -> 3', 'principal' => 220_000_000, 'rate' => 0.295, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 30, 'start' => '2024-06-30',
                'fees' => [['ARRANGEMENT_FEE', 6_600_000, 'RECEIVED', 1]], 'stage_path' => 'migrate', 'provision_rate' => 0.35, 'behaviour' => 'partial', 'stops_at' => '2025-05', 'expect' => 'GROSS -> NET mid-run'],
            ['n' => 11, 'purpose' => 'Cure 3 -> 2 -> 1', 'principal' => 140_000_000, 'rate' => 0.28, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 30, 'start' => '2024-05-31',
                'fees' => [['ARRANGEMENT_FEE', 4_200_000, 'RECEIVED', 1]], 'stage_path' => 'cure', 'provision_rate' => 0.30, 'expect' => 'NET -> GROSS mid-run'],

            // --- Lifecycle edges. ---
            ['n' => 12, 'purpose' => 'Matures mid-2025', 'principal' => 60_000_000, 'rate' => 0.27, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 12, 'start' => '2024-05-31',
                'fees' => [['ARRANGEMENT_FEE', 1_800_000, 'RECEIVED', 1]], 'expect' => 'past-maturity path'],
            ['n' => 13, 'purpose' => 'Six-month moratorium', 'principal' => 175_000_000, 'rate' => 0.10, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 36, 'start' => '2024-06-30',
                'moratorium' => 6, 'fees' => [['ARRANGEMENT_FEE', 3_500_000, 'RECEIVED', 1]], 'expect' => 'capitalised moratorium'],
            ['n' => 14, 'purpose' => 'Partial payer', 'principal' => 90_000_000, 'rate' => 0.305, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 24, 'start' => '2024-08-31',
                'fees' => [['ARRANGEMENT_FEE', 2_700_000, 'RECEIVED', 1]], 'behaviour' => 'partial', 'stops_at' => '2025-03', 'stage_path' => 'stage2', 'expect' => 'cash < schedule'],
            ['n' => 15, 'purpose' => 'Non-integral fee only', 'principal' => 110_000_000, 'rate' => 0.29, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 24, 'start' => '2024-07-31',
                'fees' => [['PENALTY_FEE', 1_500_000, 'RECEIVED', 0]], 'expect' => 'EIR == contractual'],

            // --- Deliberately blocked, one named reason each. ---
            ['n' => 16, 'purpose' => 'BLOCKER: equity', 'principal' => 50_000_000, 'rate' => 0.0, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 12, 'start' => '2024-02-29',
                'fees' => [], 'product' => 'EQUITY INVESTMENT', 'expect' => 'out of scope'],
            ['n' => 17, 'purpose' => 'BLOCKER: blank frequency', 'principal' => 75_000_000, 'rate' => 0.30, 'ppy' => 12, 'frequency' => '', 'n_payments' => 24, 'start' => '2024-09-30',
                'fees' => [['ARRANGEMENT_FEE', 2_250_000, 'RECEIVED', 1]], 'expect' => 'FREQUENCY_ASSUMED'],
            ['n' => 18, 'purpose' => 'BLOCKER: principal mismatch', 'principal' => 130_000_000, 'rate' => 0.30, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 24, 'start' => '2024-08-31',
                'fees' => [['ARRANGEMENT_FEE', 3_900_000, 'RECEIVED', 1]], 'truncate_schedule' => true, 'expect' => 'PRINCIPAL_NOT_RECONCILED'],
            ['n' => 19, 'purpose' => 'BLOCKER: no schedule', 'principal' => 95_000_000, 'rate' => 0.31, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 24, 'start' => '2024-10-31',
                'fees' => [['ARRANGEMENT_FEE', 2_850_000, 'RECEIVED', 1]], 'no_schedule' => true, 'expect' => 'ORIGINAL_SCHEDULE_MISSING'],
            ['n' => 20, 'purpose' => 'BLOCKER: unreviewed fee', 'principal' => 85_000_000, 'rate' => 0.30, 'ppy' => 12, 'frequency' => 'Monthly', 'n_payments' => 24, 'start' => '2024-11-30',
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

        $out = [];
        $outstanding = (float) $spec['principal'];
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

    private function stageFor(string $path, int $i, int $total): int
    {
        return match ($path) {
            'stage3' => $i < 2 ? 1 : ($i < 4 ? 2 : 3),
            'stage2' => $i < 3 ? 1 : 2,
            'migrate' => $i < 3 ? 1 : ($i < 6 ? 2 : 3),
            // Cure runs the other way: impaired, then recovering.
            'cure' => $i < 3 ? 3 : ($i < 6 ? 2 : 1),
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

        $this->put($out . '/Extract_A_contract_master.csv', $headers, $rows);
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

        $this->put($out . '/Extract_B_transactions.csv', $headers, $rows);
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

        $this->put($out . '/Extract_C_gl_interest.csv', $headers, $rows);
    }

    private function writeFees(string $out, array $built): void
    {
        $headers = ['contract_id', 'fee_type', 'description', 'amount', 'cashflow_direction',
            'transaction_date', 'gl_account_ref', 'currency', 'source_reference'];

        $rows = [];
        foreach ($built as $c) {
            foreach ($c['spec']['fees'] as $i => $fee) {
                [$type, $amount, $direction, $integral] = $fee;
                $rows[] = [$c['id'], $type, ucwords(strtolower(str_replace('_', ' ', $type))), $amount,
                    $direction, $c['start']->format('Y-m-d'), '4020100', 'MWK',
                    'FEE-' . substr($c['id'], -4) . '-' . ($i + 1)];
            }
        }

        $this->put($out . '/Fees.csv', $headers, $rows);
    }

    private function writeLoanBooks(string $out, array $built, array $periods): void
    {
        $headers = ['contract_id', 'customer_id', 'customer_name', 'product_group', 'product_code',
            'reporting_period', 'create_date', 'due_date', 'industry_code', 'interest_rate',
            'principal_balance', 'approved_amount', 'disbursed', 'repayments', 'carrying_amount',
            'commitments', 'facility_utilisation_rate', 'overdue_days', 'tenor', 'remaining_tenor',
            'expected_loss_provision', 'ifrs9_stage', 'calculated_ifrs9_stage',
            'ifrs9stage_pre_qualitative', 'ifrs9stage_post_qualitative', 'funding_source', 'contract_status'];

        foreach ($periods as $period) {
            $rows = [];
            foreach ($built as $c) {
                $s = $c['spec'];
                $b = $c['balances'][$period] ?? null;
                if (! $b || $b['carrying_amount'] <= 0) continue;

                $months = (int) round($s['n_payments'] * (12 / $s['ppy'])) + $s['moratorium'];
                $maturity = $c['start']->addMonthsNoOverflow($months);
                $remaining = max(0, CarbonImmutable::parse($period . '-01')->diffInMonths($maturity, false));

                $rows[] = [
                    $c['id'], 'CUS' . substr($c['id'], -4), 'Test Counterparty ' . $s['n'],
                    $s['product'], 'TL01', $period, $c['start']->format('Y-m-d'), $maturity->format('Y-m-d'),
                    'A01', round($s['rate'] * 100, 4),
                    $b['carrying_amount'], $s['principal'], $s['principal'], $b['repayments'],
                    $b['carrying_amount'], 0, 1, $b['overdue_days'], $months, $remaining,
                    $b['provision'], $b['stage'], $b['stage'], $b['stage'], $b['stage'],
                    $s['n'] % 3 === 0 ? 'FInES' : 'MAIIC', 'Active',
                ];
            }
            $this->put($out . '/loan_book_' . $period . '.csv', $headers, $rows);
        }
    }

    private function writeReadme(string $out, array $built, array $periods): void
    {
        $lines = [
            '# EIR end-to-end test fixture',
            '',
            'Generated by `php artisan eir:make-test-data`. Every figure is synthetic and',
            'internally consistent: schedules come from `ScheduleGeneratorService`, actual',
            'receipts are derived from those schedules, loan-book balances follow the actual',
            'receipts, and GL interest is posted on the contractual basis.',
            '',
            '## Load order',
            '',
            '1. `loan_book_2025-01.csv` ... `loan_book_' . end($periods) . '.csv` — the monthly tape',
            '2. `Extract_A_contract_master.csv` — contract master (intake type: contract master)',
            '3. `Extract_B_transactions.csv` — scheduled + actual (intake type: contract transactions)',
            '4. `Fees.csv` — fee lines (intake type: fees)',
            '5. `Extract_C_gl_interest.csv` — GL interest (intake type: GL interest)',
            '',
            'Then: classify fees -> calculate EIR -> approve/lock -> `eir:run-revenue` per period.',
            '',
            '## What each contract proves',
            '',
            '| Contract | Purpose | Expected outcome |',
            '|---|---|---|',
        ];
        foreach ($built as $c) {
            $lines[] = '| ' . $c['id'] . ' | ' . $c['spec']['purpose'] . ' | ' . $c['spec']['expect'] . ' |';
        }
        $lines[] = '';
        $lines[] = '## The two checks that matter most';
        $lines[] = '';
        $lines[] = '- **Rate effect must be non-zero.** Contracts 1-14 carry integral fees, so each solved';
        $lines[] = '  EIR must exceed its contractual rate and the GL reconciliation bridge must show a';
        $lines[] = '  material rate effect. Production data has never produced one (MK4.87 on the whole book).';
        $lines[] = '- **Stage 3 must accrue on the net balance.** Contracts 8, 9, 10 and 11 carry real ECL';
        $lines[] = '  allowances, so `interest_basis` must switch to NET and `unwind_amount` must be non-zero.';
        $lines[] = '  Production data has never had a non-zero allowance.';
        $lines[] = '';

        file_put_contents($out . '/README.md', implode(PHP_EOL, $lines) . PHP_EOL);
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
