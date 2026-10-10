<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Availability: string implements HasColor, HasLabel
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';
    case MadeToOrder = 'made_to_order';

    public function getLabel(): string
    {
        return match ($this) {
            self::InStock => 'In stock',
            self::LowStock => 'Low stock',
            self::OutOfStock => 'Out of stock',
            self::MadeToOrder => 'Made to order',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::LowStock => 'warning',
            self::OutOfStock => 'danger',
            self::MadeToOrder => 'info',
        };
    }

    public function canBeBought(): bool
    {
        return $this !== self::OutOfStock;
    }
}
