<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\LoanApplicationLinkedApprovalStage;
use Illuminate\Notifications\Messages\MailMessage;

class LoanApplicationApprovalStageAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public LoanApplicationLinkedApprovalStage $linkedStage;
    public string $url;

    /**
     * Create a new notification instance.
     */
    public function __construct(LoanApplicationLinkedApprovalStage $linkedStage)
    {
        $this->linkedStage = $linkedStage;


        $baseUrl = config('app.url');
        //use route helper function to generate the url
        $this->url = $baseUrl . '/loan_application/' . $this->linkedStage->loan_application_id. '/show';
        //make url not editable



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
        $ccField = [];
        // Approver's group email
        if (!empty($this->linkedStage->approver->group_email)) {
            $ccField[] = $this->linkedStage->approver->group_email;
        }

        // Current role's group email
        $role = Role::whereName($this->linkedStage->approver->current_role)->first();
        if ($role && !empty($role->group_email)) {
            $ccField[] = $role->group_email;
        }

        Log::info('URL being used in email: ' . $this->url);

        return (new MailMessage)
            ->subject('Loan Application Approval Assigned')
            ->line('You have been assigned a loan application'. ' '. '#'.$this->linkedStage->loan_application_id. ' for approval.')
            ->line('Stage: ' . $this->linkedStage->stage->name)
            ->action('View Applicationt', $this->url)
            ->line('Thank you for using our application!')
            ->cc($ccField);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'loan_application_id' => $this->linkedStage->loan_application_id,
            'stage' => $this->linkedStage->stage->name,
            'approver_id' => $this->linkedStage->approver->id,
        ];
    }
}
