<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Models\LoanApplication;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\LoanApplicationLinkedApprovalStage;

class LoanApplicationApprovalStageReturned extends Mailable
{
    use Queueable, SerializesModels;
    public  $mailData;
    /**
     * Create a new message instance.
     */
    public function __construct($mailData)
    {
        $this->mailData = $mailData;
    }




    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject:array_key_exists('subject', $this->mailData) ? $this->mailData['subject'] :  'Loan Application #'.$this->mailData['application']->id.' Has Been Returned',
            from: 'creditscoring@no_reply.com',
            to: $this->mailData['to'],
            cc: [],
            bcc: [],
            replyTo: [],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {


        return new Content(
            view: 'emails.tmail',

        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
