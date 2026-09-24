<?php

namespace App\Console\Commands;

use App\Jobs\RunEirRevenueJob;
use App\Models\User;
use Illuminate\Console\Command;

class RunEirRevenue extends Command
{
    protected $signature = 'eir:run-revenue {period : YYYY-MM} {--user= : Id of the user the run is recorded against (required; there is no signed-in user on the console)} {--contract=* : Limit the run to contract IDs} {--queue : Dispatch instead of running synchronously} {--recalculate : Supersede existing rows for the period instead of leaving them} {--reason= : Why the period is being recalculated (required with --recalculate)}';
    protected $description = 'Generate the monthly EIR amortised-cost and interest revenue roll-forward';

    public function handle(): int
    {
        $period = (string) $this->argument('period');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) !== 1) {
            $this->error('Period must use YYYY-MM format.');
            return self::INVALID;
        }

        // auth()->id() is null on the console, which used to leave every
        // console run and its superseded rows attributed to nobody. The
        // operator names the user the run is recorded against, and the
        // command refuses to run without a real one.
        $userOption = trim((string) $this->option('user'));
        if ($userOption === '' || ! ctype_digit($userOption)) {
            $this->error('--user=<id> is required: a revenue run must be recorded against the user who ran it.');
            return self::INVALID;
        }
        $user = User::find((int) $userOption);
        if ($user === null) {
            $this->error("No user with id {$userOption} exists; the run was not started.");
            return self::INVALID;
        }

        $recalculate = (bool) $this->option('recalculate');
        $reason = trim((string) $this->option('reason'));
        if ($recalculate && $reason === '') {
            $this->error('--recalculate requires --reason: a restated figure must carry its justification.');
            return self::INVALID;
        }
        if ($recalculate) {
            $this->warn("Recalculating {$period} also voids every later period for each affected contract, because each opening balance is the prior closing. Re-run those periods in order afterwards.");
        }

        $job = new RunEirRevenueJob($period, $this->option('contract') ?: null, $recalculate, $user->id, $reason ?: null);
        if ($this->option('queue')) {
            dispatch($job);
            $this->info('EIR revenue run queued.');

            return self::SUCCESS;
        }

        $summary = app()->call([$job, 'handle']);
        $blocked = $summary['blocked_contracts'];

        $this->info("EIR revenue run completed for {$period}.");
        $this->table(['Requested', 'Created', 'Recalculated', 'Unchanged', 'Blocked', 'Rows superseded', 'Cash from schedule', 'Unclassified cash'], [[
            $summary['requested'], $summary['created'], $summary['recalculated'], $summary['unchanged'], $summary['blocked'],
            $summary['superseded_rows'], $summary['cash_derived_from_schedule'], number_format($summary['unclassified_cash'], 2),
        ]]);

        if ($blocked !== []) {
            $this->newLine();
            $this->warn(count($blocked) . ' contract(s) produced no amortisation row:');
            $this->table(['Contract', 'Reason'], array_map(null, array_keys($blocked), array_values($blocked)));
        }

        return self::SUCCESS;
    }
}
