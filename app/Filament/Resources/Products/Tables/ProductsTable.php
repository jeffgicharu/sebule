<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Support\Money;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category.parent'))
            ->columns([
                SpatieMediaLibraryImageColumn::make('gallery')
                    ->label('')
                    ->collection(Product::GALLERY)
                    ->conversion('thumb')
                    ->limit(1)
                    ->width('1%'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Product $record): string => $record->category->parent
                        ? "{$record->category->parent->name} › {$record->category->name}"
                        : $record->category->name),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants')
                    ->numeric(),
                TextColumn::make('variants_min_price')
                    ->label('From')
                    ->min('variants', 'price')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '' : Money::format($state))
                    ->placeholder('No price yet'),
                TextColumn::make('variants_sum_stock_on_hand')
                    ->label('In stock')
                    ->sum('variants', 'stock_on_hand')
                    ->numeric(),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('No products yet')
            ->emptyStateDescription('Add a product, give it its options such as colour, then generate the variants customers buy.')
            ->filters([
                SelectFilter::make('status')
                    ->options(ProductStatus::class),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
