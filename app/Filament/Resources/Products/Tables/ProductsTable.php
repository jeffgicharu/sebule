<?php

namespace App\Filament\Resources\Products\Tables;

use App\Catalogue\DuplicateProduct;
use App\Enums\ProductStatus;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category.parent', 'media', 'defaultVariant.optionValues.media']))
            ->columns([
                ImageColumn::make('photo')
                    ->label('')
                    ->state(fn (Product $record): ?string => $record->mainPhoto()?->getFullUrl('thumb'))
                    ->imageSize(44)
                    ->square()
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
                // The less frequent actions sit in a menu so the row never runs off the side of the table.
                ActionGroup::make([
                    Action::make('duplicate')
                        ->label('Duplicate')
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->requiresConfirmation()
                        ->modalHeading(fn (Product $record): string => 'Duplicate '.$record->name.'?')
                        ->modalDescription('The copy is a draft with the same details, options, variants and prices, new SKUs, no stock and no photos. Nothing changes on this product.')
                        ->modalSubmitActionLabel('Duplicate')
                        ->action(function (Product $record, DuplicateProduct $duplicate) {
                            $copy = $duplicate($record);

                            Notification::make()->title('Copied as a draft. Give it its own name, SKUs and photos.')->success()->send();

                            return redirect(ProductResource::getUrl('edit', ['record' => $copy]));
                        }),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
