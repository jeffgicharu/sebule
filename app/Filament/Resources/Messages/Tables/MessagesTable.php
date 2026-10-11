<?php

namespace App\Filament\Resources\Messages\Tables;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Enums\OrderEventType;
use App\Models\Message;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('order'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Written')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('recipient')
                    ->label('To')
                    ->formatStateUsing(fn (string $state, Message $record): string => $record->channel === MessageChannel::Sms ? Phone::display($state) : $state)
                    ->searchable()
                    ->description(fn (Message $record): ?string => $record->order?->customer_name),
                TextColumn::make('subject')
                    ->label('Message')
                    ->state(fn (Message $record): string => $record->subject ?? $record->body)
                    ->limit(48)
                    ->description(fn (Message $record): string => $record->channel->getLabel()),
                TextColumn::make('order.number')
                    ->label('Order')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->tooltip(fn (Message $record): ?string => $record->status === MessageStatus::Sent ? null : $record->error),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('channel')
                    ->label('Sent as')
                    ->options(MessageChannel::class),
                SelectFilter::make('status')
                    ->options(MessageStatus::class),
                SelectFilter::make('kind')
                    ->label('About')
                    ->options(collect(OrderEventType::cases())->mapWithKeys(fn (OrderEventType $type) => [$type->value => $type->getLabel()])->all()),
            ])
            ->recordAction('read')
            ->recordActions([
                Action::make('read')
                    ->label('Read')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (Message $record): string => $record->subject ?? 'Text message')
                    ->modalDescription(fn (Message $record): string => 'To '.($record->channel === MessageChannel::Sms ? Phone::display($record->recipient) : $record->recipient)
                        .' on '.$record->created_at->format('j F Y \a\t H:i').'. '.$record->status->getLabel().'.')
                    ->modalContent(fn (Message $record) => view('filament.message', ['message' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('3xl'),
            ])
            ->emptyStateHeading('No messages yet')
            ->emptyStateDescription('Each email and text message the store writes to a customer is kept here, whether or not it was sent.');
    }
}
