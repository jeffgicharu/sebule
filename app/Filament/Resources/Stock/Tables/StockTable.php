<?php

namespace App\Filament\Resources\Stock\Tables;

use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Support\AdjustStockAction;
use App\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'optionValues.option']))
            ->columns([
                TextColumn::make('product.name')
                    ->label('Piece')
                    ->weight('medium')
                    ->searchable()
                    ->sortable()
                    // The SKU sits under the name, so the table keeps to a laptop's width.
                    ->description(fn (ProductVariant $record): string => ($record->optionValues->isNotEmpty() ? $record->label().' · ' : '').$record->sku),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stock_on_hand')
                    ->label('In stock')
                    ->numeric()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('availability')
                    ->state(fn (ProductVariant $record) => $record->availability())
                    ->badge(),
                TextColumn::make('low_stock_threshold')
                    ->label('Warns at')
                    ->state(fn (ProductVariant $record): string => $record->made_to_order ? 'Built to order' : (string) $record->low_stock_threshold),
            ])
            ->defaultSort('stock_on_hand')
            ->filters([
                SelectFilter::make('level')
                    ->label('Showing')
                    ->options([
                        'low' => 'At or below the warning level',
                        'out' => 'Sold out',
                        'made' => 'Made to order',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'low' => $query->where('made_to_order', false)->whereColumn('stock_on_hand', '<=', 'low_stock_threshold'),
                        'out' => $query->where('made_to_order', false)->where('stock_on_hand', 0),
                        'made' => $query->where('made_to_order', true),
                        default => $query,
                    }),
            ])
            ->recordActions([
                AdjustStockAction::make(),
                Action::make('history')
                    ->label('History')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('gray')
                    ->url(fn (ProductVariant $record): string => StockMovementResource::getUrl('index', ['search' => $record->sku])),
            ])
            ->emptyStateHeading('Nothing to count yet')
            ->emptyStateDescription('Each variant of each product has its own line here once the catalogue has products in it.');
    }
}
