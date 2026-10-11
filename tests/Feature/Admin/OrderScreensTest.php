<?php

namespace Tests\Feature\Admin;

use App\Enums\MessageChannel;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\StaffRole;
use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\County;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Orders\CloseUnpaidOrder;
use App\Orders\MarkOrderPaid;
use App\Orders\MoveOrderOn;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\PlacesOrders;
use Tests\TestCase;

/**
 * The back office for orders: finding one, reading it, moving it on and
 * cancelling it, and who is allowed to do which.
 */
class OrderScreensTest extends TestCase
{
    use PlacesOrders, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => StaffRole::Owner, 'name' => 'Njeri Mwangi']);
        $this->actingAs($this->owner);
    }

    public function test_the_order_list_shows_each_order_newest_first(): void
    {
        $unpaid = $this->placeOrder(['name' => 'Achieng Otieno']);
        $this->travel(5)->minutes();
        $paid = $this->paidOrder();

        $this->get(OrderResource::getUrl('index'))
            ->assertOk()
            ->assertSeeText($paid->number)
            ->assertSeeText('Wanjiru Kamau')
            ->assertSeeText('0712 345 678')
            ->assertSeeText('Paid by a test payment')
            ->assertSeeText('Achieng Otieno')
            ->assertSeeText('Nothing tried yet')
            ->assertSeeText('KSh 46,500');

        Livewire::test(ListOrders::class)->assertCanSeeTableRecords([$paid, $unpaid], inOrder: true);
    }

    public function test_an_order_is_found_by_its_number_its_customer_or_their_phone_however_it_is_typed(): void
    {
        $wanjiru = $this->placeOrder();
        $achieng = $this->placeOrder(['name' => 'Achieng Otieno', 'phone' => '254733900100']);

        foreach ([$wanjiru->number, 'wanjiru', 'Wanjiru Kamau', '0712 345 678', '0712345678', '+254 712 345678', '712345'] as $search) {
            Livewire::test(ListOrders::class)->searchTable($search)
                ->assertCanSeeTableRecords([$wanjiru])
                ->assertCanNotSeeTableRecords([$achieng]);
        }
    }

    public function test_the_order_list_filters_by_status_payment_zone_and_date(): void
    {
        $old = $this->paidOrder(['name' => 'Kamau Njoroge']);
        $this->travel(3)->days();
        $coast = $this->placeOrder(['county_id' => County::where('name', 'Mombasa')->value('id'), 'town' => 'Nyali']);
        $failed = $this->placeOrder();
        $failed->payments()->create([
            'method' => PaymentMethod::Mpesa, 'state' => PaymentState::Cancelled, 'amount' => $failed->total, 'charged' => $failed->total,
            'phone' => '254712345678', 'checkout_request_id' => 'ws_CO_1', 'result_code' => '1032', 'settled_at' => now(),
        ]);
        $collected = $this->placeOrder(['method' => 'collection', 'county_id' => null]);

        Livewire::test(ListOrders::class)
            ->filterTable('status', [OrderStatus::Paid->value])
            ->assertCanSeeTableRecords([$old])->assertCanNotSeeTableRecords([$coast, $failed, $collected])
            ->resetTableFilters()
            ->filterTable('payment', PaymentState::Cancelled->value)
            ->assertCanSeeTableRecords([$failed])->assertCanNotSeeTableRecords([$old, $coast, $collected])
            ->resetTableFilters()
            ->filterTable('delivery_zone_id', $coast->delivery_zone_id)
            ->assertCanSeeTableRecords([$coast])->assertCanNotSeeTableRecords([$old, $failed, $collected])
            ->resetTableFilters()
            ->filterTable('delivery_method', 'collection')
            ->assertCanSeeTableRecords([$collected])->assertCanNotSeeTableRecords([$old, $coast, $failed])
            ->resetTableFilters()
            ->filterTable('placed', ['from' => now()->toDateString()])
            ->assertCanSeeTableRecords([$coast, $failed, $collected])->assertCanNotSeeTableRecords([$old])
            ->resetTableFilters()
            ->filterTable('placed', ['until' => now()->subDay()->toDateString()])
            ->assertCanSeeTableRecords([$old])->assertCanNotSeeTableRecords([$coast, $failed, $collected]);
    }

    public function test_the_tabs_cut_the_list_the_way_the_work_is_done(): void
    {
        $toPrepare = $this->paidOrder();
        $ready = app(MoveOrderOn::class)($this->paidOrder(), OrderStatus::Ready, $this->owner);
        $unpaid = $this->placeOrder();
        $lapsed = $this->placeOrder();
        app(CloseUnpaidOrder::class)($lapsed);

        $see = fn (string $tab, Order $shown) => Livewire::test(ListOrders::class)
            ->set('activeTab', $tab)
            ->assertCanSeeTableRecords([$shown])
            ->assertCanNotSeeTableRecords(collect([$toPrepare, $ready, $unpaid, $lapsed])->reject->is($shown)->all());

        $see('prepare', $toPrepare);
        $see('ready', $ready);
        $see('unpaid', $unpaid);
        $see('closed', $lapsed);
    }

    public function test_the_menu_counts_orders_that_are_paid_and_not_yet_started(): void
    {
        $this->assertNull(OrderResource::getNavigationBadge());

        $this->paidOrder();
        $this->paidOrder();
        app(MoveOrderOn::class)($this->paidOrder(), OrderStatus::Ready, $this->owner);
        $this->placeOrder();

        $this->assertSame('2', OrderResource::getNavigationBadge());
    }

    public function test_an_order_page_shows_what_was_bought_who_by_how_it_was_paid_and_what_has_happened(): void
    {
        $order = $this->placeOrder(['directions' => 'Green gate opposite the pharmacy', 'marketing' => true], quantity: 2);
        $payment = $order->payments()->create([
            'method' => PaymentMethod::Mpesa, 'state' => PaymentState::Paid, 'amount' => $order->total, 'charged' => $order->total,
            'phone' => '254722000111', 'checkout_request_id' => 'ws_CO_1', 'receipt' => 'TJA5K2PXQR', 'result_code' => '0', 'settled_at' => now(),
        ]);
        app(MarkOrderPaid::class)($order, $payment);
        app(MoveOrderOn::class)($order, OrderStatus::Ready, $this->owner);

        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSeeText('Order '.$order->number)
            ->assertSeeText('Wanjiru Kamau, placed '.$order->created_at->format('j F Y'))
            // What was ordered.
            ->assertSeeText('Amani sofa')
            ->assertSeeText($order->items->first()->sku)
            ->assertSeeText('KSh 90,000')
            ->assertSeeText('KSh 1,500')
            ->assertSeeText('KSh 91,500')
            ->assertSeeText('KSh 12,620.69')
            // The customer and where it goes.
            ->assertSeeText('0712 345 678')
            ->assertSeeText('wanjiru@example.com')
            ->assertSeeText('A2, Riara Court, Kilimani, Nairobi')
            ->assertSeeText('Green gate opposite the pharmacy')
            ->assertSeeText('Promised in 1 to 3 working days from payment')
            // The payment.
            ->assertSeeText('TJA5K2PXQR')
            ->assertSeeText('0722 000 111')
            // The history, with who did what.
            ->assertSeeTextInOrder(['Order placed', 'Payment received', 'M-Pesa, receipt TJA5K2PXQR', 'Ready', 'Njeri Mwangi'])
            // What the customer was sent.
            ->assertSeeText("Order {$order->number} is ready for delivery");
    }

    public function test_the_order_address_in_the_back_office_is_its_number_and_never_its_secret_link(): void
    {
        $order = $this->placeOrder();

        $this->assertStringEndsWith('/admin/orders/'.$order->number, OrderResource::getUrl('view', ['record' => $order]));
        $this->get('/admin/orders/'.$order->token)->assertNotFound();
    }

    public function test_staff_move_an_order_on_from_its_page_and_the_customer_is_told(): void
    {
        $order = $this->paidOrder();

        Livewire::test(ViewOrder::class, ['record' => $order->number])
            ->assertActionVisible('to_ready')
            ->assertActionHasLabel('to_ready', 'Mark as ready')
            ->assertActionHidden('to_in_production')
            ->assertActionHidden('to_out_for_delivery')
            ->assertActionHidden('to_delivered')
            ->callAction('to_ready')
            ->assertNotified("Order {$order->number} is now: ready.")
            ->assertActionHidden('to_ready')
            ->assertActionVisible('to_out_for_delivery')
            ->assertActionHasLabel('to_out_for_delivery', 'Send out for delivery')
            ->callAction('to_out_for_delivery')
            ->assertActionHasLabel('to_delivered', 'Mark as delivered')
            ->callAction('to_delivered')
            ->assertActionHidden('to_delivered')
            ->assertActionHidden('cancel');

        $order->refresh();

        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertTrue($order->events()->where('type', OrderEventType::Delivered)->sole()->user->is($this->owner));
        $this->assertSame(
            ['placed', 'paid', 'ready', 'out_for_delivery', 'delivered'],
            Message::where('order_id', $order->id)->where('channel', MessageChannel::Sms)->orderBy('id')->pluck('kind')->all(),
        );
    }

    public function test_a_button_pressed_on_a_page_that_is_out_of_date_does_nothing(): void
    {
        $order = $this->paidOrder();
        $page = Livewire::test(ViewOrder::class, ['record' => $order->number]);

        // Somebody else marks it ready while this page still offers "Mark as ready".
        app(MoveOrderOn::class)($order, OrderStatus::Ready, User::factory()->create(['role' => StaffRole::Fulfilment]));

        $this->pressAnyway($page, 'to_ready');

        $this->assertSame(OrderStatus::Ready, $order->refresh()->status);
        $this->assertSame(1, $order->events()->where('type', OrderEventType::Ready)->count());
    }

    public function test_an_order_that_is_collected_is_marked_as_collected(): void
    {
        $order = app(MoveOrderOn::class)($this->paidOrder(['method' => 'collection', 'county_id' => null]), OrderStatus::Ready, $this->owner);

        Livewire::test(ViewOrder::class, ['record' => $order->number])
            ->assertActionHidden('to_out_for_delivery')
            ->assertActionHasLabel('to_delivered', 'Mark as collected');
    }

    public function test_cancelling_from_the_order_page_needs_a_reason_and_returns_the_stock(): void
    {
        $piece = $this->piece(stock: 4);
        $order = $this->placeOrder(piece: $piece);

        Livewire::test(ViewOrder::class, ['record' => $order->number])
            ->mountAction('cancel')
            ->callMountedAction()
            ->assertHasActionErrors(['reason' => 'required'])
            ->setActionData(['reason' => 'Placed twice by mistake.'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified("Order {$order->number} is cancelled.")
            ->assertActionHidden('cancel');

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(4, $piece->refresh()->stock_on_hand);
        $this->assertSame('Placed twice by mistake.', $order->events()->where('type', OrderEventType::Cancelled)->sole()->note);
    }

    public function test_support_staff_can_read_orders_and_cancel_unpaid_ones_but_not_move_them_on(): void
    {
        $this->actingAs(User::factory()->create(['role' => StaffRole::Support]));

        $paid = $this->paidOrder();
        $unpaid = $this->placeOrder();

        $this->get(OrderResource::getUrl('index'))->assertOk();
        $this->get(OrderResource::getUrl('view', ['record' => $paid]))->assertOk();

        $page = Livewire::test(ViewOrder::class, ['record' => $paid->number])
            ->assertActionHidden('to_ready')
            ->assertActionHidden('cancel');

        $this->pressAnyway($page, 'to_ready');
        $this->pressAnyway($page, 'cancel');

        $this->assertSame(OrderStatus::Paid, $paid->refresh()->status);

        Livewire::test(ViewOrder::class, ['record' => $unpaid->number])
            ->assertActionVisible('cancel')
            ->callAction('cancel', ['reason' => 'The customer asked us to.']);

        $this->assertSame(OrderStatus::Cancelled, $unpaid->refresh()->status);
    }

    public function test_only_an_owner_can_cancel_an_order_that_is_paid_for(): void
    {
        $order = $this->paidOrder();

        foreach ([StaffRole::Manager, StaffRole::Fulfilment, StaffRole::Support] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));

            $this->pressAnyway(Livewire::test(ViewOrder::class, ['record' => $order->number])->assertActionHidden('cancel'), 'cancel');

            $this->assertSame(OrderStatus::Paid, $order->refresh()->status, $role->value);
        }

        $this->actingAs($this->owner);

        Livewire::test(ViewOrder::class, ['record' => $order->number])
            ->assertActionVisible('cancel')
            ->callAction('cancel', ['reason' => 'The frame was damaged in the workshop.']);

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
    }

    public function test_fulfilment_staff_move_orders_but_do_not_see_payments(): void
    {
        $this->actingAs(User::factory()->create(['role' => StaffRole::Fulfilment]));
        $order = $this->paidOrder();

        Livewire::test(ViewOrder::class, ['record' => $order->number])->callAction('to_ready');

        $this->assertSame(OrderStatus::Ready, $order->refresh()->status);
        $this->get(PaymentResource::getUrl('index'))->assertForbidden();
        $this->assertFalse(PaymentResource::canAccess());
    }

    public function test_orders_cannot_be_created_edited_or_deleted_by_hand(): void
    {
        $order = $this->placeOrder();

        $this->assertFalse(OrderResource::canCreate());
        $this->assertFalse(OrderResource::canEdit($order));
        $this->assertFalse(OrderResource::canDelete($order));
        $this->assertSame(['index', 'view'], array_keys(OrderResource::getPages()));
    }

    public function test_the_payment_list_shows_every_attempt_with_its_receipt_and_order(): void
    {
        $order = $this->placeOrder();
        $failed = $order->payments()->create([
            'method' => PaymentMethod::Mpesa, 'state' => PaymentState::Cancelled, 'amount' => $order->total, 'charged' => 100,
            'phone' => '254712345678', 'checkout_request_id' => 'ws_CO_1', 'result_code' => '1032', 'result_description' => 'Request cancelled by user', 'settled_at' => now(),
        ]);
        $this->travel(1)->minutes();
        $paid = $order->payments()->create([
            'method' => PaymentMethod::Mpesa, 'state' => PaymentState::Paid, 'amount' => $order->total, 'charged' => 100,
            'phone' => '254722000111', 'checkout_request_id' => 'ws_CO_2', 'receipt' => 'TJA5K2PXQR', 'result_code' => '0', 'settled_at' => now(),
        ]);
        $other = $this->paidOrder(['name' => 'Achieng Otieno'])->payments()->first();

        $this->get(PaymentResource::getUrl('index'))
            ->assertOk()
            ->assertSeeText($order->number)
            ->assertSeeText('TJA5K2PXQR')
            ->assertSeeText('0722 000 111')
            ->assertSeeText('Cancelled by the customer')
            ->assertSeeText('Request cancelled by user')
            ->assertSeeText('KSh 46,500')
            ->assertSeeText('KSh 1')
            ->assertSee(OrderResource::getUrl('view', ['record' => $order]));

        Livewire::test(ListPayments::class)
            ->assertCanSeeTableRecords([$other, $paid, $failed], inOrder: true)
            ->searchTable('TJA5K2PXQR')
            ->assertCanSeeTableRecords([$paid])->assertCanNotSeeTableRecords([$failed, $other])
            ->searchTable('0722 000 111')
            ->assertCanSeeTableRecords([$paid])->assertCanNotSeeTableRecords([$failed, $other])
            ->searchTable($order->number)
            ->assertCanSeeTableRecords([$paid, $failed])->assertCanNotSeeTableRecords([$other])
            ->searchTable('')
            ->filterTable('state', PaymentState::Cancelled->value)
            ->assertCanSeeTableRecords([$failed])->assertCanNotSeeTableRecords([$paid, $other])
            ->resetTableFilters()
            ->filterTable('method', PaymentMethod::Test->value)
            ->assertCanSeeTableRecords([$other])->assertCanNotSeeTableRecords([$paid, $failed]);

        $this->assertFalse(PaymentResource::canCreate());
        $this->assertFalse(PaymentResource::canEdit($paid));
        $this->assertFalse(PaymentResource::canDelete($paid));
    }

    public function test_the_message_log_keeps_every_message_and_shows_it_as_the_customer_got_it(): void
    {
        $order = $this->paidOrder();
        $email = Message::where('order_id', $order->id)->where('channel', MessageChannel::Email)->where('kind', 'paid')->sole();
        $text = Message::where('order_id', $order->id)->where('channel', MessageChannel::Sms)->where('kind', 'paid')->sole();
        $other = Message::where('order_id', $this->placeOrder(['email' => 'achieng@example.com'])->id)->where('channel', MessageChannel::Email)->sole();

        $this->get(route('filament.admin.resources.messages.index'))
            ->assertOk()
            ->assertSeeText("Payment received for order {$order->number}")
            ->assertSeeText('wanjiru@example.com')
            ->assertSeeText('Not sent');

        Livewire::test(ListMessages::class)
            ->filterTable('channel', MessageChannel::Sms->value)
            ->assertCanSeeTableRecords([$text])->assertCanNotSeeTableRecords([$email, $other])
            ->resetTableFilters()
            ->searchTable('achieng@example.com')
            ->assertCanSeeTableRecords([$other])->assertCanNotSeeTableRecords([$email, $text])
            ->searchTable($order->number)
            ->assertCanSeeTableRecords([$email, $text])->assertCanNotSeeTableRecords([$other])
            ->mountAction(TestAction::make('read')->table($email))
            ->assertMountedActionModalSee('Thank you, Wanjiru. Your order is confirmed.')
            ->assertMountedActionModalSeeHtml('<iframe')
            ->unmountAction()
            ->mountAction(TestAction::make('read')->table($text))
            ->assertMountedActionModalSee("Sebule: payment received for order {$order->number}.")
            ->assertMountedActionModalSee('Text messages are switched off.')
            ->assertMountedActionModalDontSeeHtml('<iframe');
    }

    /**
     * Ask the page to run an action it is not offering, the way a forged
     * request would, and check the server would not even open it.
     *
     * @param  Testable<ViewOrder>  $page
     */
    private function pressAnyway(Testable $page, string $action): void
    {
        $page->call('mountAction', $action)->assertSet('mountedActions', []);
    }
}
