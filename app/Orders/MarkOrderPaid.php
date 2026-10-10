<?php

namespace App\Orders;

use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Inventory\InsufficientStock;
use App\Inventory\StockLedger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Moves an order to paid, once, when a payment for it has been confirmed.
 */
class MarkOrderPaid
{
    public function __construct(private readonly StockLedger $stock) {}

    public function __invoke(Order $order, Payment $payment): void
    {
        DB::transaction(function () use ($order, $payment): void {
            $order = Order::query()->lockForUpdate()->with('items.variant')->findOrFail($order->getKey());

            if ($order->status->isPaid()) {
                return;
            }

            // Money that arrives after the hold ran out still buys the order, if the pieces are there to take.
            $short = $order->status === OrderStatus::Expired
                ? $order->items->filter(fn (OrderItem $item) => ! $this->takeAgain($order, $item))->map->title()
                : collect();

            $order->update(['status' => OrderStatus::Paid, 'paid_at' => now(), 'closed_at' => null, 'held_until' => null]);

            $order->record(OrderEventType::Paid, $payment->method->getLabel().($payment->receipt ? ', receipt '.$payment->receipt : ''));

            if ($short->isNotEmpty()) {
                $order->record(OrderEventType::Paid, 'Paid after the hold ran out. No longer in stock: '.$short->join(', ').'.');
            }
        });
    }

    /** Take a line's stock a second time. False when it is no longer there. */
    private function takeAgain(Order $order, OrderItem $item): bool
    {
        if ($item->made_to_order || $item->stock_taken) {
            return true;
        }

        if ($item->variant === null) {
            return false;
        }

        try {
            $this->stock->adjust($item->variant, -$item->quantity, StockMovementReason::Sold, 'Order '.$order->number, source: $order);
        } catch (InsufficientStock) {
            return false;
        }

        $item->update(['stock_taken' => true]);

        return true;
    }
}
