<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StaffRole: string implements HasLabel
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Fulfilment = 'fulfilment';
    case Support = 'support';

    public function getLabel(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Store manager',
            self::Fulfilment => 'Fulfilment',
            self::Support => 'Support',
        };
    }

    /** Moves orders on: being made, ready, out for delivery, delivered. */
    public function fulfilsOrders(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Fulfilment], true);
    }

    /** Sees what was paid, by whom and with which receipt. */
    public function seesPayments(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Support], true);
    }

    /** Changes how the store runs: delivery zones and fees. */
    public function changesSettings(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /** Cancels an order that is already paid for, which means sending money back. */
    public function sendsMoneyBack(): bool
    {
        return $this === self::Owner;
    }
}
