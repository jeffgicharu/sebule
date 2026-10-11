<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Opens the page as it was last saved. A page that is not published opens only for staff.
            Action::make('preview')
                ->label(fn (Page $record): string => $record->is_published ? 'Open the page' : 'Preview')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Page $record): string => route('shop.page', $record), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }
}
