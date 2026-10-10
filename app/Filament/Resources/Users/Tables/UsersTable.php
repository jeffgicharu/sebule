<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\StaffRole;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn ($record): string => $record->email),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('role')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label('Can sign in')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->date('j M Y')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->options(StaffRole::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
