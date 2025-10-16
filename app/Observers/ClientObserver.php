<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\LoanApplication;
use Illuminate\Support\Facades\Log;

class ClientObserver
{
    /**
     * Handle the Client "created" event.
     */
    public function created(Client $client): void
    {
        //
    }

    /**
     * Handle the Client "updated" event.
     */
    public function updated(Client $client): void
    {
        Log::info('Client updated: ' . $client->id);
        if ($client->isDirty('branch_id')) {
            // Update the branch_id in the loan applications with a single query
            LoanApplication::where('client_id', $client->id)
                ->whereNull('branch_id')
                ->update(['branch_id' => $client->branch_id]);
        }
    }

    /**
     * Handle the Client "deleted" event.
     */
    public function deleted(Client $client): void
    {
        //
    }

    /**
     * Handle the Client "restored" event.
     */
    public function restored(Client $client): void
    {
        //
    }

    /**
     * Handle the Client "force deleted" event.
     */
    public function forceDeleted(Client $client): void
    {
        //
    }
}
