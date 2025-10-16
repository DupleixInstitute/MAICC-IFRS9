<?php

namespace App\Listeners;

use App\Events\LoanApplicationStatusChanged;
use App\Notifications\LoanApplicationApprovalStageIsCurrent;
use App\Notifications\LoanApplicationApprovalStageReturned;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendLoanApplicationStatusChangedNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(LoanApplicationStatusChanged $event): void
    {
        $linkedStage = $event->linkedStage;
        $application = $event->linkedStage->application;
        if ($linkedStage->status === 'sent_back') {
            $nextStage = $application->linkedStages->where('id', '<', $linkedStage->id)->last();
            if (!empty($nextStage)) {
                if (!empty($nextStage->approver)) {
                    $nextStage->approver->notify(new LoanApplicationApprovalStageIsCurrent($nextStage));
                    $nextStage->approver->notify(new LoanApplicationApprovalStageReturned($nextStage));
                }
            }

        }
        if ($linkedStage->status === 'approved') {
            $nextStage = $application->linkedStages->where('id', '>', $linkedStage->id)->first();
            if (!empty($nextStage)) {
                if (!empty($nextStage->approver)) {
                    $nextStage->approver->notify(new LoanApplicationApprovalStageIsCurrent($nextStage));
                }
            } else {
                //complete
            }
        }
    }
}
