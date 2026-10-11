<?php

namespace App\Filament\Resources\DeliveryZones\Pages;

use App\Filament\Resources\DeliveryZones\DeliveryZoneResource;
use App\Models\DeliveryZone;
use Filament\Resources\Pages\CreateRecord;

class CreateDeliveryZone extends CreateRecord
{
    protected static string $resource = DeliveryZoneResource::class;

    /** A new zone goes to the end of the list. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['position'] = (int) DeliveryZone::query()->max('position') + 1;

        return $data;
    }
}
