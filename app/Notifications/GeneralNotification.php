<?php

namespace App\Notifications;

use App\Models\CommunicationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GeneralNotification extends Notification
{
    use Queueable;

    public $template;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(CommunicationTemplate $template)
    {
        $this->template = $template;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $template = $this->template;
        $body = template_replace_tags([
            'body' => $template->description,
        ]);
        $email = (new MailMessage)
            ->subject($template->subject)->markdown('emails.basic_email', ['body' => $body]);
        return $email;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
