<?php

namespace App\Filament\Pages;

use App\Support\Phone;
use App\Support\StoreSettings as Settings;
use BackedEnum;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * What an owner or store manager can change about how the store runs: how
 * customers reach it, when the showroom is open, the VAT rate and how long
 * stock is held for an unpaid order.
 */
class StoreSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Store';

    protected static ?string $title = 'Store settings';

    protected static ?string $slug = 'store-settings';

    protected string $view = 'filament.pages.settings';

    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->role->changesSettings();
    }

    public function mount(): void
    {
        $phone = Phone::normalise((string) config('store.phone'));
        $whatsapp = Phone::normalise((string) config('store.whatsapp'));

        $this->form->fill([
            'phone' => $phone ? Phone::display($phone) : null,
            'whatsapp' => $whatsapp ? Phone::display($whatsapp) : null,
            'email' => config('store.email.reply_to'),
            'street' => config('store.showroom.street'),
            'city' => config('store.showroom.city'),
            'days' => config('store.showroom.days'),
            'opens' => config('store.showroom.opens'),
            'closes' => config('store.showroom.closes'),
            'vat' => config('store.vat_rate') / 100,
            'hold_minutes' => config('store.hold_minutes'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $kenyanMobile = fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            if (filled($value) && Phone::normalise((string) $value) === null) {
                $fail('Enter a Kenyan mobile number, such as 0712 345 678.');
            }
        };

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Reaching the store')
                    ->description('Shown on the contact page. Leave one empty and it is not shown.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('phone')
                            ->label('Phone number customers call')
                            ->tel()
                            ->rule($kenyanMobile),
                        TextInput::make('whatsapp')
                            ->label('WhatsApp number')
                            ->helperText('With a number here, product and order pages offer a WhatsApp chat that opens already saying which piece or order it is about.')
                            ->tel()
                            ->rule($kenyanMobile),
                        TextInput::make('email')
                            ->label('Email address')
                            ->helperText('Also where a reply to an order email goes.')
                            ->email()
                            ->maxLength(190),
                    ]),

                Section::make('Showroom')
                    ->description('Where customers visit and collect orders. Shown at the foot of every page, at checkout and in emails.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('street')
                            ->label('Street or building')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('city')
                            ->label('Town or city')
                            ->required()
                            ->maxLength(80),
                        CheckboxList::make('days')
                            ->label('Open on')
                            ->options(array_combine(self::DAYS, self::DAYS))
                            ->columns(4)
                            ->required()
                            ->columnSpanFull(),
                        TimePicker::make('opens')
                            ->label('Opens at')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('closes')
                            ->label('Closes at')
                            ->seconds(false)
                            ->after('opens')
                            ->validationMessages(['after' => 'The showroom has to close after it opens.'])
                            ->required(),
                    ]),

                Section::make('Selling')
                    ->columns(2)
                    ->schema([
                        TextInput::make('vat')
                            ->label('VAT rate')
                            ->helperText('Prices already include VAT. This is the rate used to show how much of a total it is. Orders already placed keep the rate they were placed at.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(50)
                            ->step(0.01)
                            ->suffix('%')
                            ->required(),
                        TextInput::make('hold_minutes')
                            ->label('Hold stock for an unpaid order for')
                            ->helperText('After this long without payment, the pieces go back on sale.')
                            ->integer()
                            ->minValue(5)
                            ->maxValue(120)
                            ->suffix('minutes')
                            ->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // The days are kept in the order of the week, however they were ticked.
        $days = array_values(array_intersect(self::DAYS, $data['days']));

        Settings::save([
            'store.phone' => filled($data['phone']) ? Phone::normalise($data['phone']) : null,
            'store.whatsapp' => filled($data['whatsapp']) ? Phone::normalise($data['whatsapp']) : null,
            'store.email.reply_to' => filled($data['email']) ? $data['email'] : null,
            'store.showroom.street' => trim($data['street']),
            'store.showroom.city' => trim($data['city']),
            'store.showroom.days' => $days,
            'store.showroom.opens' => Carbon::parse($data['opens'])->format('H:i'),
            'store.showroom.closes' => Carbon::parse($data['closes'])->format('H:i'),
            'store.vat_rate' => (int) round(((float) $data['vat']) * 100),
            'store.hold_minutes' => (int) $data['hold_minutes'],
        ]);

        Notification::make()->title('The store settings are saved.')->success()->send();
    }
}
