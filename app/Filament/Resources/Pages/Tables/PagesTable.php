<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Enums\FooterGroup;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->weight('medium')
                    ->searchable()
                    ->description(fn (Page $record): string => '/'.$record->slug),
                TextColumn::make('footer_group')
                    ->label('In the footer under')
                    ->formatStateUsing(fn (FooterGroup $state): string => match ($state) {
                        FooterGroup::Help => 'Help',
                        FooterGroup::About => 'Sebule',
                        FooterGroup::Legal => 'The bottom line',
                    })
                    ->placeholder('Not linked'),
                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Last changed')
                    ->date('j M Y'),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->paginated(false)
            ->filters([
                SelectFilter::make('footer_group')
                    ->label('In the footer under')
                    ->options(FooterGroup::class),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label(fn (Page $record): string => $record->is_published ? 'Open' : 'Preview')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Page $record): string => route('shop.page', $record), shouldOpenInNewTab: true),
                EditAction::make(),
            ])
            ->emptyStateHeading('No pages yet')
            ->emptyStateDescription('Write the pages a customer looks for before buying: delivery, returns, and how to reach you.');
    }
}
