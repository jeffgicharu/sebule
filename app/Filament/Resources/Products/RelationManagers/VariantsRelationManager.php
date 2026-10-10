<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Catalogue\VariantGenerator;
use App\Enums\StockMovementReason;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Support\MoneyInput;
use App\Inventory\InsufficientStock;
use App\Inventory\StockLedger;
use App\Models\ProductVariant;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Price')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),
                        MoneyInput::make('price')
                            ->helperText('Including VAT.')
                            ->required(),
                        MoneyInput::make('compare_at_price')
                            ->label('Price before the sale')
                            ->helperText('Leave blank unless the variant is on sale.'),
                    ]),
                Section::make('Availability')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('made_to_order')
                            ->helperText('Sold without stock and built after the order is placed.')
                            ->live(),
                        TextInput::make('lead_time_weeks')
                            ->label('Lead time')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(52)
                            ->suffix('weeks')
                            ->required(fn (Get $get): bool => (bool) $get('made_to_order'))
                            ->visible(fn (Get $get): bool => (bool) $get('made_to_order')),
                        TextInput::make('low_stock_threshold')
                            ->label('Warn when stock is at or below')
                            ->numeric()
                            ->minValue(0)
                            ->default(2)
                            ->required(),
                        Toggle::make('is_default')
                            ->label('Shown first on the product page'),
                        Toggle::make('is_active')
                            ->label('On sale in the shop')
                            ->default(true),
                    ]),
                Section::make('Size and weight')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('width_cm')->label('Width')->numeric()->minValue(0)->suffix('cm'),
                        TextInput::make('depth_cm')->label('Depth')->numeric()->minValue(0)->suffix('cm'),
                        TextInput::make('height_cm')->label('Height')->numeric()->minValue(0)->suffix('cm'),
                        TextInput::make('weight_kg')->label('Weight')->numeric()->minValue(0)->suffix('kg'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (ProductVariant $record): string => $record->label())
            ->modifyQueryUsing(fn (Builder $query) => $query->with('optionValues.option'))
            ->columns([
                TextColumn::make('label')
                    ->label('Variant')
                    ->state(fn (ProductVariant $record): string => $record->label())
                    ->weight('medium')
                    ->description(fn (ProductVariant $record): string => $record->sku),
                TextColumn::make('price')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->description(fn (ProductVariant $record): ?string => $record->isOnSale()
                        ? 'Was '.Money::format($record->compare_at_price)
                        : null),
                TextColumn::make('stock_on_hand')
                    ->label('In stock')
                    ->numeric(),
                TextColumn::make('availability')
                    ->state(fn (ProductVariant $record) => $record->availability())
                    ->badge(),
                IconColumn::make('is_default')
                    ->label('Shown first')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('On sale')
                    ->boolean(),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->emptyStateHeading('No variants yet')
            ->emptyStateDescription('A variant is what a customer actually buys: one combination of the options, with its own price and stock.')
            ->headerActions([
                Action::make('generate')
                    ->label('Generate variants')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->modalDescription('Creates a variant for every combination of the options that does not have one yet. Existing variants are not changed.')
                    ->schema([
                        MoneyInput::make('price')
                            ->label('Price for the new variants')
                            ->helperText('You can change each one afterwards.')
                            ->required(),
                    ])
                    ->action(function (array $data, VariantGenerator $generator): void {
                        $created = $generator->generate($this->getOwnerRecord(), (int) $data['price']);

                        Notification::make()
                            ->title($created->isEmpty()
                                ? 'Every combination already has a variant.'
                                : $created->count().' '.str('variant')->plural($created->count()).' created.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('adjustStock')
                    ->label('Adjust stock')
                    ->icon(Heroicon::OutlinedArrowsUpDown)
                    ->modalHeading(fn (ProductVariant $record): string => 'Adjust stock: '.$record->label())
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
                    }),
                // The less frequent actions sit in a menu so the row never runs off the side of the table.
                ActionGroup::make([
                    Action::make('stockHistory')
                        ->label('Stock history')
                        ->icon(Heroicon::OutlinedClipboardDocumentList)
                        ->url(fn (ProductVariant $record): string => StockMovementResource::getUrl('index', ['search' => $record->sku])),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
