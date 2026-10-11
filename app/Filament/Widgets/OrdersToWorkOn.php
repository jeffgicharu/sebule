<?php

namespace App\Filament\Widgets;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Paid orders that are waiting for staff to take their next step, the one
 * that has waited longest first.
 */
class OrdersToWorkOn extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Orders waiting for you')
            ->query(Order::query()
                ->whereIn('status', [OrderStatus::Paid, OrderStatus::InProduction, OrderStatus::Ready, OrderStatus::OutForDelivery])
                ->with('items'))
            ->defaultSort('paid_at')
            ->columns([
                TextColumn::make('number')
                    ->label('Order')
                    ->weight('medium'),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->description(fn (Order $record): string => $record->delivery_method === DeliveryMethod::Delivery ? 'Delivery to '.$record->county : 'Collecting'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('next')
                    ->label('Next')
                    ->state(fn (Order $record): string => ViewOrder::verb($record->nextStatus(), $record)),
                TextColumn::make('paid_at')
                    ->label('Paid')
                    ->since()
                    ->dateTimeTooltip('j M Y, H:i'),
            ])
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Nothing is waiting')
            ->emptyStateDescription('Every paid order has been delivered or collected.');
    }
}
