<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Buyer;
use App\Models\Order;
use App\Support\Money;
use App\Support\Phone;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Orders')
                    ->description(fn (Buyer $record): string => 'Every order placed with '.Phone::display($record->phone).', newest first.')
                    ->columnSpan(['default' => 3, 'xl' => 2])
                    ->schema([
                        RepeatableEntry::make('orders')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Order'),
                                TableColumn::make('Placed'),
                                TableColumn::make('Status'),
                                TableColumn::make('Total')->width('8.5rem'),
                            ])
                            ->schema([
                                TextEntry::make('number')
                                    ->weight('medium')
                                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
                                TextEntry::make('created_at')->dateTime('j M Y, H:i'),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('total')
                                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                                    ->extraAttributes(['class' => 'whitespace-nowrap']),
                            ]),
                    ]),

                Section::make('Customer')
                    ->columnSpan(['default' => 3, 'xl' => 1])
                    ->schema([
                        TextEntry::make('phone')
                            ->formatStateUsing(fn (string $state): string => Phone::display($state))
                            ->url(fn (Buyer $record): string => 'tel:+'.$record->phone)
                            ->copyable(),
                        TextEntry::make('email')->placeholder('None given')->copyable(),
                        TextEntry::make('spent')
                            ->label('Spent')
                            ->formatStateUsing(fn (int $state): string => Money::format($state))
                            ->weight('bold')
                            ->helperText(fn (Buyer $record): string => 'Across '.$record->orders_paid.' '.str('order')->plural($record->orders_paid).' paid for, of '.$record->orders_placed.' placed'),
                        TextEntry::make('first_order_at')->label('First ordered')->date('j F Y'),
                        IconEntry::make('marketing_opt_in')->label('Agreed to hear about offers')->boolean(),
                    ]),
            ]);
    }
}
