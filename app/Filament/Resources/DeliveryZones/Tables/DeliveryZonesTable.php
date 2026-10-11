<?php

namespace App\Filament\Resources\DeliveryZones\Tables;

use App\Models\DeliveryZone;
use App\Support\Money;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeliveryZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('counties'))
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('counties')
                    ->state(fn (DeliveryZone $record): string => $record->counties->pluck('name')->join(', ') ?: 'No counties')
                    ->wrap(),
                TextColumn::make('fee')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->alignEnd(),
                TextColumn::make('days_min')
                    ->label('Delivered in')
                    ->state(fn (DeliveryZone $record): string => $record->estimate()),
                IconColumn::make('is_active')
                    ->label('Delivering')
                    ->boolean(),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->paginated(false)
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading('No delivery zones')
            ->emptyStateDescription('Until there is a zone with counties in it, customers can only collect from the showroom.');
    }
}
