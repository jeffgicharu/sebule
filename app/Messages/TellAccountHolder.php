<?php

namespace App\Messages;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Jobs\DeliverMessage;
use App\Models\Customer;
use App\Models\Message;

/**
 * Writes to a customer about their account. Like every message it is kept in
 * the message log, but a link that lets its holder into the account is never
 * kept: the log holds a stand-in, and the real link exists only in the email.
 */
class TellAccountHolder
{
    /** What the message log shows where the email carries the real link. */
    public const PRIVATE_LINK = 'https://link-kept-out-of-the-log.invalid/';

    public function passwordReset(Customer $customer, #[\SensitiveParameter] string $token): void
    {
        $words = [
            'subject' => 'Choose a new password for your Sebule account',
            'heading' => 'Choose a new password.',
            'lines' => [
                'Somebody, we hope you, asked to change the password of the Sebule account for '.$customer->email.'.',
                'The button below opens a page where you choose a new one. It works once, for one hour.',
            ],
            'action' => 'Choose a new password',
            'url' => self::PRIVATE_LINK,
            'why' => 'If you did not ask for this, there is nothing you need to do: your password has not changed.',
        ];

        $refusal = EmailAllowance::refusal($customer->email);

        $message = Message::create([
            'customer_id' => $customer->getKey(),
            'channel' => MessageChannel::Email,
            'kind' => 'password_reset',
            'recipient' => $customer->email,
            'subject' => $words['subject'],
            'body' => view('mail.account-text', $words)->render(),
            'html' => view('mail.account', $words)->render(),
            'status' => $refusal ? MessageStatus::NotSent : MessageStatus::Waiting,
            'error' => $refusal,
        ]);

        if ($refusal === null) {
            DeliverMessage::dispatch($message, [
                self::PRIVATE_LINK => route('account.password.reset', ['token' => $token, 'email' => $customer->email]),
            ])->afterCommit();
        }
    }
}
