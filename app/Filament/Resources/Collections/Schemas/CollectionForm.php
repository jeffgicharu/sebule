<?php

namespace App\Filament\Resources\Collections\Schemas;

use App\Filament\Support\SlugFields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        SlugFields::name(),
                        SlugFields::slug(),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Select::make('products')
                            ->helperText('Products that belong together, shown on one page.')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Shown in the shop')
                            ->default(true),
                    ]),
            ]);
    }
}
