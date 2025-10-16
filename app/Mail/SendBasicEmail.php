<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendBasicEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $body;
    public $subject;
    public $attachments;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($subject, $body, $attachments = [])
    {
        $this->subject = $subject;
        $this->body = $body;
        $this->attachments = $attachments;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $email = $this->markdown('emails.basic_email', ['body' => $this->body])
            ->subject($this->subject)
            ->from(Setting::where('setting_key', 'company_email')->first()->setting_value, Setting::where('setting_key', 'company_name')->first()->setting_value);
        foreach ($this->attachments as $key) {
            $email->attachData($key['file_path'], $key['file_name'], $key['extra_args']);
        }
        return $email;
    }
}
