<?php

namespace App\Orders;

use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentState;
use App\Enums\StockMovementReason;
use App\Inventory\StockLedger;
use App\Messages\TellCustomer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;

/**
 * Staff cancel an order. Its stock goes back on sale, the customer is told
 * why, and when the order was paid for the history says what is owed back.
 */
class CancelOrder
{
    public function __construct(private readonly StockLedger $stock, private readonly TellCustomer $tell) {}

    /** @throws OrderCannotMove when the order has already reached the customer or been closed */
    public function __invoke(Order $order, string $reason, User $by): Order
    {
        return DB::transaction(function () use ($order, $reason, $by): Order {
            $order = Order::query()->lockForUpdate()->with('items.variant', 'payments')->findOrFail($order->getKey());

            if (! $order->canBeCancelled()) {
                throw new OrderCannotMove($order, OrderStatus::Cancelled);
            }

            $order->items->each(function (OrderItem $item) use ($order, $by): void {
                if ($item->stock_taken && $item->variant) {
                    $this->stock->adjust($item->variant, $item->quantity, StockMovementReason::Released, 'Order '.$order->number.' cancelled', $by, $order);
                }

                $item->update(['stock_taken' => false]);
            });

            $order->update(['status' => OrderStatus::Cancelled, 'closed_at' => now(), 'held_until' => null]);
            $order->record(OrderEventType::Cancelled, $reason, $by);

            $paid = $order->payments->firstWhere('state', PaymentState::Paid);

            // A test payment took nothing, so there is nothing to send back.
            if ($paid !== null && $paid->charged > 0) {
                $order->record(OrderEventType::RefundDue, self::owed($paid->charged, $paid->phone));
            }

            ($this->tell)($order, OrderEventType::Cancelled, $reason);

            return $order;
        });
    }

    /** "KSh 45,000 to 0712 345 678", as the history notes it. */
    public static function owed(int $cents, ?string $phone): string
    {
        return Money::format($cents).($phone ? ' to '.Phone::display($phone) : '');
    }
}
