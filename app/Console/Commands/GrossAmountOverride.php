<?php

namespace App\Console\Commands;

use App\Services\Eir\LoanGrossAmountOverrideService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Propose, approve, reject or withdraw a per-loan gross (carrying) amount override, reconciling
 * an already-closed, already-audited period to the figure the external audit approved. Built to
 * the identical maker-checker, audit-logged pattern as StagingBasisOverride. Every other loan is
 * unaffected whatever is decided here.
 *
 *   php artisan eir:gross-amount-override 104430000087 --propose --amount=138123456.78 --reason="..." --user=1
 *   php artisan eir:gross-amount-override --approve=1 --user=2
 *   php artisan eir:gross-amount-override --reject=1 --user=2 --reason="..."
 *   php artisan eir:gross-amount-override --withdraw=1 --user=1
 *   php artisan eir:gross-amount-override --list
 */
class GrossAmountOverride extends Command
{
    protected $signature = 'eir:gross-amount-override {contract? : Contract id to propose an override for}
        {--propose : Propose an override for the named contract}
        {--amount= : Required with --propose, the audited gross amount}
        {--approve= : Id of a proposed override to approve}
        {--reject= : Id of a proposed override to reject}
        {--withdraw= : Id of a proposed or approved override to withdraw}
        {--reason= : Required with --propose or --reject}
        {--user= : Id of the person taking the action}
        {--list : List every override and its status}';

    protected $description = "Propose, approve, reject or withdraw a per-loan exception to the build's own rebuilt gross amount; maker-checker, audit-logged";

    public function handle(LoanGrossAmountOverrideService $service): int
    {
        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;
        try {
            if ($this->option('list')) {
                $rows = DB::table('loan_gross_amount_overrides')->orderByDesc('id')->get();
                $this->table(['Id', 'Contract', 'Amount', 'Status', 'Proposed by', 'Approved by', 'Reason'],
                    $rows->map(fn ($r) => [$r->id, $r->contract_id, $r->override_amount, $r->status, $r->proposed_by, $r->approved_by, $r->reason])->all());

                return self::SUCCESS;
            }
            if ($this->option('propose')) {
                $contract = (string) $this->argument('contract');
                $reason = (string) $this->option('reason');
                $amount = $this->option('amount');
                if ($contract === '' || $reason === '' || $userId === null || $amount === null) {
                    $this->error('Propose needs a contract id, --amount, --reason and --user.');

                    return self::FAILURE;
                }
                $id = $service->propose($contract, $reason, $userId, (float) $amount);
                $this->info("Gross amount override {$id} proposed for contract {$contract}, awaiting approval.");

                return self::SUCCESS;
            }
            if ($this->option('approve') !== null) {
                if ($userId === null) {
                    $this->error('Approve needs --user.');

                    return self::FAILURE;
                }
                $result = $service->approve((int) $this->option('approve'), $userId);
                $this->info("Gross amount override {$this->option('approve')} is now {$result['status']}.");

                return self::SUCCESS;
            }
            if ($this->option('reject') !== null) {
                $reason = (string) $this->option('reason');
                if ($reason === '' || $userId === null) {
                    $this->error('Reject needs --reason and --user.');

                    return self::FAILURE;
                }
                $result = $service->reject((int) $this->option('reject'), $userId, $reason);
                $this->info("Gross amount override {$this->option('reject')} is now {$result['status']}.");

                return self::SUCCESS;
            }
            if ($this->option('withdraw') !== null) {
                if ($userId === null) {
                    $this->error('Withdraw needs --user.');

                    return self::FAILURE;
                }
                $result = $service->withdraw((int) $this->option('withdraw'), $userId);
                $this->info("Gross amount override {$this->option('withdraw')} is now {$result['status']}.");

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
