<?php

namespace App\Messages;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\OrderEventType;
use App\Jobs\DeliverMessage;
use App\Models\Order;

/**
 * Writes to a customer about their order. Every message is kept, whether or
 * not it goes: an email is sent when the customer gave an address and the
 * store is set up to send, and the text message is written down unsent.
 */
class TellCustomer
{
    public function __invoke(Order $order, OrderEventType $event, ?string $note = null): void
    {
        $order->loadMissing('items', 'payments');

        $wording = OrderWording::for($order, $event, $note);

        if ($wording === null) {
            return;
        }

        if (filled($order->email)) {
            $refusal = EmailAllowance::refusal($order->email);

            $email = $order->messages()->create([
                'channel' => MessageChannel::Email,
                'kind' => $event->value,
                'recipient' => $order->email,
                'subject' => $wording->subject,
                'body' => view('mail.order-text', ['order' => $order, 'wording' => $wording])->render(),
                'html' => view('mail.order', ['order' => $order, 'wording' => $wording])->render(),
                'status' => $refusal ? MessageStatus::NotSent : MessageStatus::Waiting,
                'error' => $refusal,
            ]);

            if ($refusal === null) {
                DeliverMessage::dispatch($email)->afterCommit();
            }
        }

        $order->messages()->create([
            'channel' => MessageChannel::Sms,
            'kind' => $event->value,
            'recipient' => $order->phone,
            'body' => $wording->sms,
            'status' => MessageStatus::NotSent,
            'error' => 'Text messages are switched off.',
        ]);
    }
}
