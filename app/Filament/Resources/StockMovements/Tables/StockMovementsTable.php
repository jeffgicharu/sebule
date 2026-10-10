<?php

namespace App\Filament\Resources\StockMovements\Tables;

use App\Enums\StockMovementReason;
use App\Models\StockMovement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['variant.product', 'variant.optionValues.option', 'user']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('variant.product.name')
                    ->label('Product')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (StockMovement $record): string => $record->variant->label().' · '.$record->variant->sku),
                TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quantity')
                    ->label('Change')
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state)
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->weight('medium'),
                TextColumn::make('balance_after')
                    ->label('Stock after')
                    ->numeric(),
                TextColumn::make('reason')
                    ->description(fn (StockMovement $record): ?string => $record->note)
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('The shop'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('reason')
                    ->options(StockMovementReason::class),
            ])
            ->emptyStateHeading('No stock changes yet')
            ->emptyStateDescription('Every time stock is received, sold, returned or corrected, it is listed here with who did it and why.');
    }
}
