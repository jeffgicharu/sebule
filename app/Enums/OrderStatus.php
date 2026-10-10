<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case InProduction = 'in_production';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingPayment => 'Awaiting payment',
            self::Paid => 'Paid',
            self::InProduction => 'In production',
            self::Ready => 'Ready',
            self::OutForDelivery => 'Out for delivery',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Not paid in time',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::Paid, self::InProduction, self::Ready, self::OutForDelivery => 'info',
            self::Delivered => 'success',
            self::Cancelled, self::Expired => 'gray',
        };
    }

    /** An order that is finished one way or the other and holds no stock it has not paid for. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled, self::Expired], true);
    }

    public function isPaid(): bool
    {
        return in_array($this, [self::Paid, self::InProduction, self::Ready, self::OutForDelivery, self::Delivered], true);
    }
}
