<?php

namespace App\Filament\Widgets;

use App\Enums\ProductStatus;
use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductVariant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pieces on sale from stock that have run down to their warning level or
 * sold out. Made-to-order pieces hold no stock and are left out.
 */
class LowStock extends TableWidget
{
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low stock')
            ->query(ProductVariant::query()
                ->where('is_active', true)
                ->where('made_to_order', false)
                ->whereColumn('stock_on_hand', '<=', 'low_stock_threshold')
                ->whereHas('product', fn (Builder $product) => $product->where('status', ProductStatus::Published))
                ->with('product', 'optionValues.option'))
            ->defaultSort('stock_on_hand')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Piece')
                    ->weight('medium')
                    ->description(fn (ProductVariant $record): string => $record->label() ?: $record->sku),
                TextColumn::make('stock_on_hand')
                    ->label('In stock')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'warning')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Sold out' : (string) $state),
                TextColumn::make('low_stock_threshold')
                    ->label('Warns at'),
            ])
            ->recordUrl(fn (ProductVariant $record): string => ProductResource::getUrl('edit', ['record' => $record->product]))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Stock is healthy')
            ->emptyStateDescription('Nothing on sale is at or below its warning level.');
    }
}
