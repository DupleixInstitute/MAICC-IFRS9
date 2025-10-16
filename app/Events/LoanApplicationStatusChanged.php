<?php

namespace App\Events;

use App\Models\LoanApplication;
use App\Models\LoanApplicationLinkedApprovalStage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanApplicationStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public LoanApplicationLinkedApprovalStage $linkedStage;

    /**
     * Create a new event instance.
     */
    public function __construct(LoanApplicationLinkedApprovalStage $linkedStage)
    {
        $this->linkedStage = $linkedStage;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
