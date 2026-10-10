<?php

namespace App\Orders;

use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Inventory\StockLedger;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Ends the hold on an order nobody paid for: its stock goes back on sale and
 * the order is marked as not paid in time. The customer can still come back to it.
 */
class CloseUnpaidOrder
{
    public function __construct(private readonly StockLedger $stock) {}

    /** Returns false when the order was paid, or closed, by the time its turn came. */
    public function __invoke(Order $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            $order = Order::query()->lockForUpdate()->with('items.variant')->findOrFail($order->getKey());

            if (! $order->awaitsPayment()) {
                return false;
            }

            $order->items->each(function (OrderItem $item) use ($order): void {
                if ($item->stock_taken && $item->variant) {
                    $this->stock->adjust($item->variant, $item->quantity, StockMovementReason::Released, 'Order '.$order->number, source: $order);
                }

                $item->update(['stock_taken' => false]);
            });

            $order->update(['status' => OrderStatus::Expired, 'closed_at' => now(), 'held_until' => null]);
            $order->record(OrderEventType::Expired);

            return true;
        });
    }
}
