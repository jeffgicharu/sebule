<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentState: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for the customer',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled by the customer',
            self::TimedOut => 'Timed out',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Cancelled, self::TimedOut => 'gray',
            self::Failed => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
