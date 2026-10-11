<?php

namespace Tests\Feature\Admin;

use App\Enums\StaffRole;
use App\Filament\Resources\DeliveryZones\DeliveryZoneResource;
use App\Filament\Resources\DeliveryZones\Pages\CreateDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\EditDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\ListDeliveryZones;
use App\Models\County;
use App\Models\DeliveryZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PlacesOrders;
use Tests\TestCase;

/**
 * Where the store delivers and what it charges, as owners and managers set it.
 */
class DeliveryZoneScreensTest extends TestCase
{
    use PlacesOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => StaffRole::Manager]));
    }

    public function test_the_list_shows_each_zone_with_its_counties_fee_and_time(): void
    {
        $this->get(DeliveryZoneResource::getUrl('index'))
            ->assertOk()
            ->assertSeeTextInOrder(['Nairobi', 'KSh 1,500', '1 to 3 working days'])
            ->assertSeeText('Kajiado, Kiambu, Machakos')
            ->assertSeeText('KSh 2,500')
            ->assertSeeText('Every county is in a zone that is delivering.');
    }

    public function test_the_list_names_the_counties_nobody_delivers_to(): void
    {
        County::where('name', 'Lamu')->update(['delivery_zone_id' => null]);
        DeliveryZone::where('name', 'Around Nairobi')->update(['is_active' => false]);

        $this->get(DeliveryZoneResource::getUrl('index'))
            ->assertOk()
            ->assertSeeText('Not delivered to: Kajiado, Kiambu, Lamu, Machakos.');
    }

    public function test_changing_a_fee_changes_what_the_next_order_is_charged(): void
    {
        $nairobi = DeliveryZone::where('name', 'Nairobi')->sole();

        Livewire::test(EditDeliveryZone::class, ['record' => $nairobi->getRouteKey()])
            ->assertFormSet(['name' => 'Nairobi', 'fee' => 1500, 'days_min' => 1, 'days_max' => 3, 'is_active' => true])
            ->fillForm(['fee' => 1800, 'days_max' => 2])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([180000, 2], [$nairobi->refresh()->fee, $nairobi->days_max]);

        // An order placed before the change keeps what it was charged; the next one pays the new fee.
        $order = $this->placeOrder();

        $this->assertSame([180000, 4680000, 2], [$order->delivery_fee, $order->total, $order->delivery_days_max]);
    }

    public function test_moving_a_county_takes_it_out_of_the_zone_it_was_in(): void
    {
        $nairobi = DeliveryZone::where('name', 'Nairobi')->sole();
        $around = DeliveryZone::where('name', 'Around Nairobi')->sole();
        $kiambu = County::where('name', 'Kiambu')->sole();
        $city = County::where('name', 'Nairobi')->sole();

        Livewire::test(EditDeliveryZone::class, ['record' => $nairobi->getRouteKey()])
            ->assertFormSet(['county_ids' => [$city->id]])
            ->fillForm(['county_ids' => [$city->id, $kiambu->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($nairobi->id, $kiambu->refresh()->delivery_zone_id);
        $this->assertSame(['Kajiado', 'Machakos'], $around->counties()->pluck('name')->all());
    }

    public function test_a_county_taken_out_of_every_zone_is_no_longer_delivered_to(): void
    {
        $nairobi = DeliveryZone::where('name', 'Nairobi')->sole();

        Livewire::test(EditDeliveryZone::class, ['record' => $nairobi->getRouteKey()])
            ->fillForm(['county_ids' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(County::where('name', 'Nairobi')->value('delivery_zone_id'));
        $this->assertFalse(County::query()->deliveredTo()->where('name', 'Nairobi')->exists());
    }

    public function test_a_new_zone_goes_to_the_end_of_the_list_with_its_counties(): void
    {
        $lamu = County::where('name', 'Lamu')->sole();

        Livewire::test(CreateDeliveryZone::class)
            ->fillForm(['name' => 'Islands', 'fee' => 12000, 'days_min' => 7, 'days_max' => 14, 'county_ids' => [$lamu->id], 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $islands = DeliveryZone::where('name', 'Islands')->sole();

        $this->assertSame([1200000, '7 to 14 working days'], [$islands->fee, $islands->estimate()]);
        $this->assertSame($islands->id, $lamu->refresh()->delivery_zone_id);
        $this->assertSame((int) DeliveryZone::max('position'), $islands->position);
        Livewire::test(ListDeliveryZones::class)->assertCanSeeTableRecords(DeliveryZone::orderBy('position')->get(), inOrder: true);
    }

    public function test_a_zone_needs_a_name_a_fee_and_days_that_make_sense(): void
    {
        Livewire::test(CreateDeliveryZone::class)
            ->fillForm(['name' => 'Nairobi', 'fee' => null, 'days_min' => 5, 'days_max' => 2])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique', 'fee' => 'required', 'days_max' => 'gte']);

        Livewire::test(CreateDeliveryZone::class)
            ->fillForm(['name' => '', 'fee' => -5, 'days_min' => 0, 'days_max' => 2])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'fee', 'days_min']);
    }

    public function test_a_zone_is_switched_off_rather_than_deleted(): void
    {
        $coast = DeliveryZone::where('name', 'Coast')->sole();

        $this->assertFalse(DeliveryZoneResource::canDelete($coast));

        Livewire::test(EditDeliveryZone::class, ['record' => $coast->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(County::query()->deliveredTo()->where('name', 'Mombasa')->exists());
    }

    public function test_only_owners_and_managers_can_change_delivery_zones(): void
    {
        $zone = DeliveryZone::first();

        foreach ([StaffRole::Fulfilment, StaffRole::Support] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));

            $this->withoutVite()->get(DeliveryZoneResource::getUrl('index'))->assertForbidden()
                ->assertSeeText('You do not have access to this page.')
                ->assertSeeText('Back to the back office');
            $this->get(DeliveryZoneResource::getUrl('create'))->assertForbidden();
            $this->get(DeliveryZoneResource::getUrl('edit', ['record' => $zone]))->assertForbidden();
            Livewire::test(EditDeliveryZone::class, ['record' => $zone->getRouteKey()])->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => StaffRole::Owner]));
        $this->get(DeliveryZoneResource::getUrl('edit', ['record' => $zone]))->assertOk();
    }
}
