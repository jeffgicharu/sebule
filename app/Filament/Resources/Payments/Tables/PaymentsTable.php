<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Payment;
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

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('order'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('order.number')
                    ->label('Order')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Payment $record): string => $record->order->customer_name)
                    ->url(fn (Payment $record): string => OrderResource::getUrl('view', ['record' => $record->order])),
                TextColumn::make('method')
                    ->label('How'),
                TextColumn::make('state')
                    ->label('Result')
                    ->badge()
                    ->description(fn (Payment $record): ?string => $record->state->isFinal() && ! in_array($record->result_code, [null, '0'], true)
                        ? $record->result_description
                        : null)
                    ->wrap(),
                TextColumn::make('receipt')
                    ->searchable()
                    ->copyable()
                    ->placeholder('None'),
                TextColumn::make('phone')
                    ->formatStateUsing(fn (?string $state): ?string => $state ? Phone::display($state) : null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->when(
                        Phone::searchDigits($search),
                        fn (Builder $query, string $digits) => $query->where('phone', 'like', "%{$digits}%"),
                        // A word that is no part of a phone number matches no phone.
                        fn (Builder $query) => $query->whereRaw('false'),
                    ))
                    ->placeholder('None'),
                TextColumn::make('amount')
                    ->label('For the order')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->alignEnd(),
                TextColumn::make('charged')
                    ->label('Asked for')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->alignEnd(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('state')
                    ->label('Result')
                    ->options(PaymentState::class),
                SelectFilter::make('method')
                    ->label('How')
                    ->options(PaymentMethod::class),
                Filter::make('made')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '<=', Carbon::parse($date)->endOfDay())))
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        ($data['from'] ?? null) ? Indicator::make('From '.Carbon::parse($data['from'])->format('j M Y'))->removeField('from') : null,
                        ($data['until'] ?? null) ? Indicator::make('Until '.Carbon::parse($data['until'])->format('j M Y'))->removeField('until') : null,
                    ]))),
            ])
            ->emptyStateHeading('No payments yet')
            ->emptyStateDescription('Each M-Pesa prompt a customer is sent is listed here with what became of it.');
    }
}
