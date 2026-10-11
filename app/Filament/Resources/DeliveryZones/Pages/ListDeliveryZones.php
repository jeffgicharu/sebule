<?php

namespace App\Filament\Resources\DeliveryZones\Pages;

use App\Filament\Resources\DeliveryZones\DeliveryZoneResource;
use App\Models\County;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDeliveryZones extends ListRecords
{
    protected static string $resource = DeliveryZoneResource::class;

    // Matches the menu label, which is written in sentence case.
    protected static ?string $title = 'Delivery zones';

    /** Which counties a customer cannot have a delivery to, so a gap is seen and not found out at checkout. */
    public function getSubheading(): ?string
    {
        $left = County::query()
            ->whereDoesntHave('zone', fn (Builder $zone) => $zone->where('is_active', true))
            ->orderBy('name')
            ->pluck('name');

        return $left->isEmpty()
            ? 'Every county is in a zone that is delivering.'
            : 'Not delivered to: '.$left->join(', ').'.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
