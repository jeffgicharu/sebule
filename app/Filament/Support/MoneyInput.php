<?php

namespace App\Filament\Support;

use App\Support\Money;
use Filament\Forms\Components\TextInput;

/**
 * A text input for an amount in shillings, stored as cents.
 */
class MoneyInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->prefix('KSh')
            ->numeric()
            ->minValue(0)
            ->formatStateUsing(fn (int|string|null $state): int|float|null => blank($state) ? null : ((int) $state) / 100)
            ->dehydrateStateUsing(fn (int|float|string|null $state): ?int => Money::parse($state));
    }
}
