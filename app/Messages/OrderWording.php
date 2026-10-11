<?php

namespace App\Messages;

use App\Enums\DeliveryMethod;
use App\Enums\OrderEventType;
use App\Enums\PaymentState;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * What the store says to a customer when something happens to their order:
 * the email's subject, heading, paragraphs and button, and the text message.
 */
final class OrderWording
{
    /** @param  list<string>  $lines */
    public function __construct(
        public readonly string $subject,
        public readonly string $heading,
        public readonly array $lines,
        public readonly string $action,
        public readonly string $url,
        public readonly string $sms,
    ) {}

    /** Null for the things that happen to an order without the customer being written to. */
    public static function for(Order $order, OrderEventType $event, ?string $note = null): ?self
    {
        $number = $order->number;
        $first = (string) Str::of($order->customer_name)->before(' ');
        $phone = Phone::display($order->phone);
        $delivering = $order->delivery_method === DeliveryMethod::Delivery;
        $track = route('orders.show', $order);
        $pay = route('orders.pay', $order);
        $held = $order->held_until?->isFuture() ? self::time($order->held_until) : null;
        $weeks = $order->items->where('made_to_order', true)->max('lead_time_weeks');
        $showroom = config('store.showroom');
        $paid = $order->payments->firstWhere('state', PaymentState::Paid);
        // Money is owed back only where some was taken: a test payment takes none.
        $owed = $paid !== null && $paid->charged > 0;

        return match ($event) {
            OrderEventType::Placed => new self(
                "We have your order {$number}",
                "Thank you, {$first}. One step is left.",
                [
                    'Your order is placed'.($held ? " and your pieces are held for you until {$held}" : '').'. Pay '.Money::format($order->total).' by M-Pesa to confirm it.',
                    'You do not need an account. The button below always opens your order.',
                ],
                'Pay for your order', $pay,
                "Sebule: we have your order {$number}. Pay ".Money::format($order->total)." to confirm it: {$pay}",
            ),

            OrderEventType::Paid => new self(
                "Payment received for order {$number}",
                "Thank you, {$first}. Your order is confirmed.",
                array_values(array_filter([
                    'We have your payment'.($paid?->receipt ? ' by '.$paid->method->inSentence().', receipt '.$paid->receipt : '').'.',
                    $delivering
                        ? "We will call {$phone} to agree a delivery day. Delivery to {$order->county} takes ".DeliveryZone::describeDays((int) $order->delivery_days_min, (int) $order->delivery_days_max).'.'
                        : "We will write to you as soon as it is ready to collect from the Sebule showroom on {$showroom['street']}, {$showroom['city']}.",
                    $weeks ? "Pieces that are made to order take about {$weeks} weeks. We will write to you when they are ready." : null,
                ])),
                'See your order', $track,
                "Sebule: payment received for order {$number}. Thank you. Follow your order: {$track}",
            ),

            OrderEventType::PaymentFailed => new self(
                "Your payment for order {$number} did not go through",
                'Your payment did not go through.',
                [
                    'Nothing was taken from you.'.($held ? " Your pieces are still held for you until {$held}." : ''),
                    'You can try again from the button below. If the M-Pesa prompt does not reach your phone, check that the phone is on and unlocked.',
                ],
                'Try the payment again', $pay,
                "Sebule: the payment for order {$number} did not go through. Nothing was taken. Try again: {$pay}",
            ),

            OrderEventType::InProduction => new self(
                "Order {$number} is being made",
                'Your order is being made.',
                ['Our workshop has started on your order.'.($weeks ? " Pieces that are made to order take about {$weeks} weeks." : '').' We will write to you as soon as it is ready.'],
                'See your order', $track,
                "Sebule: order {$number} is now being made. We will tell you when it is ready. {$track}",
            ),

            OrderEventType::Ready => $delivering
                ? new self(
                    "Order {$number} is ready for delivery",
                    'Your order is ready.',
                    ["Your order is packed and ready to leave. We will call {$phone} to agree a delivery day."],
                    'See your order', $track,
                    "Sebule: order {$number} is ready. We will call you to agree a delivery day. {$track}",
                )
                : new self(
                    "Order {$number} is ready to collect",
                    'Your order is ready to collect.',
                    [
                        "Collect it from the Sebule showroom, {$showroom['street']}, {$showroom['city']}, ".self::hours().'.',
                        "Give this order number when you arrive: {$number}.",
                    ],
                    'See your order', $track,
                    "Sebule: order {$number} is ready to collect from our showroom on {$showroom['street']}, ".self::hours().'.',
                ),

            OrderEventType::OutForDelivery => new self(
                "Order {$number} is on its way",
                'Your order is on its way.',
                ['Your order has left for '.$order->addressLine().". The driver will call {$phone} before arriving."],
                'See your order', $track,
                "Sebule: order {$number} is on its way. The driver will call you before arriving. {$track}",
            ),

            OrderEventType::Delivered => new self(
                "Order {$number} has been ".($delivering ? 'delivered' : 'collected'),
                ($delivering ? 'Delivered' : 'Collected').". Enjoy it, {$first}.",
                [
                    'Thank you for buying from Sebule. Keep this message as your record of the order: solid wood furniture is guaranteed for up to three years.',
                    'If anything is not right, tell us and we will put it right.',
                ],
                'See your order', $track,
                "Sebule: order {$number} has been ".($delivering ? 'delivered' : 'collected').'. Thank you for buying from us.',
            ),

            OrderEventType::Cancelled => new self(
                "Order {$number} has been cancelled",
                'Your order has been cancelled.',
                array_values(array_filter([
                    $note,
                    $owed ? 'Your payment will be sent back to the M-Pesa number that paid, within two working days.' : 'Nothing was taken from you.',
                ])),
                'See your order', $track,
                "Sebule: order {$number} has been cancelled. ".($owed ? 'Your payment will be sent back within two working days.' : 'Nothing was taken from you.'),
            ),

            default => null,
        };
    }

    /** "Monday to Saturday, 9 am to 6 pm" */
    public static function hours(): string
    {
        $showroom = config('store.showroom');
        $clock = fn (string $time): string => Carbon::createFromFormat('H:i', $time)->format(str_ends_with($time, ':00') ? 'g a' : 'g:i a');

        return $showroom['days'][0].' to '.end($showroom['days']).', '.$clock($showroom['opens']).' to '.$clock($showroom['closes']);
    }

    /** "6:45 pm today", or with the date when it is another day. */
    private static function time(Carbon $when): string
    {
        return $when->format('g:i a').($when->isToday() ? ' today' : ' on '.$when->format('j F'));
    }
}
