<?php

namespace App\Mail;

use App\Models\Message;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An email as it was written and kept in the message log. Where the log holds
 * a stand-in for a private link, the email carries the link itself.
 */
class CustomerEmail extends Mailable
{
    /** @param  array<string, string>  $private  the real value for each stand-in in the stored message */
    public function __construct(public Message $message, #[\SensitiveParameter] public array $private = []) {}

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
            htmlString: strtr($this->message->html, array_map(e(...), $this->private)),
            with: ['body' => strtr($this->message->body, $this->private)],
        );
    }
}
