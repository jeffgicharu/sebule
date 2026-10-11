<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Payments that need somebody: money taken for an order that was cancelled,
 * which has to be sent back by hand. An order leaves the list when its refund
 * is recorded on its page.
 */
class MoneyToSendBack extends TableWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->role->seesPayments();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Payments needing attention')
            ->query(Order::query()->owedARefund()->with('events'))
            ->defaultSort('closed_at')
            ->columns([
                TextColumn::make('number')
                    ->label('Order')
                    ->weight('medium')
                    ->description(fn (Order $record): string => $record->customer_name),
                TextColumn::make('owed')
                    ->label('To send back')
                    ->state(fn (Order $record): ?string => $record->refundDue()?->note)
                    ->wrap(),
                TextColumn::make('closed_at')
                    ->label('Cancelled')
                    ->since()
                    ->dateTimeTooltip('j M Y, H:i'),
            ])
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Nothing to send back')
            ->emptyStateDescription('No cancelled order has money waiting to be returned.');
    }
}
