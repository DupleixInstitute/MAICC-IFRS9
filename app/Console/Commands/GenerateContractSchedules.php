<?php

namespace App\Console\Commands;

use App\Models\ContractEir;
use App\Services\Eir\ScheduleGeneratorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tier-2 fallback intake (docs/EIR_Build.md §4 Phase 2): for every in-scope
 * contract that has no cash-flow schedule, generate an annuity schedule from
 * the terms already on the loan tape and store it flagged GENERATED.
 *
 * Imported schedules always win: a contract with any existing schedule rows
 * is never touched. Contracts missing the terms needed to generate (rate,
 * dates, amount) are skipped and reported — they stay at PROXY tier rather
 * than receiving a fabricated schedule.
 */
class GenerateContractSchedules extends Command
{
    protected $signature = 'eir:generate-schedules
        {--period= : Reporting period YYYY-MM to source loan terms from (default: latest)}
        {--frequency=12 : Payments per year assumed for generated schedules}
        {--dry-run : Report what would be generated without writing}';

    protected $description = 'Generate Tier-2 (GENERATED) cash-flow schedules for contracts without an imported schedule';

    public function handle(ScheduleGeneratorService $generator): int
    {
        $period = $this->option('period')
            ?: DB::table('loan_books')->max(DB::raw("LEFT(reporting_period, 7)"));

        if (! $period) {
            $this->error('No loan_books data found — import a loan book first.');
            return self::FAILURE;
        }

        $frequency = (int) $this->option('frequency');
        $dryRun    = (bool) $this->option('dry-run');

        $this->info("Generating schedules from loan terms as at {$period}" . ($dryRun ? ' (dry run)' : ''));

        $loans = DB::table('loan_books')
            ->whereRaw('LEFT(reporting_period, 7) = ?', [$period])
            ->whereNotIn('contract_id', function ($q) {
                $q->select('contract_id')->from('contract_cashflow_schedule');
            })
            ->get([
                'contract_id', 'principal_balance', 'disbursed', 'carrying_amount',
                'interest_rate', 'create_date', 'due_date',
            ]);

        $excluded = ContractEir::where('instrument_type', 'EQUITY_EXCLUDED')
            ->pluck('contract_id')
            ->flip();

        $generated = 0;
        $skipped   = [];

        foreach ($loans as $loan) {
            if (isset($excluded[$loan->contract_id])) {
                $skipped[$loan->contract_id] = 'equity-excluded instrument';
                continue;
            }

            $terms = $this->termsFromTape($loan, $frequency);
            if (is_string($terms)) {
                $skipped[$loan->contract_id] = $terms;
                continue;
            }

            try {
                $schedule = $generator->generate($terms);
            } catch (Throwable $e) {
                $skipped[$loan->contract_id] = 'generator: ' . $e->getMessage();
                continue;
            }

            if (! $dryRun) {
                DB::transaction(function () use ($loan, $schedule) {
                    $now = now();
                    DB::table('contract_cashflow_schedule')->insert(
                        array_map(fn ($row) => [
                            'contract_id'      => $loan->contract_id,
                            'schedule_version' => 1,
                            'effective_from'   => $loan->create_date,
                            'due_date'         => $row['due_date'],
                            'principal_due'    => $row['principal_due'],
                            'interest_due'     => $row['interest_due'],
                            'fee_due'          => 0,
                            'schedule_source'  => 'GENERATED',
                            'created_at'       => $now,
                            'updated_at'       => $now,
                        ], $schedule['rows'])
                    );

                    ContractEir::updateOrCreate(
                        ['contract_id' => $loan->contract_id],
                        ['schedule_source' => 'GENERATED']
                    );
                });
            }

            $generated++;
        }

        $this->info("Generated: {$generated}  Skipped: " . count($skipped));

        foreach ($skipped as $contractId => $reason) {
            $this->line("  skipped {$contractId}: {$reason}");
        }

        // Coverage strip — the number that goes in the audit pack.
        $total = DB::table('loan_books')
            ->whereRaw('LEFT(reporting_period, 7) = ?', [$period])
            ->count();
        $covered = DB::table('loan_books')
            ->whereRaw('LEFT(reporting_period, 7) = ?', [$period])
            ->whereIn('contract_id', function ($q) {
                $q->select('contract_id')->from('contract_cashflow_schedule');
            })
            ->count();
        $this->info("Schedule coverage for {$period}: {$covered}/{$total} contracts" . ($dryRun ? ' (before dry-run writes)' : ''));

        return self::SUCCESS;
    }

    /**
     * Derive generator terms from a tape row, or return a skip reason.
     * The tape stores interest_rate as a percentage (e.g. 32.10).
     */
    private function termsFromTape(object $loan, int $frequency): array|string
    {
        $rate = (float) $loan->interest_rate;
        if ($rate <= 0) {
            return 'no interest rate on tape';
        }
        // Normalise percent-vs-decimal ambiguity: 32.10 → 0.3210.
        $annualRate = $rate > 1 ? $rate / 100 : $rate;

        $principal = (float) ($loan->disbursed ?: 0) > 0
            ? (float) $loan->disbursed
            : (float) ($loan->principal_balance ?: 0);
        if ($principal <= 0) {
            $principal = (float) ($loan->carrying_amount ?: 0);
        }
        if ($principal <= 0) {
            return 'no principal / disbursed / carrying amount on tape';
        }

        if (! $loan->create_date || ! $loan->due_date) {
            return 'missing create_date or due_date';
        }

        try {
            $start = Carbon::parse($loan->create_date);
            $end   = Carbon::parse($loan->due_date);
        } catch (Throwable) {
            return 'unparseable create_date or due_date';
        }

        $termMonths = (int) round($start->diffInMonths($end));
        if ($termMonths < 1) {
            return 'due_date not after create_date';
        }

        $intervalMonths = intdiv(12, $frequency);
        $nPayments = max(1, intdiv($termMonths, $intervalMonths));

        return [
            'principal'         => $principal,
            'annual_rate'       => $annualRate,
            'payments_per_year' => $frequency,
            'n_payments'        => $nPayments,
            'start_date'        => $start,
            'moratorium_months' => 0,
        ];
    }
}
