<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Generic database notification for platform events (workspace steps,
 * ticket assignments/updates, run completions). Deliberately not queued so
 * the bell badge is correct immediately after the action.
 */
class SystemEventNotification extends Notification
{
    public function __construct(
        public string $kind,
        public string $message,
        public ?string $url = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->kind,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
