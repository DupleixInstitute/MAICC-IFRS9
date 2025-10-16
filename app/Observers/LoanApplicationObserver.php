<?php

namespace App\Observers;

use App\Models\LoanApplication;

class LoanApplicationObserver
{
    /**
     * Handle the LoanApplication "created" event.
     */
    public function created(LoanApplication $loanApplication): void
    {
        //
    }

    /**
     * Handle the LoanApplication "updated" event.
     */
    public function updated(LoanApplication $loanApplication): void
    {
        //check if stage_finished_at is set, if yes remove related reminders
        if ($loanApplication->stage_finished_at) {
            $loanApplication->reminders()->delete();
        }
    }

    /**
     * Handle the LoanApplication "deleted" event.
     */
    public function deleted(LoanApplication $loanApplication): void
    {
        //
    }

    /**
     * Handle the LoanApplication "restored" event.
     */
    public function restored(LoanApplication $loanApplication): void
    {
        //
    }

    /**
     * Handle the LoanApplication "force deleted" event.
     */
    public function forceDeleted(LoanApplication $loanApplication): void
    {
        //
    }
}
