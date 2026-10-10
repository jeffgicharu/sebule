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
}
