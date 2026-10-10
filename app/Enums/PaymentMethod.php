<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Mpesa = 'mpesa';
    case Test = 'test';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mpesa => 'M-Pesa',
            self::Test => 'Test payment',
        };
    }
}
