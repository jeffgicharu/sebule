<?php

namespace App\Orders;

use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Inventory\InsufficientStock;
use App\Inventory\StockLedger;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Lets a customer pick up an order that ran out of time, without starting
 * again: the pieces are held once more, if they are still there, at the prices
 * the order was placed with.
 */
class ResumeOrder
{
    public function __construct(private readonly StockLedger $stock) {}

    /**
     * Holds the order's pieces again, or changes nothing.
     *
     * @throws PieceNoLongerAvailable
     */
    public function __invoke(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = Order::query()->lockForUpdate()->with('items.variant')->findOrFail($order->getKey());

            if ($order->status !== OrderStatus::Expired) {
                return;
            }

            $order->items->each(fn (OrderItem $item) => $this->hold($order, $item));

            $order->update([
                'status' => OrderStatus::PendingPayment,
                'closed_at' => null,
                'held_until' => now()->addMinutes((int) config('store.hold_minutes')),
            ]);
            $order->record(OrderEventType::Resumed);
        });
    }

    private function hold(Order $order, OrderItem $item): void
    {
        if ($item->made_to_order) {
            return;
        }

        if ($item->variant === null || ! $item->variant->is_active) {
            throw new PieceNoLongerAvailable($item->title());
        }

        try {
            $this->stock->adjust($item->variant, -$item->quantity, StockMovementReason::Sold, 'Order '.$order->number, source: $order);
        } catch (InsufficientStock) {
            throw new PieceNoLongerAvailable($item->title());
        }

        $item->update(['stock_taken' => true]);
    }
}
