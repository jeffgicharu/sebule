<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\StaffRole;
use App\Enums\StockMovementReason;
use App\Models\StockMovement;
use App\Models\User;
use App\Orders\CancelOrder;
use App\Orders\MarkOrderPaid;
use App\Orders\MoveOrderOn;
use App\Orders\OrderCannotMove;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PlacesOrders;
use Tests\TestCase;

/**
 * What staff do to an order once it is paid for: move it on a step at a time,
 * or cancel it.
 */
class OrderStepsTest extends TestCase
{
    use PlacesOrders, RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role' => StaffRole::Fulfilment, 'name' => 'Otieno Odhiambo']);
    }

    public function test_a_delivered_order_from_stock_goes_ready_then_out_then_delivered(): void
    {
        $order = $this->paidOrder();
        $move = app(MoveOrderOn::class);

        $this->assertSame(OrderStatus::Ready, $order->nextStatus());
        $order = $move($order, OrderStatus::Ready, $this->staff);
        $order = $move($order, OrderStatus::OutForDelivery, $this->staff);
        $this->assertNull($order->closed_at);

        $order = $move($order, OrderStatus::Delivered, $this->staff);

        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertNotNull($order->closed_at);
        $this->assertNull($order->nextStatus());
        $this->assertSame(
            [OrderEventType::Placed, OrderEventType::Paid, OrderEventType::Ready, OrderEventType::OutForDelivery, OrderEventType::Delivered],
            $order->events()->pluck('type')->all(),
        );
    }

    public function test_an_order_with_a_made_to_order_piece_goes_through_the_workshop_first(): void
    {
        $order = $this->paidOrder(piece: $this->piece('Tana dining table', madeToOrder: true));

        $this->assertSame(OrderStatus::InProduction, $order->nextStatus());

        $order = app(MoveOrderOn::class)($order, OrderStatus::InProduction, $this->staff);

        $this->assertSame(OrderStatus::Ready, $order->nextStatus());
    }

    public function test_an_order_that_is_collected_never_goes_out_for_delivery(): void
    {
        $order = $this->paidOrder(['method' => 'collection', 'county_id' => null]);
        $move = app(MoveOrderOn::class);

        $order = $move($order, OrderStatus::Ready, $this->staff);

        $this->assertSame(OrderStatus::Delivered, $order->nextStatus());
        $this->assertSame(OrderStatus::Delivered, $move($order, OrderStatus::Delivered, $this->staff)->status);
    }

    public function test_the_history_keeps_who_moved_the_order(): void
    {
        $order = app(MoveOrderOn::class)($this->paidOrder(), OrderStatus::Ready, $this->staff);

        $event = $order->events()->where('type', OrderEventType::Ready)->sole();

        $this->assertTrue($event->user->is($this->staff));
        $this->assertNull($order->events()->where('type', OrderEventType::Paid)->sole()->user);
    }

    public function test_an_order_cannot_skip_a_step_or_go_back(): void
    {
        $order = $this->paidOrder();
        $move = app(MoveOrderOn::class);

        foreach ([OrderStatus::OutForDelivery, OrderStatus::Delivered, OrderStatus::InProduction, OrderStatus::Paid, OrderStatus::Cancelled] as $wrong) {
            try {
                $move($order, $wrong, $this->staff);
                $this->fail("Moved a paid order straight to {$wrong->value}.");
            } catch (OrderCannotMove) {
                $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
            }
        }
    }

    public function test_an_order_that_is_not_paid_for_cannot_be_moved_on(): void
    {
        $order = $this->placeOrder();

        $this->assertNull($order->nextStatus());
        $this->expectException(OrderCannotMove::class);

        app(MoveOrderOn::class)($order, OrderStatus::Ready, $this->staff);
    }

    public function test_the_same_step_taken_twice_happens_once(): void
    {
        $order = $this->paidOrder();
        $move = app(MoveOrderOn::class);
        $move($order, OrderStatus::Ready, $this->staff);

        try {
            // A second member of staff presses the same button a moment later, from a page that still shows the old status.
            $move($order, OrderStatus::Ready, $this->staff);
            $this->fail('The order was marked ready twice.');
        } catch (OrderCannotMove $problem) {
            $this->assertSame(OrderStatus::Ready, $problem->order->status);
        }

        $this->assertSame(1, $order->events()->where('type', OrderEventType::Ready)->count());
    }

    public function test_cancelling_an_unpaid_order_puts_its_stock_back_on_sale(): void
    {
        $piece = $this->piece(stock: 5);
        $order = $this->placeOrder(piece: $piece, quantity: 2);
        $this->assertSame(3, $piece->refresh()->stock_on_hand);

        $order = app(CancelOrder::class)($order, 'The customer asked us to cancel it.', $this->staff);

        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNotNull($order->closed_at);
        $this->assertNull($order->held_until);
        $this->assertSame(5, $piece->refresh()->stock_on_hand);
        $this->assertFalse($order->items()->sole()->stock_taken);

        $back = StockMovement::query()->latest('id')->first();
        $this->assertSame([2, StockMovementReason::Released, 5], [$back->quantity, $back->reason, $back->balance_after]);
        $this->assertTrue($back->user->is($this->staff));

        $event = $order->events()->where('type', OrderEventType::Cancelled)->sole();
        $this->assertSame('The customer asked us to cancel it.', $event->note);
        $this->assertTrue($event->user->is($this->staff));
        $this->assertFalse($order->events()->where('type', OrderEventType::RefundDue)->exists());
    }

    public function test_cancelling_an_order_paid_by_mpesa_says_what_is_owed_back(): void
    {
        $order = $this->placeOrder();
        $payment = $order->payments()->create([
            'method' => PaymentMethod::Mpesa, 'state' => PaymentState::Paid, 'amount' => $order->total, 'charged' => $order->total,
            'phone' => '254722000111', 'receipt' => 'TJA5K2PXQR', 'settled_at' => now(),
        ]);
        app(MarkOrderPaid::class)($order, $payment);

        $order = app(CancelOrder::class)($order, 'The frame was damaged in the workshop.', $this->staff);

        $this->assertSame('KSh 46,500 to 0722 000 111', $order->events()->where('type', OrderEventType::RefundDue)->sole()->note);
    }

    public function test_an_order_that_has_reached_the_customer_cannot_be_cancelled(): void
    {
        $order = $this->paidOrder();
        $move = app(MoveOrderOn::class);
        $move($order, OrderStatus::Ready, $this->staff);
        $move($order, OrderStatus::OutForDelivery, $this->staff);
        $order = $move($order, OrderStatus::Delivered, $this->staff);

        $this->assertFalse($order->canBeCancelled());
        $this->expectException(OrderCannotMove::class);

        app(CancelOrder::class)($order, 'Too late.', $this->staff);
    }

    public function test_money_that_arrives_for_a_cancelled_order_is_owed_back_and_does_not_revive_it(): void
    {
        $piece = $this->piece(stock: 5);
        $order = $this->placeOrder(piece: $piece);
        $prompt = $order->payments()->create([
            'method' => PaymentMethod::Mpesa, 'state' => PaymentState::Pending, 'amount' => $order->total, 'charged' => $order->total,
            'phone' => '254722000111', 'checkout_request_id' => 'ws_CO_late',
        ]);
        app(CancelOrder::class)($order, 'Placed twice by mistake.', $this->staff);

        // The customer answers the prompt that was already on their phone.
        $prompt->update(['state' => PaymentState::Paid, 'receipt' => 'TJA5K2LATE', 'settled_at' => now()]);
        app(MarkOrderPaid::class)($order, $prompt);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNull($order->paid_at);
        $this->assertSame(5, $piece->refresh()->stock_on_hand);
        $this->assertSame('Paid after the order was cancelled: KSh 46,500 to 0722 000 111', $order->events()->where('type', OrderEventType::RefundDue)->sole()->note);
    }
}
