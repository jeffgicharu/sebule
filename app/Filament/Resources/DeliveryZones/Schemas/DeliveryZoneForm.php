<?php

namespace App\Filament\Resources\DeliveryZones\Schemas;

use App\Filament\Support\MoneyInput;
use App\Models\County;
use App\Models\DeliveryZone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeliveryZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(80)
                            ->unique(ignoreRecord: true),
                        MoneyInput::make('fee')
                            ->label('Delivery fee')
                            ->helperText('Charged once for an order, whatever it holds. Including VAT.')
                            ->required(),
                        TextInput::make('days_min')
                            ->label('Delivered in, at the soonest')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->suffix('working days')
                            ->required(),
                        TextInput::make('days_max')
                            ->label('And at the latest')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(90)
                            ->suffix('working days')
                            ->gte('days_min')
                            ->validationMessages(['gte' => 'The latest cannot be sooner than the soonest.'])
                            ->required(),
                        Select::make('county_ids')
                            ->label('Counties')
                            ->helperText('A county is in one zone at a time: adding it here takes it out of the zone it was in. A county in no zone is not delivered to.')
                            ->options(fn (): array => County::query()->with('zone')->orderBy('name')->get()
                                ->mapWithKeys(fn (County $county) => [$county->id => $county->name])->all())
                            ->multiple()
                            ->searchable()
                            ->columnSpanFull()
                            ->dehydrated(false)
                            ->afterStateHydrated(fn (Select $component, ?DeliveryZone $record) => $component->state($record?->counties()->pluck('id')->all() ?? []))
                            ->saveRelationshipsUsing(function (DeliveryZone $record, ?array $state): void {
                                $chosen = array_map('intval', $state ?? []);

                                County::query()->where('delivery_zone_id', $record->getKey())->whereNotIn('id', $chosen)->update(['delivery_zone_id' => null]);
                                County::query()->whereIn('id', $chosen)->update(['delivery_zone_id' => $record->getKey()]);
                            }),
                        Toggle::make('is_active')
                            ->label('Delivering to this zone')
                            ->helperText('Switch off to stop taking deliveries here. Customers there can still collect.')
                            ->default(true),
                    ]),
            ]);
    }
}
