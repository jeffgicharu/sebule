<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The things that happen to an order, in the words its timeline uses.
 */
enum OrderEventType: string implements HasLabel
{
    case Placed = 'placed';
    case Paid = 'paid';
    case PaymentFailed = 'payment_failed';
    case Expired = 'expired';
    case Resumed = 'resumed';
    case Cancelled = 'cancelled';
    case RefundDue = 'refund_due';
    case InProduction = 'in_production';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';

    public function getLabel(): string
    {
        return match ($this) {
            self::Placed => 'Order placed',
            self::Paid => 'Payment received',
            self::PaymentFailed => 'Payment did not go through',
            self::Expired => 'Not paid in time, items released',
            self::Resumed => 'Items held again for payment',
            self::Cancelled => 'Order cancelled',
            self::RefundDue => 'Money to send back',
            self::InProduction => 'Being made',
            self::Ready => 'Ready',
            self::OutForDelivery => 'Out for delivery',
            self::Delivered => 'Delivered',
        };
    }
}
