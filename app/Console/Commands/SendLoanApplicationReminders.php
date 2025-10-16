<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\LoanApplicationReminder;
use App\Models\LoanApplicationLinkedApprovalStage;
use Illuminate\Console\Command;
// use Illuminate\Bus\Queueable;


class SendLoanApplicationReminders extends Command
{
    // use Queueable;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:loan-applications';
    protected $description = 'Send reminders for loan applications that are overdue';



    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Retrieve pending loan applications with approvers
        $pendingLoans = LoanApplicationLinkedApprovalStage::whereNotNull('approver_id')
            ->whereNull('stage_finished_at')
            ->where('is_current', 1)
            ->with('application') // Eager load the loan application
            ->get();

        // Prepare reminders data
        $reminders = $pendingLoans->map(function ($stage) {
            return [
                'loan_application_id' => $stage->loan_application_id,
                'user_id' => $stage->approver_id,
                'reminder_at' => Carbon::now(),
                'description' => 'Loan Number ' . $stage->loan_application_id . ' is pending your action.',
                'created_at' => now(),
                'updated_at' => now()
            ];
        })->toArray();

        // Insert all reminders in a single query
        LoanApplicationReminder::insert($reminders);

        $this->info('Loan application reminders have been sent.');
    }
}
