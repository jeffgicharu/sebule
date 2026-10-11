<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    /** A new page goes to the end of its list. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['position'] = (int) Page::query()->max('position') + 1;

        return $data;
    }

    /** Straight to the editor, where the page can be previewed before it is published. */
    protected function getRedirectUrl(): string
    {
        return PageResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
