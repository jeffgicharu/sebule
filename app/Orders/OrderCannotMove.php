<?php

namespace App\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use DomainException;

/**
 * Staff tried to move an order to a step it cannot take from where it is,
 * usually because somebody else moved it first.
 */
class OrderCannotMove extends DomainException
{
    public function __construct(public readonly Order $order, public readonly ?OrderStatus $to = null)
    {
        parent::__construct("Order {$order->number} is {$order->status->getLabel()} and cannot be changed that way.");
    }
}
