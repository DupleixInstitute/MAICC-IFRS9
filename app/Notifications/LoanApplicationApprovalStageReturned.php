<?php

namespace App\Notifications;

use App\Models\LoanApplicationLinkedApprovalStage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanApplicationApprovalStageReturned extends Notification implements ShouldQueue
{
    use Queueable;

    public LoanApplicationLinkedApprovalStage $linkedStage;

    /**
     * Create a new notification instance.
     */
    public function __construct(LoanApplicationLinkedApprovalStage $linkedStage)
    {
        $this->linkedStage = $linkedStage;
    }


    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Loan Application Approval')
            ->line('An application approval that you did has been returned back to you for review.')
            ->line($this->linkedStage->description)
            ->line('Please login and review it.')
            ->action('View Loan Application', route('loan_applications.show', $this->linkedStage->loan_application_id))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
