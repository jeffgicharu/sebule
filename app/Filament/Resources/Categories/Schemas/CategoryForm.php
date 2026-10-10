<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\SlugFields;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CategoryForm
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
                        Select::make('parent_id')
                            ->label('Parent category')
                            ->helperText('Categories go two levels deep, such as Living room, then Sofas.')
                            ->relationship(
                                name: 'parent',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, ?Category $record) => $query
                                    ->whereNull('parent_id')
                                    ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                                    ->orderBy('position'),
                            )
                            ->placeholder('None (top level)')
                            ->disabled(fn (?Category $record): bool => (bool) $record?->children()->exists()),
                        TextInput::make('position')
                            ->label('Order')
                            ->helperText('Lower numbers come first among categories at the same level. Leave a new category at 0 to add it at the end.')
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Shown in the shop')
                            ->default(true),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Search engines')
                    ->description('Leave blank to use the category name and description.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextInput::make('seo_title')
                            ->label('Page title')
                            ->maxLength(70),
                        TextInput::make('seo_description')
                            ->label('Page description')
                            ->maxLength(160),
                    ]),
            ]);
    }
}
