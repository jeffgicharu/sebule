<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\StaffRole;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Widgets\LowStock;
use App\Filament\Widgets\MoneyToSendBack;
use App\Filament\Widgets\OrdersToWorkOn;
use App\Filament\Widgets\SalesOverview;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Orders\CancelOrder;
use App\Orders\MarkOrderPaid;
use App\Orders\MoveOrderOn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PlacesOrders;
use Tests\TestCase;

/**
 * The back office's first screen: what has sold, what is waiting, what is
 * running low and what money has to go back.
 */
class DashboardTest extends TestCase
{
    use PlacesOrders, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-14 15:00:00');

        $this->owner = User::factory()->create(['role' => StaffRole::Owner]);
        $this->actingAs($this->owner);
    }

    public function test_sales_count_orders_by_the_day_they_were_paid_for(): void
    {
        $this->paidOn('2026-09-10 11:00:00');              // last month, by this day
        $this->paidOn('2026-09-25 11:00:00');              // last month, after this day
        $this->paidOn('2026-10-02 09:00:00', quantity: 2); // earlier this month
        $this->paidOn('2026-10-14 08:30:00');              // today
        $this->placeOrder();                               // today, not paid for

        $cancelled = $this->paidOrder();
        app(CancelOrder::class)($cancelled, 'Cancelled by the customer.', $this->owner);

        $this->assertSame([46_500_00, 1], SalesOverview::sales(now()->startOfDay(), now()));
        $this->assertSame([46_500_00 + 91_500_00, 2], SalesOverview::sales(now()->startOfMonth(), now()));

        $this->get('/admin')->assertOk();

        Livewire::test(SalesOverview::class)
            ->assertSeeTextInOrder(['Sales today', 'KSh 46,500', '1 order paid for'])
            ->assertSeeTextInOrder(['Sales this month', 'KSh 138,000', '2 orders. KSh 46,500 by this day last month'])
            ->assertSeeTextInOrder(['Orders to prepare', '4'])
            ->assertSeeTextInOrder(['Awaiting payment', '1']);
    }

    public function test_the_first_screen_lists_paid_orders_waiting_for_their_next_step_oldest_first(): void
    {
        $first = $this->paidOn('2026-10-12 09:00:00', ['name' => 'Achieng Otieno']);
        $second = $this->paidOn('2026-10-13 09:00:00', piece: $this->piece('Tana dining table', madeToOrder: true));
        $unpaid = $this->placeOrder();
        $done = $this->paidOrder();
        foreach ([OrderStatus::Ready, OrderStatus::OutForDelivery, OrderStatus::Delivered] as $step) {
            $done = app(MoveOrderOn::class)($done, $step, $this->owner);
        }

        Livewire::test(OrdersToWorkOn::class)
            ->assertCanSeeTableRecords([$first, $second], inOrder: true)
            ->assertCanNotSeeTableRecords([$unpaid, $done])
            ->assertSeeText('Achieng Otieno')
            ->assertSeeText('Mark as ready')
            ->assertSeeText('Start making it')
            ->assertSee('/admin/orders/'.$first->number);
    }

    public function test_low_stock_lists_pieces_at_or_below_their_warning_level(): void
    {
        $low = $this->piece('Kilifi planter basket', stock: 2);
        $out = $this->piece('Lamu side table', stock: 0);
        $healthy = $this->piece('Amani sofa', stock: 9);
        $made = $this->piece('Tana dining table', madeToOrder: true);
        $offSale = $this->piece('Old lamp', stock: 0);
        $offSale->update(['is_active' => false]);

        Livewire::test(LowStock::class)
            ->assertCanSeeTableRecords([$out, $low], inOrder: true)
            ->assertCanNotSeeTableRecords([$healthy, $made, $offSale])
            ->assertSeeText('Sold out')
            ->assertSeeText('Kilifi planter basket');
    }

    public function test_money_owed_on_a_cancelled_order_stays_listed_until_the_refund_is_recorded(): void
    {
        $order = $this->orderPaidByMpesa();
        $untouched = $this->paidOrder();

        Livewire::test(MoneyToSendBack::class)->assertCanNotSeeTableRecords([$order, $untouched]);

        app(CancelOrder::class)($order, 'The frame was damaged in the workshop.', $this->owner);

        Livewire::test(MoneyToSendBack::class)
            ->assertCanSeeTableRecords([$order])
            ->assertCanNotSeeTableRecords([$untouched])
            ->assertSeeText('KSh 46,500 to 0722 000 111');

        Livewire::test(ViewOrder::class, ['record' => $order->number])
            ->assertActionVisible('recordRefund')
            ->mountAction('recordRefund')
            ->callMountedAction()
            ->assertHasActionErrors(['reference' => 'required'])
            ->setActionData(['reference' => 'TJB7K9REFD'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertActionHidden('recordRefund');

        $sent = $order->events()->where('type', OrderEventType::RefundSent)->sole();

        $this->assertSame('KSh 46,500 to 0722 000 111, M-Pesa TJB7K9REFD', $sent->note);
        $this->assertTrue($sent->user->is($this->owner));
        Livewire::test(MoneyToSendBack::class)->assertCanNotSeeTableRecords([$order]);
        $this->assertSame(0, Order::query()->owedARefund()->count());
    }

    public function test_only_an_owner_records_a_refund_and_fulfilment_staff_do_not_see_the_money_list(): void
    {
        $order = $this->orderPaidByMpesa();
        app(CancelOrder::class)($order, 'The frame was damaged in the workshop.', $this->owner);

        foreach ([StaffRole::Manager, StaffRole::Support, StaffRole::Fulfilment] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));

            Livewire::test(ViewOrder::class, ['record' => $order->number])
                ->assertActionHidden('recordRefund')
                ->call('mountAction', 'recordRefund')
                ->assertSet('mountedActions', []);

            $this->assertSame($role->seesPayments(), MoneyToSendBack::canView(), $role->value);
        }

        $this->assertFalse($order->events()->where('type', OrderEventType::RefundSent)->exists());
    }

    public function test_an_order_paid_for_after_its_pieces_sold_elsewhere_says_so_in_its_history(): void
    {
        $piece = $this->piece(stock: 1);
        $order = $this->placeOrder(piece: $piece);
        $prompt = $order->payments()->create([
            'method' => PaymentMethod::Mpesa, 'state' => PaymentState::Paid, 'amount' => $order->total, 'charged' => $order->total,
            'phone' => '254722000111', 'receipt' => 'TJA5K2LATE', 'settled_at' => now(),
        ]);

        // The hold runs out, somebody else buys the last one, and then the first customer's money lands.
        $this->travel(20)->minutes();
        $this->artisan('orders:release-unpaid');
        $this->paidOrder(['name' => 'Kamau Njoroge'], $piece);
        app(MarkOrderPaid::class)($order, $prompt);

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertSame('Paid after the hold ran out: Amani sofa.', $order->events()->where('type', OrderEventType::StockShort)->sole()->note);
    }

    /**
     * An order placed and paid for at another time, with the clock put back afterwards.
     *
     * @param  array<string, mixed>  $details
     */
    private function paidOn(string $when, array $details = [], ?ProductVariant $piece = null, int $quantity = 1): Order
    {
        $this->travelTo($when);
        $order = $this->paidOrder($details, $piece, $quantity);
        $this->travelTo('2026-10-14 15:00:00');

        return $order;
    }

    private function orderPaidByMpesa(): Order
    {
        $order = $this->placeOrder();
        $payment = $order->payments()->create([
            'method' => PaymentMethod::Mpesa, 'state' => PaymentState::Paid, 'amount' => $order->total, 'charged' => $order->total,
            'phone' => '254722000111', 'receipt' => 'TJA5K2PXQR', 'settled_at' => now(),
        ]);
        app(MarkOrderPaid::class)($order, $payment);

        return $order->refresh();
    }
}
