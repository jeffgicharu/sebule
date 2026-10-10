<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\StaffRole;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email address')
                            ->helperText('They sign in with this.')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('role')
                            ->options(StaffRole::class)
                            ->default(StaffRole::Support)
                            ->required()
                            ->disabled(fn (?User $record): bool => (bool) $record?->is(auth()->user()))
                            ->helperText(fn (?User $record): ?string => $record?->is(auth()->user())
                                ? 'You cannot change your own role.'
                                : 'Owners can do everything, including managing staff.'),
                        TextInput::make('password')
                            ->label(fn (string $operation): string => $operation === 'create' ? 'Password' : 'New password')
                            ->helperText(fn (string $operation): string => $operation === 'create'
                                ? 'At least 12 characters. Give it to them in person and ask them to keep it safe.'
                                : 'Leave blank to keep the current password.')
                            ->password()
                            ->revealable()
                            ->minLength(12)
                            ->maxLength(255)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        Toggle::make('is_active')
                            ->label('Can sign in')
                            ->helperText(fn (?User $record): ?string => $record?->is(auth()->user())
                                ? 'You cannot switch off your own account.'
                                : 'Switch off when someone leaves. Their history is kept.')
                            ->default(true)
                            ->disabled(fn (?User $record): bool => (bool) $record?->is(auth()->user())),
                    ]),
            ]);
    }
}
