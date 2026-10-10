<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DeliveryMethod: string implements HasLabel
{
    case Delivery = 'delivery';
    case Collection = 'collection';

    public function getLabel(): string
    {
        return match ($this) {
            self::Delivery => 'Home delivery',
            self::Collection => 'Collection from the showroom',
        };
    }
}
