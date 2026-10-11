<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * What staff may do to an order. Every member of staff can read orders.
 */
class OrderPolicy
{
    /** Move the order to its next step. */
    public function advance(User $user, Order $order): bool
    {
        return $user->role->fulfilsOrders() && $order->nextStatus() !== null;
    }

    /** Cancelling a paid order means sending money back, which is the owner's call. */
    public function cancel(User $user, Order $order): bool
    {
        if (! $order->canBeCancelled()) {
            return false;
        }

        return $order->status->isPaid() ? $user->role->sendsMoneyBack() : true;
    }

    /** Note that money owed on the order has been sent back. */
    public function recordRefund(User $user, Order $order): bool
    {
        return $user->role->sendsMoneyBack() && $order->refundDue() !== null;
    }
}
