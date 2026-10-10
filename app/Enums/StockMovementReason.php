<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StockMovementReason: string implements HasLabel
{
    case Received = 'received';
    case Sold = 'sold';
    case Returned = 'returned';
    case Damaged = 'damaged';
    case Correction = 'correction';

    public function getLabel(): string
    {
        return match ($this) {
            self::Received => 'Stock received',
            self::Sold => 'Sold',
            self::Returned => 'Returned by customer',
            self::Damaged => 'Damaged or written off',
            self::Correction => 'Stock count correction',
        };
    }

    /**
     * The reasons staff may pick when they adjust stock by hand.
     *
     * @return array<int, self>
     */
    public static function manual(): array
    {
        return [self::Received, self::Damaged, self::Correction];
    }
}
