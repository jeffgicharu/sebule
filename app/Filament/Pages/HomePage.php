<?php

namespace App\Filament\Pages;

use App\Support\StoreSettings as Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The words on the shop's home page. Its rooms, collections and offers follow
 * the catalogue; what is written around them is set here.
 */
class HomePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Home page';

    protected static ?string $title = 'Home page';

    protected static ?string $slug = 'home-page';

    protected string $view = 'filament.pages.settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->role->changesSettings();
    }

    public function mount(): void
    {
        $this->form->fill(config('store.home'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')
                ->label('Open the home page')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(route('home'), shouldOpenInNewTab: true),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('The opening')
                    ->description('The words beside the large photo at the top of the page.')
                    ->statePath('hero')
                    ->columns(2)
                    ->schema([
                        TextInput::make('eyebrow')
                            ->label('Small line above the heading')
                            ->required()
                            ->maxLength(40),
                        TextInput::make('heading')
                            ->helperText('Keep it short: it has to fit beside the sofa in the photo on every screen.')
                            ->required()
                            ->maxLength(60),
                        Textarea::make('text')
                            ->label('Sentence under the heading')
                            ->rows(2)
                            ->required()
                            ->maxLength(180)
                            ->columnSpanFull(),
                    ]),

                Section::make('The dining banner')
                    ->description('The words on the wide photo of the dining room, which leads to the dining category.')
                    ->statePath('banner')
                    ->columns(3)
                    ->schema([
                        TextInput::make('eyebrow')
                            ->label('Small line')
                            ->required()
                            ->maxLength(40),
                        TextInput::make('heading')
                            ->required()
                            ->maxLength(70),
                        TextInput::make('link')
                            ->label('Words of the link')
                            ->required()
                            ->maxLength(50),
                    ]),

                Section::make('What a customer can count on')
                    ->description('The four promises near the foot of the page. The page is laid out for exactly four.')
                    ->schema([
                        Repeater::make('promises')
                            ->hiddenLabel()
                            ->columns(2)
                            ->minItems(4)
                            ->maxItems(4)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable()
                            ->schema([
                                TextInput::make('heading')
                                    ->required()
                                    ->maxLength(40),
                                TextInput::make('text')
                                    ->required()
                                    ->maxLength(110),
                            ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Settings::save([
            'store.home.hero.eyebrow' => trim($data['hero']['eyebrow']),
            'store.home.hero.heading' => trim($data['hero']['heading']),
            'store.home.hero.text' => trim($data['hero']['text']),
            'store.home.banner.eyebrow' => trim($data['banner']['eyebrow']),
            'store.home.banner.heading' => trim($data['banner']['heading']),
            'store.home.banner.link' => trim($data['banner']['link']),
            'store.home.promises' => array_values(array_map(fn (array $promise) => ['heading' => trim($promise['heading']), 'text' => trim($promise['text'])], $data['promises'])),
        ]);

        Notification::make()->title('The home page is saved.')->success()->send();
    }
}
