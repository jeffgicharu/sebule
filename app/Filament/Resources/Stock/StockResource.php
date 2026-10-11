<?php

namespace App\Filament\Resources\Stock;

use App\Filament\Resources\Stock\Pages\ListStock;
use App\Filament\Resources\Stock\Tables\StockTable;
use App\Models\ProductVariant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * What is in stock, variant by variant, and where to count stock in and out.
 * Fulfilment staff work here without reaching prices or product pages.
 */
class StockResource extends Resource
{
    protected static ?string $model = ProductVariant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Stock';

    protected static ?string $modelLabel = 'stock line';

    protected static ?string $pluralModelLabel = 'stock';

    protected static ?string $slug = 'stock';

    protected static ?string $breadcrumb = 'Stock';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->role->handlesStock();
    }

    public static function table(Table $table): Table
    {
        return StockTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStock::route('/'),
        ];
    }
}
