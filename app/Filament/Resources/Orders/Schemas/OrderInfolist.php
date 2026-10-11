<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\DeliveryMethod;
use App\Filament\Resources\Messages\MessageResource;
use App\Messages\OrderWording;
use App\Models\DeliveryZone;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Support\Money;
use App\Support\Phone;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn (?int $state): string => Money::format((int) $state);
        $delivering = fn (Order $record): bool => $record->delivery_method === DeliveryMethod::Delivery;

        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    Section::make('What was ordered')
                        ->schema([
                            TextEntry::make('status')
                                ->badge(),
                            RepeatableEntry::make('items')
                                ->hiddenLabel()
                                ->table([
                                    // The narrow columns are given their width, so an amount is never cut short by a long name beside it.
                                    // On a phone each row becomes a card, and every entry below is labelled as its column is headed.
                                    TableColumn::make('Piece'),
                                    TableColumn::make('Quantity')->width('5.5rem'),
                                    TableColumn::make('Each')->width('8.5rem'),
                                    TableColumn::make('Total')->width('8.5rem'),
                                ])
                                ->schema([
                                    TextEntry::make('product_name')
                                        ->label('Piece')
                                        ->state(fn (OrderItem $record): string => $record->title())
                                        ->weight('medium')
                                        ->helperText(fn (OrderItem $record): string => $record->sku.($record->made_to_order ? '. Made to order, about '.$record->lead_time_weeks.' weeks' : '')),
                                    TextEntry::make('quantity'),
                                    TextEntry::make('unit_price')->label('Each')->formatStateUsing($money)->extraAttributes(['class' => 'whitespace-nowrap']),
                                    TextEntry::make('line_total')->label('Total')->formatStateUsing($money)->extraAttributes(['class' => 'whitespace-nowrap']),
                                ]),
                            Grid::make(4)
                                ->schema([
                                    TextEntry::make('subtotal')->label('Pieces')->formatStateUsing($money),
                                    TextEntry::make('delivery_fee')->label('Delivery')
                                        ->formatStateUsing(fn (int $state, Order $record): string => $delivering($record) ? Money::format($state) : 'Free, collected'),
                                    TextEntry::make('total')->formatStateUsing($money)->weight('bold'),
                                    TextEntry::make('vat_amount')->label('VAT in the total')->formatStateUsing($money),
                                ]),
                        ]),

                    Section::make('Payments')
                        ->schema([
                            RepeatableEntry::make('payments')
                                ->hiddenLabel()
                                ->placeholder('No payment has been tried for this order.')
                                ->table([
                                    TableColumn::make('When')->width('7.5rem'),
                                    TableColumn::make('How'),
                                    TableColumn::make('Result'),
                                    TableColumn::make('Asked for')->width('7rem'),
                                    TableColumn::make('Receipt')->width('10rem'),
                                ])
                                ->schema([
                                    TextEntry::make('created_at')->label('When')->dateTime('j M, H:i')->extraAttributes(['class' => 'whitespace-nowrap']),
                                    TextEntry::make('method')
                                        ->label('How')
                                        ->helperText(fn (Payment $record): ?string => $record->phone ? Phone::display($record->phone) : null),
                                    TextEntry::make('state')
                                        ->label('Result')
                                        ->badge()
                                        ->helperText(fn (Payment $record): ?string => $record->state->isFinal() && $record->result_code !== null && $record->result_code !== '0'
                                            ? $record->result_description
                                            : null),
                                    TextEntry::make('charged')->label('Asked for')->formatStateUsing($money)->extraAttributes(['class' => 'whitespace-nowrap']),
                                    TextEntry::make('receipt')->placeholder('None')->copyable()->extraAttributes(['class' => 'whitespace-nowrap']),
                                ]),
                        ]),

                    Section::make('History')
                        ->description('Everything that has happened to this order, oldest first.')
                        ->schema([
                            RepeatableEntry::make('events')
                                ->hiddenLabel()
                                ->table([
                                    TableColumn::make('When')->width('10.5rem'),
                                    TableColumn::make('What happened'),
                                    TableColumn::make('By')->width('9rem'),
                                ])
                                ->schema([
                                    TextEntry::make('created_at')->label('When')->dateTime('j M Y, H:i')->extraAttributes(['class' => 'whitespace-nowrap']),
                                    TextEntry::make('type')
                                        ->label('What happened')
                                        ->helperText(fn (OrderEvent $record): ?string => $record->note),
                                    TextEntry::make('user.name')->label('By')->placeholder('The shop'),
                                ]),
                        ]),
                ])->columnSpan(['default' => 3, 'xl' => 2]),

                Group::make([
                    Section::make('Customer')
                        ->schema([
                            TextEntry::make('customer_name')->label('Name'),
                            TextEntry::make('phone')
                                ->formatStateUsing(fn (string $state): string => Phone::display($state))
                                ->url(fn (Order $record): string => 'tel:+'.$record->phone)
                                ->copyable(),
                            TextEntry::make('email')->placeholder('None given')->copyable(),
                            IconEntry::make('marketing_opt_in')->label('Agreed to hear about offers')->boolean(),
                        ]),

                    Section::make(fn (Order $record): string => $delivering($record) ? 'Delivery' : 'Collection')
                        ->schema([
                            TextEntry::make('address')
                                ->state(fn (Order $record): string => $delivering($record)
                                    ? $record->addressLine()
                                    : 'Sebule showroom, '.config('store.showroom.street').', '.config('store.showroom.city'))
                                ->helperText(fn (Order $record): ?string => $delivering($record) ? null : OrderWording::hours()),
                            TextEntry::make('directions')
                                ->visible(fn (Order $record): bool => filled($record->directions)),
                            TextEntry::make('zone_name')
                                ->label('Zone')
                                ->visible($delivering)
                                ->helperText(fn (Order $record): string => 'Promised in '.DeliveryZone::describeDays((int) $record->delivery_days_min, (int) $record->delivery_days_max).' from payment'),
                        ]),

                    Section::make('Messages')
                        ->description('What the customer has been sent about this order.')
                        ->schema([
                            RepeatableEntry::make('messages')
                                ->hiddenLabel()
                                ->placeholder('Nothing has been written to the customer yet.')
                                ->contained(false)
                                ->schema([
                                    TextEntry::make('subject')
                                        ->hiddenLabel()
                                        ->state(fn (Message $record): string => $record->subject ?? str($record->body)->limit(70)->toString())
                                        ->weight('medium')
                                        ->url(fn (Message $record): string => MessageResource::getUrl('index', ['search' => $record->order?->number]))
                                        ->helperText(fn (Message $record): string => $record->channel->getLabel().' · '.$record->status->getLabel().' · '.$record->created_at->format('j M, H:i')),
                                ]),
                        ]),
                ])->columnSpan(['default' => 3, 'xl' => 1]),
            ]);
    }
}
