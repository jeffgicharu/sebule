<?php

namespace App\Messages;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Models\Message;

/**
 * Whether an email may be sent. The store writes to whatever address a
 * visitor types, so sending is rationed: for the whole store each day, and
 * for any one address.
 */
class EmailAllowance
{
    /** Whether real email leaves this installation, rather than being written to a log. */
    public static function isOn(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }

    /** Why an email to this address should be kept and not sent, or null when it may go. */
    public static function refusal(string $address): ?string
    {
        if (! self::isOn()) {
            return 'Email sending is not switched on.';
        }

        // example.com and its kin are set aside for examples and reach nobody, so nothing is spent on them.
        if (preg_match('/@(.+\.)?example\.(com|org|net)$/i', $address) || str_ends_with(strtolower($address), '.test')) {
            return 'This address is an example, not a real mailbox.';
        }

        $today = Message::query()
            ->where('channel', MessageChannel::Email)
            ->whereIn('status', [MessageStatus::Waiting, MessageStatus::Sent])
            ->where('created_at', '>=', now()->startOfDay());

        if ((clone $today)->where('recipient', $address)->count() >= config('store.email.address_daily_limit')) {
            return 'This address has had its limit of emails for today.';
        }

        if ($today->count() >= config('store.email.daily_limit')) {
            return 'The store has reached its limit of emails for today.';
        }

        return null;
    }
}
