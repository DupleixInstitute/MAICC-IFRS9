<?php

namespace App\Console\Commands;

use App\Services\Eir\LoanStagingBasisOverrideService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Propose, approve, reject or withdraw a per-loan staging basis override (December 2025 Loan
 * Book Investigation Findings v1, section 10): for a named loan, use the directive's own
 * already-computed schedule-aware count instead of the governed staging policy's bucket-ageing
 * basis. Maker-checker like every other decision in this system; every step is audit-logged.
 * Every other loan is unaffected whatever is decided here.
 *
 *   php artisan eir:staging-basis-override 104430000087 --propose --reason="..." --user=1
 *   php artisan eir:staging-basis-override --approve=1 --user=2
 *   php artisan eir:staging-basis-override --reject=1 --user=2 --reason="..."
 *   php artisan eir:staging-basis-override --withdraw=1 --user=1
 *   php artisan eir:staging-basis-override --list
 */
class StagingBasisOverride extends Command
{
    protected $signature = 'eir:staging-basis-override {contract? : Contract id to propose an override for}
        {--propose : Propose an override for the named contract}
        {--approve= : Id of a proposed override to approve}
        {--reject= : Id of a proposed override to reject}
        {--withdraw= : Id of a proposed or approved override to withdraw}
        {--reason= : Required with --propose or --reject}
        {--user= : Id of the person taking the action}
        {--list : List every override and its status}';

    protected $description = "Propose, approve, reject or withdraw a per-loan exception to the staging policy's basis; maker-checker, audit-logged, never a typed-in day count";

    public function handle(LoanStagingBasisOverrideService $service): int
    {
        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;
        try {
            if ($this->option('list')) {
                $rows = DB::table('loan_staging_basis_overrides')->orderByDesc('id')->get();
                $this->table(['Id', 'Contract', 'Status', 'Proposed by', 'Approved by', 'Reason'],
                    $rows->map(fn ($r) => [$r->id, $r->contract_id, $r->status, $r->proposed_by, $r->approved_by, $r->reason])->all());

                return self::SUCCESS;
            }
            if ($this->option('propose')) {
                $contract = (string) $this->argument('contract');
                $reason = (string) $this->option('reason');
                if ($contract === '' || $reason === '' || $userId === null) {
                    $this->error('Propose needs a contract id, --reason and --user.');

                    return self::FAILURE;
                }
                $id = $service->propose($contract, $reason, $userId);
                $this->info("Override {$id} proposed for contract {$contract}, awaiting approval.");

                return self::SUCCESS;
            }
            if ($this->option('approve') !== null) {
                if ($userId === null) {
                    $this->error('Approve needs --user.');

                    return self::FAILURE;
                }
                $result = $service->approve((int) $this->option('approve'), $userId);
                $this->info("Override {$this->option('approve')} is now {$result['status']}.");

                return self::SUCCESS;
            }
            if ($this->option('reject') !== null) {
                $reason = (string) $this->option('reason');
                if ($reason === '' || $userId === null) {
                    $this->error('Reject needs --reason and --user.');

                    return self::FAILURE;
                }
                $result = $service->reject((int) $this->option('reject'), $userId, $reason);
                $this->info("Override {$this->option('reject')} is now {$result['status']}.");

                return self::SUCCESS;
            }
            if ($this->option('withdraw') !== null) {
                if ($userId === null) {
                    $this->error('Withdraw needs --user.');

                    return self::FAILURE;
                }
                $result = $service->withdraw((int) $this->option('withdraw'), $userId);
                $this->info("Override {$this->option('withdraw')} is now {$result['status']}.");

                return self::SUCCESS;
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->error('Give one of --propose, --approve, --reject, --withdraw or --list.');

        return self::FAILURE;
    }
}
