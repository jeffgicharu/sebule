<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

/**
 * A name field that fills in the slug as a new record is typed, and the slug
 * field that goes with it.
 */
class SlugFields
{
    public static function name(): TextInput
    {
        return TextInput::make('name')
            ->required()
            ->maxLength(255)
            ->live(onBlur: true)
            ->afterStateUpdated(function (Set $set, ?string $state, string $operation): void {
                if ($operation === 'create') {
                    $set('slug', Str::slug((string) $state));
                }
            });
    }

    public static function slug(): TextInput
    {
        return TextInput::make('slug')
            ->label('Web address name')
            ->helperText('Lower-case words joined by hyphens. Changing it breaks links people have saved.')
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
            ->unique(ignoreRecord: true);
    }
}
