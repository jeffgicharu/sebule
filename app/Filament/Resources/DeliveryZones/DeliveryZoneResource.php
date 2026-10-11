<?php

namespace App\Filament\Resources\DeliveryZones;

use App\Filament\Resources\DeliveryZones\Pages\CreateDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\EditDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\ListDeliveryZones;
use App\Filament\Resources\DeliveryZones\Schemas\DeliveryZoneForm;
use App\Filament\Resources\DeliveryZones\Tables\DeliveryZonesTable;
use App\Models\DeliveryZone;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Where the store delivers and what it charges: each zone is a group of
 * counties with one fee. Owners and store managers set them.
 */
class DeliveryZoneResource extends Resource
{
    protected static ?string $model = DeliveryZone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Delivery zones';

    protected static ?string $breadcrumb = 'Delivery zones';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->role->changesSettings();
    }

    /**
     * A zone is switched off, never deleted, so the orders delivered under it
     * keep saying where they went.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DeliveryZoneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeliveryZonesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliveryZones::route('/'),
            'create' => CreateDeliveryZone::route('/create'),
            'edit' => EditDeliveryZone::route('/{record}/edit'),
        ];
    }
}
