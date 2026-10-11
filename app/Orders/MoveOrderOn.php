<?php

namespace App\Orders;

use App\Enums\OrderStatus;
use App\Messages\TellCustomer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Staff move a paid order to its next step. The history keeps who did it,
 * and the customer is told.
 */
class MoveOrderOn
{
    public function __construct(private readonly TellCustomer $tell) {}

    /** @throws OrderCannotMove when the order is no longer one step short of that status */
    public function __invoke(Order $order, OrderStatus $to, User $by): Order
    {
        return DB::transaction(function () use ($order, $to, $by): Order {
            $order = Order::query()->lockForUpdate()->with('items', 'payments')->findOrFail($order->getKey());

            if ($order->nextStatus() !== $to || $to->event() === null) {
                throw new OrderCannotMove($order, $to);
            }

            $order->update([
                'status' => $to,
                'closed_at' => $to->isClosed() ? now() : null,
            ]);

            $order->record($to->event(), by: $by);

            ($this->tell)($order, $to->event());

            return $order;
        });
    }
}
