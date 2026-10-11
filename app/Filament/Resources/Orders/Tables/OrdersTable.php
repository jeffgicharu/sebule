<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentState;
use App\Models\Order;
use App\Support\Money;
use App\Support\Phone;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('payments')->withSum('items as pieces', 'quantity'))
            ->columns([
                TextColumn::make('number')
                    ->label('Order')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Order $record): string => (int) $record->pieces.' '.str('piece')->plural((int) $record->pieces)),
                TextColumn::make('created_at')
                    ->label('Placed')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->description(fn (Order $record): string => Phone::display($record->phone))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        fn (Builder $either) => $either
                            ->where('customer_name', 'ilike', "%{$search}%")
                            ->when(Phone::searchDigits($search), fn (Builder $phone, string $digits) => $phone->orWhere('phone', 'like', "%{$digits}%")),
                    )),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('payment')
                    ->state(fn (Order $record): string => self::payment($record)[0])
                    ->description(fn (Order $record): ?string => self::payment($record)[1])
                    ->color(fn (Order $record): ?string => $record->status->isPaid() ? null : 'gray'),
                TextColumn::make('county')
                    ->label('Going to')
                    ->state(fn (Order $record): string => $record->delivery_method === DeliveryMethod::Delivery ? (string) $record->county : 'Collection')
                    ->description(fn (Order $record): ?string => $record->delivery_method === DeliveryMethod::Delivery ? $record->zone_name : 'From the showroom'),
                TextColumn::make('total')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->alignEnd()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderStatus::class)
                    ->multiple(),
                SelectFilter::make('payment')
                    ->label('Payment')
                    ->options(PaymentState::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $state) => $query->whereHas('payments', fn (Builder $payment) => $payment->where('state', $state)),
                    )),
                SelectFilter::make('delivery_zone_id')
                    ->label('Delivery zone')
                    ->relationship('zone', 'name'),
                SelectFilter::make('delivery_method')
                    ->label('Delivery or collection')
                    ->options(DeliveryMethod::class),
                Filter::make('placed')
                    ->schema([
                        DatePicker::make('from')->label('Placed from'),
                        DatePicker::make('until')->label('Placed until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '<=', Carbon::parse($date)->endOfDay())))
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        ($data['from'] ?? null) ? Indicator::make('Placed from '.Carbon::parse($data['from'])->format('j M Y'))->removeField('from') : null,
                        ($data['until'] ?? null) ? Indicator::make('Placed until '.Carbon::parse($data['until'])->format('j M Y'))->removeField('until') : null,
                    ]))),
            ])
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('Orders appear here the moment a customer places one, paid or not.');
    }

    /**
     * How the order stands for money, and the receipt when there is one.
     *
     * @return array{0: string, 1: string|null}
     */
    private static function payment(Order $order): array
    {
        if ($paid = $order->payments->firstWhere('state', PaymentState::Paid)) {
            return ['Paid by '.$paid->method->inSentence(), $paid->receipt];
        }

        $last = $order->payments->first();

        return [$last ? $last->state->getLabel() : 'Nothing tried yet', null];
    }
}
