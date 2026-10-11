<?php

namespace App\Filament\Support;

use App\Enums\StockMovementReason;
use App\Inventory\InsufficientStock;
use App\Inventory\StockLedger;
use App\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Stock counted in or out of one variant by hand, with a reason. It goes
 * through the stock ledger like every other change, so it is written down
 * with who made it and can never take stock below zero.
 */
class AdjustStockAction
{
    public static function make(): Action
    {
        return Action::make('adjustStock')
            ->label('Adjust stock')
            ->icon(Heroicon::OutlinedArrowsUpDown)
            ->visible(fn (): bool => (bool) auth()->user()?->role->handlesStock())
            ->modalHeading(fn (ProductVariant $record): string => 'Adjust stock: '.$record->product->name.($record->optionValues->isNotEmpty() ? ', '.$record->label() : ''))
            ->modalDescription(fn (ProductVariant $record): string => "There are {$record->stock_on_hand} in stock now.")
            ->schema([
                TextInput::make('quantity')
                    ->label('Change')
                    ->helperText('Use a minus sign to remove stock, for example -2.')
                    ->integer()
                    ->required()
                    ->notIn([0]),
                Select::make('reason')
                    ->options(collect(StockMovementReason::manual())
                        ->mapWithKeys(fn (StockMovementReason $reason) => [$reason->value => $reason->getLabel()])
                        ->all())
                    ->required(),
                TextInput::make('note')
                    ->maxLength(255),
            ])
            ->action(function (ProductVariant $record, array $data, StockLedger $ledger): void {
                try {
                    $movement = $ledger->adjust(
                        $record,
                        (int) $data['quantity'],
                        StockMovementReason::from($data['reason']),
                        $data['note'] ?? null,
                        auth()->user(),
                    );
                } catch (InsufficientStock $exception) {
                    Notification::make()
                        ->title('Not enough stock')
                        ->body("Only {$exception->available} in stock, so {$exception->requested} cannot be removed.")
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title("Stock is now {$movement->balance_after}.")
                    ->success()
                    ->send();
            });
    }
}
