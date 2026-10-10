<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => self::inTreeOrder($query)->with('parent')->withCount('children'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight(fn (Category $record): string => $record->parent_id ? 'normal' : 'semibold')
                    ->extraAttributes(fn (Category $record): array => $record->parent_id
                        ? ['style' => 'padding-inline-start: 1.5rem']
                        : []),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('Top level'),
                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->numeric(),
                IconColumn::make('is_active')
                    ->label('Shown')
                    ->boolean(),
            ])
            ->paginated(false)
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Parent')
                    ->relationship('parent', 'name'),
                TernaryFilter::make('is_active')
                    ->label('Shown in the shop'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Category $record): bool => $record->children_count > 0 || $record->products_count > 0),
            ]);
    }

    /**
     * Each top-level category followed by its children, both in their own order.
     *
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    private static function inTreeOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('COALESCE((SELECT parent.position FROM categories AS parent WHERE parent.id = categories.parent_id), categories.position)')
            ->orderByRaw('COALESCE(categories.parent_id, categories.id)')
            ->orderByRaw('categories.parent_id IS NOT NULL')
            ->orderBy('categories.position')
            ->orderBy('categories.name');
    }
}
