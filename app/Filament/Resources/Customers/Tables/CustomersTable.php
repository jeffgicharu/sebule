<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Buyer;
use App\Support\Money;
use App\Support\Phone;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    ->description(fn (Buyer $record): string => Phone::display($record->phone))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        fn (Builder $either) => $either
                            ->where('name', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%")
                            ->when(Phone::searchDigits($search), fn (Builder $phone, string $digits) => $phone->orWhere('phone', 'like', "%{$digits}%")),
                    ))
                    ->sortable(),
                TextColumn::make('email')
                    ->placeholder('None given'),
                TextColumn::make('orders_paid')
                    ->label('Orders')
                    ->state(fn (Buyer $record): string => $record->orders_paid.' paid for')
                    ->description(fn (Buyer $record): ?string => $record->orders_placed > $record->orders_paid
                        ? ($record->orders_placed - $record->orders_paid).' not paid for or cancelled'
                        : null)
                    ->sortable(),
                TextColumn::make('spent')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('last_order_at')
                    ->label('Last order')
                    ->date('j M Y')
                    ->sortable(),
                IconColumn::make('marketing_opt_in')
                    ->label('Offers')
                    ->boolean()
                    ->tooltip(fn (Buyer $record): string => $record->marketing_opt_in ? 'Agreed to hear about offers' : 'Has not agreed to hear about offers'),
            ])
            ->defaultSort('last_order_at', 'desc')
            ->filters([
                TernaryFilter::make('bought')
                    ->label('Has bought')
                    ->placeholder('Everybody')
                    ->trueLabel('Has paid for an order')
                    ->falseLabel('Has never paid for one')
                    ->queries(
                        true: fn (Builder $query) => $query->where('orders_paid', '>', 0),
                        false: fn (Builder $query) => $query->where('orders_paid', 0),
                    ),
                TernaryFilter::make('marketing_opt_in')
                    ->label('Offers')
                    ->placeholder('Everybody')
                    ->trueLabel('Agreed to hear about offers')
                    ->falseLabel('Has not agreed'),
            ])
            ->emptyStateHeading('No customers yet')
            ->emptyStateDescription('Everybody who places an order is listed here, with what they have bought.');
    }
}
