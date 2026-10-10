<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductStatus;
use App\Filament\Support\SlugFields;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOptionValue;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Details')
                            ->columns(2)
                            ->schema([
                                SlugFields::name(),
                                SlugFields::slug(),
                                Select::make('category_id')
                                    ->label('Category')
                                    ->relationship(
                                        name: 'category',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query) => $query->with('parent')->orderBy('parent_id')->orderBy('position'),
                                    )
                                    ->getOptionLabelFromRecordUsing(
                                        fn (Category $record): string => $record->parent ? "{$record->parent->name} › {$record->name}" : $record->name,
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                ToggleButtons::make('status')
                                    ->options(ProductStatus::class)
                                    ->default(ProductStatus::Draft)
                                    ->inline()
                                    ->required(),
                                TextInput::make('summary')
                                    ->helperText('One sentence shown under the name in listings.')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                RichEditor::make('description')
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Photos')
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('gallery')
                                    ->label('Photos for every variant')
                                    ->helperText('Room settings and other photos that apply whatever is chosen. Photos of one colour or finish go on that choice, under Options.')
                                    ->collection(Product::GALLERY)
                                    ->image()
                                    ->multiple()
                                    ->reorderable()
                                    ->maxFiles(12)
                                    ->maxSize(10240),
                            ]),
                        Tab::make('Options')
                            ->schema([
                                Repeater::make('options')
                                    ->label('What customers choose between')
                                    ->helperText('For example Colour and Size. After changing these, use "Generate variants" under Variants to create the combinations.')
                                    ->relationship()
                                    ->orderColumn('position')
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                    ->addActionLabel('Add an option')
                                    ->defaultItems(0)
                                    ->schema([
                                        TextInput::make('name')
                                            ->datalist(['Colour', 'Fabric', 'Finish', 'Size'])
                                            ->required()
                                            ->maxLength(50),
                                        Repeater::make('values')
                                            ->label('Choices')
                                            ->relationship()
                                            ->orderColumn('position')
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                            ->addActionLabel('Add a choice')
                                            ->minItems(1)
                                            ->columns(2)
                                            ->schema([
                                                TextInput::make('label')
                                                    ->required()
                                                    ->maxLength(50),
                                                ColorPicker::make('swatch')
                                                    ->helperText('Shown as a colour dot. Leave blank for sizes.'),
                                                SpatieMediaLibraryFileUpload::make('images')
                                                    ->label('Photos of this choice')
                                                    ->collection(ProductOptionValue::IMAGES)
                                                    ->image()
                                                    ->multiple()
                                                    ->reorderable()
                                                    ->maxFiles(8)
                                                    ->maxSize(10240)
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Specifications')
                            ->columns(2)
                            ->schema([
                                Textarea::make('materials')
                                    ->rows(3),
                                Textarea::make('care')
                                    ->label('Care instructions')
                                    ->rows(3),
                                TextInput::make('warranty_months')
                                    ->label('Warranty')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(240)
                                    ->suffix('months'),
                                Toggle::make('assembly_required')
                                    ->label('Needs assembly')
                                    ->inline(false),
                            ]),
                        Tab::make('Search engines')
                            ->columns(2)
                            ->schema([
                                TextInput::make('seo_title')
                                    ->label('Page title')
                                    ->helperText('Leave blank to use the product name.')
                                    ->maxLength(70),
                                TextInput::make('seo_description')
                                    ->label('Page description')
                                    ->helperText('Leave blank to use the summary.')
                                    ->maxLength(160),
                            ]),
                    ]),
            ]);
    }
}
