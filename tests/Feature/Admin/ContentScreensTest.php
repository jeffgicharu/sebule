<?php

namespace Tests\Feature\Admin;

use App\Enums\FooterGroup;
use App\Enums\StaffRole;
use App\Filament\Pages\HomePage;
use App\Filament\Pages\StoreSettings as StoreSettingsPage;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Concerns\PlacesOrders;
use Tests\TestCase;

/**
 * What owners and store managers change without a developer: the store's
 * pages, the words on its home page, and how the store runs.
 */
class ContentScreensTest extends TestCase
{
    use PlacesOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => StaffRole::Manager]));
    }

    public function test_the_page_list_shows_every_page_with_where_it_is_linked_and_a_way_to_open_it(): void
    {
        $this->get(PageResource::getUrl('index'))->assertOk()
            ->assertSeeText('Delivery')
            ->assertSeeText('/delivery')
            ->assertSeeText('Terms of sale')
            ->assertSee(url('/delivery'));
    }

    public function test_a_new_page_is_written_as_a_draft_previewed_and_then_published(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Care guide', 'slug' => 'care-guide', 'summary' => 'How to look after solid wood.', 'body' => "## Wood\n\nDust it weekly.", 'footer_group' => FooterGroup::Help, 'is_published' => false])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::where('slug', 'care-guide')->sole();

        $this->assertFalse($page->is_published);
        $this->assertSame((int) Page::max('position'), $page->position);
        $this->assertStringEndsWith('/admin/pages/'.$page->id.'/edit', PageResource::getUrl('edit', ['record' => $page]));

        // Staff read it as it will look; nobody else can.
        $this->get('/care-guide')->assertOk()->assertSeeText('Dust it weekly.')->assertSeeText('This page is not published.');

        Livewire::test(EditPage::class, ['record' => $page->id])
            ->assertActionHasLabel('preview', 'Preview')
            ->assertActionHasUrl('preview', url('/care-guide'))
            ->fillForm(['is_published' => true, 'body' => "## Wood\n\nDust it every week."])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertActionHasLabel('preview', 'Open the page');

        auth()->logout();
        $this->get('/care-guide')->assertOk()->assertSeeText('Dust it every week.')->assertDontSeeText('This page is not published.');
        $this->get(route('home'))->assertSee(url('/care-guide'));
    }

    public function test_a_page_cannot_take_an_address_the_shop_uses_or_another_page_has(): void
    {
        foreach (['admin' => 'not_in', 'cart' => 'not_in', 'delivery' => 'unique', 'Care Guide' => 'regex', 'care_guide' => 'regex', '-care' => 'regex'] as $slug => $rule) {
            Livewire::test(CreatePage::class)
                ->fillForm(['title' => 'Care guide', 'slug' => $slug, 'body' => 'Words.'])
                ->call('create')
                ->assertHasFormErrors(['slug' => $rule]);
        }

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => '', 'slug' => 'care', 'body' => ''])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required', 'body' => 'required']);
    }

    public function test_editing_a_page_changes_what_customers_read_at_once(): void
    {
        $returns = Page::where('slug', 'returns')->sole();
        $this->get('/returns')->assertSeeText('within 7 days of delivery');

        Livewire::test(EditPage::class, ['record' => $returns->id])
            ->fillForm(['title' => 'Returns and exchanges', 'body' => 'You can return a piece within 14 days of delivery.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('/returns')->assertOk()
            ->assertSeeText('within 14 days of delivery')
            ->assertSee('<title>Returns and exchanges | Sebule</title>', escape: false);
        // The link at the foot of every page carries the new title too.
        $this->get(route('home'))->assertSeeText('Returns and exchanges');
    }

    public function test_store_settings_change_how_the_shop_is_reached_and_when_it_is_open(): void
    {
        Livewire::test(StoreSettingsPage::class)
            ->assertFormSet(['street' => 'Ngong Road', 'city' => 'Nairobi', 'vat' => 16, 'hold_minutes' => 15, 'phone' => null, 'whatsapp' => null])
            ->fillForm([
                'phone' => '0712 000 111', 'whatsapp' => '+254 733 000 222', 'email' => 'hello@sebule.test',
                'street' => 'Karen Road', 'city' => 'Nairobi', 'days' => ['Sunday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], 'opens' => '08:30', 'closes' => '17:00',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('The store settings are saved.');

        $this->assertSame(['254712000111', '254733000222', 'hello@sebule.test'], [config('store.phone'), config('store.whatsapp'), config('store.email.reply_to')]);

        $this->get('/contact')->assertOk()
            ->assertSeeText('0712 000 111')
            ->assertSee('https://wa.me/254733000222', escape: false)
            ->assertSee('mailto:hello@sebule.test', escape: false)
            ->assertSeeText('Karen Road, Nairobi')
            // The days are put back in the order of the week.
            ->assertSeeText('Tuesday to Sunday, 8:30 am to 5 pm');
        // At the foot of every page the days and the hours each have a line.
        $this->get(route('home'))->assertSeeText('Karen Road, Nairobi')->assertSeeInOrder(['Tuesday to Sunday', '8:30 am to 5 pm']);
    }

    public function test_the_vat_rate_and_the_hold_apply_to_the_next_order_and_not_to_ones_already_placed(): void
    {
        $before = $this->placeOrder();

        Livewire::test(StoreSettingsPage::class)
            ->fillForm(['vat' => 8, 'hold_minutes' => 30])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->travel(1)->minutes();
        $after = $this->placeOrder();

        $this->assertSame([1600, 800], [$before->refresh()->vat_rate, $after->vat_rate]);
        $this->assertSame((int) round(4650000 * 800 / 10800), $after->vat_amount);
        $this->assertEqualsWithDelta(30, now()->diffInMinutes($after->held_until), 0.1);
        $this->assertEqualsWithDelta(14, now()->diffInMinutes($before->held_until), 0.1);
    }

    public function test_store_settings_refuse_what_makes_no_sense(): void
    {
        Livewire::test(StoreSettingsPage::class)
            ->fillForm(['phone' => '12345', 'whatsapp' => 'call me', 'email' => 'not-an-email', 'street' => '', 'days' => [], 'opens' => '18:00', 'closes' => '09:00', 'vat' => 80, 'hold_minutes' => 1])
            ->call('save')
            ->assertHasFormErrors(['phone', 'whatsapp', 'email', 'street', 'days', 'closes', 'vat', 'hold_minutes']);

        $this->assertSame(0, Setting::count());
    }

    public function test_settings_staff_saved_are_still_in_force_on_the_next_request(): void
    {
        StoreSettings::save(['store.showroom.street' => 'Karen Road', 'store.hold_minutes' => 30]);

        // A new request starts from the shipped configuration and reads what was saved.
        config(['store.showroom.street' => 'Ngong Road', 'store.hold_minutes' => 15]);
        StoreSettings::apply();

        $this->assertSame(['Karen Road', 30], [config('store.showroom.street'), config('store.hold_minutes')]);

        // And again with nothing cached, as after a release.
        Cache::flush();
        config(['store.showroom.street' => 'Ngong Road']);
        StoreSettings::apply();

        $this->assertSame('Karen Road', config('store.showroom.street'));

        // Something that is not a setting is never taken from the table.
        config(['app.debug' => false]);
        Setting::create(['key' => 'app.debug', 'value' => true]);
        StoreSettings::save(['app.key' => 'stolen', 'store.vat_rate' => 1600]);
        StoreSettings::apply();

        $this->assertFalse(config('app.debug'));
        $this->assertNotSame('stolen', config('app.key'));
        $this->assertFalse(Setting::where('key', 'app.key')->exists());
    }

    public function test_the_home_pages_words_are_changed_from_the_back_office(): void
    {
        $this->get(route('home'))->assertSeeText('Furniture for the way Kenya lives.')->assertSeeText('Pay with M-Pesa');

        Livewire::test(HomePage::class)
            ->assertFormSet(fn (array $state): bool => $state['hero']['heading'] === 'Furniture for the way Kenya lives.' && count($state['promises']) === 4)
            ->fillForm(['hero' => ['eyebrow' => 'New this season', 'heading' => 'Built once. Kept for good.', 'text' => 'Solid wood furniture from our Nairobi workshop.'], 'banner' => ['heading' => 'Sunday lunch for twelve.']])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('The home page is saved.');

        $this->get(route('home'))->assertOk()
            ->assertSeeText('New this season')
            ->assertSeeText('Built once. Kept for good.')
            ->assertSeeText('Solid wood furniture from our Nairobi workshop.')
            ->assertSeeText('Sunday lunch for twelve.')
            ->assertSeeText('Delivered across Kenya')
            ->assertDontSeeText('Furniture for the way Kenya lives.');

        Livewire::test(HomePage::class)
            ->fillForm(['hero' => ['heading' => '']])
            ->call('save')
            ->assertHasFormErrors(['hero.heading' => 'required']);
    }

    public function test_only_owners_and_managers_change_content_and_settings(): void
    {
        $page = Page::first();

        foreach ([StaffRole::Fulfilment, StaffRole::Support] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));

            foreach ([PageResource::getUrl('index'), PageResource::getUrl('create'), PageResource::getUrl('edit', ['record' => $page]), StoreSettingsPage::getUrl(), HomePage::getUrl()] as $address) {
                $this->get($address)->assertForbidden();
            }

            Livewire::test(StoreSettingsPage::class)->assertForbidden();
            Livewire::test(HomePage::class)->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => StaffRole::Owner]));
        $this->get(StoreSettingsPage::getUrl())->assertOk();
        $this->get(HomePage::getUrl())->assertOk();
    }
}
