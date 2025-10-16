<?php

namespace App\Observers;

use App\Models\LoanApplicationReminder;
use App\Models\LoanApplicationLinkedApprovalStage;

class LoanApplicationLinkedApprovalStageObserver
{
    /**
     * Handle the LoanApplicationLinkedApprovalStage "created" event.
     */
    public function created(LoanApplicationLinkedApprovalStage $loanApplicationLinkedApprovalStage): void
    {
        //
    }

    /**
     * Handle the LoanApplicationLinkedApprovalStage "updated" event.
     */
    public function updated(LoanApplicationLinkedApprovalStage $loanApplicationLinkedApprovalStage): void
    {
        if ($loanApplicationLinkedApprovalStage->stage_finished_at) {
           //dele
           $reminders = LoanApplicationReminder::where('loan_application_id', $loanApplicationLinkedApprovalStage->loan_application_id)
               ->where('user_id', $loanApplicationLinkedApprovalStage->approver_id)
               ->delete();
        }
    }

    /**
     * Handle the LoanApplicationLinkedApprovalStage "deleted" event.
     */
    public function deleted(LoanApplicationLinkedApprovalStage $loanApplicationLinkedApprovalStage): void
    {
        //
    }

    /**
     * Handle the LoanApplicationLinkedApprovalStage "restored" event.
     */
    public function restored(LoanApplicationLinkedApprovalStage $loanApplicationLinkedApprovalStage): void
    {
        //
    }

    /**
     * Handle the LoanApplicationLinkedApprovalStage "force deleted" event.
     */
    public function forceDeleted(LoanApplicationLinkedApprovalStage $loanApplicationLinkedApprovalStage): void
    {
        //
    }
}
