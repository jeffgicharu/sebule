<?php

namespace App\Filament\Resources\Collections\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CollectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->numeric(),
                IconColumn::make('is_active')
                    ->label('Shown')
                    ->boolean(),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->emptyStateHeading('No collections yet')
            ->emptyStateDescription('A collection groups products that belong together, such as a matching living room range, and shows them on one page.')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
