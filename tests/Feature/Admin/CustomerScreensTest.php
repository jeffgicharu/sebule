<?php

namespace Tests\Feature\Admin;

use App\Enums\StaffRole;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Buyer;
use App\Models\User;
use App\Orders\CancelOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PlacesOrders;
use Tests\TestCase;

/**
 * Who has ordered and what they have bought, as staff read it.
 */
class CustomerScreensTest extends TestCase
{
    use PlacesOrders, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => StaffRole::Owner]);
        $this->actingAs($this->owner);
    }

    public function test_a_customer_is_one_phone_number_with_everything_ordered_from_it(): void
    {
        $this->paidOrder(['email' => null]);
        $this->travel(1)->days();
        $this->paidOrder(['name' => 'Wanjiru K. Kamau', 'marketing' => true], quantity: 2);
        $this->placeOrder();
        app(CancelOrder::class)($this->paidOrder(), 'Cancelled by the customer.', $this->owner);
        $this->paidOrder(['name' => 'Achieng Otieno', 'phone' => '254733900100', 'email' => 'achieng@example.com']);

        $wanjiru = Buyer::findOrFail('254712345678');

        // Four orders from one number: two paid for, one waiting, one cancelled.
        $this->assertSame([4, 2, 46_500_00 + 91_500_00], [$wanjiru->orders_placed, $wanjiru->orders_paid, $wanjiru->spent]);
        $this->assertSame('wanjiru@example.com', $wanjiru->email);
        $this->assertSame(2, Buyer::count());

        $this->get(CustomerResource::getUrl('index'))
            ->assertOk()
            ->assertSeeText('0712 345 678')
            ->assertSeeText('2 paid for')
            ->assertSeeText('2 not paid for or cancelled')
            ->assertSeeText('KSh 138,000')
            ->assertSeeText('Achieng Otieno')
            ->assertSeeText('achieng@example.com');
    }

    public function test_a_customer_goes_by_the_name_and_choices_on_their_latest_order(): void
    {
        $this->paidOrder(['name' => 'W. Kamau', 'marketing' => true]);
        $this->travel(1)->hours();
        $this->placeOrder(['name' => 'Wanjiru Kamau', 'email' => null, 'marketing' => false]);

        $buyer = Buyer::sole();

        $this->assertSame(['Wanjiru Kamau', 'wanjiru@example.com', false], [$buyer->name, $buyer->email, $buyer->marketing_opt_in]);
    }

    public function test_customers_are_found_by_name_email_or_phone_and_sorted_by_what_they_spent(): void
    {
        $this->paidOrder();
        $this->paidOrder(['name' => 'Achieng Otieno', 'phone' => '254733900100', 'email' => 'achieng@example.com'], quantity: 3);
        $this->placeOrder(['name' => 'Kamau Njoroge', 'phone' => '254110222333', 'email' => null, 'marketing' => true]);

        [$wanjiru, $achieng, $kamau] = [Buyer::find('254712345678'), Buyer::find('254733900100'), Buyer::find('254110222333')];

        foreach (['achieng', 'ACHIENG@example.com', '0733 900 100', '733900'] as $search) {
            Livewire::test(ListCustomers::class)->searchTable($search)
                ->assertCanSeeTableRecords([$achieng])
                ->assertCanNotSeeTableRecords([$wanjiru, $kamau]);
        }

        Livewire::test(ListCustomers::class)
            ->sortTable('spent', 'desc')
            ->assertCanSeeTableRecords([$achieng, $wanjiru, $kamau], inOrder: true)
            ->filterTable('bought', false)
            ->assertCanSeeTableRecords([$kamau])->assertCanNotSeeTableRecords([$achieng, $wanjiru])
            ->resetTableFilters()
            ->filterTable('marketing_opt_in', true)
            ->assertCanSeeTableRecords([$kamau])->assertCanNotSeeTableRecords([$achieng, $wanjiru]);
    }

    public function test_a_customer_page_lists_their_orders_with_links_and_what_they_have_spent(): void
    {
        $first = $this->paidOrder();
        $this->travel(2)->days();
        $second = $this->placeOrder();
        $other = $this->paidOrder(['name' => 'Achieng Otieno', 'phone' => '254733900100']);

        $this->get(CustomerResource::getUrl('view', ['record' => '254712345678']))
            ->assertOk()
            ->assertSeeText('Wanjiru Kamau')
            ->assertSeeText('Every order placed with 0712 345 678, newest first.')
            ->assertSeeTextInOrder([$second->number, 'Awaiting payment', $first->number])
            ->assertSee('/admin/orders/'.$first->number)
            ->assertSeeText('KSh 46,500')
            ->assertSeeText('Across 1 order paid for, of 2 placed')
            ->assertDontSeeText($other->number);

        $this->get(CustomerResource::getUrl('view', ['record' => '254700000000']))->assertNotFound();
    }

    public function test_fulfilment_staff_do_not_see_the_customer_list(): void
    {
        $this->paidOrder();
        $this->actingAs(User::factory()->create(['role' => StaffRole::Fulfilment]));

        $this->get(CustomerResource::getUrl('index'))->assertForbidden();
        $this->get(CustomerResource::getUrl('view', ['record' => '254712345678']))->assertForbidden();

        foreach ([StaffRole::Manager, StaffRole::Support] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(CustomerResource::getUrl('index'))->assertOk();
        }
    }

    public function test_customers_cannot_be_created_edited_or_deleted_by_hand(): void
    {
        $this->paidOrder();
        $buyer = Buyer::sole();

        $this->assertFalse(CustomerResource::canCreate());
        $this->assertFalse(CustomerResource::canEdit($buyer));
        $this->assertFalse(CustomerResource::canDelete($buyer));
    }
}
