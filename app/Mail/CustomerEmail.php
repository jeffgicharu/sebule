<?php

namespace App\Mail;

use App\Models\Message;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An email exactly as it was written and kept in the message log.
 */
class CustomerEmail extends Mailable
{
    public function __construct(public Message $message) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: filled(config('store.email.reply_to')) ? [new Address(config('store.email.reply_to'), config('app.name'))] : [],
            subject: $this->message->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.plain',
            htmlString: $this->message->html,
            with: ['body' => $this->message->body],
        );
    }
}
